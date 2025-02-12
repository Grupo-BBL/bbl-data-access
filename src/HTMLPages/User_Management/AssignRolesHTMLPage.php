<?php


use GTKHTMLPage;
use DataAccessManager;

class AssignRolesHTMLPage extends GTKHTMLPage
{
    public function processPost()
    {
        $user = $_POST['user'];
        $roles = $_POST['roles'];

        
        if (is_string($roles)) {
            $roles = explode(',', $roles);
        }

        
        $flatRoleDataAccess = DataAccessManager::get('flat_role');

       
        $assignableRoles = $flatRoleDataAccess->rolesUserCanAddTo($user);

        
        $filteredRoles = array_filter($roles, function($role) use ($assignableRoles) {
            return in_array($role, array_column($assignableRoles, 'id'));
        });

        
        $result = $flatRoleDataAccess->assignRolesToUser($user, $filteredRoles);

        if ($result) {
            $this->messages[] = json_encode(['success' => true, 'message' => 'Roles asignados exitosamente.']);
        } else {
            $this->messages[] = json_encode(['success' => false, 'message' => 'Error al asignar los roles.']);
        }
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
    
        <h1>Asignar Roles a Usuario</h1>

        <?php
        echo $this->renderMessages();
        ?>

        <form action="/auth/assign_roles.php" method="post">
            <label for="user">Usuario:</label>
            <input type="text" id="user" name="user" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <br>
            <label for="roles">Roles:</label>
            <input type="text" id="roles" name="roles" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <br>
            <br>
            <input type="submit" value="Asignar Roles" class="px-8 w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 rounded">
        </form>
    
        <?php return ob_get_clean();
    }
}
?>