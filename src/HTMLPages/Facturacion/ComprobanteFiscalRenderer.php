<?php

namespace HTMLPages\Facturacion;

use HTMLPages\GTKHTMLPage;
use Model\Base\ComprobanteFiscal;
use DataAccessManager;

class ComprobanteFiscalRenderer extends GTKHTMLPage
{
    private $comprobanteDA;

    public function __construct()
    {
        parent::__construct();
        $this->authenticationRequired = true;
        $this->comprobanteDA = DataAccessManager::get("comprobantes_fiscales");
    }

    public function renderBody()
    {
        $comprobantes = $this->comprobanteDA->selectAll();

        ob_start(); ?>

        <div class="container mx-auto mt-4">
            <h1 class="text-2xl font-bold mb-4">Gestión de Comprobantes Fiscales</h1>

            <div class="mb-4">
                <a href="?action=new" class="btn btn-primary">Nuevo Comprobante</a>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>e-NCF</th>
                        <th>Tipo</th>
                        <th>Fecha Emisión</th>
                        <th>RNC Emisor</th>
                        <th>RNC Receptor</th>
                        <th>Monto Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comprobantes as $comprobante): ?>
                        <tr>
                            <td><?= htmlspecialchars($comprobante['encf']) ?></td>
                            <td><?= htmlspecialchars($comprobante['tipo_comprobante']) ?></td>
                            <td><?= htmlspecialchars($comprobante['fecha_emision']) ?></td>
                            <td><?= htmlspecialchars($comprobante['rnc_emisor']) ?></td>
                            <td><?= htmlspecialchars($comprobante['rnc_receptor']) ?></td>
                            <td><?= number_format($comprobante['monto_total'], 2) ?></td>
                            <td><?= htmlspecialchars($comprobante['estado']) ?></td>
                            <td>
                                <?php if ($comprobante['estado'] === 'borrador'): ?>
                                    <a href="?action=edit&id=<?= $comprobante['id'] ?>" class="btn btn-edit">Editar</a>
                                    <a href="?action=firmar&id=<?= $comprobante['id'] ?>" class="btn btn-sign" onclick="return confirm('¿Está seguro de firmar este comprobante?')">Firmar</a>
                                <?php elseif ($comprobante['estado'] === 'firmado'): ?>
                                    <a href="?action=enviar&id=<?= $comprobante['id'] ?>" class="btn btn-send" onclick="return confirm('¿Está seguro de enviar este comprobante a la DGII?')">Enviar a DGII</a>
                                <?php endif; ?>
                                <a href="?action=view&id=<?= $comprobante['id'] ?>" class="btn btn-view">Ver</a>
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
            .btn-sign {
                background-color: #ffc107;
                color: black;
            }
            .btn-send {
                background-color: #17a2b8;
                color: white;
            }
            .btn-view {
                background-color: #6c757d;
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

        if ($action === 'view' && $id) {
            return $this->renderView($id);
        }

        if ($action === 'save') {
            $data = $_POST;
            unset($data['action']);
            
            try {
                if (isset($data['id'])) {
                    $this->comprobanteDA->update($data);
                    $this->messages[] = "Comprobante actualizado exitosamente.";
                } else {
                    $this->comprobanteDA->insert($data);
                    $this->messages[] = "Comprobante creado exitosamente.";
                }
            } catch (\Exception $e) {
                $this->messages[] = "Error: " . $e->getMessage();
            }
        }

        if ($action === 'firmar' && $id) {
            try {
                $comprobante = $this->comprobanteDA->find($id);
                $comprobante->firmar();
                $this->messages[] = "Comprobante firmado exitosamente.";
            } catch (\Exception $e) {
                $this->messages[] = "Error: " . $e->getMessage();
            }
        }

        if ($action === 'enviar' && $id) {
            try {
                $comprobante = $this->comprobanteDA->find($id);
                $comprobante->enviarDGII();
                $this->messages[] = "Comprobante enviado exitosamente a la DGII.";
            } catch (\Exception $e) {
                $this->messages[] = "Error: " . $e->getMessage();
            }
        }
    }

