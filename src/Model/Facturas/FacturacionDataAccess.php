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
            ]),
            new GTKColumnMapping($this, "FGEnum", [
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

        error_log("Procesando factura: " . json_encode($factura));

        // Obtener información adicional usando los nuevos DataAccess
        $clienteDA = DataAccessManager::get('Cliente');
        $itemsDA = DataAccessManager::get('FacturacionItems');
        $pagoDA = DataAccessManager::get('FacturacionPago');

        $cliente = $clienteDA->getClienteInfo($factura['FGEcli']);
        $items = $itemsDA->getItemsPorFactura($factura['FGEnum']);
        $infoPago = $pagoDA->getInfoPago($facturaId);

        error_log("Buscando items para número interno de factura: " . $factura['FGEnum']);
        error_log("Items encontrados: " . json_encode($items));
        error_log("Info del cliente: " . json_encode($cliente));
        error_log("Info del pago: " . json_encode($infoPago));

        // Procesar los items
        $itemsProcesados = array_map(function($item) use ($factura) {
            $cantidad = floatval($item['yFGEcan']);
            $precioUnitario = floatval($item['yFGEval']);
            $subtotal = $cantidad * $precioUnitario;
            
            // Si el item está exento (yFGEexe = 1), no se calcula ITBIS
            $itbis = $item['yFGEexe'] ? 0 : ($subtotal * 0.18);
            
            return [
                "descripcion" => $item['yFGEdes'],
                "cantidad" => $cantidad,
                "precio_unitario" => $precioUnitario,
                "descuento" => 0,
                "itbis" => $itbis,
                "subtotal" => $subtotal,
                "isc" => 0,
                "propina_legal" => 0
            ];
        }, $items);

        error_log("Items procesados: " . json_encode($itemsProcesados));

        // Construir el array para el JSON
        $jsonData = [
            "origen" => "Stonewood-app",
            "factura" => [
                "numero_factura" => $factura['FGEncf'],
                "fecha_emision" => date('Y-m-d', strtotime($factura['FGEfec'])),
                
                "receptor" => [
                    "tipo_documento" => $cliente['tipo_documento'] ?? 'RNC',
                    "numero_documento" => $cliente['stFCRNC_CED'] ?? $cliente['stCRNC'] ?? '',
                    "nombre" => $cliente['nombre'] ?? $factura['FGEncl'],
                    "direccion" => $cliente['direccion'] ?? '',
                    "telefono" => $cliente['telefono'] ?? '',
                    "email" => $cliente['email'] ?? ''
                ],

                "items" => $itemsProcesados,

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

        error_log("JSON final: " . json_encode($jsonData));
        
        // Enviar el JSON a la API
        $resultado = $this->enviarJsonFactura($jsonData);
        if ($resultado) {
            error_log("JSON enviado exitosamente a la API para la factura: " . $factura['FGEncf']);
        } else {
            error_log("Error al enviar JSON a la API para la factura: " . $factura['FGEncf']);
        }
        
        return json_encode($jsonData, JSON_PRETTY_PRINT);
    }
    
    /**
     * Enviar el JSON de factura al endpoint correspondiente
     * @param array $jsonData El array de datos de factura
     * @return bool Resultado de la operación
     */
    public function enviarJsonFactura($jsonData)
    {
        try {
            // URL del endpoint que recibe las facturas (localhost para desarrollo)
            $url = 'http://localhost:8000/api/recibir-factura';
            
            error_log("Enviando factura a: " . $url);
            
            // Inicializar cURL
            $ch = curl_init($url);
            
            // Configurar opciones de cURL
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            
            // Tiempo máximo de espera (30 segundos)
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            // Ejecutar la solicitud
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Verificar errores de cURL
            if (curl_errno($ch)) {
                error_log('Error cURL al enviar factura: ' . curl_error($ch));
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            
            // Verificar respuesta exitosa (códigos 2xx)
            if ($httpCode >= 200 && $httpCode < 300) {
                error_log('Factura enviada exitosamente. Respuesta: ' . $response);
                return true;
            } else {
                error_log('Error al enviar factura. Código: ' . $httpCode . '. Respuesta: ' . $response);
                return false;
            }
        } catch (Exception $e) {
            error_log('Excepción al enviar factura: ' . $e->getMessage());
            return false;
        }
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
