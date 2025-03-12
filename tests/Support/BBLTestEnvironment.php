<?php

class  BBLTestEnvironment 
{
    private string $baseDir;
    private string $testRunDir;
    private string $dbDir;
    private string $dbFile;
    private string $envFile;
    private static ?BBLTestEnvironment $instance = null;

    private function __construct() 
    {
        $this->baseDir = dirname(__DIR__, 2); // Go up two levels from tests/Support
        $this->setupTestRunDirectory();
        $this->createDirectories();
        $this->createEnvFile();
    }

    public static function init(): BBLTestEnvironment 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function setupTestRunDirectory() 
    {
        $timestamp        = date('Y-m-d_H-i-s');
        $this->testRunDir = $this->baseDir . "/test-runs/" . $timestamp;
        $this->dbDir      = $this->testRunDir . "/database";
        $this->dbFile     = $this->dbDir . "/test.db";
        $this->envFile    = $this->testRunDir . "/env.php";
    }

    private function createDirectories() 
    {
        if (!file_exists($this->testRunDir)) {
            mkdir($this->testRunDir, 0777, true);
        }
        if (!file_exists($this->dbDir)) {
            mkdir($this->dbDir, 0777, true);
        }
    }

    private function createEnvFile() 
    {
        $envContent = <<<PHP
<?php

global \$_GLOBALS;

// Database Configurations
\$_GLOBALS["DataAccessManager_DB_CONFIG"] = [
    "appDB" => [
        "connectionString" => "sqlite:{$this->dbFile}",
        "userName" => "",
        "password" => "",
        "journal_mode" => "WAL",
        "synchronous" => "NORMAL",
        "cache_size" => "-20000",
        "foreign_keys" => "ON"
    ]
];

// Map the configurations
\$_GLOBALS["DataAccessManager_dataAccessorConstructions"] = \$_GLOBALS["GTK_DATA_ACCESS_CONSTRUCTIONS"];

\$_GLOBALS["EMAIL_QUEUE_USER"]       = "apikey";
\$_GLOBALS["EMAIL_QUEUE_PASSWORD"]   = "SG.should-be-your-sendgrid-api-key";
\$_GLOBALS["EMAIL_QUEUE_PORT"]       = 465;
\$_GLOBALS["EMAIL_QUEUE_SMTP_HOST"]  = "smtp.sendgrid.net";
\$_GLOBALS["EMAIL_QUEUE_SEND_FROM"]  = "tested_by_automation@bbl.do";


PHP;

        file_put_contents($this->envFile, $envContent);
        chmod($this->envFile, 0666);
    }

    public function getEnvFilePath(): string 
    {
        return $this->envFile;
    }

    public function getTestRunDir(): string 
    {
        return $this->testRunDir;
    }

    public function getDbFile(): string 
    {
        return $this->dbFile;
    }

    public function cleanup() 
    {
        // Optional: Clean up old test runs
        $testRunsDir = $this->baseDir . "/test-runs";
        $dirs = glob($testRunsDir . "/*", GLOB_ONLYDIR);
        
        // Keep only the last 5 test runs
        $keepCount = 5;
        if (count($dirs) > $keepCount) {
            $oldDirs = array_slice($dirs, 0, -$keepCount);
            foreach ($oldDirs as $dir) {
                $this->recursiveDelete($dir);
            }
        }
    }

    private function recursiveDelete($dir) 
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursiveDelete($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
} 