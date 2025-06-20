<?php


class SessionDataAccess extends DataAccess 
{
	public $_currentUser		       = null;
	public $didCheckForCurrentUser = false;

	public function currentUserHasPermission($permission)
	{
		$currentUser = $this->getCurrentUser();

		if ($currentUser)
		{
			return DataAccessManager::get("persona")->hasPermission($permission, $currentUser);
		}

		return false;
	}
	public function currentUserHasOneOfPermissions($permissions)
	{
		$currentUser = $this->getCurrentUser();
		
		if ($currentUser)
		{
			return DataAccessManager::get("persona")->hasOneOfPermissions(
				$permissions, 
				$currentUser
			);
		}

		return false;
	}

	public function currentUserHasRoles($roles)
	{
		return $this->currentUserIsInGroups($roles);
	}

	public function currentUserIsInGroups($groups)
	{
		$currentUser = $this->getCurrentUser();

		if ($currentUser)
		{
			return DataAccessManager::get("persona")->isInGroup(
				$currentUser,
				$groups);
		}

		return false;
	}
	public function clearCurrentSession()
	{
		return $this->clearCurrentSessioAndRedirectTo("/auth/login.php");
	}
	public function clearCurrentSessioAndRedirectTo($sendWithRedirect = "/auth/login.php")
	{
		$currentSession = $this->getCurrentApacheSession();

		if ($currentSession)
		{
			$this->cancelSession($currentSession);
		}

		GTKCookie::clearAuthCookie();
		
		header("Cache-Control: no-cache, must-revalidate"); // HTTP 1.1
		header("Pragma: no-cache");                         // HTTP 1.0
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");   // Date in the past


		// Notify successful logout and redirect to the home page after 3 seconds
		$message     = 'Has cerrado sesión correctamente. En 3 segundos serás redirigido a la página de inicio.';
		$redirectURL = '/index.php'; // Change this to the URL of your home page
		
		echo "<!DOCTYPE html>
		<html lang='es'>
		<head>
			<meta charset='UTF-8'>
			<meta http-equiv='X-UA-Compatible' content='IE=edge'>
			<meta name='viewport' content='width=device-width, initial-scale=1.0'>
			<title>Cierre de sesión</title>
			<script>
				setTimeout(function() {
					window.location.href = '$redirectURL';
				}, 3000);
			</script>
		</head>
		<body>
			<p>$message</p>
			<p>Si no eres redirigido automáticamente, haz clic <a href='$redirectURL'>aquí</a>.</p>
		</body>
		</html>";
		die(); // Terminate the script execution
	}

	public function isDeveloper()
	{
		if ($this->currentUserHasRoles([
			"DEV",
			"DEVELOPER",
		]))
		{
			return true;
		}

		return false;
	}

	public function getCurrentUser()
	{
		$debug = false;

		static $session     = null;
		static $currentUser = null;

		if ($debug)
		{
			error_log("`getCurrentUser` - Checking if current user is set: ".print_r($currentUser,true));
		}

		
		if (!$this->didCheckForCurrentUser)
		{
			$this->didCheckForCurrentUser = true;

			$session = $this->getCurrentApacheSession();

			if ($debug)
			{
				error_log("`getCurrentUser` - Got session: ".print_r($session, true));	
			}

			if ($session)
			{	
				
				$currentUser = $this->getUserFromSession($session);
			
				if ($debug)
				{
					error_log("`getCurrentUser` - Got current user: ".print_r($currentUser, true));
				}
			}
			else
			{
				if ($debug)
				{
					error_log("`getCurrentUser` - No session found.");
				}
			}

			
		}

		if ($debug)
		{
			error_log("`getCurrentUser` - Returning current user: ".print_r($currentUser, true));
		}

		return $currentUser;
	}


	public function getCurrentUserIfAny()
	{
		return $this->getCurrentUser();
	}


	public function getCurrentApacheUserOrSendToLoginWithRedirect($sendWithRedirect = null)
	{
		$debug = false;

		static $didSearchForSession = false;
		static $session 			= null;

		if (!$didSearchForSession)
		{
			$session = $this->getCurrentApacheSession();

			if ($debug)
			{
				error_log("Did search for session - got: ".print_r($session, true));
			}
		}

		if ($session)
		{
			if ($this->verifySession($session))
			{
				$user = $this->getUserFromSession($session);

				if ($debug)
				{
					error_log("`getUserFromSession` - got user: ".print_r($user, true));

				}

				return $user;
			}
		}

		if ($sendWithRedirect)
		{
			redirectToURL("/auth/login.php", null, [
				"redirectTo" => $sendWithRedirect,
			]);
		}
		
		return null;
	}

	public function getUserFromSession($session)
	{
		$user_id = $this->valueForKey("user_id", $session);
			
		return DataAccessManager::get("persona")->getOne("id", $user_id);
	}
 
