<?php

/**
 * Task Scheduling Framework with general-purpose properties
 */

/**
 * AbstractTaskScheduler
 * Base class for all task scheduling implementations
 */
abstract class AbstractTaskScheduler 
{
    // Common task properties
    protected $taskName;
    protected $command;
    protected $interval; // in minutes
    protected $properties = [];
    
    // General-purpose property constants (platform-agnostic)
    const PROP_RUN_AS_USER = 'run_as_user';           // User to run as
    const PROP_RUN_AS_PASSWORD = 'run_as_password';   // Password for the user
    const PROP_WORKING_DIRECTORY = 'working_directory'; // Working directory
    const PROP_START_TIME = 'start_time';             // Time of day to start (HH:MM)
    const PROP_END_TIME = 'end_time';                 // Time of day to end (HH:MM)
    const PROP_START_DATE = 'start_date';             // Date to start (YYYY-MM-DD)
    const PROP_END_DATE = 'end_date';                 // Date to end (YYYY-MM-DD)
    const PROP_DAYS_OF_WEEK = 'days_of_week';         // Days to run (array: 0-6, 0=Sunday)
    const PROP_DAYS_OF_MONTH = 'days_of_month';       // Days to run (array: 1-31)
    const PROP_MONTHS = 'months';                     // Months to run (array: 1-12)
    const PROP_DESCRIPTION = 'description';           // Task description
    const PROP_DISABLED = 'disabled';                 // Whether task is disabled
    const PROP_WAKE_TO_RUN = 'wake_to_run';           // Wake computer to run
    const PROP_RUN_WITH_HIGHEST_PRIVILEGES = 'run_with_highest_privileges'; // Admin privileges
    const PROP_OUTPUT_FILE = 'output_file';           // File for stdout
    const PROP_ERROR_FILE = 'error_file';             // File for stderr
    const PROP_ENVIRONMENT_VARIABLES = 'environment_variables'; // Env vars as array
    const PROP_EMAIL_NOTIFICATION = 'email_notification'; // Email for notifications
    const PROP_PROCESS_PRIORITY = 'process_priority'; // -20 to 19, lower is higher priority
    const PROP_PREVENT_CONCURRENT = 'prevent_concurrent'; // Don't run if still running
    const PROP_LOG_ROTATE = 'log_rotate';             // Rotate log files
    
    /**
     * Constructor
     * 
     * @param string $taskName Name of the task
     * @param string $command Command to execute
     * @param int $interval Interval in minutes
     */
    public function __construct($taskName = null, $command = null, $interval = 1) {
        $this->taskName = $taskName;
        $this->command = $command;
        $this->interval = $interval;
    }
    
    /**
     * Static factory method to create appropriate scheduler
     * 
     * @param string $type Explicit scheduler type (windows, cron, systemd)
     * @return AbstractTaskScheduler The appropriate scheduler instance
     */
    public static function createGeneric($type = null) {
        if ($type === null) {
            // Auto-detect system type
            $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
            
            if ($isWindows) {
                return new WindowsTaskScheduler();
            } else {
                // Default to cron on Unix/Linux systems
                // Could add further detection here for systemd, launchd, etc.
                if (self::isSystemdAvailable()) {
                    return new SystemdTaskScheduler();
                } else {
                    return new CronTaskScheduler();
                }
            }
        } else {
            // Explicitly create the requested type
            switch (strtolower($type)) {
                case 'windows':
                    return new WindowsTaskScheduler();
                case 'cron':
                    return new CronTaskScheduler();
                case 'systemd':
                    return new SystemdTaskScheduler();
                default:
                    throw new Exception("Unknown scheduler type: $type");
            }
        }
    }
    
    /**
     * Detect if systemd is available
     * 
     * @return bool True if systemd is available
     */
    protected static function isSystemdAvailable() {
        exec("which systemctl 2>/dev/null", $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Set the task name
     * 
     * @param string $taskName Name of the task
     * @return $this For method chaining
     */
    public function setTaskName($taskName) {
        $this->taskName = $taskName;
        return $this;
    }
    
    /**
     * Set the command to execute
     * 
     * @param string $command Command to execute
     * @return $this For method chaining
     */
    public function setCommand($command) {
        $this->command = $command;
        return $this;
    }
    
    /**
     * Set the interval in minutes
     * 
     * @param int $minutes Interval in minutes
     * @return $this For method chaining
     * @throws Exception If interval is less than 1
     */
    public function setInterval($minutes) {
        if ($minutes < 1) {
            throw new Exception("Interval must be at least 1 minute");
        }
        $this->interval = $minutes;
        return $this;
    }
    
    /**
     * Set a property value
     * 
     * @param string $key Property key
     * @param mixed $value Property value
     * @return $this For method chaining
     */
    public function setProperty($key, $value) {
        $this->properties[$key] = $value;
        return $this;
    }
    
    /**
     * Set multiple properties at once
     * 
     * @param array $properties Properties as key-value pairs
     * @return $this For method chaining
     */
    public function setProperties(array $properties) {
        foreach ($properties as $key => $value) {
            $this->setProperty($key, $value);
        }
        return $this;
    }
    
    /**
     * Get a property value
     * 
     * @param string $key Property key
     * @param mixed $default Default value if property doesn't exist
     * @return mixed Property value or default
     */
    public function getProperty($key, $default = null) {
        return isset($this->properties[$key]) ? $this->properties[$key] : $default;
    }
    
    /**
     * Validate required properties
     * 
     * @throws Exception If required properties are missing
     */
    protected function validateRequiredProperties() {
        if (empty($this->taskName)) {
            throw new Exception("Task name is required");
        }
        
        if (empty($this->command)) {
            throw new Exception("Command is required");
        }
    }
    
    /**
     * Schedule the task
     * 
     * @return array Result of the operation
     */
    public function schedule() {
        $this->validateRequiredProperties();
        
        if ($this->exists()) {
            return $this->update();
        } else {
            return $this->create();
        }
    }
    
    /**
     * Create a new task
     * 
     * @return array Result of the operation
     */
    abstract protected function create();
    
    /**
     * Update an existing task
     * 
     * @return array Result of the operation
     */
    abstract protected function update();
    
    /**
     * Remove the task
     * 
     * @return array Result of the operation
     */
    abstract public function remove();
    
    /**
     * Check if the task exists
     * 
     * @return bool True if task exists
     */
    abstract public function exists();
    
    /**
     * Run the task immediately
     * 
     * @return array Result of the operation
     */
    abstract public function runNow();
    
    /**
     * Get details of the task
     * 
     * @return array Task details
     */
    abstract public function getDetails();
}
