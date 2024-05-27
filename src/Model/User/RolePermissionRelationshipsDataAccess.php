<?php

class RolePermissionRelationshipsDataAccess extends DataAccess
{
    public $cache;

    public function register()
    {
            $columnMappings = [
			new GTKColumnMapping($this, "role_permission_relationship_id", [
                "formLabel" => "ID",
                "isPrimaryKey" => true, 
                "isAutoIncrement" => true, 
                "hideOnForms" => true,
            ]), 
            new GTKColumnMapping($this, "permission_id", [
                "columnType" => "INTEGER",
            ]),
            new GTKColumnMapping($this, "role_id", [
                "columnType" => "INTEGER",
            ]),
            new GTKColumnMapping($this, "qualifiers"),
			new GTKColumnMapping($this, "comments"),
			new GTKColumnMapping($this, "is_active", [
                "columnType" => "BOOLEAN",
            ]),
			new GTKColumnMapping($this, "date_created"),
			new GTKColumnMapping($this, "date_modified"),
		];

		$this->dataMapping 			= new GTKDataSetMapping($this, $columnMappings);
		$this->defaultOrderByColumn = "name";
		$this->defaultOrderByOrder  = "DESC";
    }

    public function migrate()
    {
        $this->getDB()->query("CREATE TABLE IF NOT EXISTS {$this->tableName()} 
        (role_permission_relationship_id INTEGER PRIMARY KEY,
         role_id,
         permission_id, 
         comments,
         is_active,
         date_created,
         date_modified,
        UNIQUE(role_permission_relationship_id))");

        $this->getDB()->query("ALTER TABLE ".$this->tableName()." ADD COLUMN qualifiers;");
    }

    public function permissionRelationsForRole($role)
    {
        $debug = false;

        $roleID = null;

        if (is_string($role) || is_numeric($role))
        {
            $roleID = $role;
        }
        else
        {
            $roleID = DataAccessManager::get('roles')->identifierForItem($role);
        }
        
        if ($debug)
        {
            gtk_log("Role ID: $roleID");
        }

        $query = new SelectQuery($this);

        $query->where(new WhereClause(
            "role_id", "=", $roleID
        ));

        /*
        $isActiveClause = new WhereGroup("OR");

        $isActiveClause->where(new WhereClause(
            "is_active", "=",  true
        ));

        $isActiveClause->where(new WhereClause(
            "is_active", "=",  "1"
        ));

        $query->where($isActiveClause);
        */

        if ($debug)
        {
            gtk_log("Query Count : ".$query->count());
            gtk_log("Query SQL   : ".$query->sql());
        }

        $result = $query->executeAndReturnAll();

        return $result;
    }

    public function permissionsForRole($role)
    {
        $debug = false;

        if (is_string($role) || is_numeric($role))
        {
            $roleID = $role;
        }
        else
        {
            $roleID = DataAccessManager::get('roles')->identifierForItem($role);
        }
        
        if ($debug)
        {
            gtk_log("Role ID: $roleID");
        }

        if (isset($this->cache[$roleID]))
        {
            if ($debug)
            {
                gtk_log("Returning from cache: ".print_r($this->cache[$roleID], true));
            }
            return $this->cache[$roleID];
        }

        $permissionRelationsForRole = $this->permissionRelationsForRole($role);

        $permissionIDS = [];

        foreach ($permissionRelationsForRole as $permissionRelation)
        {
            if ($debug)
            {
                gtk_log("Permission Relations: ".print_r($permissionRelation, true));
            }
            $permissionIDS[] = $permissionRelation["permission_id"];
        }

        if ($debug)
        {
            gtk_log("Permission IDs fpr Role (".serialize($role).") - : ".print_r($permissionIDS, true));
        }

        $permissions = DataAccessManager::get('permissions')->getByIdentifier($permissionIDS);    

        if ($debug)
        {
            gtk_log("Got Permissions: ".print_r($permissions, true));
        }

        $toReturn = [];

        foreach ($permissions as $permission)
        {
            if ($debug)
            {
                gtk_log("Permission: ".print_r($permission, true));
            }
            $toReturn[] = $permission["name"];
        }

        if ($debug)
        {
            gtk_log("Will return: Permissions: ".print_r($toReturn, true));
        }

        $this->cache[$roleID] = $toReturn;

        return $toReturn;
    }


    public function selectForRole($role)
    {
        $roleID = null;

        if (is_string($role) || is_numeric($role))
        {
            $roleID = $role;
        }
        else
        {
            $roleID = DataAccessManager::get('roles')->identifierForItem($role);
        }

        $permissions = $this->findByParameter("role_id", $roleID);

        return $permissions;
    }

    public function permissionDictionaryForRole($role)
    {
        $selected = $this->selectForRole($role);
        $toReturn = [];

        foreach ($selected as $grantedPermission)
        {
            $permissionID = $grantedPermission["permission_id"];

            $toReturn[$permissionID] = $grantedPermission;
        }

        return $toReturn;
    }

    public function getPermissionRelationshipForRolePermission($roleIDOrNameOrObject, $permissionNameOrIDOrObject)
    {
        $query = new SelectQuery($this);
        
        $permissionID = null;

        if (is_numeric($permissionNameOrIDOrObject))
        {
            $permissionID = $permissionNameOrIDOrObject;
        }
        else if (is_string($permissionNameOrIDOrObject))
        {
            $permission = DataAccessManager::get("permissions")->where("name", $permissionNameOrIDOrObject);
            $permissionID = $permission["id"];
        }
        else if (is_array($permissionNameOrIDOrObject))
        {
            $permissionID = $permissionNameOrIDOrObject["id"];
        }

        if (!$permissionID)
        {
            throw new Exception("Permission with ID or Name does not exist: ".$permissionNameOrIDOrObject);
        }

        $query->addWhereClause(new WhereClause(
            "permission_id", "=", $permissionID
        ));

        $roleID = null;

        if (is_numeric($roleIDOrNameOrObject))
        {
            $roleID = $roleIDOrNameOrObject;
        }
        else if (is_string($roleIDOrNameOrObject))
        {
            $role = DataAccessManager::get("roles")->where("name", $roleIDOrNameOrObject);
            $roleID = $role["id"];
        }
        else if (is_array($roleIDOrNameOrObject))
        {
            $roleID = $roleIDOrNameOrObject["id"];
        }

        if (!$roleID)
        {
            throw new Exception("Role with ID or Name does not exist: ".$roleIDOrNameOrObject);
        }

        $query->addWhereClause(new WhereClause(
            "role_id", "=", $roleID
        ));

        $permissionRelationship = $query->executeAndReturnOne();

        return $permissionRelationship;
    }
}
