<?php

use function Deployer\desc;

class GTKHTMLFragment
{
	public function render()
	{
		return "";
	}
}

class GTKMenuItemPair 
{
    public $menuText;
    public $boxId;
    public $boxContent;
    public $accessRequirements;

    function sanitizeForHtmlId($input) 
    {
        // Ensure the ID starts with a letter or underscore
        if (!preg_match('/^[a-zA-Z_]/', $input)) {
            $input = '_' . $input;
        }
        
        // Replace invalid characters with underscores
        $output = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $input);
        
        return $output;
    }
    

    public function __construct($menuText, $boxContent, $accessRequirements = null) {
        $this->menuText           = $menuText;
        $this->boxId              = 'nav_menu_box_'.$this->sanitizeForHtmlId($menuText);
        $this->boxContent         = $boxContent;
        $this->accessRequirements = $accessRequirements;
    }

    public function prepareHeaderItemsForUser($user, &$menuString, &$hiddenBoxString) {
        if ($this->checkAccess($user)) 
        {
            $menuString      .= "<li><a onclick=\"showContent('{$this->boxId}')\">{$this->menuText}</a></li>\n";
            $hiddenBoxString .= "<div id=\"{$this->boxId}\" class=\"box\">\n{$this->boxContent}\n</div>\n";
        }
    }

    public function checkAccess($user) 
    {
        if (!$this->accessRequirements) 
        {
            return true;
        }

        $hasRequiredRole = empty($this->accessRequirements['roles']) || 
                           DataAccessManager::get("session")->currentUserIsInGroups($this->accessRequirements['roles']);
        
        $hasRequiredPermission = empty($this->accessRequirements['permissions']) || 
                                 array_reduce($this->accessRequirements['permissions'], function($carry, $permission) {
                                     return $carry || DataAccessManager::get("session")->currentUserHasPermission($permission);
                                 }, false);
        
        return $hasRequiredRole && $hasRequiredPermission;
    }
}

class GTKHTMLPage
{
	public $get; 
	public $post; 
	public $server;
	public $cookie;
	public $phpSession;
	public $files;
	public $env;

	public $allowsCache = false;
	
	public $didSearchForCurrentUser;
	public $didSearchForCurrentSession;

	//----------------------------------------------------------
	public $messages        = [];
	public $user;
	public $session;

	public $header;
	public $footer;

	public bool    $authenticationRequired = false; // ovveride in construct
	public ?string $permissionRequired 	   = null;  // override in construct

	/**
	 * Array to store stylesheets
	 */
	protected $stylesheets = [];
	
	/**
	 * Array to store scripts
	 */
	protected $scripts = [];

	public function __construct($options = [])
	{
		// $pageOptions = OAM::get(get_class($this));
	}

	public function setAuthenticate($toSet)
	{
		$this->authenticate = $toSet;
	}

	public function setAuthorize($toSet)
	{
		$this->authorize = $toSet;
	}

	public function currentPath()
	{
	    if (isset($_SERVER["PATH_INFO"])) 
		{
			return $_SERVER["PATH_INFO"];
		} 
		elseif (isset($_SERVER["REQUEST_URI"])) 
		{
			return parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
		}
		return null; // Or throw an exception if preferred
	}

	public function currentURL()
	{
		$protocol = $this->server["HTTPS"] ? "https" : "http";
		$host = $this->server["HTTP_HOST"];
		$path = $this->currentPath();
		return $protocol."://".$host.$path;
	}

	public function getClientIP() 
	{
		$ipAddress = null;
		
		if (!empty($this->server['HTTP_CLIENT_IP'])) 
		{
			$ipAddress = $this->server['HTTP_CLIENT_IP'];
		} 
		elseif (!empty($this->server['HTTP_X_FORWARDED_FOR'])) 
		{
			$ipAddress = $this->server['HTTP_X_FORWARDED_FOR'];
		} 
		else 
		{
			$ipAddress = $this->server['REMOTE_ADDR'];
		}
		
		return $ipAddress;
	}

	public function getCurrentUser()
	{
		return $this->currentUser();
	}

	public function getUserID()
	{
		$user = $this->getCurrentUser();

		if (!$user)
		{
			return null;
		}

		return DAM::get("persona")->valueForIdentifier($user);
	}
	
	



	//--- Process GET, POST, PUT, PATCH, renderBODY - Overidable Methods
	//----------------------------------------------------------
	//----------------------------------------------------------

	public function processGet($getObject)
	{
		$debug = false;

		if ($debug)
		{
			error_log("Process GET Basic: ".print_r($getObject, true));
		}
		// Does nothing..

		
	}

	public function processPost()
	{
		$debug = false;

		if ($debug)
		{
			error_log("Process POST Basic: ".print_r($this->post, true));
		}
		// Does nothing..
	}


