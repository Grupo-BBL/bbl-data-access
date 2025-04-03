<?php


/**
 * WindowsTaskScheduler
 * Implementation for Windows Task Scheduler with property mapping
 */
class WindowsTaskScheduler extends AbstractTaskScheduler {
    private $schtasksPath = 'C:\\Windows\\System32\\schtasks.exe';
    
    /**
     * Constructor with Windows-specific initialization
     * 
     * @param string $taskName Name of the task
     * @param string $command Command to execute
     * @param int $interval Interval in minutes
     */
    public function __construct($taskName = null, $command = null, $interval = 1) {
        parent::__construct($taskName, $command, $interval);
        $this->validateWindowsEnvironment();
    }
    
    /**
     * Set the path to schtasks.exe
     * 
     * @param string $path Path to schtasks.exe
     * @return $this For method chaining
     * @throws Exception If path is invalid
     */
    public function setSchtasksPath($path) {
        if (!file_exists($path)) {
            throw new Exception("Invalid schtasks.exe path: {$path}");
        }
        $this->schtasksPath = $path;
        return $this;
    }
    
    /**
     * Validate Windows environment
     * 
     * @throws Exception If schtasks.exe not found
     */
    private function validateWindowsEnvironment() {
        if (!file_exists($this->schtasksPath)) {
            throw new Exception("Windows scheduler command (schtasks.exe) not found at: {$this->schtasksPath}");
        }
    }
    
