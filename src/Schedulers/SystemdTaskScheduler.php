<?php
/**
 * SystemdTaskScheduler
 * Implementation for systemd timer/service with property mapping
 */
class SystemdTaskScheduler extends AbstractTaskScheduler {
    private $systemdPath = '/etc/systemd/system/';
    private $systemctlPath = '/usr/bin/systemctl';
    
    /**
     * Constructor with systemd-specific initialization
     * 
     * @param string $taskName Name of the task
     * @param string $command Command to execute
     * @param int $interval Interval in minutes
     */
    public function __construct($taskName = null, $command = null, $interval = 1) {
        parent::__construct($taskName, $command, $interval);
        $this->validateSystemdEnvironment();
    }
    
    /**
     * Validate systemd environment
     * 
     * @throws Exception If systemd not found
     */
    private function validateSystemdEnvironment() {
        if (!file_exists($this->systemctlPath)) {
            throw new Exception("Required systemd command (systemctl) not found");
        }
        
        if (!is_dir($this->systemdPath)) {
            throw new Exception("Systemd system directory not found: {$this->systemdPath}");
        }
    }
    
    /**
     * Create systemd timer and service
     * 
     * @return array Result of the operation
     */
    protected function create() {
        try {
            // Create service unit file
            $serviceContent = $this->generateServiceUnit();
            $servicePath = $this->systemdPath . $this->getUnitName() . '.service';
            
            // Create timer unit file
            $timerContent = $this->generateTimerUnit();
            $timerPath = $this->systemdPath . $this->getUnitName() . '.timer';
            
            // Write files (may require sudo)
            file_put_contents($servicePath, $serviceContent);
            file_put_contents($timerPath, $timerContent);
            
            // Reload systemd, enable and start timer
            $commands = [
                "systemctl daemon-reload",
                "systemctl enable {$this->getUnitName()}.timer",
                "systemctl start {$this->getUnitName()}.timer"
            ];
            
            $output = [];
            $success = true;
            
            foreach ($commands as $cmd) {
                exec($cmd, $cmdOutput, $returnCode);
                $output = array_merge($output, $cmdOutput);
                if ($returnCode !== 0) {
                    $success = false;
                    break;
                }
            }
            
            return [
                'success' => $success,
                'message' => $success ? 
                    "Task '{$this->taskName}' scheduled successfully with systemd." :
                    "Failed to schedule task with systemd.",
                'output' => $output,
                'service_file' => $serviceContent,
                'timer_file' => $timerContent
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to create systemd units: " . $e->getMessage(),
                'output' => []
            ];
        }
    }
    
    /**
     * Generate systemd service unit content
     * 
     * @return string Service unit content
     */
    private function generateServiceUnit() {
        $unit = "[Unit]\n";
        $unit .= "Description={$this->taskName}\n";
        
        $unit .= "\n[Service]\n";
        $unit .= "Type=oneshot\n";
        
        // Add working directory if specified
        $workingDir = $this->getProperty(self::PROP_WORKING_DIRECTORY);
        if ($workingDir) {
            $unit .= "WorkingDirectory={$workingDir}\n";
        }
        
        // Add user if specified
        $user = $this->getProperty(self::PROP_RUN_AS_USER);
        if ($user) {
            $unit .= "User={$user}\n";
        }
        
        // Add environment variables
        $envVars = $this->getProperty(self::PROP_ENVIRONMENT_VARIABLES, []);
        foreach ($envVars as $name => $value) {
            $unit .= "Environment=\"{$name}={$value}\"\n";
        }
        
        // Add nice level if specified
        $priority = $this->getProperty(self::PROP_PROCESS_PRIORITY);
        if ($priority !== null) {
            $unit .= "Nice={$priority}\n";
        }
        
        // Add the command
        $unit .= "ExecStart={$this->command}\n";
        
        // Add output handling
        $outputFile = $this->getProperty(self::PROP_OUTPUT_FILE);
        $errorFile = $this->getProperty(self::PROP_ERROR_FILE);
        
        if ($outputFile) {
            $unit .= "StandardOutput=append:{$outputFile}\n";
        }
        if ($errorFile) {
            $unit .= "StandardError=append:{$errorFile}\n";
        }
        
        return $unit;
    }
    
