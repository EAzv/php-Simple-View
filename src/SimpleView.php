<?php

namespace SimpleView;

use Psr\Http\Message\ResponseInterface;

/**
 *   SimpleView
 *	A simple class to help render PHP PSR-7 projects
 *
 * @author Eduardo Azevedo <eduhazvdo@gmail.com>
 */
class SimpleView
{

	private $_index     = array();
	private $_stack     = array();
	private $_context   = "";
	private $_wrapper   = null;

	/** @var array store variables to be used within view files */
	private $_variables = array();

	/** @var array store assets' link templates */
	private $_assets    = array();

	/** @var array class configurations */
	private $_config    = array(
							'folder'   => false, // default folder where are stored the view files
							'base_url' => "/", // base url for internal links
							'file_ext' => ".phtml", // view files extension
							'charset'  => "utf-8",
						);


	/**
	 * Constructs
	 * It is mandatory to pass the location of views folder in the settings argument
	 * @uses new SimpleView\SimpleView(['folder' => './views'])
	 * @param array $config class settings
	 */
	public function __construct (array $config = [])
	{
		if (!isset($config['folder']))
			throw new \Exception("The folder to store template files hasn't been defined");

		foreach($config as $key => $val)
			$this->config($key, $val);

		$this->_context = uniqid("ViewHelper:");
	}

	/**
	 * get or set configuration parameters
	 * @param string $key
	 * @param string $value
	 * @return void|mix
	 */
	public function config (string $key, $value=null)
	{
		if ($value === null)
			return isset($this->_config[$key]) ? $this->_config[$key] : false;

		if ($key == 'assets'){
			foreach($value as $_id => $_val){
				if (is_string($_val)):
					$this->_assets[$_id] = array( $_val, '');
				elseif (is_array($_val)):
					$_val = array_merge($_val, ['','']);
					$this->_assets[$_id] = $_val;
				endif;
			}
		} else {
			$this->_config[$key] = $value;
		}
	}

	/**
	 * sets variables to be accessible inside view context
	 * @param array|string $arg
	 * @param mix $val
	 * @return SimpleView
	 */
	public function set ($arg, $val=null):SimpleView
	{
		if(is_array($arg))
			$this->_variables = array_merge($this->_variables, $arg);
		else
			$this->_variables[$arg] = $val;
		return $this;
	}

	/**
	 * defines the wrapper for the current view
	 * @param string $file
	 * @return SimpleView
	 */
	public function wrapper (string $file):SimpleView
	{
		$this->_wrapper = $file;
		return $this;
	}

	/**
	 * includes content in wrapper views
	 * @return string
	 */
	public function content ():string
	{
		return $this->_context;
	}



	/**
	 * Outputs a rendered view
     * @param string $action
     * @param array  $args variables to be available within view action
	 * @return string
	 */
	public function read (string $action, array $args=[]):string
	{
		$this->set($args);
		return $this->_prepare($action);
	}

	/**
	 * Outputs a rendered view as an PSR-7 Response object
     * @param ResponseInterface $response
     * @param string            $action
     * @param array             $args variables to be available within view action
	 * @return ResponseInterface
	 */
	public function render (ResponseInterface $response, string $action, array $args=[]):ResponseInterface
	{
		$response->getBody()->write($this->read($action, $args));
		return $response;
	}

	/**
	 * get asset links
	 * @param string $key
	 * @param string $asset
	 * @return string
	 */
	public function assets (string $key, string $asset=null): string
	{
		if(!isset($this->_assets[$key]))
			throw new \Exception("The passed asset key ({$key}) doesn't exist.");
		list($path, $bind) = $this->_assets[$key];
		if($asset != null)
			return $this->url($path . $asset) . $bind;
		return $this->url($path);
	}


	/**
	 * parse ulr links
	 * @todo Fix for external links
	 * @return string
	 */
	public function url (string $path=null): string
	{
		//return $this->_base_url . $path;
		return $this->_path_normalize($path, '/');
	}

	/**
	 * Escape html tags from a given string
	 * @param string $val
	 * @return string
	 */
	public function escape ($val):string
	{
		return htmlspecialchars($val, ENT_QUOTES, $this->config('charset'));
	}

	/**
	 * Processes and returns an action view
	 * @param string $action action view
	 * @return string rendered content
	 */
	private function _prepare (string $action):string
	{
		$this->_index[] = 'folder';
		$this->_stack['folder'] = $this->_context;
		$this->_require($action);

		$content = "";
		$_delimiter = uniqid('--delimiter').'--';

		if(isset($this->_index[2]))
			$this->_stack[$this->_index[2]] = $_delimiter . $this->_stack[$this->_index[2]];

		for ($i = 1; $i < count($this->_index); $i++){
			$extend = isset($this->_index[$i+1]);
			$next_content = $extend ? $this->_stack[$this->_index[$i+1]] : "";
			$content .= $this->_stack[$this->_index[$i]];
			$content = str_replace($this->_context, $next_content, $content);
			unset($this->_stack[$this->_index[$i]]);
			unset($this->_index[$i]);
		}
		$content = str_replace($this->_context, $content, $this->_stack[$this->_index[0]]);

		if(substr_count($content, $_delimiter) > 1)
			$content = substr($content, 0, strpos($content, $_delimiter, strpos($content, $_delimiter)+strlen($_delimiter)));
		$content = str_replace($_delimiter, "", $content);
		return $content;
	}

	/**
	 * Executes and indexes the content of a view file
	 * @param string $action action view
	 * @return void
	 * @todo make it work with _ephemeral_context
	 */
	private function _require (string $file)
	{
		$this->_wrapper = null;
		$path = $this->_path_normalize($this->config('folder') .'/'. $file . $this->config('file_ext'));
		if(!file_exists($path))
			throw new \Exception("File {$path} don't exist");
		ob_start();
		extract($this->_variables);
		require $path;
		if($this->_wrapper)
			$this->_require($this->_wrapper);
		$content = ob_get_contents();
		ob_end_clean();
		$this->_index[] = $path;
		$this->_stack[$path] = $content;
	}

	/**
	 * Isolates the action view context
	 * @todo
	 */
	private function _ephemeral_context ():Callable
	{}

	/**
	 * Normalize a file path
	 * @param  string $path
	 * @return string
	 */
	private function _path_normalize (string $path, string $slash=DIRECTORY_SEPARATOR):string
	{
		$path = str_replace('\\', '/', $path);
		$path = preg_replace('/\/+/', '/', $path);
		if ('/' != $slash)
			$path = str_replace('/', $slash, $path);
		return ($path);
	}

}


