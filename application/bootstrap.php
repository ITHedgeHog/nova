<?php defined('SYSPATH') or die('No direct script access.');

//-- Environment setup --------------------------------------------------------

/**
 * Set the default time zone.
 *
 * @see  http://kohanaframework.org/guide/using.configuration
 * @see  http://php.net/timezones
 */
date_default_timezone_set('GMT');

/**
 * Set the default locale.
 *
 * @see  http://kohanaframework.org/guide/using.configuration
 * @see  http://php.net/setlocale
 */
setlocale(LC_ALL, 'en_US.utf-8');

/**
 * Enable the Kohana auto-loader.
 *
 * @see  http://kohanaframework.org/guide/using.autoloading
 * @see  http://php.net/spl_autoload_register
 */
spl_autoload_register(array('Kohana', 'auto_load'));

/**
 * Enable the Kohana auto-loader for unserialization.
 *
 * @see  http://php.net/spl_autoload_call
 * @see  http://php.net/manual/var.configuration.php#unserialize-callback-func
 */
ini_set('unserialize_callback_func', 'spl_autoload_call');

//-- Configuration and initialization -----------------------------------------

// set the Kohana environment
Kohana::$environment = Kohana::DEVELOPMENT;

/**
 * Set Kohana::$environment if $_ENV['KOHANA_ENV'] has been supplied.
 * 
 */
if (isset($_ENV['KOHANA_ENV']))
{
	Kohana::$environment = $_ENV['KOHANA_ENV'];
}

/**
 * Initialize Kohana, setting the default options.
 *
 * The following options are available:
 *
 * - string   base_url    path, and optionally domain, of your application   NULL
 * - string   index_file  name of your index file, usually "index.php"       index.php
 * - string   charset     internal character set used for input and output   utf-8
 * - string   cache_dir   set the internal cache directory                   APPPATH/cache
 * - boolean  errors      enable or disable error handling                   TRUE
 * - boolean  profile     enable or disable internal profiling               TRUE
 * - boolean  caching     enable or disable internal caching                 FALSE
 */
$url = $_SERVER['SCRIPT_NAME'];
$url = substr($url, 0, strpos($url, '.php'));
$url = substr($url, 0, (strlen($url) - strpos(strrev($url), '/')));

Kohana::init(array(
	'base_url'		=> $url,
	'index_file'	=> 'index.php',
	'error'			=> TRUE,
	'profile'		=> (Kohana::$environment == 'production') ? FALSE : TRUE,
));

/**
 * Attach the file write to logging. Multiple writers are supported.
 */
Kohana::$log->attach(new Kohana_Log_File(APPPATH.'logs'));

/**
 * Attach a file reader to config. Multiple readers are supported.
 */
Kohana::$config->attach(new Kohana_Config_File);

/**
 * Enable modules. Modules are referenced by a relative or absolute path.
 */
$base_modules = array(
	'nova'			=> MODPATH.'nova/core',
	'thresher'		=> MODPATH.'nova/thresher',
	'override'		=> MODPATH.'override',
	'install'		=> MODPATH.'nova/install',
	'update'		=> MODPATH.'nova/update',
	'database'		=> MODPATH.'kohana/database',
	'jelly'			=> MODPATH.'kohana/jelly',
	'userguide'		=> MODPATH.'kohana/userguide',
	'dbforge'		=> MODPATH.'nova/dbforge',
	'assets'		=> MODPATH.'assets',
	//'xml'			=> MODPATH.'kohana/xml',
);

// merge the base modules with whatever is in the modules section of the nova config file
$modules = array_merge(Kohana::config('nova.modules'), $base_modules);

// set the modules
Kohana::modules($modules);

/**
 * Set the routes. Each route must have a minimum of a name, a URI and a set of
 * defaults for the URI.
 */
Route::set('default', '(<controller>(/<action>(/<id>)))')
	->defaults(array(
		'controller' => 'main',
		'action'     => 'index',
	));

if ( ! defined('SUPPRESS_REQUEST'))
{
	/**
	 * Execute the main request. A source of the URI can be passed, eg: $_SERVER['PATH_INFO'].
	 * If no source is specified, the URI will be automatically detected.
	 */

	Events::event('preCreate');
	$request = Request::instance();
	Events::event('postCreate');

	Events::event('preExecute');
	
	if (Kohana::$environment == 'production')
	{
		try {
			$request->execute();
		}
		catch (Exception $e)
		{
			switch ($e->getCode())
			{
				case -1:
				case 0:
				case 404:
					$request = Request::factory('error/404')->execute();
				break;
			}
		}
	}
	else
	{
		$request->execute();
	}
	
	Events::event('postExecute');

	Events::event('preHeaders');
	$request->send_headers();
	Events::event('postHeaders');

	Events::event('preResponse');
	echo $request->response;
	Events::event('postResponse');
}