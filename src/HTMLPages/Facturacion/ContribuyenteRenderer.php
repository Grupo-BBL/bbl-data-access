<?php

namespace HTMLPages\Facturacion;

use HTMLPages\GTKHTMLPage;
use Model\Base\Contribuyente;
use DataAccessManager;

class ContribuyenteRenderer extends GTKHTMLPage
{
    private $contribuyenteDA;

    public function __construct()
    {
        parent::__construct();
        $this->authenticationRequired = true;
        $this->contribuyenteDA = DataAccessManager::get("contribuyentes");
    }

    public function renderBody()
    {
        $contribuyentes = $this->contribuyenteDA->selectAll();

        ob_start(); ?>

        <div class="container mx-auto mt-4">
            <h1 class="text-2xl font-bold mb-4">Gestión de Contribuyentes</h1>

            <div class="mb-4">
                <a href="?action=new" class="btn btn-primary">Nuevo Contribuyente</a>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>RNC</th>
                        <th>Razón Social</th>
                        <th>Nombre Comercial</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contribuyentes as $contribuyente): ?>
                        <tr>
                            <td><?= htmlspecialchars($contribuyente['rnc']) ?></td>
                            <td><?= htmlspecialchars($contribuyente['razon_social']) ?></td>
                            <td><?= htmlspecialchars($contribuyente['nombre_comercial']) ?></td>
                            <td><?= htmlspecialchars($contribuyente['tipo_contribuyente']) ?></td>
                            <td><?= htmlspecialchars($contribuyente['estado']) ?></td>
                            <td>
                                <a href="?action=edit&id=<?= $contribuyente['id'] ?>" class="btn btn-edit">Editar</a>
                                <a href="?action=delete&id=<?= $contribuyente['id'] ?>" class="btn btn-delete" onclick="return confirm('¿Está seguro de eliminar este contribuyente?')">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <style>
            .table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 1rem;
            }
            .table th, .table td {
                border: 1px solid #ddd;
                padding: 8px;
            }
            .table th {
                background-color: #f2f2f2;
                text-align: left;
            }
            .table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .btn {
                padding: 8px 16px;
                border-radius: 4px;
                text-decoration: none;
                display: inline-block;
                margin: 2px;
            }
            .btn-primary {
                background-color: #007bff;
                color: white;
            }
            .btn-edit {
                background-color: #28a745;
                color: white;
            }
            .btn-delete {
                background-color: #dc3545;
                color: white;
            }
        </style>

        <?php return ob_get_clean();
    }

    public function processPost()
    {
        $action = $_POST['action'] ?? $_GET['action'] ?? null;
        $id = $_POST['id'] ?? $_GET['id'] ?? null;

        if ($action === 'new' || $action === 'edit') {
            return $this->renderForm($id);
        }

        if ($action === 'save') {
            $data = $_POST;
            unset($data['action']);
            
            try {
                if (isset($data['id'])) {
                    $this->contribuyenteDA->update($data);
                    $this->messages[] = "Contribuyente actualizado exitosamente.";
                } else {
                    $this->contribuyenteDA->insert($data);
                    $this->messages[] = "Contribuyente creado exitosamente.";
                }
            } catch (\Exception $e) {
                $this->messages[] = "Error: " . $e->getMessage();
            }
        }

        if ($action === 'delete' && $id) {
            try {
                $this->contribuyenteDA->delete($id);
                $this->messages[] = "Contribuyente eliminado exitosamente.";
            } catch (\Exception $e) {
                $this->messages[] = "Error: " . $e->getMessage();
            }
        }
    }

    private function renderForm($id = null)
    {
        $contribuyente = null;
        if ($id) {
            $contribuyente = $this->contribuyenteDA->find($id);
        }

        ob_start(); ?>

        <div class="container mx-auto mt-4">
            <h1 class="text-2xl font-bold mb-4"><?= $id ? 'Editar' : 'Nuevo' ?> Contribuyente</h1>

            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" class="max-w-lg">
                <?php if ($id): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="rnc">RNC:</label>
                    <input type="text" id="rnc" name="rnc" required pattern="[0-9]{9,11}" 
                           value="<?= $contribuyente ? htmlspecialchars($contribuyente['rnc']) : '' ?>"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="razon_social">Razón Social:</label>
                    <input type="text" id="razon_social" name="razon_social" required
                           value="<?= $contribuyente ? htmlspecialchars($contribuyente['razon_social']) : '' ?>"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="nombre_comercial">Nombre Comercial:</label>
                    <input type="text" id="nombre_comercial" name="nombre_comercial"
                           value="<?= $contribuyente ? htmlspecialchars($contribuyente['nombre_comercial']) : '' ?>"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="tipo_contribuyente">Tipo de Contribuyente:</label>
                    <select id="tipo_contribuyente" name="tipo_contribuyente" required class="form-control">
                        <option value="PERSONA_FISICA" <?= $contribuyente && $contribuyente['tipo_contribuyente'] === 'PERSONA_FISICA' ? 'selected' : '' ?>>
                            Persona Física
                        </option>
                        <option value="PERSONA_JURIDICA" <?= $contribuyente && $contribuyente['tipo_contribuyente'] === 'PERSONA_JURIDICA' ? 'selected' : '' ?>>
                            Persona Jurídica
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="estado">Estado:</label>
                    <select id="estado" name="estado" required class="form-control">
                        <option value="activo" <?= $contribuyente && $contribuyente['estado'] === 'activo' ? 'selected' : '' ?>>
                            Activo
                        </option>
                        <option value="inactivo" <?= $contribuyente && $contribuyente['estado'] === 'inactivo' ? 'selected' : '' ?>>
                            Inactivo
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <input type="hidden" name="action" value="save">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>

        <style>
            .form-group {
                margin-bottom: 1rem;
            }
            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
            }
            .form-control {
                width: 100%;
                padding: 0.375rem 0.75rem;
                border: 1px solid #ced4da;
                border-radius: 0.25rem;
            }
            .btn-secondary {
                background-color: #6c757d;
                color: white;
            }
        </style>

        <?php return ob_get_clean();
    }
} 