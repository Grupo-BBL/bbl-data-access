<?php

class CustomRoleDataAccess extends RoleDataAccess
{
    protected $relationTable;
    protected $relationColumns;

    public function __construct($pdo, $tableName, $relationTable, $relationColumns)
    {
        parent::__construct($pdo, $tableName);
        $this->relationTable = $relationTable;
        $this->relationColumns = $relationColumns;
    }

    public function assignToOne($uno, $mucho)
    {
        $entityOne = $this->getOne("id", $uno);
        $entityMucho = $this->getOne("id", $mucho);

        if ($entityOne && $entityMucho) {
            $this->addRelation($entityOne, $entityMucho);
            return true;
        }
        return false;
    }

    public function removeFromOne($uno, $mucho)
    {
        $entityOne = $this->getOne("id", $uno);
        $entityMucho = $this->getOne("id", $mucho);

        if ($entityOne && $entityMucho) {
            $this->removeRelation($entityOne, $entityMucho);
            return true;
        }
        return false;
    }

    protected function addRelation($entityOne, $entityMucho)
    {
        $toInsert = [
            $this->relationColumns['one'] => $entityOne["id"],
            $this->relationColumns['mucho'] => $entityMucho["id"],
            "date_created" => date("Y-m-d H:i:s"),
        ];

        $this->insert($this->relationTable, $toInsert);
    }

    protected function removeRelation($entityOne, $entityMucho)
    {
        $this->delete($this->relationTable, [
            $this->relationColumns['one'] => $entityOne["id"],
            $this->relationColumns['mucho'] => $entityMucho["id"],
        ]);
    }
}