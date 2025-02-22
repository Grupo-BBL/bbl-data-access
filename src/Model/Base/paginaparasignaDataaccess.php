<?php

class paginaparasignaDataacces
{
    public $NombreDataAccessParaUno;
    public $NombreDataAccessParaMuchos;
    private $relations = [];

    public function addRelation($oneKey, $oneData, $manyKey, $manyData)
    {
        if (!isset($this->relations[$oneKey])) {
            $this->relations[$oneKey] = [
                'one' => $oneData,
                'many' => []
            ];
        }
        $this->relations[$oneKey]['many'][$manyKey] = $manyData;
    }

  
    public function getOne($oneKey)
    {
        return $this->relations[$oneKey]['one'] ?? null;
    }

 
    public function getMany($oneKey)
    {
        return $this->relations[$oneKey]['many'] ?? [];
    }

   
    public function getAllRelations()
    {
        return $this->relations;
    }
}