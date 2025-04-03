<?php


/**
 * CronTaskScheduler
 * Implementation for Unix/Linux crontab with property mapping
 */
class CronTaskScheduler extends AbstractTaskScheduler {
    /**
     * Constructor with Unix-specific initialization
     * 
     * @param string $taskName Name of the task
     * @param string $command Command to execute
     * @param int $interval Interval in minutes
     */
    public function __construct($taskName = null, $command = null, $interval = 1) {
        parent::__construct($taskName, $command, $interval);
        $this->validateUnixEnvironment();
    }
    
    /**
     * Validate Unix environment
     * 
     * @throws Exception If crontab not found
     */
    private function validateUnixEnvironment() {
        exec("which crontab", $output, $returnCode);
        if ($returnCode !== 0) {
            throw new Exception("Required Unix command (crontab) not found");
        }
    }
    
    /**
     * Create a new cron job
     * 
     * @return array Result of the operation
     */
    protected function create() {
        // Get current crontab
        $crontabCmd = "crontab -l 2>/dev/null";
        $userName = $this->getProperty(self::PROP_RUN_AS_USER);
        
        if ($userName) {
            $crontabCmd = "sudo -u {$userName} " . $crontabCmd;
        }
        
        exec($crontabCmd, $crontab, $returnCode);
        
        // If no crontab exists yet, start with an empty one
        if ($returnCode !== 0) {
            $crontab = [];
        }
        
        // Build cron expression
        $cronExpression = $this->getCronExpression();
        
        // Create task identifier comment for later updates
        $taskIdentifier = "# TASK_ID: {$this->taskName}";
        
        // Remove existing task with same ID if it exists
        $updatedCrontab = [];
        $skipNext = false;
        
        foreach ($crontab as $line) {
            if (strpos($line, $taskIdentifier) !== false) {
                $skipNext = true;
                continue;
            }
            
            if ($skipNext) {
                $skipNext = false;
                continue;
            }
            
            $updatedCrontab[] = $line;
        }
        
        // Add environment variables
        $envVars = $this->getProperty(self::PROP_ENVIRONMENT_VARIABLES, []);
        $mailTo = $this->getProperty(self::PROP_EMAIL_NOTIFICATION);
        
        if ($mailTo || !empty($envVars)) {
            $updatedCrontab[] = "# Environment for {$this->taskName}";
            
            if ($mailTo) {
                $updatedCrontab[] = "MAILTO=\"{$mailTo}\"";
            }
            
            foreach ($envVars as $name => $value) {
                $updatedCrontab[] = "{$name}=\"{$value}\"";
            }
        }
        
        // Add task identifier
        $updatedCrontab[] = $taskIdentifier;
        
        // Check if task should be disabled
        $disabled = $this->getProperty(self::PROP_DISABLED, false);
        if ($disabled) {
            $updatedCrontab[] = "# DISABLED: " . date('Y-m-d H:i:s');
        }
        
        // Build command with all modifiers
        $cronCommand = $this->buildCronCommand();
        
        // Add the cron line
        if (!$disabled) {
            $updatedCrontab[] = "{$cronExpression} {$cronCommand}";
        } else {
            $updatedCrontab[] = "# {$cronExpression} {$cronCommand}";
        }
        
        // Write back updated crontab
        $tempFile = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($tempFile, implode("\n", $updatedCrontab) . "\n");
        
        // Use specific user if provided
        $updateCmd = "crontab {$tempFile}";
        if ($userName) {
            $updateCmd = "sudo -u {$userName} " . $updateCmd;
        }
        
        exec($updateCmd, $output, $returnCode);
        
        // Create logrotate config if needed
        if ($this->getProperty(self::PROP_LOG_ROTATE, false)) {
            $this->setupLogRotate();
        }
        
        unlink($tempFile);
        
        if ($returnCode === 0) {
            return [
                'success' => true,
                'message' => "Task '{$this->taskName}' scheduled successfully with cron.",
                'output' => $output,
                'cron_expression' => $cronExpression,
                'command' => $cronCommand
            ];
        } else {
            return [
                'success' => false,
                'message' => "Failed to schedule task with cron. Error code: {$returnCode}",
                'output' => $output,
                'cron_expression' => $cronExpression,
                'command' => $cronCommand
            ];
        }
    }
    
