<?php
/*
Accessed via Singleton.
OptionsAccessManager::get($key);
*/
class OptionsAccessManager
{
	private static $instance;
	private $options = [];

	public static function get($key)
	{
		$instance = self::getSingleton();

		if (isset($instance->options[$key])) 
		{
			return $instance->options[$key];
		}
		else 
		{
			return null;
		}
	}

	public static function getSingleton($options = null)
	{
		if (self::$instance === null) 
		{
			if (!$options)
			{
				global $_GLOBALS;
				$options = $_GLOBALS["OPTIONS_ACCESS_MANAGER_OPTIONS"];
			}

			self::$instance = new self($options);
		}

		return self::$instance;
	}

	public function __construct($options = [])
	{
		$this->options = $options;
	}

	public function getOptions()
	{
		return $this->options;
	}
}



