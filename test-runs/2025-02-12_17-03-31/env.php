<?php

global $_GLOBALS;

// Database Configurations
$_GLOBALS["DataAccessManager_DB_CONFIG"] = [
    "appDB" => [
        "connectionString" => "sqlite:/Users/gtavares/GitHub/gtk-core/gtk-data-access/test-runs/2025-02-12_17-03-31/database/test.db",
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

$_GLOBALS["EMAIL_QUEUE_USER"]       = "apikey";
$_GLOBALS["EMAIL_QUEUE_PASSWORD"]   = "SG.should-be-your-sendgrid-api-key";
$_GLOBALS["EMAIL_QUEUE_PORT"]       = 465;
$_GLOBALS["EMAIL_QUEUE_SMTP_HOST"]  = "smtp.sendgrid.net";
$_GLOBALS["EMAIL_QUEUE_SEND_FROM"]  = "tested_by_automation@bbl.do";