	public function gtk_renderBody()
	{
		$toReturn = $this->renderMessages();

		if (method_exists($this, "renderBody"))
		{
			$toReturn .= $this->renderBody();
		}
		else
		{
			$toReturn .= "<h1 class='font-bold'>";
			$toReturn .= "No `renderBody` method found";
			$toReturn .= "</h1>";
		}
		
		return $toReturn;
	}
	
	public function renderMessages()
	{
		$toReturn = "";

		if (count($this->messages) > 0)
		{
			$toReturn .= "<h1 class='font-bold'>";
			$toReturn .= "Mensajes";
			$toReturn .= "</h1>";
			$toReturn .= "<div>";
			foreach ($this->messages as $message)
			{
				$toReturn .= "<div>";
				if (is_string($message))
				{
					$toReturn .= $message;
				}
				else
				{
					$toReturn .= print_r($message, true);
				}
				$toReturn .= "</div>";
			}
			$toReturn .= "</div>";
		}

		return $toReturn;
	}

	/**
	 * Add a message to be displayed on the page
	 * @param string $type The type of message (error, success, warning, info)
	 * @param string $text The message text
	 */
	public function addMessage($type, $text)
	{
		$this->messages[] = [
			'type' => $type,
			'text' => $text
		];
	}

	public function processPUT(){}
	public function processPatch(){}


	//--- Header, Footer methods
	//---------------------------------------------------------
	//---------------------------------------------------------

	public function includeOrRenderPathOrIncludeDefault($toIncludeOrRender, $theDefaultPath)
	{
		if (is_callable($toIncludeOrRender))
		{
			return $toIncludeOrRender();
		}

		$classExists = class_exists($toIncludeOrRender);

		if ($classExists && method_exists($toIncludeOrRender, "renderForUser"))
		{
			$instance = new $toIncludeOrRender();
			return $instance->renderForUser($this->currentUser());
		}
		else if ($classExists && method_exists($toIncludeOrRender, "render"))
		{
			$instance = new $toIncludeOrRender();
			return $instance->render();
		}

		$classExists = class_exists($theDefaultPath);

		if ($classExists && method_exists($theDefaultPath, "renderForUser"))
		{
			$instance = new $theDefaultPath();
			return $instance->renderForUser($this->currentUser());
		}
		else if ($classExists && method_exists($theDefaultPath, "render"))
		{
			$instance = new $theDefaultPath();
			return $instance->render();
		}

		return "";
	}

	public function renderItemAttribute($attribute)
	{
		if (is_string($attribute))
		{
			return $attribute;
		}
		else if (is_callable($attribute))
		{
			return $attribute($this);
		}
	}

	
	public function gtk_renderHeader()
	{
		$contentType = $this->server["CONTENT_TYPE"] ?? "text/html";

		global $_GLOBALS;

		$defaultHeader = null;

		if (isset($_GLOBALS["DEFAULT_HEADER"]))
		{
			$defaultHeader = $_GLOBALS["DEFAULT_HEADER"];
		}
		else
		{
			$defaultHeader = null;
		}

		// die("Header: ".$this->header . " Default: ".$defaultHeader);

		switch($contentType)
		{
			case "application/json":
				return "";
			case "application/xml":
				return "";
			case "application/x-www-form-urlencoded":
			case "multipart/form-data":
			case "text/plain":
			case "text/html":
			default:
				return $this->includeOrRenderPathOrIncludeDefault($this->header, $defaultHeader);
		}
	}

	public function gtk_renderFooter()
	{
		$contentType = $this->server["CONTENT_TYPE"] ?? "text/html";
		global $_GLOBALS;

		$defaultFooter = null;

		if (isset($_GLOBALS["DEFAULT_FOOTER"]))
		{
			$defaultFooter = $_GLOBALS["DEFAULT_FOOTER"];
		}
		else
		{
			$defaultFooter = null;
		}

		switch($contentType)
		{
			case "application/json":
				return "";
			case "application/xml":
				return "";
			case "application/x-www-form-urlencoded":
			case "multipart/form-data":
			case "text/plain":
			case "text/html":
			default:
				return $this->includeOrRenderPathOrIncludeDefault($this->footer, $defaultFooter);
		}
	}

