<?php

class RolePermissionRelationshipsDataAccess extends DataAccess
{
    public $cache;

    public function __construct(PDO $PDODBObject, $options)
    {
        $this->setTableName("role_permission_relationships");
        parent::__construct($PDODBObject, $options);
    }

    public function getFreshColumnMappings()
    {
        return [
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
    }

    public function permissionRelationsForRole($role)
    {
        try {
        $debug = false;

            // Obtener el ID del rol
            $roleID = is_string($role) || is_numeric($role) 
                ? $role 
                : DataAccessManager::get('roles')->identifierForItem($role);

            if ($debug) {
                error_log("Role ID: $roleID");
            }

            // Construir la consulta
            $query = new SelectQuery($this);
            $query->where('role_id', '=', $roleID)
                  ->where('is_active', '=', true);

            if ($debug) {
                error_log("Query SQL: " . $query->sql());
            }

            return $query->executeAndReturnAll();

        } catch (Exception $e) {
            error_log("Error en permissionRelationsForRole: " . $e->getMessage());
            throw $e;
        }
    }

    public function permissionsForRole($role)
    {
        try {
        $debug = false;

            // Obtener el ID del rol
            $roleID = is_string($role) || is_numeric($role) 
                ? $role 
                : DataAccessManager::get('roles')->identifierForItem($role);
            
            if ($debug) {
                error_log("Buscando permisos para rol ID: " . $roleID);
            }

            // Verificar caché
            if (isset($this->cache[$roleID])) {
                if ($debug) {
                    error_log("Retornando desde caché");
                }
                return $this->cache[$roleID];
            }

            $permissionRelations = $this->permissionRelationsForRole($role);

            if (empty($permissionRelations)) {
                if ($debug) {
                    error_log("No se encontraron relaciones de permisos");
                }
                return [];
            }

            // Extraer IDs de permisos
            $permissionIDs = array_column($permissionRelations, 'permission_id');

            if (empty($permissionIDs)) {
                if ($debug) {
                    error_log("No se encontraron IDs de permisos");
                }
                return [];
            }

            // Obtener los permisos
            $permissions = DataAccessManager::get('permissions')->getByIdentifier($permissionIDs);

            if ($debug) {
                error_log("Permisos encontrados: " . json_encode($permissions));
            }

            // Extraer nombres de permisos
            $permissionNames = array_column($permissions, 'name');

            // Guardar en caché
            $this->cache[$roleID] = $permissionNames;

            return $permissionNames;

        } catch (Exception $e) {
            error_log("Error en permissionsForRole: " . $e->getMessage());
            throw $e;
        }
    }

    public function getPermissionRelationshipForRolePermission($roleIDOrNameOrObject, $permissionNameOrIDOrObject)
    {
        try {
            // Obtener ID del permiso
            $permissionID = null;
            if (is_numeric($permissionNameOrIDOrObject)) {
                $permissionID = $permissionNameOrIDOrObject;
            } else if (is_string($permissionNameOrIDOrObject)) {
                $permission = DataAccessManager::get("permissions")->whereOne("name", $permissionNameOrIDOrObject);
                $permissionID = $permission["id"];
            } else if (is_array($permissionNameOrIDOrObject)) {
                $permissionID = $permissionNameOrIDOrObject["id"];
            }

            if (!$permissionID) {
                throw new Exception("No se pudo determinar el ID del permiso");
            }

            // Obtener ID del rol
            $roleID = null;
            if (is_numeric($roleIDOrNameOrObject)) {
                $roleID = $roleIDOrNameOrObject;
            } else if (is_string($roleIDOrNameOrObject)) {
                $role = DataAccessManager::get("roles")->whereOne("name", $roleIDOrNameOrObject);
                $roleID = $role["id"];
            } else if (is_array($roleIDOrNameOrObject)) {
                $roleID = $roleIDOrNameOrObject["id"];
            }

            if (!$roleID) {
                throw new Exception("No se pudo determinar el ID del rol");
            }

            // Construir y ejecutar la consulta
            $query = new SelectQuery($this);
            $query->where('permission_id', '=', $permissionID)
                  ->where('role_id', '=', $roleID);

            return $query->executeAndReturnOne();

        } catch (Exception $e) {
            error_log("Error en getPermissionRelationshipForRolePermission: " . $e->getMessage());
            throw $e;
        }
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
}