	public function getUserWithApiKey($apiKey)
	{	
		global $_GLOBALS;

		$email = $_GLOBALS["API_KEY_ARRAY"][$apiKey];

		static $cache = [];
		if (array_key_exists($email, $cache))
		{
			return $cache[$email];
		}

		$user = DataAccessManager::get('persona')->getOne("email", $email);
		
		$cache[$email] = $user;

		return $user;
	}

	public function processAPIKey($apiKey)
	{
		global $_GLOBALS;
		$accessKeyArray = $_GLOBALS["API_KEY_ARRAY"];
	
		if (!$accessKeyArray) {
			error_log("API Key array not found.");
			return null;
		}

		if ($debug)
		{
			error_log("API Key: ".$apiKey);
		}
		
		if (array_key_exists($apiKey,$accessKeyArray))
		{
			$defaultSessionLength = 60 * 60 * 24 * 30;
			$email = $accessKeyArray[$apiKey];
			$user =  DataAccessManager::get('persona')->getOne("email", $email);

			if ($debug)
			{
				error_log("API Key found: ".$apiKey);
				error_log("User found: ".print_r($user, true));
			}

			$session = [];
			$session['user']        = $user;
			$session['user_id']     = DAM::get("persona")->identifierForItem($user);
			$session['apikey']      = true;
			$session['valid_until'] = time() + $defaultSessionLength;
			return $session;
		}
		else
		{
			error_log("API Key not found: ".$apiKey);
			return null;
		}
	}

	public function processAuthCookie($authToken, $options)
	{
		$debug = false;

		if ($debug)
		{
			error_log("`getCurrentApacheSession` - Got auth token: ".$authToken);
		}

		$session = DataAccessManager::get("session")->getSessionById($authToken);

		if ($debug)
		{
			error_log("`getCurrentApacheSession` - Got session: ".$session);
		}

		$isValid = $this->verifySession($session);

		if ($debug)
		{
			error_log("Got `isValid`: ".$isValid);
		}

		if (!$isValid)
		{
			if (isTruthy($options["redirectIfInvalid"]))
			{
				redirectToURL("/auth/login.php", null, [
					"redirectTo" => $options["redirectTo"] ?? null,
				]);
				return null;
			}

			if (isTruthy($options["requireValid"]))
			{
				return null;
			}
		}

		if ($session)
		{
			$session["user"] = $this->getUserFromSession($session);
		}
		
		return $session;
	}


	public function getCurrentApacheSession($options = [
		"requireValid"	    => true,
		"redirectIfInvalid" => false,
		"redirectTo"        => null,
	])
	{
		$debug = false;

		global $_SERVER;

		if ($debug)
		{
			$headers = getallheaders();
			error_log("Headers: ".print_r($headers, true));
			// error_log("Server: ".print_r($_SERVER, true));
		}

		$httpTokenKey = "STONEWOOD_AUTH_TOKEN";

		$apiKey = null;

		global $_COOKIE;
		
		if (isset($_SERVER[$httpTokenKey])) 
		{
    		$apiKey = $_SERVER[$httpTokenKey];
		}
		else if (isset($_GET[$httpTokenKey]))
		{
    		$apiKey = $_GET[$httpTokenKey];
		}

		if ($apiKey)
		{
			return $this->processAPIKey($apiKey);
		}
		elseif (isset($_COOKIE['AuthCookie']))
		{
			$authToken = $_COOKIE['AuthCookie'];

			return $this->processAuthCookie($authToken, $options);
		}

		if ($debug)
		{
			error_log("`getCurrentApacheSession` - No auth token or http header for: ".$httpTokenKey);
		}

		return null;

	}


	public function register()
	{	
		$columnMappings = [
			new GTKColumnMapping($this, "id", [
				"isPrimaryKey"    => true, 
				"isAutoIncrement" => true, 
				"hideOnForms"     => true,
				"type"            => "int",
			]),
			new GTKColumnMapping($this, "session_guid", [
				"type" => "varchar(255)",
			]),
			new GTKColumnMapping($this, "user_id", [
				"formLabel" => "User ID",
				"type"     => "int",
			]),
			new GTKColumnMapping($this, "created_at", [
				"formLabel" => "Created At",
				"type"     => "datetime",
			]),
			new GTKColumnMapping($this, "valid_until", [
				"formLabel" => "Valid Until",
				"type"     => "datetime",
			]),
			new GTKColumnMapping($this, "canceled", [
				"formLabel" => "Canceled",
				"type"     => "tinyint(1)",
			]),
		];

		$this->dataMapping = new GTKDataSetMapping($this, $columnMappings);

		/*
		$this->getDB()->query("CREATE TABLE IF NOT EXISTS {$this->tableName()} 
		  (id, 
		   user_id,
		   created_at,
		   valid_until,
		   canceled,
		   UNIQUE (id))");
		*/

		$this->defaultOrderByColumnKey = "created_at";
		$this->defaultOrderByOrder  = "DESC";


		
	}
	
