<?php

class SetPasswordTokenDataAccess extends DataAccess 
{
	public function register()
	{	
		$debug = 0;
	
		if ($debug)
		{
			error_log('User Data Access Register (getDB):');
		}

		$columnMappings = [
            new GTKColumnMapping($this, "id", [
                "isPrimaryKey" => true,
                "isAutoIncrement" => true,
			]),
			new GTKColumnMapping($this, "user_id"),
			new GTKColumnMapping($this, "origin"),
            new GTKColumnMapping($this, "token", [
                "isUnique" => true,
            ]),
			new GTKColumnMapping($this, "fecha_creado"),
			new GTKColumnMapping($this, "lifetime"),
            new GTKColumnMapping($this, "invalidated_at"),
        ];

		$this->dataMapping		    = new GTKDataSetMapping($this, $columnMappings);
		$this->defaultOrderByColumn = "fecha_creado";
		$this->defaultOrderByOrder  = "DESC";  
	}

    public function hasUserRequestedALinkInTheLast5Minutes($user)
    {
        $fiveMinutesAgo = new DateTime('-5 minutes');

        $query = new SelectQuery($this);

        $query->addClause(new WhereClause(
            "user_id", '=', DataAccessManager::get("persona")->valueForIdentifier($user)
        ));

        $query->addClause(new WhereClause(
            "fecha_creado", '>=', $fiveMinutesAgo->format('Y-m-d H:i:s')
        ));

        $results = $query->executeAndReturnAll();
        
        return (count($results) > 0);
    }


    public function getByToken($token)
    {
        $debug = false;

        try
        {
            $result = $this->where('token', $token);

            if (empty($result)) 
            {
                if ($debug) 
                {
                    error_log("Token not found: $token");
                }
                return null;
            }

            $record = $result[0];

            return $record;
        } 
        catch (Exception $e) 
        {
            if ($debug) 
            {
                error_log("Exception: " . $e->getMessage());
            }
            // In case of an exception, consider the token invalid
            return null;
        }
    }
    public function invalidateToken($token)
    {
        $debug = false;

        $toUpdate = [];

        $toUpdate["token"]    = $this->valueForKey("token", $token);
        $toUpdate["lifetime"] = 0;
        $toUpdate["invalidated_at"] = date('Y-m-d H:i:s');

        try
        {
            if ($debug)
            {
                error_log("Will update SetPasswordToken: ".print_r($toUpdate, true));
            }
            // $this->update($toUpdate);

            $this->updateWhereKey($toUpdate, "token");
            
            if ($debug)
            {
                error_log("Did update token.");
            }
        }
        catch (Exception $e)
        {
            error_log("`invalidateToken` - Exception: ".$e->getMessage());
            return null;
        }
    }

    public function createTokenForUserFromOrigin($user, $origin, $lifetime = 3600)
    {
        $debug = 0;

        $toInsert = [];

        $toInsert["user_id"]      = DataAccessManager::get('persona')->valueForIdentifier($user);
        $toInsert["origin"]       = $origin;
        $toInsert["token"]        = bin2hex(random_bytes(32));
        $toInsert["fecha_creado"] = date("Y-m-d H:i:s");
        $toInsert["lifetime"]     = $lifetime;

        try
        {
            $this->insert($toInsert);

            return $toInsert["token"];
        }
        catch (Exception $e)
        {
            error_log("Exception: ".$e->getMessage());
            return null;
        }
    }

    public function resetPasswordForToken($token, $newPassword)
    {
        $debug = false;
        
        $fullToken = DataAccessManager::get('SetPasswordTokenDataAccess')->getByToken($token);
        $personaID = DataAccessManager::get('SetPasswordTokenDataAccess')->valueForKey("user_id", $fullToken);

        $persona   = DataAccessManager::get('persona')->getByIdentifier($personaID);

        if (!$persona)
        {
            error_log("FUNNY BUSINESS.");
        }

        if ($debug)
        {

        }

        try
        {
            $this->beginTransaction();

            DataAccessManager::get('persona')->updatePasswordHashForPersona($persona, $newPassword);
            $this->invalidateToken($fullToken);

            if ($debug)
            {
                error_log("Token invalidated: $token");
                error_log("Will commit transaction!");
            }
    
            $this->commit();

            if ($debug)
            {
                error_log("Did commit transaction!");
            }

            return true;
        }
        catch (Exception $e)
        {
            $this->rollback();
            error_log("Exception: ".$e->getMessage());
            return null;
        }

    }

    public function isValidToken($token)
    {
        $debug = false;

        try
        {
            $result = $this->where('token', $token);

            if (empty($result)) 
            {
                if ($debug) 
                {
                    error_log("Token not found: $token");
                }
                return false;
            }

            $record = $result[0];

            if (isTruthy($record["invalidated_at"]))
            {
                return false;
            }

            $createdTime = strtotime($record['fecha_creado']);
            $currentTime = time();
            $lifetime    = $record['lifetime'];
            $sumOfTime   = $createdTime + $lifetime;

            if ($debug)
            {
                error_log("Token: $token");
                error_log("Lifetime: $lifetime");
                error_log("Created time   : $createdTime");
                error_log("Current time   : $currentTime");
                error_log("Sum of time    : $sumOfTime");
                error_log("Time remaining : ".($sumOfTime - $currentTime));
            }

            if ($sumOfTime < $currentTime) 
            {
                if ($debug) 
                {
                    error_log("Token expired: $token");
                }
                return false;
            }

            return true;

        } 
        catch (Exception $e) 
        {
            if ($debug) 
            {
                error_log("Exception: " . $e->getMessage());
            }
            // In case of an exception, consider the token invalid
            return false;
        }
    }
}