    private function renderForm($id = null)
    {
        $comprobante = null;
        if ($id) {
            $comprobante = $this->comprobanteDA->find($id);
        }

        ob_start(); ?>

        <div class="container mx-auto mt-4">
            <h1 class="text-2xl font-bold mb-4"><?= $id ? 'Editar' : 'Nuevo' ?> Comprobante Fiscal</h1>

            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" class="max-w-lg">
                <?php if ($id): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="tipo_comprobante">Tipo de Comprobante:</label>
                    <select id="tipo_comprobante" name="tipo_comprobante" required class="form-control">
                        <option value="E31" <?= $comprobante && $comprobante['tipo_comprobante'] === 'E31' ? 'selected' : '' ?>>
                            Factura de Crédito Fiscal Electrónica
                        </option>
                        <option value="E32" <?= $comprobante && $comprobante['tipo_comprobante'] === 'E32' ? 'selected' : '' ?>>
                            Factura de Consumo Electrónica
                        </option>
                        <option value="E34" <?= $comprobante && $comprobante['tipo_comprobante'] === 'E34' ? 'selected' : '' ?>>
                            Nota de Débito Electrónica
                        </option>
                        <option value="E35" <?= $comprobante && $comprobante['tipo_comprobante'] === 'E35' ? 'selected' : '' ?>>
                            Nota de Crédito Electrónica
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fecha_emision">Fecha de Emisión:</label>
                    <input type="date" id="fecha_emision" name="fecha_emision" required
                           value="<?= $comprobante ? htmlspecialchars($comprobante['fecha_emision']) : date('Y-m-d') ?>"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="rnc_emisor">RNC Emisor:</label>
                    <input type="text" id="rnc_emisor" name="rnc_emisor" required pattern="[0-9]{9,11}"
                           value="<?= $comprobante ? htmlspecialchars($comprobante['rnc_emisor']) : '' ?>"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="rnc_receptor">RNC Receptor:</label>
                    <input type="text" id="rnc_receptor" name="rnc_receptor" required pattern="[0-9]{9,11}"
                           value="<?= $comprobante ? htmlspecialchars($comprobante['rnc_receptor']) : '' ?>"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="monto_gravado">Monto Gravado:</label>
                    <input type="number" id="monto_gravado" name="monto_gravado" required step="0.01" min="0"
                           value="<?= $comprobante ? htmlspecialchars($comprobante['monto_gravado']) : '0.00' ?>"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="monto_exento">Monto Exento:</label>
                    <input type="number" id="monto_exento" name="monto_exento" required step="0.01" min="0"
                           value="<?= $comprobante ? htmlspecialchars($comprobante['monto_exento']) : '0.00' ?>"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="total_itbis">Total ITBIS:</label>
                    <input type="number" id="total_itbis" name="total_itbis" required step="0.01" min="0"
                           value="<?= $comprobante ? htmlspecialchars($comprobante['total_itbis']) : '0.00' ?>"
                           class="form-control">
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

    private function renderView($id)
    {
        $comprobante = $this->comprobanteDA->find($id);
        if (!$comprobante) {
            $this->messages[] = "Comprobante no encontrado.";
            return;
        }

        ob_start(); ?>

        <div class="container mx-auto mt-4">
            <h1 class="text-2xl font-bold mb-4">Ver Comprobante Fiscal</h1>

            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">Información General</h2>
                    <table class="table-info">
                        <tr>
                            <th>e-NCF:</th>
                            <td><?= htmlspecialchars($comprobante['encf']) ?></td>
                        </tr>
                        <tr>
                            <th>Tipo:</th>
                            <td><?= htmlspecialchars($comprobante['tipo_comprobante']) ?></td>
                        </tr>
                        <tr>
                            <th>Fecha Emisión:</th>
                            <td><?= htmlspecialchars($comprobante['fecha_emision']) ?></td>
                        </tr>
                        <tr>
                            <th>Estado:</th>
                            <td><?= htmlspecialchars($comprobante['estado']) ?></td>
                        </tr>
                    </table>

                    <h2 class="card-title mt-4">Información Fiscal</h2>
                    <table class="table-info">
                        <tr>
                            <th>RNC Emisor:</th>
                            <td><?= htmlspecialchars($comprobante['rnc_emisor']) ?></td>
                        </tr>
                        <tr>
                            <th>RNC Receptor:</th>
                            <td><?= htmlspecialchars($comprobante['rnc_receptor']) ?></td>
                        </tr>
                    </table>

                    <h2 class="card-title mt-4">Montos</h2>
                    <table class="table-info">
                        <tr>
                            <th>Monto Gravado:</th>
                            <td><?= number_format($comprobante['monto_gravado'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Monto Exento:</th>
                            <td><?= number_format($comprobante['monto_exento'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Total ITBIS:</th>
                            <td><?= number_format($comprobante['total_itbis'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Monto Total:</th>
                            <td><?= number_format($comprobante['monto_total'], 2) ?></td>
                        </tr>
                    </table>

                    <?php if ($comprobante['estado'] !== 'borrador'): ?>
                        <h2 class="card-title mt-4">XML del Documento</h2>
                        <pre class="xml-view"><?= htmlspecialchars($comprobante['xml_documento']) ?></pre>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-4">
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">Volver</a>
            </div>
        </div>

        <style>
            .card {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                padding: 20px;
            }
            .card-title {
                font-size: 1.25rem;
                font-weight: bold;
                margin-bottom: 1rem;
            }
            .table-info {
                width: 100%;
                margin-bottom: 1rem;
            }
            .table-info th {
                text-align: left;
                padding: 8px;
                width: 200px;
                background-color: #f8f9fa;
            }
            .table-info td {
                padding: 8px;
            }
            .xml-view {
                background: #f8f9fa;
                padding: 1rem;
                border-radius: 4px;
                overflow-x: auto;
            }
            .mt-4 {
                margin-top: 1rem;
            }
        </style>

        <?php return ob_get_clean();
    }
} 