    /**
     * Build the cron command with all modifiers
     * 
     * @return string Complete command for crontab
     */
    private function buildCronCommand() {
        $command = "";
        $workingDir = $this->getProperty(self::PROP_WORKING_DIRECTORY);
        
        // Change to working directory if specified
        if ($workingDir) {
            $command .= "cd {$workingDir} && ";
        }
        
        // Add lock file handling if specified (prevent concurrent execution)
        if ($this->getProperty(self::PROP_PREVENT_CONCURRENT, false)) {
            $lockFile = "/tmp/{$this->taskName}.lock";
            $command .= "[ -f {$lockFile} ] || (touch {$lockFile} && ";
        }
        
        // Add nice level if specified
        $priority = $this->getProperty(self::PROP_PROCESS_PRIORITY);
        if ($priority !== null) {
            $command .= "nice -n {$priority} ";
        }
        
        // Add the actual command
        $command .= $this->command;
        
        // Close lock file handling if used
        if ($this->getProperty(self::PROP_PREVENT_CONCURRENT, false)) {
            $command .= " && rm {$lockFile})";
        }
        
        // Add output redirections
        $outputFile = $this->getProperty(self::PROP_OUTPUT_FILE);
        $errorFile = $this->getProperty(self::PROP_ERROR_FILE);
        
        if ($outputFile) {
            // If logrotate is enabled and this isn't a special file
            if ($this->getProperty(self::PROP_LOG_ROTATE, false) && 
                strpos($outputFile, '/dev/') !== 0) {
                $command .= " >> " . $outputFile;
            } else {
                $command .= " > " . $outputFile;
            }
        }
        
        if ($errorFile) {
            $command .= " 2> " . $errorFile;
        } elseif ($outputFile) {
            // If stdout is redirected but stderr isn't, redirect stderr to stdout
            $command .= " 2>&1";
        }
        
        return $command;
    }
    
    /**
     * Set up log rotation configuration
     */
    private function setupLogRotate() {
        $outputFile = $this->getProperty(self::PROP_OUTPUT_FILE);
        
        if ($outputFile && strpos($outputFile, '/dev/') !== 0) {
            $logrotateConfig = "{$outputFile} {\n";
            $logrotateConfig .= "    daily\n";
            $logrotateConfig .= "    rotate 7\n";
            $logrotateConfig .= "    compress\n";
            $logrotateConfig .= "    delaycompress\n";
            $logrotateConfig .= "    missingok\n";
            $logrotateConfig .= "    notifempty\n";
            $logrotateConfig .= "}\n";
            
            $logrotateFile = "/etc/logrotate.d/" . preg_replace('/[^a-z0-9_-]/i', '_', $this->taskName);
            
            // Try to create logrotate config (might need sudo)
            try {
                file_put_contents($logrotateFile, $logrotateConfig);
            } catch (Exception $e) {
                // Silently fail if we can't create logrotate config
            }
        }
    }
    
    /**
     * Generate cron expression based on properties or interval
     * 
     * @return string Cron expression (minute hour day-of-month month day-of-week)
     */
    private function getCronExpression() {
        // Check for specific time settings
        $minutes = null;
        $hours = null;
        $daysOfMonth = "*";
        $months = "*";
        $daysOfWeek = "*";
        
        // Parse start time (if specified)
        $startTime = $this->getProperty(self::PROP_START_TIME);
        if ($startTime) {
            list($timeHour, $timeMinute) = explode(':', $startTime);
            $hours = $timeHour;
            $minutes = $timeMinute;
        }
        
        // Parse days of week (if specified)
        $specifiedDays = $this->getProperty(self::PROP_DAYS_OF_WEEK);
        if (is_array($specifiedDays) && !empty($specifiedDays)) {
            $daysOfWeek = implode(',', $specifiedDays);
        }
        
        // Parse days of month (if specified)
        $specifiedDaysOfMonth = $this->getProperty(self::PROP_DAYS_OF_MONTH);
        if (is_array($specifiedDaysOfMonth) && !empty($specifiedDaysOfMonth)) {
            $daysOfMonth = implode(',', $specifiedDaysOfMonth);
        }
        
        // Parse months (if specified)
        $specifiedMonths = $this->getProperty(self::PROP_MONTHS);
        if (is_array($specifiedMonths) && !empty($specifiedMonths)) {
            $months = implode(',', $specifiedMonths);
        }
        
        // If no specific minute/hour is set, calculate from interval
        if ($minutes === null) {
            if ($this->interval < 60) {
                // Format for minutes
                if ($this->interval == 1) {
                    $minutes = "*"; // Every minute
                } else {
                    $minutes = "*/" . $this->interval;
                }
                $hours = "*"; // Every hour
            } else {
                $minutes = "0"; // On the hour
                
                if ($this->interval < 1440) { // Less than a day
                    // Every n hours
                    $intervalHours = floor($this->interval / 60);
                    if ($hours === null) {
                        $hours = "*/" . $intervalHours;
                    }
                } else {
                    // Daily or longer
                    if ($hours === null) {
                        $hours = "0"; // At midnight
                    }
                    
                    // Every n days
                    $intervalDays = floor($this->interval / 1440);
                    if ($intervalDays > 1) {
                        $daysOfMonth = "*/" . $intervalDays;
                    }
                }
            }
        }
        
        return "{$minutes} {$hours} {$daysOfMonth} {$months} {$daysOfWeek}";
    }
    
    /**
     * Update an existing cron job
     * 
     * @return array Result of the operation
     */
    protected function update() {
        // For cron, update is the same as create
        return $this->create();
    }
    
