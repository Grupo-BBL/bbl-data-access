<?php
class PruebaDataAccess extends DataAccess
{
    protected $tableName = 'prueba';

    public function getFreshColumnMappings()
    {
        return [
            'id' => 'id',
            'nombre' => 'nombre',
            'descripcion' => 'descripcion',
            'created_at' => 'created_at'
        ];
    }

    public function getAllPruebas()
    {
        $query = new SelectQuery($this);
        return $query->executeAndReturnAll();
    }

    public function getPruebaById($id)
    {
        $query = new SelectQuery($this);
        $query->addClause(new WhereClause('id', '=', $id));
        return $query->executeAndReturnFirst();
    }

    public function createPrueba($data)
    {
        $query = new InsertQuery($this);
        $query->setValues($data);
        return $query->execute();
    }

    public function updatePrueba($id, $data)
    {
        $query = new UpdateQuery($this);
        $query->addClause(new WhereClause('id', '=', $id));
        $query->setValues($data);
        return $query->execute();
    }

    public function deletePrueba($id)
    {
        $query = new DeleteQuery($this);
        $query->addClause(new WhereClause('id', '=', $id));
        return $query->execute();
    }
}