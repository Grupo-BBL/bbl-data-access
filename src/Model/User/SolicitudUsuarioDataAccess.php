<?php

class CreateUserForWeb
{
	public $message = "";

	public function handleUserItemDelegateOptions(&$user, &$item, &$delegate = null, $options = null)
	{
		DataAccessManager::get("SolicitudUsuarioDataAccess")->createUserByUserItemDelegateOptions($user, $item, $this, $options);
		return $this;
	}

	public function didCreateUserFromSolicitud($maybeCreatedUser, $solicitud)
	{
		$this->message .= "Usuario creado existosamente.";
		$this->message .= "<br/>";


		$userEmail   = $solicitud["email"];
		$createdUser = DataAccessmanager::get("persona")->getOne("email", $userEmail);
		$userID      = DataAccessmanager::get("persona")->valueForKey("id", $createdUser);
		
		$url = ShowURLTo("persona", $userID);
		
		$this->message .= "<a href='$url'>Ir a usuario.</a>";

		DataAccessManager::get("EmailQueueManager")->addToQueue(
			$solicitud["email"],
			Glang::get("UserAccountRequest/Action/Approved/Email/Subject"),
			Glang::get("UserAccountRequest/Action/Approved/Email/Body")
		);
	}
	public function didError($e)
	{
		if (is_string($e))
		{
			$this->message = $e;
		}
		else if (is_array($e))
		{
			foreach ($e as $eMessage)
			{
				$this->message .= $eMessage;
			}
		}
	}
	public function exceptionCreatingUser($e)
	{
		error_log("Inside anonymous - exceptionCreatingUser");
		// $message = QueryExceptionManager
		// $this->message .= "Occurio un error grave.";
		$this->message .= $e->getMessage();
		error_log("Finished inside anonymous - exceptionCreatingUser");
	}
	public function render($get, $post, $server, $cookie, $session, $files, $env)
	{
		error_log("Inside anonymous render");
		return $this->message;
	}
}

class SolicitudUsuarioDataAccess extends DataAccess 
{

	public function isIllegalPromotionToUserByUserItem($user, $item)
	{
		$debug = false;

		if (!DataAccessManager::get("persona")->isInGroup($user, [ "DEVS", "SOFTWARE_ADMIN" ]))
		{
			return true;
		}

		$email         = $item["email"];
		$exisitingUser = DataAccessManager::get("persona")->getOne("email", $email);

		if ($debug)
		{
			error_log("Searching for existing user with email: $email, got: ".print_r($exisitingUser, true));
		}
		
		if ($exisitingUser)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function createUserByUserItemDelegateOptions(&$user, &$item, &$delegate = null, $options = null)
	{
		$debug = false;

		error_log("`createUserByUserItemDelegateOptions` - Item: ".print_r($item, true));
		error_log("Is cedula truthy?: ".isTruthy($item["cedula"]));

		$cedula = $item["cedula"];

		$toCreate = [
			"email"              => $item["email"],
			"nombres"            => $item["nombres"],
			"apellidos"          => $item["apellidos"],
			"created_by_user_id" => $user["id"],
			"fecha_creado"       => date('Y-m-d H:i:s'),
			"estado"			 => "activo",
		];

		if ($cedula == '')
		{
			error_log("Is empty string...yes");
		}
		else
		{
			$toCreate["cedula"] = $item["cedula"];
		}

		try
		{
			$outError = '';

			if ($debug)
			{
				error_log("`createUserByUserItemDelegateOptions` - Will insert user.");
			}

			$created = DataAccessManager::get("persona")->insertOrError($toCreate, $outError);

			
			if ($debug)
			{
				error_log("`createUserByUserItemDelegateOptions` - Did insert user.");
			}

			if ($outError != '')
			{
				if ($debug)
				{
					error_log("Will call delegate `didError`");
				}
				$delegate->didError($outError);
				return;
			} 
			else if ($delegate)
			{
				if ($debug)
				{
					error_log("Will call `didCreateUser`: ".$created);
				}
				$delegate->didCreateUserFromSolicitud($created, $toCreate);
				return;
			}
			else
			{
				error_log("WILL DO NOTHING");
			}
		}
		catch (Exception $e)
		{
			if ($delegate)
			{
				if ($debug)
				{
					error_log("Will call `exceptionCreatingUser`");
				}
				$delegate->exceptionCreatingUser($e);
			}
			else
			{
				throw $e;
			}
		}
		
	}

	public function register()
	{	
		$columnMappings = [
			new GTKColumnMapping($this, "id", [
				"isPrimaryKey" => true,
				"isAutoIncrement" => true,
				"type" => "int",
			]),
			new GTKColumnMapping($this, "empresa_o_transporte", [
				"type" => "varchar(255)",
			]),
			new GTKColumnMapping($this, "cedula", [ 
				"isUnique" 	   => true,
				"type"         => "varchar(11)",
			]),
			new GTKColumnMapping($this, "nombres", [
				"type" => "varchar(255)",
			]),			
            new GTKColumnMapping($this, "apellidos", [
				"type" => "varchar(255)",
			]),		
			new GTKColumnMapping($this, "email", [
				"type" => "varchar(255)",
			]),			
            // new GTKColumnMapping($this, "whatsapp"),		
			// new GTKColumnMapping($this, "fecha_solicitud"),
			// new GTKColumnMapping($this, "fecha_aprobado"),
			// new GTKColumnMapping($this, "fecha_cancelado"),
		];

		$this->dataMapping		    = new GTKDataSetMapping($this, $columnMappings);
		$this->defaultOrderByColumnKey = "fecha_creado";
		$this->defaultOrderByOrder  = "DESC";

		$generarUsuarioAction = new DataAccessAction($this, "generarUsuarioAction", "Generar Usuario");
		/*
		$generarUsuarioAction->_showOnEditForUserItemObject                 = $this;
		$generarUsuarioAction->_showOnEditForUserItemFunction               = "canGenerateUserByUserItem";
		$generarUsuarioAction->_showOnListsForUserItemObject                = $this;
		$generarUsuarioAction->_showOnListsForUserItemFunction              = "canGenerateUserByUserItem";
		*/
		$generarUsuarioAction->isInvalidForUserItemDelegateOptionsObject   = $this;
		$generarUsuarioAction->isInvalidForUserItemDelegateOptionsFunction = "isIllegalPromotionToUserByUserItem";
		$generarUsuarioAction->doObjectForUserItem						   = new CreateUserForWeb();
		$generarUsuarioAction->doFunctionForUserItem					   = "handleUserItemDelegateOptions";
		// $generarUsuarioAction->onSuccess = 
		// $generarUsuarioAction->onException = 
		
		$this->_actions = [
			"generarUsuarioAction" => $generarUsuarioAction,
		];
    }

	public function didCreateNewOnFormWith($postObject, $newItem, $user)
	{
		die(Glang::get("SolicitudUsuario/recibido"));
	}

	public function columnToCheckIfExists()
	{
		return "email";
	}

	/*
	public function userHasPermissionTo($routeAsString, $user)
	{
		if ($routeAsString == "create")
		{
			return false;
		}

		return parent::userHasPermissionTo($routeAsString, $user);
	}
	*/
}
