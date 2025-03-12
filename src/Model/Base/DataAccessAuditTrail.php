<?php

class DataAccessAuditTrail extends DataAccess 
{
    // protected $tableName = 'data_access_audit_trail';

    public function register()
    {
        $columnMappings = [
            'id',
            'data_access_name',
            'record_id',
            'action_type',
            'user_id',
            'user_email',
            'changes',
            'created_at',
        ];

        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }

    /*
    protected function createGTKColumnMapping() 
    {
        return [
            'id' => [
                'type' => 'INTEGER',
                'primary' => true,
                'autoincrement' => true
            ],
            'data_access_name' => [
                'type' => 'VARCHAR',
                'length' => 255,
                'null' => false,
                'label' => 'Data Access',
                'description' => 'Name of the data access class'
            ],
            'record_id' => [
                'type' => 'VARCHAR',
                'length' => 255,
                'null' => false,
                'label' => 'Record ID',
                'description' => 'ID of the affected record'
            ],
            'action_type' => [
                'type' => 'VARCHAR',
                'length' => 50,
                'null' => false,
                'label' => 'Action',
                'description' => 'Type of action performed (INSERT/UPDATE/DELETE)'
            ],
            'user_id' => [
                'type' => 'INTEGER',
                'null' => true,
                'label' => 'User ID',
                'description' => 'ID of the user who made the change'
            ],
            'user_email' => [
                'type' => 'VARCHAR',
                'length' => 255,
                'null' => true,
                'label' => 'User Email',
                'description' => 'Email of the user who made the change'
            ],
            'changes' => [
                'type' => 'TEXT',
                'null' => true,
                'label' => 'Changes',
                'description' => 'JSON encoded changes made to the record'
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'label' => 'Created At',
                'description' => 'When the change was recorded'
            ]
        ];
    }
    */
}