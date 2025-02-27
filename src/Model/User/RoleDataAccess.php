<?php

use Dflydev\DotAccessData\Data;

class RoleDataAccess extends DataAccess
{

    public function getDefaultOptionsForSelectForUser($user)
    {
        return [
            "columnValue" => "id",
            "columnName"  => "name",
        ];
    }

    public function columnToCheckIfExists()
    {
        return "name";
    }

    public function isQualifiedRole($role)
    {
        $value =  $role["needs_qualifier"];

        switch ($value)
        {
            case "TRUE":
            case "true":
            case true:
            case 1:
                return true;
            default:
                return false;
        }
    }

    public function register()
    {
        $columnMappings = [
			new GTKColumnMapping($this, "id",	[
                "isPrimaryKey"    => true, 
                "isAutoIncrement" => true, 
                "hideOnForms"     => true, 
            ]), 
			new GTKColumnMapping($this, "name", [
                "isUnique" => true,
            ]),
			new GTKColumnMapping($this, "purpose"),
			new GTKColumnMapping($this, "is_active", [
                'formInputType' => 'select', 
				'possibleValues' => [ 
					true  => ['label'=>'TRUE'],
					false => ['label'=>'FALSE'],
				],
            ]),
            new GTKColumnMapping($this, "needs_qualifier", [
                'formInputType' => 'select', 
				'possibleValues' => [ 
					true  => ['label'=>'TRUE'],
					false => ['label'=>'FALSE'],
				],
            ]),
            new GTKColumnMapping($this, "qualifier_data_source", [
                "customInputFunctionClass"  => null,
                "customInputFunctionScope"  => "object", //--- instance?? ---
                "customInputFunctionObject" => DataAccessManager::get('DataAccessManager'),
                "customInputFunction"       => "generateSelectForUserColumnValueName",
            ]),
            new GTKColumnMapping($this, "qualifier_data_source_column"),
            new GTKColumnMapping($this, "qualifier_data_label_column"),
            new GTKColumnMapping($this, "permissionsArray", [
                "hideOnForms" => true, 
                "hideOnLists" => true,
            ]),
            new GTKColumnMapping($this, "is_root_role"),
			new GTKColumnMapping($this, "date_created"),
			new GTKColumnMapping($this, "date_modified"),
		];
		$this->dataMapping 			= new GTKDataSetMapping($this, $columnMappings);
		$this->defaultOrderByColumnKey = "name";
		$this->defaultOrderByOrder  = "DESC";
    }

    public function updateWithPHPKeys(&$item, $options = null, &$outError = null)
    {

        list($permissionsList, $roleData) = segregateArrayKeysWithPrefix('permission_granted_on_', $item);
        
        $roleReturn = parent::updateWithPHPKeys($roleData, $options);

        foreach ($permissionsList as $permission)
        {
            error_log("Permission: ".serialize($permission));
        }
        
        return $roleReturn;

    }

    public function isUserInRoleNamed($role, $user)
    {
        return DataAccessManager::get("roles")->isUserInRoleNamed($role, $user);
    }

    public function isUserInAnyOfTheseRoles($user, $toCheck)
    {
        $isAllowed = false;

        $roles = DataAccessManager::get('roles')->rolesForUser($user);

        foreach ($roles as $role)
        {
            if (in_array($role, $toCheck))
            {
                return true;
            }
        }

        return false;
    }

    public function isUserInAnyOfTheseRolesNamed($user, $toCheck)
    {
        $isAllowed = false;

        $roles = DataAccessManager::get('roles')->rolesForUser($user);

        foreach ($roles as $role)
        {
            if (in_array($role["name"], $toCheck))
            {
                return true;
            }
        }

        return false;
    }

    public function getRolesForUser(&$user = null)
    {
        return $this->rolesForUser($user);
    }

    public function rolesForUser(&$user = null)
    {
        if (!$user)
        {
            return [];
        }

        if (isset($user["gtk_cache"]["roles"]))
        {
            return $user["gtk_cache"]["roles"];
        }

        $roleRelatiosnForUser = DataAccessManager::get("role_person_relationships")->roleRelationsForUser($user);

        $roleIDS = [];

        foreach ($roleRelatiosnForUser as $roleRelation)
        {
            $roleIDS[] = $roleRelation["role_id"];
        }

        $query = new SelectQuery($this);

        $query->addClause(new WhereClause(
            "id", "IN", $roleIDS
        ));

        $roles = $query->executeAndReturnAll();

        if (!isset($user["gtk_cache"]))
        {
            $user["gtk_cache"] = [];
        }

        $user["gtk_cache"]["roles"] = $roles;

        return $roles;
    }

    public function getPermissionsForRole(&$role)
    {
        $debug = false;

        if (isset($role["permissions"]))
        {
            return $role["permissions"];
        }

        $permissions = [];
    
        $rolePermissions = DataAccessManager::get("role_permission_relationships")->permissionRelationsForRole($role);
        
        $permissions = [];

        foreach ($rolePermissions as $rolePermission)
        {
            $permissions[] = DataAccessManager::get("permissions")->getOne("id", $rolePermission["permission_id"]);
        }
    

        $role["permissions"] = $permissions;

        return $permissions;
    }

