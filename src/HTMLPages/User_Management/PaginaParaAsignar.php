<?php

use GTKHTMLPage;
use DataAccessManager;

class PaginaParaAsignar extends GTKHTMLPage
{
    public $NombreDataAccessParaUno;
    public $NombreDataAccessParaMuchos;

    public function __construct()
    {
        parent::__construct();
        $this->authenticationRequired = true;
    }

    public function processPost()
    {
        $uno = $_POST['uno'];
        $mucho = $_POST['mucho'] ?? null;
        $action = $_POST['action'] ?? null;

        $unoDataAccess = DataAccessManager::get($this->NombreDataAccessParaUno);
        $relationDataAccess = DataAccessManager::get('role_permission_relationships');

        if ($this->NombreDataAccessParaUno == 'roles' && $this->NombreDataAccessParaMuchos == 'permissions') {
            // Asignar permisos a roles
            if ($action == 'assign') {
                $result = $relationDataAccess->assignPermissionsToRole($uno, [$mucho]);
            } else if ($action == 'remove') {
                $result = $unoDataAccess->removePermissionFromRole($uno, ['id' => $mucho]);
            }
        } else if ($this->NombreDataAccessParaUno == 'persona' && $this->NombreDataAccessParaMuchos == 'roles') {
            // Asignar roles a usuarios
            $flatRoleDataAccess = DataAccessManager::get('flat_roles');
            if ($action == 'assign') {
                $result = $flatRoleDataAccess->assignRolesToUser($uno, [$mucho]);
            } else if ($action == 'remove') {
                $result = $flatRoleDataAccess->removeRolesFromUser($uno, [$mucho]);
            }
        }

        if (isset($result)) {
            if ($result) {
                $this->messages[] = json_encode(['Accion realizada exitosamente.']);
            } else {
                $this->messages[] = json_encode(['Error al realizar la operación.']);
            }
        }
    }

    public function renderMessages()
    {
        $toReturn = "";

        if (count($this->messages) > 0)
        {
            $toReturn .= "<h1>Mensajes</h1>";
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

    public function hasRelation($uno, $mucho)
    {
        if ($this->NombreDataAccessParaUno == 'roles' && $this->NombreDataAccessParaMuchos == 'permissions') {
            $relations = DataAccessManager::get('role_permission_relationships')->permissionRelationsForRole($uno);
        } else if ($this->NombreDataAccessParaUno == 'persona' && $this->NombreDataAccessParaMuchos == 'roles') {
            $relations = DataAccessManager::get('flat_roles')->roleRelationsForUser($uno);
        }
        return in_array($mucho, array_column($relations, 'permission_id'));
    }

    public function filterUnique($items)
    {
        $uniqueItems = [];
        $seen = [];

        foreach ($items as $item) {
            if (!in_array($item['name'], $seen)) {
                $uniqueItems[] = $item;
                $seen[] = $item['name'];
            }
        }

        return $uniqueItems;
    }

    public function renderBody()
    {
        $unoDataAccess = DataAccessManager::get($this->NombreDataAccessParaUno);
        $muchosDataAccess = DataAccessManager::get($this->NombreDataAccessParaMuchos);

        $unos = $unoDataAccess->selectAll();
        $muchos = $this->filterUnique($muchosDataAccess->selectAll());

        $selectedUno = $_POST['uno'] ?? null;
        $assignedMuchos = $selectedUno ? DataAccessManager::get('role_permission_relationships')->permissionRelationsForRole($selectedUno) : [];

        ob_start(); ?>
    
        <div class="container mx-auto mt-4">
            <h1 class="text-2xl font-bold mb-4">Asignar <?php echo ucfirst($this->NombreDataAccessParaMuchos); ?> a <?php echo ucfirst($this->NombreDataAccessParaUno); ?></h1>

            <?php echo $this->renderMessages(); ?>

            <form action="<?php echo $_SERVER['REQUEST_URI'] ?? ''; ?>" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <div class="mb-4">
                    <label for="uno" class="block text-gray-700 text-sm font-bold mb-2"><?php echo ucfirst($this->NombreDataAccessParaUno); ?>:</label>
                    <select id="uno" name="uno" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" onchange="this.form.submit()">
                        <option value="">Seleccione un <?php echo ucfirst($this->NombreDataAccessParaUno); ?></option>
                        <?php foreach ($unos as $uno): ?>
                            <option value="<?php echo htmlspecialchars($uno['id']); ?>" <?php echo $selectedUno == $uno['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($this->NombreDataAccessParaUno == 'persona' ? $uno['email'] : $uno['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selectedUno): ?>
                <!-- Aquí puedes agregar más contenido si es necesario -->
                <?php endif; ?>
            </form>

            <?php if ($selectedUno): ?>
            <h2 class="text-xl font-bold mb-4"><?php echo ucfirst($this->NombreDataAccessParaMuchos); ?> Asignados</h2>
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="py-2"><?php echo ucfirst($this->NombreDataAccessParaMuchos); ?></th>
                        <th class="py-2">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($muchos as $mucho): ?>
                        <tr>
                            <td class="border px-4 py-2"><?php echo htmlspecialchars($mucho['name']); ?></td>
                            <td class="border px-4 py-2">
                                <?php if ($this->hasRelation($selectedUno, $mucho['id'])): ?>
                                    <form action="<?php echo $_SERVER['REQUEST_URI'] ?? ''; ?>" method="POST">
                                        <input type="hidden" name="uno" value="<?php echo htmlspecialchars($selectedUno); ?>">
                                        <input type="hidden" name="mucho" value="<?php echo htmlspecialchars($mucho['id']); ?>">
                                        <input type="submit" name="action" value="remove" class="px-4 bg-red-500 hover:bg-red-700 text-white font-bold py-2 rounded">
                                    </form>
                                <?php else: ?>
                                    <form action="<?php echo $_SERVER['REQUEST_URI'] ?? ''; ?>" method="POST">
                                        <input type="hidden" name="uno" value="<?php echo htmlspecialchars($selectedUno); ?>">
                                        <input type="hidden" name="mucho" value="<?php echo htmlspecialchars($mucho['id']); ?>">
                                        <input type="submit" name="action" value="assign" class="px-4 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 rounded">
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    
        <?php return ob_get_clean();
    }
}