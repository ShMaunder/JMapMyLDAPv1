<?php
/**
 * @package     Shmanic
 * @subpackage  SSO
 *
 * @author      Shaun Maunder <shaun@shmanic.com>
 * @copyright   Copyright (C) 2011 Shaun Maunder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * --- Original based on JAuthTools ---
 * @url         http://joomlacode.org/gf/project/jauthtools
 * @author      Sam Moffatt <sam.moffatt@toowoombarc.qld.gov.au>
 * @author      Toowoomba Regional Council Information Management Department
 * @copyright   (C) 2008 Toowoomba Regional Council/Sam Moffatt
 * ------------------------------------
 */

defined('_JEXEC') or die;

jimport('joomla.base.observable');
jimport('joomla.user.authentication');

/**
 * Provides a framework for SSO and login methods. The framework
 * is similar to the JAuthTools framework.
 *
 * @package		Shmanic
 * @subpackage	SSO
 * @since		1.0
 */
class JSSOAuthentication extends JObservable
{
	/**
	 * Class constructor.
	 *
	 * @since   1.0
	 */
	public function __construct()
	{
		$lang = JFactory::getLanguage();
		$lang->load('lib_jssomysite', JPATH_SITE); //for errors

		$isLoaded = JPluginHelper::importPlugin('sso');

		if(!$isLoaded) {
			$this->_reportError(new JException(JText::_('LIB_JSSOMYSITE_ERROR_IMPORT_PLUGINS')));
		}
	}

	/**
	 * Detect the remote SSO user by looping through all SSO
	 * plugins. Once a detection is found, it is put into
	 * the options parameter array and method is returned as
	 * true. Uses the same framework as JAuthTools SSO.
	 *
	 * @param  array  &$options  An array containing action and autoregister value (byref)
	 *
	 * @return  Boolean  Return true if a remote user has been detected
	 * @since   1.0
	 */
	public function detect(&$options = array())
	{
		$plugins = JPluginHelper::getPlugin('sso');

		foreach ($plugins as $plugin) {
			$name = $plugin->name;
			$className = 'plg' . $plugin->type . $name;

			if(class_exists($className)) {
				$plugin = new $className($this, (array)$plugin);
			} else {
				$this->_reportError(new JException(JText::sprintf('LIB_JSSOMYSITE_ERROR_PLUGIN_CLASS', $className, $name)));
				continue;
			}

			// we need to check the ip rule & list before attempting anything...
			$params = new JRegistry;
			$params->loadString($plugin->params);

			$myip = JRequest::getVar('REMOTE_ADDR', 0, 'server');

			$iplist = explode("\n", $params->get('ip_list', ''));
			$ipcheck = JSSOHelper::doIPCheck($myip, $iplist, $params->get('ip_rule', 'allowall')=='allowall');

			if($ipcheck) {

				// Try to authenticate remote user
				$username = $plugin->detectRemoteUser();

				if (!empty($username))
				{
					// Detection is successful
					$options['username'] = $username;
					$options['type'] = $name;
					$options['sso'] = true;
					return true;
				}
			}
		}
	}

