<?php

class FacturacionAuxiliarDataAccess extends DataAccess
{
    public function register()
    {
        $columnMappings = [
            new GTKColumnMapping($this, "AUXcod", [
                "isPrimaryKey" => true,
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "AUXnom", [
                "isRequired" => true,
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "AUXrnc", [
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "AUXdir", [
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "AUXtel", [
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "AUXema", [
                "columnType" => "TEXT"
            ])
        ];
        
        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }

    /**
     * Obtener información completa del cliente
     */
    public function getClienteInfo($clienteId)
    {
        $query = new SelectQuery($this);
        $query->where("AUXcod", "=", $clienteId);
        $cliente = $query->executeAndReturnFirst();

        if ($cliente) {
            $cliente['tipo_documento'] = $this->determinarTipoDocumento($cliente['AUXrnc']);
        }

        return $cliente;
    }

    /**
     * Determinar tipo de documento basado en el RNC
     */
    private function determinarTipoDocumento($rnc)
    {
        if (empty($rnc)) {
            return 'RNC';
        }
        
        $length = strlen(trim($rnc));
        if ($length == 11) {
            return 'RNC';
        } elseif ($length == 13) {
            return 'CED';
        }
        return 'RNC';
    }

    public function getTableName()
    {
        return "ffAuxiliar";
    }

    public function getDBConfigName()
    {
        return "oldStoneDB";
    }
}