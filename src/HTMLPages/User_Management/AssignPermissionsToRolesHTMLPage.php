<?php

use GTKHTMLPage;
use DataAccessManager;

class AssignPermissionsToRolesHTMLPage extends GTKHTMLPage
{
    public function processPost()
    {
        $role = $_POST['role'];
        $permissions = $_POST['permissions'];

        // Convertir permisos a un array si es necesario
        if (is_string($permissions)) {
            $permissions = explode(',', $permissions);
        }


        $rolePermissionDataAccess = DataAccessManager::get('role_permission_relationships');

        // Asignar los permisos al rol
        $result = $this->assignPermissionsToRole($rolePermissionDataAccess, $role, $permissions);

        if ($result) {
            $this->messages[] = json_encode(['success' => true, 'message' => 'Permisos asignados exitosamente.']);
        } else {
            $this->messages[] = json_encode(['success' => false, 'message' => 'Error al asignar los permisos.']);
        }
    }

    private function assignPermissionsToRole($rolePermissionDataAccess, $role, $permissions)
    {
        try {
            foreach ($permissions as $permission) {
                $permissionID = $this->getPermissionID($permission);
                $roleID = $this->getRoleID($role);

                $relationship = [
                    'role_id' => $roleID,
                    'permission_id' => $permissionID,
                    'is_active' => true,
                    'date_created' => date('Y-m-d H:i:s'),
                    'date_modified' => date('Y-m-d H:i:s')
                ];

                $rolePermissionDataAccess->insert($relationship);
            }

            return true; 
        } catch (Exception $e) {
            error_log("Error al asignar permisos: " . $e->getMessage());
            return false; 
        }
    }

    private function getPermissionID($permission)
    {
        if (is_numeric($permission)) {
            return $permission;
        } else if (is_string($permission)) {
            $permissionData = DataAccessManager::get("permissions")->where("name", $permission);
            return $permissionData["id"];
        } else if (is_array($permission)) {
            return $permission["id"];
        }

        throw new Exception("Permission with ID or Name does not exist: " . $permission);
    }

    private function getRoleID($role)
    {
        if (is_numeric($role)) {
            return $role;
        } else if (is_string($role)) {
            $roleData = DataAccessManager::get("roles")->where("name", $role);
            return $roleData["id"];
        } else if (is_array($role)) {
            return $role["id"];
        }

        throw new Exception("Role with ID or Name does not exist: " . $role);
    }

    public function renderMessages()
    {
        $toReturn = "";

        if (count($this->messages) > 0)
        {
            $toReturn .= "<h1 class='font-bold'>Mensajes</h1>";
            $toReturn .= "<div>";
            foreach ($this->messages as $message)
            {
                $toReturn .= "<div>";
                if (is_string($message))
                {
                    $toReturn .= htmlspecialchars($message);
                }
                else
                {
                    $toReturn .= print_r($message, true);
                }
                $toReturn .= "</div>";
            }
            $toReturn .= "</div>";
        }

        return $toReturn;
    }

    public function renderBody()
    {
        ob_start(); ?>
    
        <h1>Asignar Permisos a Rol</h1>

        <?php
        echo $this->renderMessages();
        ?>

        <form action="/auth/assign_permissions.php" method="post">
            <label for="role">Rol:</label>
            <input type="text" id="role" name="role" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <br>
            <label for="permissions">Permisos:</label>
            <input type="text" id="permissions" name="permissions" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <br>
            <br>
            <input type="submit" value="Asignar Permisos" class="px-8 w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 rounded">
        </form>
    
        <?php return ob_get_clean();
    }
}
?>