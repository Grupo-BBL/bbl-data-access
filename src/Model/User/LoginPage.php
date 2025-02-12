<?php 

interface LoginDelegate
{
	public function userDoesNotExist();
	public function userDoesNotHavePassword($user);
	public function userIsNotActive($user);
	public function userAndPasswordDoNotMatch();
	public function tooManyLoginAttempts();
	public function successfulMatchFromUntrustedDevice();
	public function successfulMatch();
}

class LoginUser
{
	public $oDelegate;

	public function setDelegate($delegate)
	{
		$this->oDelegate = $delegate;
	}

	public function verifyPassword($password, $passwordHash)
	{
		return password_verify($password, $passwordHash);
	}

	public function loginOnApache()
	{
		$debug = false;


		if ($_SERVER["REQUEST_METHOD"] === "POST")
		{
			$userName = $_POST["user_id"];
			$password = $_POST["password"];

			if ($debug)
			{
				error_log("User ID: ".$userName);
				error_log("Password: ".$password);
				error_log("_POST_".print_r($_POST, true));
			}

			$this->loginUser($userName, $password);
		}
		else
		{
			// $this->loginUser($_GET["user_id"], $_GET["password"]);
		}
	}

	public function loginUser($userName, $password, $pDelegate = null)
	{
		$debug    = true;
		$delegate = null;

		if (!$pDelegate)
		{
			if (!$this->oDelegate)
			{
				throw new Exception("Needs delegate to process login");
			}

			$delegate = $this->oDelegate;
		}
		else
		{
			$delegate = $pDelegate;
		}

		if ($debug)
		{
			error_log("delegate is ".get_class($delegate));
		}

		if (!$userName || !$password)
		{
			$missingFields = [];

			if (!$userName)
			{
				$missingFields[] = "userName";  
			}

			if (!$password)
			{
				$missingFields[] = "password";
			}

			return $delegate->incompleteData($missingFields);
		}
		$user_data_access = DataAccessManager::get("persona");

		$keysToTry = [
			"cedula",
			"email",
		];

		$user = null;

		foreach ($keysToTry as $key)
		{
			if (!$user)
			{
				$user = $user_data_access->getOne($key, $userName);
				if($debug){
					error_log("el usuario ingresado fue: ".print_r($user,true));
				}
			}
			else
			{
				break;
			}
		}

		if (!$user)
		{
			return $delegate->userDoesNotExist();
		}

		if (!$user_data_access->isActive($user))
		{
			if($debug)
			{
				gtk_log("`LoginPage` - no esta activo");
			}
			return $delegate->userIsNotActive($user);
		}

		$passwordHash = $user_data_access->valueForKey("password_hash", $user);

		if ($debug)
		{
			error_log("Got password_hash: ".$passwordHash);
		}

		if (!isTruthy($passwordHash))
		{
			return $delegate->userDoesNotHavePassword($user);
		}

		$passwordIsCorrect = $this-> verifyPassword($password, $passwordHash);

		if ($debug)
		{
			error_log("Password is correct!!");
		}

		if (!$passwordIsCorrect)
		{
			$maxLoginAttempts = 9;
			$loginAttempts    = 0;

			$tooManyLoginAttempts = ($loginAttempts >= $maxLoginAttempts);

			if ($tooManyLoginAttempts)
			{
				return $delegate->tooManyLoginAttempts();
			}
			else
			{
				return $delegate->userAndPasswordDoNotMatch($user);
			}
			
		}

		return $delegate->successfulMatchForUser($user);
	}
}





class GTKDefaultLoginPageDelegate extends GTKHTMLPage
{
	// public $messages = [];

	/*
	public function __construct(Type $var = null) {
		$this->var = $var;
	} 
	*/ 

	public function __construct($options = [])
	{
		parent::__construct($options);
		$this->setAuthenticate(false);
	}

