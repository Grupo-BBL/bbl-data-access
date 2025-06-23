<?php

class PermissionPersonRelationshipDataAccess extends DataAccess
{
    public function register()
    {
        $columnMappings = [
			new GTKColumnMapping($this, "permission_person_relationship_id", [
                "formLabel"    => true,
                "isPrimaryKey" => true, 
                "hideOnForms"  => true, 
            ]), 
            new GTKColumnMapping($this, "permission_id"),
            new GTKColumnMapping($this, "persona_id"),
			new GTKColumnMapping($this, "comments"),
			new GTKColumnMapping($this, "is_active"),
			new GTKColumnMapping($this, "date_created"),
			new GTKColumnMapping($this, "date_modified"),
		];

		$this->dataMapping 			= new GTKDataSetMapping($this, $columnMappings);
		$this->defaultOrderByColumnKey = "date_modified";
		$this->defaultOrderByOrder  = "DESC";
    }


}
