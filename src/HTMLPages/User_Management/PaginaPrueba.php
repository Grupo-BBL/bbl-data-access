<?php
class PaginaPrueba extends GTKHTMLPage
{
    private $pruebaDA;

    public function __construct()
    {
        parent::__construct();
        $this->authenticationRequired = true;
        $this->pruebaDA = DataAccessManager::get("prueba");
    }

    public function renderBody()
    {
        $pruebas = $this->pruebaDA->getAllPruebas();

        ob_start(); ?>

        <div class="container mx-auto mt-4">
            <h1 class="text-2xl font-bold mb-4">Datos de Prueba</h1>

            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Creado en</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pruebas as $prueba): ?>
                        <tr>
                            <td><?= htmlspecialchars($prueba['id']) ?></td>
                            <td><?= htmlspecialchars($prueba['nombre']) ?></td>
                            <td><?= htmlspecialchars($prueba['descripcion']) ?></td>
                            <td><?= htmlspecialchars($prueba['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <style>
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
            }
            .table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .table tr:nth-child(odd) {
                background-color: #ffffff;
            }
        </style>

        <?php return ob_get_clean();
    }
}