	function guidv4($data = null) {
		// Generate 16 bytes (128 bits) of random data or use the data passed into the function.
		$data = $data ?? random_bytes(16);
		assert(strlen($data) == 16);
	
		// Set version to 0100
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		// Set bits 6-7 to 10
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
	
		// Output the 36 character UUID.
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
	
	public function newSessionForUser($user)
	{
		$query = "INSERT INTO {$this->tableName()} 
			(session_guid,  user_id, created_at, valid_until,  canceled)
			VALUES
			(:session_guid,  :user_id,  :created_at, :valid_until,  :canceled)";
			
		$statement = $this->getDB()->prepare($query);
		
		$session_value = uniqid();

		$defaultSessionLength = 60 * 60 * 24 * 30; // 30 days

		$statement->bindValue(':session_guid', $session_value);
		$statement->bindValue(':user_id',     			DataAccessManager::get("persona")->valueForIdentifier($user));
		$statement->bindValue(':created_at', 		 	date(DATE_ATOM));
		$statement->bindValue(':valid_until', 			time() + $defaultSessionLength);
		$statement->bindValue(':canceled', 	  			0);
		
		// Execute the INSERT statement
		$result = $statement->execute();
		
		if ($result) 
		{
			GTKCookie::setAuthCookie($session_value);

			return $session_value;
		} 
		else 
		{
			// INSERT failed
			// Handle the error
			echo 'INSERT FAILED';
			return 0;
		}
	}

	
	
	public function getSessionById($id) {
		$debug = false;
		
		$query = "SELECT * FROM {$this->tableName()} WHERE session_guid = :session_guid";
		
		if ($debug)
		{
			error_log("Running query...: ".$query);
		}
		
		$statement = $this->getDB()->prepare($query);
		
		
		$statement->bindValue(':session_guid', $id);
				
		$statement->execute();
		
        // Fetch the result
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

		if ($debug)
		{
			$toPrint = serialize($result);
			echo "Result: {$toPrint}<br/>";
		}

		if (count($result) > 1)
		{
			error_log("More than one session found for id: {$id}");
			die("Error. Favor avisar administador.");
		}
		else if (count($result) === 0)
		{
			return null;
		}


		$session = $result[0];

		$session["isValid"] = $this->verifySession($session);

		return $session;
	}

	public function isValid($session)
	{
		return $this->verifySession($session);
	}
	
	public function verifySession($session)
	{
		$debug = false;

		if (!$session)
		{
			return false;
		}

		if ($debug) { error_log("Verifying session: ".serialize($session)); }

		$validUntil = $session["valid_until"];

		if (!$validUntil)
		{
			return false;
		}

		if (time() > $validUntil)
		{
			if ($debug) 
			{ 
				error_log("Time: ".time()." > valid_until: ".$session["valid_until"]); 
			}
			return false;
		}
		
		if (isTruthy($session['canceled']))
		{
			return false;
		}

		return true;
	}

	public function cancelSession($session)
	{
		$sql = "UPDATE {$this->tableName()} SET canceled = 1 WHERE session_guid = :session_guid";

		$statement = $this->getDB()->prepare($sql);

		$statement->bindValue(':session_guid', $session["session_guid"]);

		$result = $statement->execute();

		if ($result) 
		{
			return true;
		} 
		else 
		{
			// INSERT failed
			// Handle the error
			// echo 'INSERT FAILED';
			return false;
		}
	}

	public static function routeToPage(
		$requestPath, 
		$get, 
		$post, 
		$server, 
		$cookie, 
		$session, 
		$files, 
		$env
	){
		$user = DataAccessManager::get("session")->getCurrentUser();

		// die(print_r($user, true));

		$isLoginPath = in_array($requestPath, [
			"/auth/login.php", 
			"/auth/login",
			"/login",
			"/login.php",
		]);

		$isLogoutPath = in_array($requestPath, [
			"/auth/logout.php",
			"/auth/logout",
			"/logout",
			"/logout.php",
		]);

		if ($isLoginPath)
		{
			// die("Is Login path");
			if ($user)
			{
				//die("User is logged in");
				header("Location: /");
				exit();
			}
			else
			{
				global $_GTK_SUPER_GLOBALS;
				$loginPage = new GTKDefaultLoginPageDelegate();
				echo $loginPage->render(...$_GTK_SUPER_GLOBALS);
				return;
			}
		}


		if ($isLogoutPath)
		{
			if ($user)
			{
				DataAccessManager::get("session")->clearCurrentSession();
				die("Logged out");
			}
			else
			{
				header("Location: /auth/login.php");
				exit();
			}
		}

		$cleanPath = substr($requestPath, 1);

		$dataAccessManager = DataAccessManager::getSingleton();

		$toRender = $dataAccessManager->toRenderForPath($cleanPath, DataAccessManager::get("session")->getCurrentUser());

		if ($toRender)
		{
		  renderPage($toRender);
		}
		else
		{
		  echo "<h1>404 - Not Found - ".$cleanPath."</h1>";
		}
	}
}
