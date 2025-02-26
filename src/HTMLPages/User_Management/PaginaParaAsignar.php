<?php

class PaginaParaAsignar extends GTKHTMLPage
{
    public $NombreDataAccessParaUno;
    public $NombreDataAccessParaMuchos;
    public $NombreRelacion;
    private $relation;

    public function __construct($NombreDataAccessParaUno = null, $NombreDataAccessParaMuchos = null, $NombreRelacion = null, $unoColumn = null, $muchoColumn = null)
    {
        parent::__construct();
        $this->authenticationRequired = true;
        $this->NombreDataAccessParaUno = $NombreDataAccessParaUno;
        $this->NombreDataAccessParaMuchos = $NombreDataAccessParaMuchos;
        $this->NombreRelacion = $NombreRelacion;

        
        if ($unoColumn === null || $muchoColumn === null) {
            throw new InvalidArgumentException("Las columnas unoColumn y muchoColumn son obligatorias.");
        }

        $this->relation = new OneToManyRelation($NombreRelacion, $unoColumn, $muchoColumn);

     
        error_log("PaginaParaAsignar initialized with: NombreDataAccessParaUno = $NombreDataAccessParaUno, NombreDataAccessParaMuchos = $NombreDataAccessParaMuchos, NombreRelacion = $NombreRelacion, unoColumn = $unoColumn, muchoColumn = $muchoColumn");
    }

    public function processPost()
    {
        $uno = $_POST['uno'] ?? null;
        $mucho = $_POST['mucho'] ?? null;
        $action = $_POST['action'] ?? null;

        // Log the POST data
        error_log("processPost: uno = " . print_r($uno, true));
        error_log("processPost: mucho = " . print_r($mucho, true));
        error_log("processPost: action = " . print_r($action, true));

        if ($uno && $mucho && $action) {
            if ($action == 'assign') {
                if ($this->relation->assignRelation($uno, $mucho)) {
                    $this->messages[] = "Relación asignada correctamente.";
                } else {
                    $this->messages[] = "Error al asignar la relación.";
                }
            } elseif ($action == 'remove') {
                if ($this->relation->removeRelation($uno, $mucho)) {
                    $this->messages[] = "Relación eliminada correctamente.";
                } else {
                    $this->messages[] = "Error al eliminar la relación.";
                }
            }
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

    public function hasRelation($unoId, $muchoId)
    {
        try {
            if (empty($this->NombreDataAccessParaUno) || empty($this->NombreDataAccessParaMuchos)) {
                error_log("Error: Nombres de DataAccess no configurados");
                return false;
            }

            if (empty($unoId) || empty($muchoId)) {
                error_log("Error: IDs inválidos");
                return false;
            }

            return $this->relation->hasRelation($unoId, $muchoId);
        } catch (Exception $e) {
            error_log("Error en hasRelation: " . $e->getMessage());
            return false;
        }
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

        $selectedUno = $_POST['uno'] ?? $_GET['uno'] ?? null;

        // Log the selected uno
        error_log("renderBody: selectedUno = " . print_r($selectedUno, true));

        $assigned = [];
        $notAssigned = [];

        foreach ($muchos as $mucho) {
            if ($this->hasRelation($selectedUno, $mucho['id'])) {
                $assigned[] = $mucho;
            } else {
                $notAssigned[] = $mucho;
            }
        }

        
        error_log("renderBody: assigned = " . print_r($assigned, true));
        error_log("renderBody: notAssigned = " . print_r($notAssigned, true));

        // Paginación
        $itemsPerPage = 25;
        $currentPage = $_GET['page'] ?? 1;
        $totalItems = count($assigned) + count($notAssigned);
        $totalPages = ceil($totalItems / $itemsPerPage);
        $startIndex = ($currentPage - 1) * $itemsPerPage;
        $pagedItems = array_slice(array_merge($assigned, $notAssigned), $startIndex, $itemsPerPage);

        ob_start(); ?>
    
        <div class="container mx-auto mt-4">
            <h1 class="text-2xl font-bold mb-4">Asignar <?php echo ucfirst($this->NombreDataAccessParaMuchos); ?> a <?php echo ucfirst($this->NombreDataAccessParaUno); ?></h1>

            <?php echo $this->renderMessages(); ?>

            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <div class="mb-4">
                    <label for="uno" class="block text-gray-700 text-sm font-bold mb-2"><?php echo ucfirst($this->NombreDataAccessParaUno); ?>:</label>
                    <select id="uno" name="uno" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" onchange="this.form.submit()">
                        <option value="">Seleccione un <?php echo ucfirst($this->NombreDataAccessParaUno); ?></option>
                        <?php foreach ($unos as $uno): ?>
                            <option value="<?php echo htmlspecialchars($uno['id']); ?>" <?php echo $selectedUno == $uno['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($this->NombreDataAccessParaUno == 'persona' ? $uno['email'] : $uno['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
            </form>

            <?php if ($selectedUno): ?>
            <h2 class="text-xl font-bold mb-4"><?php echo ucfirst($this->NombreDataAccessParaMuchos); ?> Asignados</h2>
            <style>
               .form-group {
                    margin-bottom: 15px;
                }
                .form-group label {
                    display: block;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .btn-remove {
                    background-color: #dc3545;
                    color: white;
                    padding: 10px 15px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                }
                .btn-remove:hover {
                    background-color: #c82333;
                }
                .btn-assign {
                    background-color: #28a745;
                    color: white;
                    padding: 10px 15px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                }
                .btn-assign:hover {
                    background-color: #218838;
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
            </style>
            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo ucfirst($this->NombreDataAccessParaMuchos); ?></th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagedItems as $mucho): ?>
                        <tr class="<?php echo in_array($mucho, $assigned) ? 'bg-green-100' : ''; ?>">
                            <td><?php echo htmlspecialchars($mucho['name']); ?></td>
                            <td>
                                <?php if (in_array($mucho, $assigned)): ?>
                                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                        <input type="hidden" name="uno" value="<?php echo htmlspecialchars($selectedUno); ?>">
                                        <input type="hidden" name="mucho" value="<?php echo htmlspecialchars($mucho['id']); ?>">
                                        <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                        <input type="submit" name="action" value="remove" class="btn btn-remove">
                                    </form>
                                <?php else: ?>
                                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                        <input type="hidden" name="uno" value="<?php echo htmlspecialchars($selectedUno); ?>">
                                        <input type="hidden" name="mucho" value="<?php echo htmlspecialchars($mucho['id']); ?>">
                                        <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                        <input type="submit" name="action" value="assign" class="btn btn-assign">
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&uno=<?php echo htmlspecialchars($selectedUno); ?>" class="<?php echo $i == $currentPage ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    
        <?php return ob_get_clean();
    }
}