    /**
     * Generate systemd timer unit content
     * 
     * @return string Timer unit content
     */
    private function generateTimerUnit() {
        $unit = "[Unit]\n";
        $unit .= "Description=Timer for {$this->taskName}\n";
        
        $unit .= "\n[Timer]\n";
        
        // Convert interval to systemd time format
        if ($this->interval < 60) {
            $onUnitActiveSec = "{$this->interval}m";
        } else if ($this->interval < 1440) {
            $hours = floor($this->interval / 60);
            $onUnitActiveSec = "{$hours}h";
        } else {
            $days = floor($this->interval / 1440);
            $onUnitActiveSec = "{$days}d";
        }
        
        $unit .= "OnUnitActiveSec={$onUnitActiveSec}\n";
        
        // Add specific time if set
        $startTime = $this->getProperty(self::PROP_START_TIME);
        if ($startTime) {
            $unit .= "OnCalendar=*-*-* {$startTime}:00\n";
        }
        
        // Add persistent flag to catch up on missed runs
        if (!$this->getProperty(self::PROP_PREVENT_CONCURRENT, false)) {
            $unit .= "Persistent=true\n";
        }
        
        $unit .= "\n[Install]\n";
        $unit .= "WantedBy=timers.target\n";
        
        return $unit;
    }
    
    /**
     * Get systemd unit name (sanitized task name)
     * 
     * @return string Sanitized unit name
     */
    private function getUnitName() {
        return preg_replace('/[^a-z0-9_-]/i', '_', $this->taskName);
    }
    
    /**
     * Update existing systemd units
     * 
     * @return array Result of the operation
     */
    protected function update() {
        // For systemd, update is the same as create
        return $this->create();
    }
    
    /**
     * Remove systemd timer and service
     * 
     * @return array Result of the operation
     */
    public function remove() {
        $commands = [
            "systemctl stop {$this->getUnitName()}.timer",
            "systemctl disable {$this->getUnitName()}.timer",
            "rm {$this->systemdPath}{$this->getUnitName()}.timer",
            "rm {$this->systemdPath}{$this->getUnitName()}.service",
            "systemctl daemon-reload"
        ];
        
        $output = [];
        $success = true;
        
        foreach ($commands as $cmd) {
            exec($cmd, $cmdOutput, $returnCode);
            $output = array_merge($output, $cmdOutput);
            if ($returnCode !== 0) {
                $success = false;
            }
        }
        
        return [
            'success' => $success,
            'message' => $success ? 
                "Task '{$this->taskName}' removed successfully." :
                "Failed to remove task completely.",
            'output' => $output
        ];
    }
    
    /**
     * Check if systemd units exist
     * 
     * @return bool True if task exists
     */
    public function exists() {
        $cmd = "systemctl list-unit-files {$this->getUnitName()}.timer";
        exec($cmd, $output, $returnCode);
        return $returnCode === 0 && !empty($output);
    }
    
    /**
     * Run task immediately
     * 
     * @return array Result of the operation
     */
    public function runNow() {
        if (!$this->exists()) {
            throw new Exception("Cannot run: Task '{$this->taskName}' does not exist");
        }
        
        $cmd = "systemctl start {$this->getUnitName()}.service";
        exec($cmd, $output, $returnCode);
        
        return [
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ?
                "Task '{$this->taskName}' started successfully." :
                "Failed to start task.",
            'output' => $output
        ];
    }
    
    /**
     * Get task details
     * 
     * @return array Task details
     */
    public function getDetails() {
        if (!$this->exists()) {
            throw new Exception("Task '{$this->taskName}' does not exist");
        }
        
        $commands = [
            "systemctl status {$this->getUnitName()}.timer",
            "systemctl status {$this->getUnitName()}.service"
        ];
        
        $details = [];
        
        foreach ($commands as $cmd) {
            exec($cmd, $output, $returnCode);
            $details[] = implode("\n", $output);
        }
        
        return [
            'success' => true,
            'details' => $details,
            'output' => $output
        ];
    }
} 