    /**
     * Create a new Windows scheduled task
     * 
     * @return array Result of the operation
     */
    protected function create() {
        // Convert interval to appropriate schedule type
        $scheduleInfo = $this->getWindowsScheduleInfo();
        
        // Build the command
        $cmd = "\"{$this->schtasksPath}\" /Create /TN \"{$this->taskName}\" /TR \"{$this->command}\" ";
        $cmd .= "/SC {$scheduleInfo['type']} /MO {$scheduleInfo['interval']} /F";
        
        // Map general-purpose properties to Windows parameters
        $this->appendWindowsPropertyParameters($cmd);
        
        // Execute the command
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0) {
            return [
                'success' => true,
                'message' => "Task '{$this->taskName}' scheduled successfully on Windows.",
                'output' => $output,
                'command' => $cmd
            ];
        } else {
            return [
                'success' => false,
                'message' => "Failed to schedule task on Windows. Error code: {$returnCode}",
                'output' => $output,
                'command' => $cmd
            ];
        }
    }
    
    /**
     * Map general-purpose properties to Windows schtasks parameters
     * 
     * @param string &$cmd Command to append to
     */
    private function appendWindowsPropertyParameters(&$cmd) {
        // Start time
        $startTime = $this->getProperty(self::PROP_START_TIME);
        if ($startTime) {
            $cmd .= " /ST " . $this->formatWindowsTime($startTime);
        }
        
        // End time
        $endTime = $this->getProperty(self::PROP_END_TIME);
        if ($endTime) {
            $cmd .= " /ET " . $this->formatWindowsTime($endTime);
        }
        
        // Run as user
        $user = $this->getProperty(self::PROP_RUN_AS_USER);
        if ($user) {
            $cmd .= " /RU \"{$user}\"";
            
            // Password
            $password = $this->getProperty(self::PROP_RUN_AS_PASSWORD);
            if ($password && $user !== 'SYSTEM') {
                $cmd .= " /RP \"{$password}\"";
            }
        }
        
        // Run with highest privileges
        if ($this->getProperty(self::PROP_RUN_WITH_HIGHEST_PRIVILEGES, false)) {
            $cmd .= " /RL HIGHEST";
        }
        
        // Start date
        $startDate = $this->getProperty(self::PROP_START_DATE);
        if ($startDate) {
            $cmd .= " /SD " . $this->formatWindowsDate($startDate);
        }
        
        // End date
        $endDate = $this->getProperty(self::PROP_END_DATE);
        if ($endDate) {
            $cmd .= " /ED " . $this->formatWindowsDate($endDate);
        }
        
        // Days of week
        $daysOfWeek = $this->getProperty(self::PROP_DAYS_OF_WEEK);
        if ($daysOfWeek && is_array($daysOfWeek)) {
            $dayMap = [0 => 'SUN', 1 => 'MON', 2 => 'TUE', 3 => 'WED', 4 => 'THU', 5 => 'FRI', 6 => 'SAT'];
            $winDays = [];
            
            foreach ($daysOfWeek as $day) {
                if (isset($dayMap[$day])) {
                    $winDays[] = $dayMap[$day];
                }
            }
            
            if (!empty($winDays)) {
                $cmd .= " /D " . implode(',', $winDays);
            }
        }
        
        // Wake to run
        if ($this->getProperty(self::PROP_WAKE_TO_RUN, false)) {
            $cmd .= " /WAKE";
        }
        
        // Disabled
        if ($this->getProperty(self::PROP_DISABLED, false)) {
            $cmd .= " /DISABLE";
        }
        
        // Working directory (Windows doesn't have a direct parameter, handled through command)
        $workingDir = $this->getProperty(self::PROP_WORKING_DIRECTORY);
        if ($workingDir) {
            // We'd need to wrap the command in a cd command, but this gets complex
            // For simplicity, omitting this advanced implementation
        }
    }
    
    /**
     * Format time for Windows
     * 
     * @param string $time Time in HH:MM format
     * @return string Formatted time for Windows
     */
    private function formatWindowsTime($time) {
        // Windows expects HH:MM format, which is already our standard format
        return $time;
    }
    
    /**
     * Format date for Windows
     * 
     * @param string $date Date in YYYY-MM-DD format
     * @return string Formatted date for Windows (MM/DD/YYYY)
     */
    private function formatWindowsDate($date) {
        // Windows expects MM/DD/YYYY format
        $parts = explode('-', $date);
        if (count($parts) === 3) {
            return "{$parts[1]}/{$parts[2]}/{$parts[0]}";
        }
        return $date;
    }
    
    /**
     * Update existing Windows scheduled task
     * 
     * @return array Result of the operation
     */
    protected function update() {
        // For Windows, update is the same as create with /F flag
        return $this->create();
    }
    
    /**
     * Remove Windows scheduled task
     * 
     * @return array Result of the operation
     */
    public function remove() {
        $cmd = "\"{$this->schtasksPath}\" /Delete /TN \"{$this->taskName}\" /F";
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0) {
            return [
                'success' => true,
                'message' => "Task '{$this->taskName}' removed successfully from Windows.",
                'output' => $output
            ];
        } else {
            return [
                'success' => false,
                'message' => "Failed to remove task from Windows. Error code: {$returnCode}",
                'output' => $output
            ];
        }
    }
    
    /**
     * Check if Windows scheduled task exists
     * 
     * @return bool True if task exists
     */
    public function exists() {
        $cmd = "\"{$this->schtasksPath}\" /Query /TN \"{$this->taskName}\" 2>&1";
        exec($cmd, $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Run Windows scheduled task immediately
     * 
     * @return array Result of the operation
     */
    public function runNow() {
        if (!$this->exists()) {
            throw new Exception("Cannot run: Task '{$this->taskName}' does not exist");
        }
        
        $cmd = "\"{$this->schtasksPath}\" /Run /TN \"{$this->taskName}\"";
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0) {
            return [
                'success' => true,
                'message' => "Task '{$this->taskName}' started successfully.",
                'output' => $output
            ];
        } else {
            return [
                'success' => false,
                'message' => "Failed to start task. Error code: {$returnCode}",
                'output' => $output
            ];
        }
    }
    
    /**
     * Get details of Windows scheduled task
     * 
     * @return array Task details
     */
    public function getDetails() {
        if (!$this->exists()) {
            throw new Exception("Task '{$this->taskName}' does not exist");
        }
        
        $cmd = "\"{$this->schtasksPath}\" /Query /TN \"{$this->taskName}\" /V /FO LIST";
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0) {
            $details = [];
            $currentKey = '';
            
            foreach ($output as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $parts = explode(':', $line, 2);
                if (count($parts) == 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $details[$key] = $value;
                }
            }
            
            return [
                'success' => true,
                'details' => $details,
                'output' => $output
            ];
        } else {
            return [
                'success' => false,
                'message' => "Failed to retrieve task details. Error code: {$returnCode}",
                'output' => $output
            ];
        }
    }
    
    /**
     * Convert minutes interval to Windows schedule info
     * 
     * @return array Schedule type and interval
     */
    private function getWindowsScheduleInfo() {
        if ($this->interval < 1) {
            throw new Exception("Interval must be at least 1 minute");
        }
        
        if ($this->interval < 60) {
            return [
                'type' => 'MINUTE',
                'interval' => (string)$this->interval
            ];
        } else if ($this->interval < 1440) { // Less than a day
            $hours = floor($this->interval / 60);
            return [
                'type' => 'HOURLY',
                'interval' => (string)$hours
            ];
        } else {
            $days = floor($this->interval / 1440);
            return [
                'type' => 'DAILY',
                'interval' => (string)$days
            ];
        }
    }
}
