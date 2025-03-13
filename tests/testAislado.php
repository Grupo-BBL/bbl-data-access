<?php
$dataAccessPath=dirname(__FILE__,2)."/src/Model/Base/DataAccess.php";
$dataAccessMPath=dirname(__FILE__,2)."/src/Model/Base/DataAccessManager.php";
$gtkColumnVirtualPath=dirname(__FILE__,2)."/src/Model/Base/GTKColumnVirtual.php";
$gtkColumnMappingPath=dirname(__FILE__,2)."/src/Model/Base/GTKColumnMapping.php";
$gtkColumnBasePath=dirname(__FILE__,2)."/src/Model/Base/GTKColumnBase.php";
$gtkDataSetPath=dirname(__FILE__,2)."/src/Model/Base/GTKDataSetMapping.php";
$personaDataAccessPath=dirname(__FILE__,2)."/src/Model/User/PersonaDataAccess.php";
$dataAccessActionPath=dirname(__FILE__,2)."/src/Model/Base/DataAccessAction.php";
$glangPath=dirname(__FILE__,2)."/src/Translations/Glang.php";
$langPath=dirname(__FILE__,2)."/configure/lang.php";
$arrayHelpersPath=dirname(__FILE__,2)."/vendor/gtk/gtk-helpers/src/lib/ArrayHelpers.php";
$ifRespondsPath=dirname(__FILE__,2)."/vendor/gtk/gtk-helpers/src/lib/ifResponds.php";
error_log($dataAccessPath);
require_once($arrayHelpersPath);
require_once($ifRespondsPath);
require_once($dataAccessPath);
require_once($dataAccessMPath);
require_once($personaDataAccessPath);
require_once($dataAccessActionPath);
require_once($gtkColumnBasePath);
require_once($gtkColumnMappingPath);
require_once($gtkColumnVirtualPath);
require_once($gtkDataSetPath);
require_once($glangPath);
require_once($langPath);

// $autoloadPath = dirname(__FILE__,2)."/vendor/autoload.php";
// require_once($autoloadPath);

$db_path = "sqlite:C:\proyectos\stonewood-app\Data\AppStonewood-Production.db";

$config =[
	"class" 	  => "StonewoodPersona",                     			   
	"db" 		  => "appDB",
	"tableName"   => "personas",
];
$sqlitePdo = new PDO($db_path);
$sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// SETUP

$dataAccess = new DataAccess($sqlitePdo, $config);

DataAccessManager::get('persona')


$tableName = $dataAccess->tableName;
//NOTE USE PDO DIRECTLY

// ($resultado, $esperado, $mensaje)
// testIsNull($resultado, "el resultado es: ".$resultado)
IF ($tableName != "personas")
{
    die("no se asigno correctamente el valor de tablename se obtuvo: ". $tableName );
}else
{
    error_log("tableName: ".$tableName);
}
//TEAR DOWN 
