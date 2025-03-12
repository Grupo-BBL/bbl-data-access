<?php

global $_GLOBALS;

// Database Configurations
$_GLOBALS["DataAccessManager_DB_CONFIG"] = [
    "appDB" => [
        "connectionString" => "sqlite:/Users/gtavares/GitHub/gtk-core/gtk-data-access/test-runs/2025-02-12_16-54-40/database/test.db",
        "userName" => "",
        "password" => "",
        "journal_mode" => "WAL",
        "synchronous" => "NORMAL",
        "cache_size" => "-20000",
        "foreign_keys" => "ON"
    ]
];
// Map the configurations
$_GLOBALS["DataAccessManager_dataAccessorConstructions"] = $_GLOBALS["GTK_DATA_ACCESS_CONSTRUCTIONS"];
