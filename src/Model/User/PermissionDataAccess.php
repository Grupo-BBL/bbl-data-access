<?php

enum PermissionType: int {
    case None    = 0;
    case Read    = 1;
    case Write   = 3;

}

class PermissionDataAccess extends DataAccess 
{
    public function register()
    {
        $columnMappings = [
			new GTKColumnMapping($this, "id",  [
                "formLabel"       => "ID",
                "isPrimaryKey"    => true, 
                "isAutoIncrement" => true, 
                "hideOnForms"     => true, 
                "type"            => "int",
            ]), 
			new GTKColumnMapping($this, "name", [
                "formLabel"  => "Nombre",
                "isUnique"   => true,
                "isNullable" => false,
                "type"       => "varchar(255)",
            ]),
			new GTKColumnMapping($this, "comments", [
                "formLabel" => "Comentarios",
                "type"     => "text",
            ]),
			new GTKColumnMapping($this, "is_active", [
                "formLabel" => "¿Esta Activo?",
                "type"     => "tinyint(1)",
            ]),
			new GTKColumnMapping($this, "date_created", [
                "formLabel" => "Fecha Creacion",
                "type"     => "datetime",
            ]),
			new GTKColumnMapping($this, "date_modified", [
                "formLabel" => "Fecha Modificado",
                "type"     => "datetime",
            ]),
		];
        
		$this->dataMapping 			= new GTKDataSetMapping($this, $columnMappings);
		$this->defaultOrderByColumnKey = "name";
		$this->defaultOrderByOrder  = "DESC";
    }

    function hasPermission(&$permission, &$user)
    {
        return DataAccessManager::get("persona")->hasPermission($permission, $user);
    }

    function hasPermissionOnItem(&$permission, &$user, $item)
    {
        return DataAccessManager::get("persona")->hasPermission($permission, $user);
    }

    function permissionsForRole($role)
    {
        return DataAccessManager::get("role_permission_relationships")->permissionsForRole($role);
    }

    public function getPermissionsForUser($user)
    {
        return DataAccessManager::get("persona")->permissionsForUser($user);
    }
}
