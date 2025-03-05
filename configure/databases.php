<?php

global $_GLOBALS;
if (!$_GLOBALS)
{
	$_GLOBALS = [];
}


$_GLOBALS["GTK_DATA_ACCESS_CONSTRUCTIONS"] = [
	"phpinfo" => [
		"class" => "GTKPHPInfoDataAccess",
	],
	"dynamo" => [
		"class" => "GTKPHPInfoDataAccess",
	],
	/*
	*
	*
	*/
	"ShowDataSourceRenderer" => [
        "class" => "ShowDataSourceRenderer",
		"db" 	=> "appDB",
	],
	"NewDataSourceRenderer" => [
        "class" => "NewDataSourceRenderer",
		"db" 	=> "appDB",
	],
	"EditDataSourceRenderer" => [
        "class" => "EditDataSourceRenderer",
		"db" 	=> "appDB",
	],
	"AllDataSourceRenderer" => [
        "class" => "AllDataSourceRenderer",
		"db" 	=> "appDB",
	],
	//////////////////////////////////////////
	// -
	// - Roles
	// -
	//////////////////////////////////////////
	//////////////////////////////////////////
	"roles" => [
		"class"       => "RoleDataAccess", 
		"db"          => "appDB",
	],
	"role_person_relationships" => [
		"class"       => "FlatRoleDataAccess", 
		"db"          => "appDB",
	],
	"role_person_relationships" => [
		"class" => "RolePersonRelationshipsDataAccess",                  
		"db" 	=> "appDB",
	],
	"role_permission_relationships" => [
		"class" 	  => "RolePermissionRelationshipsDataAccess",              
		"db" 		  => "appDB",
		"tableName"   => "role_permission_relationships",
	],
	"reglas_registros" => [
        "class" => "ReglasRegistrosDataAccess",
        "db" => "appDB",
		"tableName"   => "reglas_registros",
    ],

	// "permission_person_relationships";
	//////////////////////////////////////////
	// - 
	// - Usuario
	// - 
	//////////////////////////////////////////
	//////////////////////////////////////////
	"request_password_reset" => [
		"class" => "RequestPasswordResetController",
	],
	"persona" => [
		"class" => "PersonaDataAccess",                     			   
		"db" 	=> "appDB",
	],
	"email_queue" => [
		"class"       			   => "EmailQueueManager", 
		"db"          			   => "appDB",
		"tableName"  		       => "EmailQueue",
		"defaultOrderByColumnKey"  => "CreatedAt",
		"defaultOrderByOrder"      => "DESC",
		"singleItemName"	       => "Email",
		"pluralItemName"	       => "Emails",
		// "_allowsCreation"      => false;,	
	],
	"mail_list_manager" => [
		"class"       => "MailListManager",
		"db"          => "appDB",
	],
	"permissions" => [
		"class"                => "PermissionDataAccess",                               
		"db" 		           => "appDB",
		"tableName"            => "permissions",
		"defaultOrderByColumnKey" => "name",
		"defaultOrderByOrder"  => "DESC",
	],
    "SetPasswordTokenDataAccess" => [
		"class"                 => "SetPasswordTokenDataAccess",
		"db"                    => "appDB",
		"defaultOrderByColumnKey" 	=> "fecha_creado",
		"defaultOrderByOrder"  	=> "DESC",  
	],
	"session" => [
		"class"       => "SessionDataAccess",
		"db"          => "appDB",
	],
	"solicitud_usuario" => [
        "class"       		   => "SolicitudUsuarioDataAccess",
        "db"          		   => "appDB",
		"tableName" 		   => "solicitudes_usuario",
		"defaultOrderByColumnKey" => "fecha_creado",
		"defaultOrderByOrder"  => "DESC",
	],
];
