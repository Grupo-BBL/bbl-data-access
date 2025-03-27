<?php




class FacturacionDataAccess extends DataAccess
{
    public function register()
    {
        $columnMappings = [
            new GTKColumnMapping($this, "FGEseq", [
                "isPrimaryKey" => true,
                "isAutoIncrement" => true,
                "columnType" => "INTEGER"
            ]),
            new GTKColumnMapping($this, "FGEcli", [
                "isRequired" => true,
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "FGEncl", [
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "FGEncf", [
                "isRequired" => true,
                "isUnique" => true,
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "FGEfec", [
                "isRequired" => true,
                "columnType" => "DATETIME"
            ]),
            new GTKColumnMapping($this, "FGEtot", [
                "isRequired" => true,
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "FGEitb", [
                "isRequired" => true,
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "FGEtip", [
                "columnType" => "TEXT"
            ])
        ];
        
        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }

    /**
     * Obtener todas las facturas ordenadas por fecha descendente
     * 
     * @return array Lista de facturas
     */
    public function selectAll()
    {
        $query = new SelectQuery($this);
        $query->orderBy("FGEfec", "DESC");
        return $query->executeAndReturnAll();
    }

    /**
     * Obtener una factura por su ID
     * 
     * @param int $id ID de la factura
     * @return array|null La factura o null si no se encuentra
     */
    public function getFacturaPorId($id)
    {
        return $this->getOne("FGEseq", $id);
    }

    /**
     * Obtener facturas por rango de fechas
     * 
     * @param string $fechaInicio Fecha inicial en formato Y-m-d
     * @param string $fechaFin Fecha final en formato Y-m-d
     * @return array Lista de facturas
     */
    public function getFacturasPorFecha($fechaInicio, $fechaFin)
    {
        $query = new SelectQuery($this);
        $query->where("FGEfec", ">=", $fechaInicio);
        $query->where("FGEfec", "<=", $fechaFin);
        $query->orderBy("FGEfec", "DESC");
        return $query->executeAndReturnAll();
    }

    /**
     * Obtener facturas por cliente
     * 
     * @param string $cliente Código del cliente
     * @return array Lista de facturas
     */
    public function getFacturasPorCliente($cliente)
    {
        $query = new SelectQuery($this);
        $query->where("FGEcli", "=", $cliente);
        $query->orderBy("FGEfec", "DESC");
        return $query->executeAndReturnAll();
    }

    /**
     * Obtener factura por NCF
     * 
     * @param string $ncf Número de Comprobante Fiscal
     * @return array|null La factura o null si no se encuentra
     */
    public function getFacturaPorNCF($ncf)
    {
        return $this->getOne("FGEncf", $ncf);
    }

    /**
     * Generar el JSON para envío a la API
     */
    public function generarJsonEnvio($facturaId)
    {
        // Obtener la factura
        $factura = $this->getFacturaPorId($facturaId);
        if (!$factura) {
            throw new Exception("Factura no encontrada");
        }

        // Obtener información adicional usando los nuevos DataAccess
        $auxiliarDA = DataAccessManager::get('FacturacionAuxiliar');
        $itemsDA = DataAccessManager::get('FacturacionItems');
        $pagoDA = DataAccessManager::get('FacturacionPago');

        $cliente = $auxiliarDA->getClienteInfo($factura['FGEcli']);
        $items = $itemsDA->getItemsPorFactura($facturaId);
        $infoPago = $pagoDA->getInfoPago($facturaId);

        // Construir el array para el JSON
        $jsonData = [
            "origen" => "Stonewood-app",
            "factura" => [
                "numero_factura" => $factura['FGEncf'],
                "fecha_emision" => date('Y-m-d', strtotime($factura['FGEfec'])),
                
                "receptor" => [
                    "tipo_documento" => $cliente['tipo_documento'] ?? 'RNC',
                    "numero_documento" => $cliente['AUXrnc'] ?? '',
                    "nombre" => $cliente['AUXnom'] ?? $factura['FGEncl'],
                    "direccion" => $cliente['AUXdir'] ?? '',
                    "telefono" => $cliente['AUXtel'] ?? '',
                    "email" => $cliente['AUXema'] ?? ''
                ],

                "items" => array_map(function($item) {
                    $subtotal = floatval($item['yFGEval']) * floatval($item['yFGEcan']);
                    return [
                        "descripcion" => $item['yFGEdes'],
                        "cantidad" => floatval($item['yFGEcan']),
                        "precio_unitario" => floatval($item['yFGEval']),
                        "descuento" => 0,
                        "itbis" => $subtotal * 0.18,
                        "isc" => 0,
                        "propina_legal" => 0
                    ];
                }, $items),

                "totales" => [
                    "subtotal" => floatval($factura['FGEtot']) - floatval($factura['FGEitb']),
                    "descuento_global" => 0,
                    "itbis" => floatval($factura['FGEitb']),
                    "isc" => 0,
                    "propina_legal" => 0,
                    "total" => floatval($factura['FGEtot'])
                ],

                "condiciones_pago" => [
                    "forma_pago" => $infoPago['forma_pago'] ?? '1',
                    "plazo" => intval($infoPago['plazo'] ?? 0),
                    "moneda" => $infoPago['moneda'] ?? 'DOP'
                ],

                "referencias" => [
                    "orden_compra" => "",
                    "pedido" => "",
                    "otros_referencias" => []
                ]
            ]
        ];

        return json_encode($jsonData, JSON_PRETTY_PRINT);
    }

    /**
     * Obtener el nombre de la tabla
     */
    public function getTableName()
    {
        return "ffFactGral";
    }

    /**
     * Obtener el nombre de la configuración de base de datos
     */
    public function getDBConfigName()
    {
        return "oldStoneDB";
    }
}
