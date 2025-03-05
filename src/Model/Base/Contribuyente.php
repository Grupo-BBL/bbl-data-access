<?php

namespace Model\Base;

class Contribuyente extends DataAccess
{
    public function __construct()
    {
        $this->_tableName = 'contribuyentes';
        $this->singleItemName = 'Contribuyente';
        $this->pluralItemName = 'Contribuyentes';
        
        $this->dataMapping = [
            'rnc' => [
                'type' => 'string',
                'required' => true,
                'label' => 'RNC',
                'validation' => '/^[0-9]{9,11}$/'
            ],
            'razon_social' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Razón Social'
            ],
            'nombre_comercial' => [
                'type' => 'string',
                'required' => false,
                'label' => 'Nombre Comercial'
            ],
            'tipo_contribuyente' => [
                'type' => 'enum',
                'required' => true,
                'label' => 'Tipo de Contribuyente',
                'options' => ['PERSONA_FISICA', 'PERSONA_JURIDICA']
            ],
            'estado' => [
                'type' => 'enum',
                'required' => true,
                'label' => 'Estado',
                'options' => ['activo', 'inactivo'],
                'default' => 'activo'
            ]
        ];

        $this->defaultOrderByColumnKey = 'razon_social';
    }

    public function insertFromForm($data)
    {
        // Validar RNC
        if (!preg_match('/^[0-9]{9,11}$/', $data['rnc'])) {
            throw new \Exception("RNC inválido. Debe tener entre 9 y 11 dígitos.");
        }

        // Validar tipo de contribuyente
        if (!in_array($data['tipo_contribuyente'], ['PERSONA_FISICA', 'PERSONA_JURIDICA'])) {
            throw new \Exception("Tipo de contribuyente inválido.");
        }

        // Si no se proporciona nombre comercial, usar razón social
        if (empty($data['nombre_comercial'])) {
            $data['nombre_comercial'] = $data['razon_social'];
        }

        return parent::insertFromForm($data);
    }

    public function beforeInsert(&$data)
    {
        // Asegurarse de que el estado tenga un valor válido
        if (empty($data['estado'])) {
            $data['estado'] = 'activo';
        }
        return true;
    }

    public function buscarPorRNC($rnc)
    {
        return $this->selectFirst([
            'where' => [
                ['rnc', '=', $rnc]
            ]
        ]);
    }
} 