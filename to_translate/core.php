<?php
/**
 * Script that initialises all stuff
 * @license GNU GPLv3 http://opensource.org/licenses/gpl-3.0.html
 * @package Kinokpk.com releaser
 * @author ZonD80 <admin@kinokpk.com>
 * @copyright (C) 2008-now, ZonD80, Germany, TorrentsBook.com
 * @link http://dev.kinokpk.com
 */

/**
 * Constant to deny direct access to inclusion scripts
 * @var boolean
 */
define('IN_TRACKER', true);

// SET PHP ENVIRONMENT
@error_reporting(E_ALL & ~E_NOTICE);
@ini_set('error_reporting', E_ALL & ~E_NOTICE);
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '0');
@ini_set('ignore_repeated_errors', '1');
@session_start();
date_default_timezone_set('UTC');

/**
 * Directory separator. Fucking windows compatibility
 * @var string
 */
define ('DS',DIRECTORY_SEPARATOR);
$DS = DS;

/**
 * Full path to releaser sources
 * @var string
 */
define ('ROOT_PATH', dirname(__FILE__).$DS);
// Variables for Start Time
/**
 * Script start time for debug
 * @var float
 */
$tstart = microtime(true); // Start time

require_once(ROOT_PATH . 'include'.DS.'secrets.php');

/* @var database object */
require_once(ROOT_PATH . 'classes'.DS.'database'.DS.'database.class.php');
$REL_DB = new REL_DB($db);
unset($db);

/* @var object general cache object */
require_once(ROOT_PATH . 'classes'.DS.'cache'.DS.'cache.class.php');
$REL_CACHE=new Cache();
if (REL_CACHEDRIVER=='native') {
	require_once(ROOT_PATH .  'classes'.DS.'cache'.DS.'fileCacheDriver.class.php');
	$REL_CACHE->addDriver(NULL, new FileCacheDriver());
}
elseif (REL_CACHEDRIVER=='memcached') {
	require_once(ROOT_PATH .  'classes'.DS.'cache'.DS.'MemCacheDriver.class.php');
	$REL_CACHE->addDriver(NULL, new MemCacheDriver());
}

require_once(ROOT_PATH . 'include'.DS.'functions.php');
require_once(ROOT_PATH . 'include'.DS.'classes.php');

// TinyMCE security
require_once(ROOT_PATH . 'include'.DS.'htmLawed.php');
// Ban system
require_once(ROOT_PATH.'classes'.DS.'bans'.DS.'ipcheck.class.php');

require_once(ROOT_PATH . 'include'.DS.'blocks.php');

// old,compatibility,deprecated functions and function aliases
//require_once(ROOT_PATH . 'include'.DS.'functions_deprecated.php');

// IN AJAX MODE?

ajaxcheck();
?>