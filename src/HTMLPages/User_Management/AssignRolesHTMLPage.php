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

        $flatRoleDataAccess = DataAccessManager::get('role_person_relationships');

        $result = $flatRoleDataAccess->assignRolesToUser($user, $roles);

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
        $userDataAccess = DataAccessManager::get('persona');
        $roleDataAccess = DataAccessManager::get('roles');

        $users = $userDataAccess->selectAll();
        $roles = $roleDataAccess->selectAll();

        ob_start(); ?>
    
        <h1>Asignar Roles a Usuario</h1>

        <?php
        echo $this->renderMessages();
        ?>

        <form action="<?php echo $_SERVER['REQUEST_URI'] ?? ''; ?>" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <label for="user">Usuario:</label>
            <select id="user" name="user" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['id']); ?>"><?php echo htmlspecialchars($user['email']); ?></option>
                <?php endforeach; ?>
            </select>
            <br>
            <label for="roles">Roles:</label>
            <select id="roles" name="roles[]" multiple required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                <?php foreach ($roles as $role): ?>
                    <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <br>
            <br>
            <input type="submit" value="Asignar Roles" class="px-8 w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 rounded">
        </form>
    
        <?php return ob_get_clean();
    }
}