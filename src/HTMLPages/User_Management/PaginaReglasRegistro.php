<?php

class PaginaReglasRegistro extends GTKHTMLPage
{
    private $reglasDA;
    private $currentUser;

    public function __construct()
    {
        parent::__construct();
        $this->authenticationRequired = true;
        $this->reglasDA = DataAccessManager::get("reglas_registros");
        $this->currentUser = DataAccessManager::get("session")->getCurrentUser();
    }

    public function processPost()
    {
        $action = $_POST['action'] ?? null;
        $id = $_POST['id'] ?? null;

        if ($action === 'delete' && $id) {
            $this->reglasDA->delete($id);
            $this->messages[] = "Regla eliminada correctamente.";
        }
    }

    public function renderMessages()
    {
        $toReturn = "";

        if (count($this->messages) > 0) {
            $toReturn .= "<h1>Mensajes</h1>";
            $toReturn .= "<div>";
            foreach ($this->messages as $message) {
                $toReturn .= "<div>";
                if (is_string($message)) {
                    $toReturn .= htmlspecialchars($message);
                } else {
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
        $user = $this->getCurrentUser();
        $userRoles = DataAccessManager::get("roles")->rolesForUser($user);

        $isAdminOrDev = false;
        foreach ($userRoles as $role) {
            if (in_array(strtolower($role['name']), ['admin', 'dev', 'devs'])) {
                $isAdminOrDev = true;
                break;
            }
        }

        if (!$isAdminOrDev) {
            echo "Access denied. Only admin or dev users can access this page.";
            return;
        }

        // Parámetros de paginación y filtrado
        $busqueda = $_GET['busqueda'] ?? '';
        $filtroModelo = $_GET['modelo'] ?? '';
        $filtroRol = isset($_GET['rol']) ? intval($_GET['rol']) : null;
        $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
        $porPagina = 20;

        // Construir opciones de filtrado
        $options = ['orderby' => 'nombre'];
        $where = ['is_active' => 1];

        if (!empty($busqueda)) {
            $where[] = "nombre LIKE '%{$busqueda}%'";
        }

        if (!empty($filtroModelo)) {
            $where['modelo'] = $filtroModelo;
        }

        if ($filtroRol !== null) {
            if ($filtroRol === 0) {
                $where[] = "(role_id IS NULL OR es_global = 1)";
            } else {
                $where['role_id'] = $filtroRol;
            }
        }

        $options['where'] = $where;
        $options['limit'] = $porPagina;
        $options['offset'] = ($pagina - 1) * $porPagina;

        // Obtener reglas y contar total
        $reglas = $this->reglasDA->getAll($options);

        // Contar total para paginación
        $optionsCount = $options;
        $optionsCount['count'] = true;
        unset($optionsCount['limit']);
        unset($optionsCount['offset']);
        $total = $this->reglasDA->getAll($optionsCount);
        $totalRegistros = is_array($total) ? count($total) : $total;
        $totalPaginas = ceil($totalRegistros / $porPagina);

        // Obtener todos los modelos dinámicamente
        $modelos = $this->obtenerModelos();
        $roles = DataAccessManager::get("roles")->getAll(['orderby' => 'name']);

        ob_start(); ?>

        <div class="container mx-auto mt-4">
            <h1 class="text-2xl font-bold mb-4">Gestión de Reglas de Registro</h1>

            <?php echo $this->renderMessages(); ?>

            <!-- Formulario de búsqueda -->
            <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="busqueda" class="block text-gray-700 text-sm font-bold mb-2">Buscar:</label>
                        <input type="text" class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" id="busqueda" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    <div>
                        <label for="modelo" class="block text-gray-700 text-sm font-bold mb-2">Modelo:</label>
                        <select class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" id="modelo" name="modelo">
                            <option value="">Todos</option>
                            <?php foreach ($modelos as $modelo): ?>
                            <option value="<?= $modelo ?>" <?= $filtroModelo == $modelo ? 'selected' : '' ?>><?= $modelo ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="rol" class="block text-gray-700 text-sm font-bold mb-2">Rol:</label>
                        <select class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" id="rol" name="rol">
                            <option value="">Todos</option>
                            <option value="0" <?= $filtroRol === 0 ? 'selected' : '' ?>>Global/Sin rol</option>
                            <?php foreach ($roles as $rol): ?>
                            <option value="<?= $rol['id'] ?>" <?= $filtroRol == $rol['id'] ? 'selected' : '' ?>><?= htmlspecialchars($rol['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="btn btn-primary w-full">Filtrar</button>
                    </div>
                </form>
            </div>

            <!-- Tabla de reglas -->
            <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xl font-bold">Reglas de Registro</span>
                    <a href="ReglasRegistroForm.php" class="btn btn-primary">Nueva Regla</a>
                </div>
                <?php if (empty($reglas)): ?>
                <div class="alert alert-info">No se encontraron reglas con los criterios especificados.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Modelo</th>
                                <th>Rol</th>
                                <th>Global</th>
                                <th>Lectura</th>
                                <th>Escritura</th>
                                <th>Creación</th>
                                <th>Eliminación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reglas as $regla): ?>
                            <tr>
                                <td><?= htmlspecialchars($regla['nombre']) ?></td>
                                <td><?= htmlspecialchars($regla['modelo']) ?></td>
                                <td>
                                    <?php
                                    if ($regla['role_id']) {
                                        $rol = DataAccessManager::get("roles")->getById($regla['role_id']);
                                        echo htmlspecialchars($rol['name'] ?? 'N/A');
                                    } else {
                                        echo '<span class="badge bg-secondary">Sin rol</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($regla['es_global']): ?>
                                        <span class="badge bg-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($regla['aplicar_lectura']): ?>
                                        <span class="badge bg-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($regla['aplicar_escritura']): ?>
                                        <span class="badge bg-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($regla['aplicar_creacion']): ?>
                                        <span class="badge bg-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($regla['aplicar_eliminacion']): ?>
                                        <span class="badge bg-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                        <input type="hidden" name="id" value="<?= $regla['id'] ?>">
                                        <input type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de eliminar esta regla?')">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginación">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&modelo=<?= urlencode($filtroModelo) ?>&rol=<?= $filtroRol ?>">Anterior</a>
                        </li>

                        <?php for ($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++): ?>
                        <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&modelo=<?= urlencode($filtroModelo) ?>&rol=<?= $filtroRol ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&modelo=<?= $filtroModelo ?>&rol=<?= $filtroRol ?>">Siguiente</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .btn-primary {
                background-color: #007bff;
                color: white;
                padding: 10px 15px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn-primary:hover {
                background-color: #0056b3;
            }
            .btn-success {
                background-color: #28a745;
                color: white;
                padding: 10px 15px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn-success:hover {
                background-color: #218838;
            }
            .btn-info {
                background-color: #17a2b8;
                color: white;
                padding: 5px 10px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn-info:hover {
                background-color: #138496;
            }
            .btn-danger {
                background-color: #dc3545;
                color: white;
                padding: 5px 10px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn-danger:hover {
                background-color: #c82333;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
            }
            .table th, .table td {
                border: 1px solid #ddd;
                padding: 8px;
            }
            .table th {
                background-color: #f2f2f2;
                text-align: left;
            }
            .badge {
                padding: 5px 10px;
                border-radius: 4px;
                color: white;
            }
            .badge.bg-success {
                background-color: #28a745;
            }
            .badge.bg-secondary {
                background-color: #6c757d;
            }
            .pagination .page-item .page-link {
                padding: 10px 15px;
                border: 1px solid #ddd;
                margin: 0 5px;
                border-radius: 4px;
                text-decoration: none;
                color: #007bff;
            }
            .pagination .page-item .page-link:hover {
                background-color: #f2f2f2;
            }
            .pagination .page-item.active .page-link {
                background-color: #007bff;
                color: white;
            }
            .pagination .page-item.disabled .page-link {
                color: #6c757d;
            }
        </style>

        <?php return ob_get_clean();
    }

    private function obtenerModelos()
    {
        $modelos = [];
        $dataAccessors = DataAccessManager::getSingleton()->getRegisteredKeys();

        foreach ($dataAccessors as $dataAccessor) {
            $modelos[] = $dataAccessor;
        }

        return $modelos;
    }

    private function getCurrentUser()
    {
        return DataAccessManager::get("session")->getCurrentUser();
    }
}