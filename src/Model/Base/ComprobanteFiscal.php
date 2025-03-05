<?php

namespace Model\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComprobanteFiscal extends DataAccess
{
    public function __construct()
    {
        $this->_tableName = 'comprobantes_fiscales';
        $this->singleItemName = 'Comprobante Fiscal';
        $this->pluralItemName = 'Comprobantes Fiscales';
        
        $this->dataMapping = [
            'encf' => [
                'type' => 'string',
                'required' => true,
                'label' => 'e-NCF',
                'unique' => true
            ],
            'tipo_comprobante' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Tipo de Comprobante'
            ],
            'fecha_emision' => [
                'type' => 'date',
                'required' => true,
                'label' => 'Fecha de Emisión'
            ],
            'fecha_firma' => [
                'type' => 'datetime',
                'required' => true,
                'label' => 'Fecha de Firma'
            ],
            'rnc_emisor' => [
                'type' => 'string',
                'required' => true,
                'label' => 'RNC Emisor',
                'validation' => '/^[0-9]{9,11}$/'
            ],
            'rnc_receptor' => [
                'type' => 'string',
                'required' => true,
                'label' => 'RNC Receptor',
                'validation' => '/^[0-9]{9,11}$/'
            ],
            'monto_gravado' => [
                'type' => 'decimal',
                'required' => true,
                'label' => 'Monto Gravado',
                'default' => 0
            ],
            'monto_exento' => [
                'type' => 'decimal',
                'required' => true,
                'label' => 'Monto Exento',
                'default' => 0
            ],
            'total_itbis' => [
                'type' => 'decimal',
                'required' => true,
                'label' => 'Total ITBIS',
                'default' => 0
            ],
            'monto_total' => [
                'type' => 'decimal',
                'required' => true,
                'label' => 'Monto Total'
            ],
            'estado' => [
                'type' => 'enum',
                'required' => true,
                'label' => 'Estado',
                'options' => ['borrador', 'firmado', 'enviado', 'aceptado', 'rechazado'],
                'default' => 'borrador'
            ],
            'xml_documento' => [
                'type' => 'text',
                'required' => false,
                'label' => 'XML del Documento'
            ]
        ];

        $this->defaultOrderByColumnKey = 'fecha_emision';
        $this->defaultOrderByOrder = 'DESC';
    }

    public function detalles()
    {
        return DataAccessManager::get('detalles_comprobantes')->select([
            'where' => [
                ['comprobante_id', '=', $this->valueForKey('id')]
            ],
            'orderBy' => [
                ['numero_linea', 'ASC']
            ]
        ]);
    }

    public function emisor()
    {
        return DataAccessManager::get('contribuyentes')->buscarPorRNC($this->valueForKey('rnc_emisor'));
    }

    public function receptor()
    {
        return DataAccessManager::get('contribuyentes')->buscarPorRNC($this->valueForKey('rnc_receptor'));
    }

    public function generarXML()
    {
        // TODO: Implementar generación de XML según especificaciones DGII
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><ECF></ECF>');
        // Implementar la estructura según norma DGII
        return $xml->asXML();
    }

    public function firmar()
    {
        if ($this->valueForKey('estado') !== 'borrador') {
            throw new \Exception("Solo se pueden firmar comprobantes en estado borrador.");
        }

        // TODO: Implementar firma digital
        $this->update([
            'estado' => 'firmado',
            'fecha_firma' => date('Y-m-d H:i:s')
        ]);

        return true;
    }

    public function enviarDGII()
    {
        if ($this->valueForKey('estado') !== 'firmado') {
            throw new \Exception("Solo se pueden enviar comprobantes firmados.");
        }

        // TODO: Implementar envío a DGII
        $this->update([
            'estado' => 'enviado'
        ]);

        return true;
    }

    public function beforeInsert(&$data)
    {
        // Validar RNCs
        if (!preg_match('/^[0-9]{9,11}$/', $data['rnc_emisor']) || 
            !preg_match('/^[0-9]{9,11}$/', $data['rnc_receptor'])) {
            throw new \Exception("RNC inválido.");
        }

        // Validar montos
        if ($data['monto_total'] <= 0) {
            throw new \Exception("El monto total debe ser mayor a cero.");
        }

        // Asegurar estado inicial
        $data['estado'] = 'borrador';
        
        return true;
    }
} 