    /**
     * Remove a cron job
     * 
     * @return array Result of the operation
     */
    public function remove() {
        // Get current crontab
        $userName = $this->getProperty(self::PROP_RUN_AS_USER);
        $crontabCmd = "crontab -l 2>/dev/null";
        
        if ($userName) {
            $crontabCmd = "sudo -u {$userName} " . $crontabCmd;
        }
        
        exec($crontabCmd, $crontab, $returnCode);
        
        if ($returnCode !== 0) {
            return [
                'success' => false,
                'message' => "Failed to read crontab",
                'output' => $crontab
            ];
        }
        
        // Find and remove task
        $taskIdentifier = "# TASK_ID: {$this->taskName}";
        $updatedCrontab = [];
        $skipNext = false;
        $found = false;
        
        foreach ($crontab as $line) {
            if (strpos($line, $taskIdentifier) !== false) {
                $found = true;
                $skipNext = true;
                continue;
            }
            
            if ($skipNext) {
                $skipNext = false;
                continue;
            }
            
            $updatedCrontab[] = $line;
        }
        
        if (!$found) {
            return [
                'success' => false,
                'message' => "Task '{$this->taskName}' not found in crontab",
                'output' => []
            ];
        }
        
        // Write back updated crontab
        $tempFile = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($tempFile, implode("\n", $updatedCrontab) . "\n");
        
        $updateCmd = "crontab {$tempFile}";
        if ($userName) {
            $updateCmd = "sudo -u {$userName} " . $updateCmd;
        }
        
        exec($updateCmd, $output, $returnCode);
        unlink($tempFile);
        
        // Remove logrotate config if it exists
        $logrotateFile = "/etc/logrotate.d/" . preg_replace('/[^a-z0-9_-]/i', '_', $this->taskName);
        if (file_exists($logrotateFile)) {
            @unlink($logrotateFile);
        }
        
        return [
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ?
                "Task '{$this->taskName}' removed successfully from crontab." :
                "Failed to update crontab after removing task.",
            'output' => $output
        ];
    }
    
    /**
     * Check if a cron job exists
     * 
     * @return bool True if task exists
     */
    public function exists() {
        $userName = $this->getProperty(self::PROP_RUN_AS_USER);
        $crontabCmd = "crontab -l 2>/dev/null";
        
        if ($userName) {
            $crontabCmd = "sudo -u {$userName} " . $crontabCmd;
        }
        
        exec($crontabCmd, $crontab, $returnCode);
        
        if ($returnCode !== 0) {
            return false;
        }
        
        $taskIdentifier = "# TASK_ID: {$this->taskName}";
        
        foreach ($crontab as $line) {
            if (strpos($line, $taskIdentifier) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Run a cron job immediately
     * 
     * @return array Result of the operation
     */
    public function runNow() {
        if (!$this->exists()) {
            throw new Exception("Cannot run: Task '{$this->taskName}' does not exist");
        }
        
        // Build command with all modifiers
        $command = $this->buildCronCommand();
        
        // Execute command
        $output = [];
        $returnCode = 0;
        
        // Run as specific user if specified
        $userName = $this->getProperty(self::PROP_RUN_AS_USER);
        if ($userName) {
            exec("sudo -u {$userName} {$command}", $output, $returnCode);
        } else {
            exec($command, $output, $returnCode);
        }
        
        return [
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ?
                "Task '{$this->taskName}' executed successfully." :
                "Task execution failed with code {$returnCode}",
            'output' => $output
        ];
    }
    
    /**
     * Get details of a cron job
     * 
     * @return array Task details
     */
    public function getDetails() {
        if (!$this->exists()) {
            throw new Exception("Task '{$this->taskName}' does not exist");
        }
        
        $userName = $this->getProperty(self::PROP_RUN_AS_USER);
        $crontabCmd = "crontab -l 2>/dev/null";
        
        if ($userName) {
            $crontabCmd = "sudo -u {$userName} " . $crontabCmd;
        }
        
        exec($crontabCmd, $crontab, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to read crontab");
        }
        
        $taskIdentifier = "# TASK_ID: {$this->taskName}";
        $taskDetails = [];
        $inTask = false;
        
        foreach ($crontab as $line) {
            if (strpos($line, $taskIdentifier) !== false) {
                $inTask = true;
                continue;
            }
            
            if ($inTask) {
                if (strpos($line, '#') === 0) {
                    // Parse comment lines for metadata
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $key = trim(substr($parts[0], 1)); // Remove # and trim
                        $value = trim($parts[1]);
                        $taskDetails[$key] = $value;
                    }
                } else {
                    // Parse cron line
                    $taskDetails['schedule'] = $line;
                    break;
                }
            }
        }
        
        // Add current properties
        $taskDetails['properties'] = $this->properties;
        
        return [
            'success' => true,
            'details' => $taskDetails,
            'output' => $crontab
        ];
    }
    
    /**
     * Get task name
     * 
     * @return string Task name
     */
    public function getTaskName() {
        return $this->taskName;
    }
    
    /**
     * Get command
     * 
     * @return string Command
     */
    public function getCommand() {
        return $this->command;
    }
    
    /**
     * Get interval
     * 
     * @return int Interval in minutes
     */
    public function getInterval() {
        return $this->interval;
    }
    
    /**
     * Get all properties
     * 
     * @return array Properties
     */
    public function getProperties() {
        return $this->properties;
    }
}