	public function renderBody  ()
	{

		
		ob_start(); ?>
        <h1 class="ml-12 text-2xl font-bold my-4 center">Login</h1>

        <?php if (count($this->messages)): ?>
            <div class="space-y-2">
                <?php foreach ($this->messages as $message): ?>
                    <p class="text-red-600"><?php echo htmlspecialchars($message); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
                
        <form action="/auth/login.php" method="post">
			<table>
            <tr>
            <td><label for="user_id"><?php echo Glang::get("login_form/id"); ?></label>
            <td><input type="text" name="user_id" id="user_id">
			</tr>
                
            <tr>
            <td><label for="password"><?php echo Glang::get("login_form/password"); ?></label>
        	<td><input type="password" name="password" id="password">
			</tr>
			</table>
                
            <input type="submit">
        </form>


		<?php
		global $_GLOBALS;
		if (isset($_GLOBALS["CUSTOMER_SUPPORT_EMAIL"])):


			$mailto = "mailto:" . $_GLOBALS["CUSTOMER_SUPPORT_EMAIL"];
			$subject = Glang::get("request_user_email/subject");
			$body    = Glang::get("request_user_email/body");
			// URL-encode subject and body
			$subject = urlencode($subject);
			$body = urlencode($body);
			
			$full_mailto_link = $mailto . "?subject=" . $subject . "&body=" . $body;
		?>
			
			<div class="px-8 py-8">
			<a href="<?php echo $full_mailto_link; ?>">Request User</a>
			<br/>
			<br/>
		<?php endif; ?>

		<br/>
		<a href="/auth/requestPasswordToken.php">Request or reset Password</a>
		</div>

		<?php 
		return ob_get_clean();
	}

	public function incompleteData($missingFields)
	{
		$this->messages[] = Glang::get("login_form/incomplete_data");
	}
	public function userDoesNotExist()
	{
		$this->messages[] = Glang::get("login_form/user_not_found");
	}
	public function userIsNotActive($user)
	{
		$this->messages[] = Glang::get("login_form/user_not_active");
	}
	public function userDoesNotHavePassword($user)
	{
		$debug = false;;

		if ($debug)
		{
			error_log("Processing user...: ".print_r($user, true));
		}

		$email = DataAccessManager::get("persona")->valueForKey("email", $user);

		if (!$email)
        {
			$message = Glang::get("login_form/no_email_for_user");

            throw new Exception($message);
            return new FailureResult(0, $message);
        }

		$passwordSetLink = DataAccessManager::get("RequestPasswordResetController")->passwordSetLinkForUser($user, "USER_LOGIN");

		if ($debug)
		{
			echo $passwordSetLink;
		}
		
		DataAccessManager::get("EmailQueueManager")->addToQueue(
            $email,
			Glang::get("login_form/please_create_password_email/subject"),
            Glang::get("login_form/please_create_password_email/body"));

	    echo Glang::get("login_form/please_create_password_email/message_sent_page");
		die();
	}
	public function userAndPasswordDoNotMatch()
	{
		$this->messages[] = Glang::get("login_form/user_password_does_not_match");
	}
	public function tooManyLoginAttempts()
	{
		return;
	}
	public function successfulMatchFromUntrustedDevice()
	{
		return;
	}
	public function successfulMatchForUser($user)
	{
		$debug = false;

		if ($debug)
		{
			error_log("Did get successful match for user: ".print_r($user, true));
		}
		
		// The SameSite attribute can have three possible values:
		// 
		// 	"Strict"  : Not sent in cross-site requests. It means the cookie is only sent if the request originates from the same site as the domain that set the cookie. This provides a high level of protection against CSRF attacks but may impact functionality in scenarios where legitimate cross-site requests are needed.
		// 	"Lax"	  : Not sent in cross-site requests initiated by external websites through HTTP methods other than "GET". For example, cookies will be sent in cross-site requests that are triggered by clicking on links or loading images from external sites. This provides some protection against CSRF attacks while maintaining compatibility with common scenarios.
		// 	"None"	  : Sent in all cross-site requests. This value is typically used in conjunction with the "Secure" attribute, indicating that the cookie should only be sent over HTTPS connections. This allows the cookie to be sent in cross-site requests that are essential for certain functionalities, such as embedded content or OAuth flows. However, it should be used with caution and proper security measures to prevent abuse.
		$sessionGuid = DataAccessManager::get('session')->newSessionForUser($user);


		// expires or options set to false - 3rd param
		// http only set to false 		   - 5th param
		setcookie('AuthCookie', $sessionGuid, [
			'expires'   => time() + 86400 * 7,
			'path' 	    => '/', 
			'secure'    => isWindows() ? false : true,
			'httponly'  => true,
			'samesite'  => 'Strict'
		]);
		
		// domain => null,
		// header("Set-Cookie: ".self::SESSION_NAME."=".$session_id."; expires=".date('D, Y-M-d H:i:s', $expires)." GMT; path=/; HttpOnly; secure=true; SameSite=Strict");
		redirectToPath('/', "Bienvenidos!");
		die();
	}
}
  
