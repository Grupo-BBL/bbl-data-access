<?php

function gtk_log($toLog)
{
    echo $toLog."\n";
    error_log($toLog);
}

function getErrorLogPath()
{
    // PHP_OS
    $repoRoot = dirname(__FILE__, 2);

    $GTK_DIRECTORY_SEPERATOR = "/";

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
    {
        $GTK_DIRECTORY_SEPERATOR = "\\";
    }

    $errorLogPath = $repoRoot.$GTK_DIRECTORY_SEPERATOR."seed.log";

    return $errorLogPath;
}

ini_set("error_log", getErrorLogPath());
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__FILE__, 2)."/vendor/autoload.php";

//-------------------------------------------------------------------------
//-------------------------------------------------------------------------
//-------------------------------------------------------------------------
//-------------------------------------------------------------------------

global $_GLOBALS;

$dataAccessorConstructions = $_GLOBALS["DataAccessManager_dataAccessorConstructions"];

echo "Creating permissions for #: ".count($dataAccessorConstructions)." data accessor constructions. \n";

foreach ($dataAccessorConstructions as $key => $construction)
{
    $dataAccessor = DataAccessManager::get($key);
    
    if (method_exists($dataAccessor, "createOrAnnounceTable"))
    {
        $dataAccessor->createOrAnnounceTable();
    }
}

//-------------------------------------------------------------------------
//-------------------------------------------------------------------------
//-------------------------------------------------------------------------
//-------------------------------------------------------------------------

foreach ($dataAccessorConstructions as $key => $construction)
{
    $permissions = [
        "create",
        "read",
        "update",
        "delete",
        //-------------------------------------------------------------------------
        "all",
    ];

    foreach ($permissions as $permissionKey)
    {
        $permissionName = $key.".".$permissionKey;

        $permission = [
            "name"         => $permissionName,
            "is_active"    => true,
            "date_created" => date("Y-m-d H:i:s"),
        ];

        echo "Creating permission: ".$permissionName."\n";
        
        DataAccessManager::get("permissions")->insertIfNotExists($permission);
    }
}

//-------------------------------------------------------------------------
//-------------------------------------------------------------------------
//-------------------------------------------------------------------------
//-------------------------------------------------------------------------

global $_GTK_DATA_ACCESS;
global $_STONEWOOD_LIB;

$PERMISSION_ARRAYS_TO_ADD = [
    // $_GTK_DATA_ACCESS["PERMISSIONS"] ?? [],
    // $_STONEWOOD_LIB["PERMISSIONS"]   ?? [],
];

foreach ($PERMISSION_ARRAYS_TO_ADD as $permissions)
{
    foreach ($permissions as $permission)
    {
        $permission = [
            "name"         => $permission,
            "is_active"    => true,
            "date_created" => date("Y-m-d H:i:s"),
        ];

        DataAccessManager::get("permissions")->insertIfNotExists($permission);

        echo "Creating permission: ".$permission["name"]."\n";
    }
}

//-------------------------------------------------------------------------
//-------------------------------------------------------------------------
//-------------------------------------------------------------------------
//-------------------------------------------------------------------------


$roles = [];

/*

*/

foreach ($roles as $role)
{

    echo "Creating role: ".$role['name']."\n";
    DataAccessManager::get("roles")->createOrManageRole($role);
}

//-------------------------------------------------------------------------
//-------------------------------------------------------------------------
//-------------------------------------------------------------------------
//-------------------------------------------------------------------------

$usersToAdd = [];

foreach ($usersToAdd as $user)
{
    try
    {
        $userFromDB = DataAccessManager::get("persona")->where("email", $user['email']);

        if (!$userFromDB)
        {
            $didInsert  = DataAccessManager::get("persona")->createUser($user);	
            $userFromDB = DataAccessManager::get("persona")->where("email", $user['email']);
        }

        if (!$userFromDB)
        {
            gtk_log("Failed to create user: ".$user['cedula']);
            continue;
        }

        $roles = $user['roles'] ?? [];
        
        echo "Will assign roles ".count($roles)." to user: ".$user['cedula']."...\n";

        foreach ($roles as $role)
        {
            DataAccessManager::get("persona")->assignRoleToUser($role, $userFromDB);
            echo "Assigned role: ".$role." to user: ".$user['cedula']."\n";
        }
    }
    catch (Exception $e)
    {
        $userFromDB = null;
        echo 'Excecption Creating User: '.$e->getMessage()."\n";
        echo "User: ".print_r($user, true)."\n";
        die();
    }
    echo "----------------------------------------------------------------------------------------\n";
    echo "----------------------------------------------------------------------------------------\n";
    echo "----------------------------------------------------------------------------------------\n";
}
