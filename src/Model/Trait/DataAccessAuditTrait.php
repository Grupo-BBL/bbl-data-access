<?php

trait DataAccessAuditTrait 
{
    protected function recordAudit(string $action, $recordId, ?array $changes = null) 
    {        
        if (true)
        {
            error_log("Ignoring recordAudit");
            return;
        }

        $dataAccessName = get_class($this);

        if (method_exists($this, 'getDataAccessorName')) 
        {
            $dataAccessName = $this->getDataAccessorName();
        }

        // I want to check if this is DataAccessAuditTrail
        if ($dataAccessName == 'DataAccessAuditTrail')
        {
            return;
        }   


        $auditTrail = DAM::get('data_access_audit_trail');
        
        // Get current user if available
        $user      = DAM::get("session")->getCurrentUser();
        $userID    = null;
        $userEmail = null;

        
        if ($user)
        {
            $userID    = DAM::get("persona")->identifierForItem($user);
            $userEmail = DAM::get("persona")->valueForKey("email", $user);
        }
        $toInsert = [
            'data_access_name' => $dataAccessName,
            'record_id'        => $recordId,
            'action_type'      => $action,
            'user_id'          => $userID,
            'user_email'       => $userEmail,
            'changes'          => json_encode($changes),
            'created_at'       => date('Y-m-d H:i:s')
        ];

        $auditTrail->insert($toInsert);
    }

    /**
     * Calculate changes between old and new data
     */
    protected function calculateChanges(?array $oldData, array $newData): array 
    {
        if (!$oldData) {
            return $newData; // For inserts, all fields are new
        }

        $changes = [];

        foreach ($newData as $key => $newValue) {
            $oldValue = $oldData[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        $this->removeIdentifierKeyFromItem($changes);
        
        return $changes;
    }
}