	public function handleNotAuthenticated()
	{
		if (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json') 
		{
			return json_encode([
				"error" => "Not authenticated",
			]);
		}
		else
		{
			redirectToURL("/auth/login.php", null, []);
		}
	}

	public function handleNotAuthorized()
	{
		die("No autorizado.");
	}

	public function currentSession()
	{
		if (!$this->session)
		{
			if (!$this->didSearchForCurrentSession)
			{
				$this->didSearchForCurrentSession = true;

				$this->session = DataAccessManager::get("session")->getCurrentApacheSession([
					"requireValid" => true,
				]);
			}
		}

		return $this->session;
	}

	public function currentUser()
	{
		if (!$this->user)
		{
			if (!$this->didSearchForCurrentUser)
			{
				$this->didSearchForCurrentUser = true;
			
				$this->user = DataAccessManager::get("session")->getCurrentUser();
				
			}
		}

		return $this->user;
	}

	public function dataSourceFor($name)
	{
		return DataAccessManager::get($name);
	}

	public function getDataSource($name)
	{
		return DataAccessManager::get($name);
	}

	public function isAuthorized()
	{
		$debug = false;

		$isAuthorized = true;

		if ($this->permissionRequired == "public")
		{
			return true;
		}

		if ($this->permissionRequired)
		{
			if (!$this->currentUser())
			{
				if ($debug)
				{
					error_log("No current user, will redirect...");
				}
				return $this->handleNotAuthenticated();
			}

			$userDataAccess = DataAccessManager::get("persona");

			$currentUser = $this->currentUser();

			$isAuthorized = $userDataAccess->hasPermission($this->permissionRequired, $currentUser);
		}

		if ($debug)
		{
			$message = "`render` for ".get_class($this)." : Is authorized: ".print_r($isAuthorized, true)." for permission: ".$this->permissionRequired;

			error_log($message);
		}

		return $isAuthorized;
	}

	public function setCustomLogFile()
	{
		// DO NOTHING
	}

	public function hiddenInput($name, $value)
    {
        return "<input type='hidden' name='".$name."' value='".$value."'>";
    }


	public function render($get, $post, $server, $cookie, $session, $files, $env)
	{
		$debug = true;

		$html = null;

		$this->setCustomLogFile();

		$this->get 		  = $get;
		$this->post 	  = $post;
		$this->server 	  = $server;
		$this->cookie 	  = $cookie;
		$this->phpSession = $session;
		$this->files      = $files;
		$this->env 		  = $env;

		if ($this->allowsCache)
		{
			
			session_start();
			header("Cache-Control: no-cache, must-revalidate");
			header("Pragma: no-cache");
			
		}
		
		if ($debug)
		{
			error_log("`render` : Got current user: ".print_r($this->currentUser(), true));
			
			
			error_log("`render` : Got current session: ".print_r($this->currentSession(), true));
		}
		

		if ($this->authenticationRequired && !$this->currentUser())
		{
			if ($debug)
			{
				error_log("Authentication required, No user, no session, will redirect...");
			}
			return $this->handleNotAuthenticated();
		}

		if (!$this->isAuthorized())
		{
			return $this->handleNotAuthorized();
		}

		$this->processGet($get);

		switch ($this->server["REQUEST_METHOD"])
		{
			case "POST":
				$toReturn = $this->processPost($post, $files);
				if ($toReturn)
				{
					return $toReturn;
				}
				break;
			case "PATCH":
				$this->processPatch($post);
				break;
			case "PUT":
				$this->processPut($post);
				break;
		}

		if ($this->shouldRespondWithJSON())
		{
			if (method_exists($this, "renderJSON"))
			{
				return $this->renderJSON();
			}
		}

		// Add stylesheets
		$html .= $this->renderStylesheets();
		
		$html .= "<body>\n";
		
		// Add header, body, footer content
		$html .= $this->gtk_renderHeader();
		$html .= $this->gtk_renderBody();
		$html .= $this->gtk_renderFooter();
		
		// Add scripts at the end of body
		$html .= $this->renderScripts();
		
		$html .= "</body>\n</html>";
		
		return $html;
	}

	public function shouldRespondWithJSON()
	{
		return isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json';
	}

	function htmlEscape($string)
	{
    	if ($string)
    	{
        	return htmlspecialchars($string);
    	}
	}

	/**
	 * Adds a stylesheet to the page
	 * 
	 * @param string $url URL of the stylesheet to add
	 * @return void
	 */
	public function addStylesheet($url)
	{
		$this->stylesheets[] = $url;
	}

	/**
	 * Adds a script to the page
	 * 
	 * @param string $url URL of the script to add
	 * @return void
	 */
	public function addScript($url)
	{
		$this->scripts[] = $url;
	}

	/**
	 * Renders the stylesheets in the head section
	 * 
	 * @return string HTML for the stylesheets
	 */
	protected function renderStylesheets()
	{
		$html = '';
		foreach ($this->stylesheets as $stylesheet) {
			$html .= '<link rel="stylesheet" href="' . $stylesheet . '">' . PHP_EOL;
		}
		return $html;
	}

	/**
	 * Renders the scripts at the end of the body
	 * 
	 * @return string HTML for the scripts
	 */
	protected function renderScripts()
	{
		$html = '';
		foreach ($this->scripts as $script) {
			$html .= '<script src="' . $script . '"></script>' . PHP_EOL;
		}
		return $html;
	}

}

class GTKEditPage extends GTKHTMLPage
{

}



class GTKEntityPage extends GTKHTMLPage
{
	public $entity;
	public $entityDataSourceName;

	public function entityValue($key)
    {
        
        return $this->getDataSource($this->entityDataSourceName)->valueForKey($key, $this->entity);
    }

}
