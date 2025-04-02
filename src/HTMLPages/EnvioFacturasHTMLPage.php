<?php

class EnvioFacturasHTMLPage extends GTKHTMLPage
{
    public $messages = [];
    public $itemsPorPagina = 40;

    private function obtenerEstadoFactura($facturaId) {
        $db = DataAccessManager::get('Facturacion')->getDB();
        $sql = "SELECT estado, fecha_envio, fecha_recibido, fecha_procesado, mensaje 
                FROM ffFacturasEnvio 
                WHERE factura_id = :factura_id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':factura_id' => $facturaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function actualizarEstadoFactura($facturaId, $ncf, $estado = 'ENVIADO') {
        try {
            $db = DataAccessManager::get('Facturacion')->getDB();
            
            // Verificar si existe
            $sqlCheck = "SELECT COUNT(*) as existe FROM ffFacturasEnvio WHERE factura_id = :factura_id";
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->execute([':factura_id' => $facturaId]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC)['existe'] > 0;
            
            if ($existe) {
                // Actualizar
                $sqlUpdate = "UPDATE ffFacturasEnvio 
                             SET estado = :estado,
                                 fecha_envio = GETDATE()
                             WHERE factura_id = :factura_id";
                $stmt = $db->prepare($sqlUpdate);
                return $stmt->execute([
                    ':factura_id' => $facturaId,
                    ':estado' => $estado
                ]);
            } else {
                // Insertar
                $sqlInsert = "INSERT INTO ffFacturasEnvio (factura_id, ncf, estado, fecha_envio)
                             VALUES (:factura_id, :ncf, :estado, GETDATE())";
                $stmt = $db->prepare($sqlInsert);
                return $stmt->execute([
                    ':factura_id' => $facturaId,
                    ':ncf' => $ncf,
                    ':estado' => $estado
                ]);
            }
        } catch (Exception $e) {
            error_log("Error en actualizarEstadoFactura: " . $e->getMessage());
            throw $e;
        }
    }

    public function processPost()
    {
        if (isset($_POST['enviar_factura'])) {
            try {
                $facturaId = $_POST['factura_id'];
                $ncf = $_POST['factura_ncf'];
                $facturacionDA = DataAccessManager::get('Facturacion');
                
                // Verificar si la factura ya fue enviada
                $estadoActual = $this->obtenerEstadoFactura($facturaId);
                if ($estadoActual && $estadoActual['estado'] === 'PROCESADO') {
                    $this->messages[] = [
                        'type' => 'error',
                        'text' => "La factura con NCF: $ncf ya fue procesada y no puede ser enviada nuevamente"
                    ];
                    return;
                }
                
                // Generar el JSON
                $jsonData = $facturacionDA->generarJsonEnvio($facturaId);
                
                // Actualizar estado
                $this->actualizarEstadoFactura($facturaId, $ncf);
                
                $this->messages[] = [
                    'type' => 'success',
                    'text' => "Factura con NCF: $ncf marcada para envío"
                ];
                
            } catch (Exception $e) {
                $this->messages[] = [
                    'type' => 'error',
                    'text' => "Error al preparar la factura: " . $e->getMessage()
                ];
            }
        }
    }

    public function renderMessages()
    {
        $toReturn = "";

        if (count($this->messages) > 0) {
            foreach ($this->messages as $message) {
                $class = ($message['type'] === 'success') ? 'success-alert' : 'error-alert';
                $toReturn .= "<div class='alert $class'>";
                $toReturn .= "<p class='font-bold'>" . htmlspecialchars($message['text']) . "</p>";
                $toReturn .= "</div>";
            }
        }

        return $toReturn;
    }

    private function renderPaginacion($paginaActual, $totalPaginas) {
        if ($totalPaginas <= 1) {
            return '';
        }

        // Obtener los valores actuales de los filtros
        $clienteFilter = isset($_GET['filtro_cliente']) ? $_GET['filtro_cliente'] : '';
        $ncfFilter = isset($_GET['filtro_ncf']) ? $_GET['filtro_ncf'] : '';
        $fechaInicioFilter = isset($_GET['filtro_fecha_inicio']) ? $_GET['filtro_fecha_inicio'] : '';
        $fechaFinFilter = isset($_GET['filtro_fecha_fin']) ? $_GET['filtro_fecha_fin'] : '';

        // Construir la base de la URL con los filtros
        $params = [];
        if (!empty($clienteFilter)) $params[] = 'filtro_cliente=' . urlencode($clienteFilter);
        if (!empty($ncfFilter)) $params[] = 'filtro_ncf=' . urlencode($ncfFilter);
        if (!empty($fechaInicioFilter)) $params[] = 'filtro_fecha_inicio=' . urlencode($fechaInicioFilter);
        if (!empty($fechaFinFilter)) $params[] = 'filtro_fecha_fin=' . urlencode($fechaFinFilter);

        // Función helper para construir URLs de paginación
        $buildPageUrl = function($page) use ($params) {
            $pageParams = $params;
            $pageParams[] = 'pagina=' . $page;
            return '?' . implode('&', $pageParams);
        };

        $html = '<div class="pagination">';
        
        // Botón "Anterior"
        if ($paginaActual > 1) {
            $html .= '<a href="' . $buildPageUrl($paginaActual - 1) . '" class="btn-pagina">&laquo; Anterior</a>';
        }

        // Mostrar primera página
        if ($paginaActual > 3) {
            $html .= '<a href="' . $buildPageUrl(1) . '" class="btn-pagina">1</a>';
            if ($paginaActual > 4) {
                $html .= '<span class="pagina-ellipsis">...</span>';
            }
        }

        // Páginas alrededor de la página actual
        for ($i = max(1, $paginaActual - 2); $i <= min($totalPaginas, $paginaActual + 2); $i++) {
            if ($i == $paginaActual) {
                $html .= '<span class="pagina-actual">' . $i . '</span>';
            } else {
                $html .= '<a href="' . $buildPageUrl($i) . '" class="btn-pagina">' . $i . '</a>';
            }
        }

        // Mostrar última página
        if ($paginaActual < $totalPaginas - 2) {
            if ($paginaActual < $totalPaginas - 3) {
                $html .= '<span class="pagina-ellipsis">...</span>';
            }
            $html .= '<a href="' . $buildPageUrl($totalPaginas) . '" class="btn-pagina">' . $totalPaginas . '</a>';
        }

        // Botón "Siguiente"
        if ($paginaActual < $totalPaginas) {
            $html .= '<a href="' . $buildPageUrl($paginaActual + 1) . '" class="btn-pagina">Siguiente &raquo;</a>';
        }

        $html .= '</div>';
        
        // Agregar información de paginación
        $html .= '<div class="pagination-info">Página ' . $paginaActual . ' de ' . $totalPaginas . '</div>';

        return $html;
    }

    private function obtenerFacturasPaginadas($pagina = 1)
    {
        $facturacionDA = DataAccessManager::get('Facturacion');
        $db = $facturacionDA->getDB();
        
        $whereConditions = [];
        $params = [];
        
        // Construir condiciones WHERE basadas en los filtros
        if (!empty($_GET['filtro_cliente'])) {
            $whereConditions[] = "f.FGEcli LIKE :cliente";
            $params[':cliente'] = '%' . $_GET['filtro_cliente'] . '%';
        }
        
        if (!empty($_GET['filtro_ncf'])) {
            $whereConditions[] = "f.FGEncf LIKE :ncf";
            $params[':ncf'] = '%' . $_GET['filtro_ncf'] . '%';
        }
        
        if (!empty($_GET['filtro_fecha_inicio']) && !empty($_GET['filtro_fecha_fin'])) {
            $whereConditions[] = "f.FGEfec BETWEEN :fecha_inicio AND :fecha_fin";
            $params[':fecha_inicio'] = $_GET['filtro_fecha_inicio'] . ' 00:00:00';
            $params[':fecha_fin'] = $_GET['filtro_fecha_fin'] . ' 23:59:59';
        }
        
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        }
        
        // Consulta para contar el total de registros
        $sqlCount = "SELECT COUNT(*) as total 
                     FROM ffFactGral f 
                     LEFT JOIN ffFacturasEnvio fe ON f.FGEseq = fe.factura_id 
                     $whereClause";
        
        $stmt = $db->prepare($sqlCount);
        $stmt->execute($params);
        $totalFacturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Calcular el offset
        $offset = ($pagina - 1) * $this->itemsPorPagina;
        
        // Consulta paginada para SQL Server
        $sql = "SELECT f.*, fe.estado as estado_envio, fe.fecha_envio, 
                       fe.fecha_recibido, fe.fecha_procesado
                FROM (
                    SELECT *, ROW_NUMBER() OVER (ORDER BY FGEfec DESC) as RowNum
                    FROM ffFactGral f
                    $whereClause
                ) as f
                LEFT JOIN ffFacturasEnvio fe ON f.FGEseq = fe.factura_id
                WHERE RowNum BETWEEN :start AND :end";
        
        $params[':start'] = $offset + 1;
        $params[':end'] = $offset + $this->itemsPorPagina;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'facturas' => $facturas,
            'total' => $totalFacturas,
            'paginas' => ceil($totalFacturas / $this->itemsPorPagina)
        ];
    }

    private function obtenerEstiloEstado($estado, $estadoEnvio) {
        if ($estadoEnvio === 'PROCESADO') {
            return 'background-color: #10B981; color: white;';
        } elseif ($estadoEnvio === 'ENVIADO') {
            return 'background-color: #3B82F6; color: white;';
        } elseif ($estadoEnvio === 'RECIBIDO') {
            return 'background-color: #6366F1; color: white;';
        } else {
            return 'background-color: #9CA3AF; color: white;';
        }
    }

    public function renderBody()
    {
        // Obtener el número de página actual
        $paginaActual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
        
        // Obtener las facturas paginadas
        $resultado = $this->obtenerFacturasPaginadas($paginaActual);
        $facturas = $resultado['facturas'];
        $totalPaginas = $resultado['paginas'];

        ob_start(); ?>

        <style>
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
            .alert {
                padding: 10px;
                margin-bottom: 20px;
                border-radius: 5px;
            }
            .success-alert {
                background-color: rgb(220, 252, 231);
                color: rgb(22, 101, 52);
                border: 1px solid #86efac;
            }
            .error-alert {
                background-color: rgb(255, 252, 252);
                color: rgb(0, 0, 0);
                border: 1px solid #f5c6cb;
            }
            .table-container {
                overflow-x: auto;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f4f4f4;
                font-weight: bold;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            tr:hover {
                background-color: #f5f5f5;
            }
            .btn-enviar, .btn-buscar {
                background-color: #007bff;
                color: white;
                padding: 6px 12px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn-enviar:hover, .btn-buscar:hover {
                background-color: #0056b3;
            }
            .filters {
                margin-bottom: 20px;
                padding: 15px;
                background-color: #f8f9fa;
                border-radius: 5px;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
                align-items: end;
            }
            .filter-group {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .filter-group label {
                font-weight: bold;
                color: #555;
            }
            .filters input, .filters select {
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                width: 100%;
            }
            .pagination {
                margin-top: 20px;
                text-align: center;
            }
            .btn-pagina, .pagina-actual {
                display: inline-block;
                padding: 8px 12px;
                margin: 0 4px;
                border: 1px solid #ddd;
                border-radius: 4px;
                text-decoration: none;
                color: #007bff;
            }
            .btn-pagina:hover {
                background-color: #f8f9fa;
            }
            .pagina-actual {
                background-color: #007bff;
                color: white;
                border-color: #007bff;
            }
            .pagina-ellipsis {
                display: inline-block;
                padding: 8px 12px;
                margin: 0 4px;
            }
            .pagination-info {
                margin-top: 10px;
                color: #666;
                font-size: 0.9em;
            }
            .estado-badge {
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 0.875rem;
                font-weight: 500;
                text-align: center;
            }
            .tooltip {
                position: relative;
                display: inline-block;
            }
            .tooltip .tooltiptext {
                visibility: hidden;
                width: 200px;
                background-color: #333;
                color: #fff;
                text-align: center;
                border-radius: 6px;
                padding: 5px;
                position: absolute;
                z-index: 1;
                bottom: 125%;
                left: 50%;
                margin-left: -100px;
                opacity: 0;
                transition: opacity 0.3s;
            }
            .tooltip:hover .tooltiptext {
                visibility: visible;
                opacity: 1;
            }
        </style>

        <div class="container">
            <h1 class="text-2xl font-bold mb-4">Envío de Facturas Electrónicas</h1>

            <?php echo $this->renderMessages(); ?>

            <form method="GET" action="" class="filters">
                <div class="filter-group">
                    <label for="filtro_cliente">Cliente:</label>
                    <input type="text" id="filtro_cliente" name="filtro_cliente" 
                           value="<?php echo htmlspecialchars(isset($_GET['filtro_cliente']) ? $_GET['filtro_cliente'] : ''); ?>" 
                           placeholder="Código del cliente">
                </div>
                <div class="filter-group">
                    <label for="filtro_ncf">NCF:</label>
                    <input type="text" id="filtro_ncf" name="filtro_ncf" 
                           value="<?php echo htmlspecialchars(isset($_GET['filtro_ncf']) ? $_GET['filtro_ncf'] : ''); ?>" 
                           placeholder="Número de comprobante">
                </div>
                <div class="filter-group">
                    <label for="filtro_fecha_inicio">Fecha Inicio:</label>
                    <input type="date" id="filtro_fecha_inicio" name="filtro_fecha_inicio" 
                           value="<?php echo htmlspecialchars(isset($_GET['filtro_fecha_inicio']) ? $_GET['filtro_fecha_inicio'] : ''); ?>">
                </div>
                <div class="filter-group">
                    <label for="filtro_fecha_fin">Fecha Fin:</label>
                    <input type="date" id="filtro_fecha_fin" name="filtro_fecha_fin" 
                           value="<?php echo htmlspecialchars(isset($_GET['filtro_fecha_fin']) ? $_GET['filtro_fecha_fin'] : ''); ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-buscar">Buscar</button>
                </div>
            </form>

            <div class="table-container">
                <table id="tablaFacturas">
                    <thead>
                        <tr>
                            <th>NCF</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>ITBIS</th>
                            <th>Estado</th>
                            <th>Estado Envío</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($facturas as $factura): 
                            $estadoEnvio = $factura['estado_envio'] ?? 'PENDIENTE';
                            $estiloEstado = $this->obtenerEstiloEstado($factura['FGEtip'], $estadoEnvio);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($factura['FGEncf']); ?></td>
                            <td><?php echo htmlspecialchars($factura['FGEcli']); ?></td>
                            <td><?php echo htmlspecialchars($factura['FGEfec']); ?></td>
                            <td><?php echo number_format($factura['FGEtot'], 2); ?></td>
                            <td><?php echo number_format($factura['FGEitb'], 2); ?></td>
                            <td><?php echo htmlspecialchars($factura['FGEtip']); ?></td>
                            <td>
                                <div class="tooltip">
                                    <span class="estado-badge" style="<?php echo $estiloEstado; ?>">
                                        <?php echo htmlspecialchars($estadoEnvio); ?>
                                    </span>
                                    <?php if ($factura['fecha_envio']): ?>
                                    <span class="tooltiptext">
                                        Enviado: <?php echo $factura['fecha_envio']; ?><br>
                                        <?php if ($factura['fecha_recibido']): ?>
                                        Recibido: <?php echo $factura['fecha_recibido']; ?><br>
                                        <?php endif; ?>
                                        <?php if ($factura['fecha_procesado']): ?>
                                        Procesado: <?php echo $factura['fecha_procesado']; ?>
                                        <?php endif; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($estadoEnvio !== 'PROCESADO'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="factura_id" value="<?php echo htmlspecialchars($factura['FGEseq']); ?>">
                                    <input type="hidden" name="factura_ncf" value="<?php echo htmlspecialchars($factura['FGEncf']); ?>">
                                    <button type="submit" name="enviar_factura" class="btn-enviar">Enviar</button>
                                </form>
                                <?php else: ?>
                                <span class="estado-badge" style="background-color: #059669;">Completada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php echo $this->renderPaginacion($paginaActual, $totalPaginas); ?>
        </div>

        <?php return ob_get_clean();
    }
}