	/**
	 * If a detection has been successful then it will try to
	 * authenticate with the special onSSOAuthenticate method
	 * in any of the authentication plugins.
	 *
	 * @param  string  $username  String containing detected username
	 * @param  array   $options   An array containing action, autoregister and detection name
	 *
	 * @return  mixed  Returns a JAuthenticationReponse on success, nothing on failure
	 * @since   1.0
	 */
	public function authenticate($username, $options)
	{
		// Get plugins
		$plugins = JPluginHelper::getPlugin('authentication');

		// Create authencication response
		JAuthentication::getInstance();
		$response = new JAuthenticationResponse;

		/*
		 * Loop through the plugins and await until one succeeds
		 *
		 * Any errors raised in the plugin should be returned via the JAuthenticationResponse
		 * and handled appropriately.
		 */
		foreach ($plugins as $plugin) {
			$className = 'plg' . $plugin->type . $plugin->name;
			if(class_exists($className)) {
				$plugin = new $className($this, (array)$plugin);

				if(method_exists($plugin, 'onSSOAuthenticate')) {
					//try to authenticate
					$plugin->onSSOAuthenticate($username, $options, $response);

					// If authentication is successful break out of the loop
					if($response->status === JAUTHENTICATE_STATUS_SUCCESS) {
						if(empty($response->type)) {
							$response->type = isset($plugin->_name) ? $plugin->_name : $plugin->name;
						}
						return $response;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Attempts a SSO by calling the detection function, then
	 * authenticates the returned username and lastly calls the
	 * onUserLogin user plugin events.
	 *
	 * @param  array   $options   An array containing action and autoregister
	 *
	 * @return  boolean  True on successful SSO login
	 * @since   1.0
	 */
	public function login($options = array())
	{
		if($this->detect($options)) { //get the sso username through detection methods
			$app = JFactory::getApplication();
			$response = $this->authenticate($options['username'], $options);

			if($response) {
				//import the user plugin group.
				JPluginHelper::importPlugin('user');

				//lets fire the onUserLogin event
				$results = $app->triggerEvent('onUserLogin', array((array)$response, $options));

				if(!in_array(false, $results, true)) {
					return true;
				} else {
					// User plugin error
					$this->_reportError(new JException(JText::sprintf('LIB_JSSOMYSITE_ERROR_USER_PLUGIN', $options['username'])));
					return false;
				}
			}

			$this->_reportError(new JException(JText::sprintf('LIB_JSSOMYSITE_ERROR_AUTHENTICATION', $options['username'])));
			return false;
		}
	}

	/**
	 * Reports an error to the screen if debug mode is enabled.
	 * Will also report to the logger for administrators.
	 *
	 * @param  mixed  $exception  The authentication error can either be
	 *                              a string or a JException.
	 *
	 * @return  string  Exception comment string
	 * @since   1.0
	 */
	protected function _reportError($exception = null)
	{
		$comment = is_null($exception) ? JText::_('LIB_JSSOMYSITE_ERROR_UNKNOWN') : $exception;

		$errorlog = array('status'=>'SSO Fail: ', 'comment'=>$comment);

		jimport('joomla.error.log');

                // Due to Joomla removing JLog methods in later versions...
                if (method_exists(JLog, 'getInstance'))
                {
                        // Legacy method for writing to log file
                        $log = JLog::getInstance();
                        $log->addEntry($errorlog);
                }
                else
                {
                        // Newer method
                        JLog::addLogger(array(), JLog::ERROR);
                        JLog::add((string) $errorlog['comment'], JLog::ERROR, $errorlog['status']);
                }

		if(JDEBUG) {
			JError::raiseWarning('SOME_ERROR_CODE', $comment);
		}

		return $comment;
	}

}

/**
 * SSO Helper class.
 *
 * @package		Shmanic
 * @subpackage	SSO
 * @since		1.0
 */
class JSSOHelper extends JObject
{
	/**
	 * Do a IP range address check. There are four different
	 * types of ranges; single; wildcard; mask; section.
	 *
	 * @param  $ip        string   String of IP address to check
	 * @param  $ranges    array    An array of IP range addresses
	 * @param  $allowAll  boolean  IP mode (i.e. allow all except...)
	 *
	 * @return  boolean  True means the ip is in the range
	 * @since   1.0
	 */
	public static function doIPCheck($ip, $ranges, $allowAll=false)
	{
		$return = self::checkIP($ip, $ranges);

		return $allowAll ? !$return : $return;
	}

	/**
	 * Determine the type of each range entry in ranges. Then
	 * call the corresponding method. This method shall return
	 * true if any of the ranges match the ip.
	 *
	 * adapted from http://www.php.net/manual/en/function.ip2long.php#102898
	 *
	 * @param  $ip        string   String of IP address to check
	 * @param  $ranges    array    An array of IP range addresses
	 *
	 * @return  boolean  True means the ip is in the range
	 * @since   1.0
	 */
	protected static function checkIP($ip, $ranges)
	{
		if(!count($ranges) || $ranges[0]=='')
			return false;

		foreach($ranges as $range) {
			$type = null;
			if(strpos($range, '*')) 		$type = 'wildcard';
			elseif(strpos($range, '/')) 	$type = 'mask';
			elseif(strpos($range, '-')) 	$type = 'section';
			elseif(ip2long($range)) 		$type = 'single';

			if($type) {
				$sub_rst = call_user_func(array('self','_sub_checker_' . $type), $range, $ip);

				if($sub_rst) return true;
			}
		}

		return false;

	}

	/**
	 * Single IP address check (i.e. 192.168.0.2).
	 *
	 * adapted from http://www.php.net/manual/en/function.ip2long.php#102898
	 *
	 * @param  $allowed   string   A single IP address
	 * @param  $ip        string   String of IP address to check
	 *
	 * @return  boolean  True means the ip is matching
	 * @since   1.0
	 */
	protected static function _sub_checker_single($allowed, $ip)
	{
		return (ip2long($allowed) == ip2long($ip));
	}

	/**
	 * Wildcard IP address check (i.e. 192.168.0.*).
	 *
	 * adapted from http://www.php.net/manual/en/function.ip2long.php#102898
	 *
	 * @param  $allowed   string   A wildcard IP address
	 * @param  $ip        string   String of IP address to check
	 *
	 * @return  boolean  True means the ip is matching
	 * @since   1.0
	 */
	protected static function _sub_checker_wildcard($allowed, $ip)
	{
		$allowed_ip_arr = explode('.', $allowed);
		$ip_arr = explode('.', $ip);
		for($i = 0;$i < count($allowed_ip_arr);$i++) {
			if($allowed_ip_arr[$i] == '*') {
				return true;
			} else {
				if(false == ($allowed_ip_arr[$i] == $ip_arr[$i]))
					return false;
			}
		}
	}

	/**
	 * Mask based IP address check (i.e. 192.168.0.0/24).
	 *
	 * adapted from http://www.php.net/manual/en/function.ip2long.php#102898
	 *
	 * @param  $allowed   string   A mask based IP address
	 * @param  $ip        string   String of IP address to check
	 *
	 * @return  boolean  True means the ip is matching
	 * @since   1.0
	 */
	protected static function _sub_checker_mask($allowed, $ip)
	{
		list($allowed_ip_ip, $allowed_ip_mask) = explode('/', $allowed);

		if($allowed_ip_mask <= 0) return false;
		$ip_binary_string = sprintf("%032b",ip2long($ip));
		$net_binary_string = sprintf("%032b",ip2long($allowed_ip_ip));

		return (substr_compare($ip_binary_string,$net_binary_string,0,$allowed_ip_mask) === 0);

	}

	/**
	 * Section based IP address check (i.e. 192.168.0.0-192.168.0.2).
	 *
	 * adapted from http://www.php.net/manual/en/function.ip2long.php#102898
	 *
	 * @param  $allowed   string   A section based IP address
	 * @param  $ip        string   String of IP address to check
	 *
	 * @return  boolean  True means the ip is matching
	 * @since   1.0
	 */
	protected static function _sub_checker_section($allowed, $ip)
	{
		list($begin, $end) = explode('-', $allowed);
		$begin = ip2long($begin);
		$end = ip2long($end);
		$ip = ip2long($ip);
		return ($ip >= $begin && $ip <= $end);
	}
}
