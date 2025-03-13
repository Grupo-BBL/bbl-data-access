<?php


if (!function_exists("gtk_log"))
{
    function gtk_log($toLog)
    {
        echo $toLog."\n";
    }
}

// This will load the optimized classmap autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize test environment
$testEnv = BBLTestEnvironment::init();

// Set the environment file path globally
global $_GLOBALS;
$_GLOBALS["ENV_FILE_PATH"] = $testEnv->getEnvFilePath();

// Environment setup only (for reference)
putenv('DB_HOSTNAME=127.0.0.1');
putenv('DB_USERNAME=kanboard');
putenv('DB_PASSWORD=kanboard');
putenv('DB_NAME=kanboard');

require_once __DIR__ . '/Support/Functions/functions.php';

// Clean up old test runs
// $testEnv->cleanup();

DataAccessManager::registerAccessor("testable_items", [
    "class" => "TestableDataAccess",
    "db"    => "appDB",
]);

// Initialize the database
DataAccessManager::configureSystem();
DataAccessManager::createTables();



// Automatically include all files in Support directory
$supportDir = __DIR__ . '/Support';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($supportDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        require_once $file->getPathname();
    }
} 