    public function addPermissionToRole(&$role, $maybePermissionIDArrayOrName)
    {
        $debug = false;

        $permissionID         = null;
        $permissionName       = null;
        $permissionQualifiers = null;

        if (is_numeric($maybePermissionIDArrayOrName))
        {
            $permissionID = $maybePermissionIDArrayOrName;
        }
        else if (is_string($maybePermissionIDArrayOrName))
        {
            $permissionName = $maybePermissionIDArrayOrName;
        }
        else if (is_array($maybePermissionIDArrayOrName))
        {
            if (array_key_exists("id", $maybePermissionIDArrayOrName))
            {
                $permission = $maybePermissionIDArrayOrName;
                $permissionID = $permission["id"];
            }
            else
            {
                $permissionName       = is_string($maybePermissionIDArrayOrName[0]) ? $maybePermissionIDArrayOrName[0] : null;
                $permissionQualifiers = is_array($maybePermissionIDArrayOrName[1]) ? $maybePermissionIDArrayOrName[1] : null;
            }
        }

        if ($permissionName)
        {
            $permission = DataAccessManager::get("permissions")->getOne("name", $maybePermissionIDArrayOrName);
            if (!$permission)
            {
                throw new Exception("`addPermissionToRole` :: Invalid Permission Name: ".$maybePermissionIDArrayOrName);
            }
            $permissionID = $permission["id"];
        }
    
        if (!$permissionID)
        {
            throw new Exception("Invalid Permission ID or Name: ".print_r($maybePermissionIDArrayOrName, true));
        }

        $toInsert = [
            "role_id"       => $role["id"],
            "permission_id" => $permissionID,
            "is_active"     => true,
            "date_created"  => date("Y-m-d H:i:s"),
        ];

        if ($permissionQualifiers)
        {
            $toInsert["qualifiers"] = $permissionQualifiers;
        }
        
        DataAccessManager::get("role_permission_relationships")->insert($toInsert);
    }

    public function removePermissionFromRole(&$role, $permissionToRemove)
    {
        $debug = false;

        $rolePermissions = DataAccessManager::get("role_permission_relationships")->permissionsForRole($role);
        
        foreach ($rolePermissions as $rolePermission)
        {
            if ($rolePermission["permission_id"] == $permissionToRemove["id"])
            {
                DataAccessManager::get("role_permission_relationships")->delete($rolePermission);
            }
        }
    }

    public function createRole(&$role)
    {
        $debug = false;

        $didInsert = $this->insertAssociativeArray($role);

        if (!$didInsert)
        {
            
            if ($debug)
            {
                gtk_log("Role Not Created: ".serialize($role));
            }

            throw new Exception("Role Not Created");
        }

        $roleFromDB = $this->getOne("name", $role["name"]);
        
        if ($debug)
        {
            gtk_log("Role Created: ".serialize($role));
        }
        
        return $roleFromDB;
    }

    public function manageRole(&$existingRole, &$roleInConfig)
    {
        $debug = false;

        if ($debug)
        {
            gtk_log("Managing Role: ".serialize($roleInConfig["name"]));
        }

        return $existingRole;
    }

    public function createOrManageRole(&$roleToCreateOrManage)
    {
        $debug = false;
        gtk_log("Role to create or manage: ".serialize($roleToCreateOrManage));

        $roleName = $roleToCreateOrManage["name"];

        $roleFromDB = $this->getOne("name", $roleName);

        if ($roleFromDB)
        {
            gtk_log(" - Role Exists: ".serialize($roleToCreateOrManage));
            $roleFromDB = $this->manageRole($roleFromDB, $roleToCreateOrManage);
        }
        else
        {
            gtk_log(" - Does not exist...will create role...: ($roleName) ".serialize($roleToCreateOrManage));
            $roleFromDB = $this->createRole($roleToCreateOrManage);
        }

        gtk_log(" - Role from DB: ".$this->identifierForItem($roleFromDB));

        $permissions = $this->getPermissionsForRole($roleFromDB);

        if (isset($roleToCreateOrManage["permissions_to_remove"])) 
        {
            $permissionsToRemove = $roleToCreateOrManage["permissions_to_remove"];

            foreach ($permissionsToRemove as $permission)
            {
                gtk_log(" - Removing Permission from $roleName: ".serialize($permission));
                
                if (in_array($permission, $permissions))
                {
                    $this->removePermissionFromRole($existingRole, $permission);
                }
            }
        }
        else
        {
            gtk_log(" - No permissions to remove for: ".$roleName);
        }

        if (isset($roleToCreateOrManage["permissions"])) 
        {
            $permissionsToAdd = $roleToCreateOrManage["permissions"];

            $permissions = DataAccessManager::get("permissions")->permissionsForRole($roleFromDB);

            if ($debug)
            {
                if (is_array($permissions))
                {
                    gtk_log("Permissions (Existing: ".count($permissions)." - Expected: ".count($permissionsToAdd).")");
                }
            }

            foreach ($permissionsToAdd as $permission)
            {

                $permissionName       = $permission;
                $permissionQualifiers = null;

                if (is_array($permission))
                {
                    $permissionName       = $permission[0];
                    $permissionQualifiers = $permission[1];
                }


                if (!in_array($permissionName, $permissions))
                {
                    gtk_log("Adding Permission to $roleName: ".serialize($permission));
                    $this->addPermissionToRole($roleFromDB, $permission);
                }
                else
                {
                    if ($permissionQualifiers)
                    {
                        $permissionRelationship = DataAccessManager::get("role_permission_relationships")->getPermissionRelationshipForRolePermission($roleFromDB, $permission);

                        $toUpdate = [
                            "id"         => $permissionRelationship["id"],
                            "qualifiers" => $permissionQualifiers,
                        ];

                        DataAccessManager::get("role_permission_relationships")->update($toUpdate);
                    }
                }
            }

            if ($debug)
            {
                gtk_log("Finished adding permissions to $roleName");
            }
        }
        else
        {
            if ($debug)
            {
                gtk_log("No permissions to add for: ".$roleName);
            }
        }
    }

}
