<?php

class OneToManyRelation
{
    private $relationTable;
    private $unoColumn;
    private $muchoColumn;
    private $dataAccessManager;

    public function __construct($relationTable, $unoColumn, $muchoColumn)
    {
        $this->relationTable = $relationTable;
        $this->unoColumn = $unoColumn;
        $this->muchoColumn = $muchoColumn;
        $this->dataAccessManager = DataAccessManager::get($relationTable);

        // Log the initialization
        error_log("OneToManyRelation initialized with: relationTable = $relationTable, unoColumn = $unoColumn, muchoColumn = $muchoColumn");
    }

    public function hasRelation($unoId, $muchoId)
    {
        try {
            // Log the input parameters
            error_log("hasRelation: unoId = $unoId, muchoId = $muchoId");

            $count = $this->dataAccessManager->count([
                'where' => [
                    ['type' => 'column', 'phpKey' => $this->unoColumn, 'value' => $unoId],
                    ['type' => 'column', 'phpKey' => $this->muchoColumn, 'value' => $muchoId]
                ]
            ]);

            // Log the count result
            error_log("hasRelation: count = $count");

            return $count > 0;
        } catch (Exception $e) {
            error_log("Error en hasRelation: " . $e->getMessage());
            return false;
        }
    }

    public function assignRelation($unoId, $muchoId)
    {
        try {
            $relationship = [
                $this->unoColumn => $unoId,
                $this->muchoColumn => $muchoId,
                'is_active' => 1,
                'date_created' => date("Y-m-d H:i:s"),
                'date_modified' => date("Y-m-d H:i:s"),
            ];

            // Log the relationship data
            error_log("assignRelation: relationship = " . print_r($relationship, true));

            return $this->dataAccessManager->insert($relationship);
        } catch (Exception $e) {
            error_log("Error en assignRelation: " . $e->getMessage());
            return false;
        }
    }

    public function removeRelation($unoId, $muchoId)
    {
        try {
            $conditions = [
                $this->unoColumn => $unoId,
                $this->muchoColumn => $muchoId
            ];

            // Log the conditions
            error_log("removeRelation: conditions = " . print_r($conditions, true));

            $this->dataAccessManager->deleterelation($conditions);
            return true;
        } catch (Exception $e) {
            error_log("Error en removeRelation: " . $e->getMessage());
            return false;
        }
    }
}