<?php
/**
 * @package     Shmanic.Plugin
 * @subpackage  System.SSO
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

jimport('joomla.plugin.plugin');
jimport('shmanic.jssomysite');

/**
 * SSO system plugin class.
 *
 * @package		Shmanic.Plugin
 * @subpackage	System.SSO
 * @since		1.0
 */
class plgSystemJSSOMySite extends JPlugin 
{
	/**
	 * Initiate SSO after the initial Joomla platform initalisaion.
	 * This method checks for backend access, url bypass, ip rules
	 * and current user logged on before passing onto the SSO 
	 * library.
	 *
	 * @return  void
	 * @since   1.0
	 */
	function onAfterInitialise() 
	{
		$this->loadLanguage(); //import languages for frontend errors
		$lang = JFactory::getLanguage();
		$lang->load('lib_joomla', JPATH_SITE); //not sure why, but we have to manually import languages for app
		
		if(!class_exists('JSSOAuthentication')) { //checks for the required library
			return JERROR::raiseWarning('SOME_ERROR_CODE', JText::_('PLG_JSSOMYSITE_ERROR_NO_LIBRARY'));
		}
		
		$options = array();
		
		$user =& JFactory::getUser();
		if($user->id) return false; //there is somebody already logged on
		
		$myip = JRequest::getVar('REMOTE_ADDR', 0, 'server');
		
		/* we are going to check the ip rule and exceptions */
		$iplist = explode("\n", $this->params->get('ip_list', ''));
		$ipcheck = JSSOHelper::doIPCheck($myip, $iplist, $this->params->get('ip_rule', 'allowall')=='allowall');

		if(!$ipcheck) return false; //this ip isn't allowed

		$options['action'] = 'core.login.site';
		
		/* we are going to check if we are in backend.
		 * if so then we need to check if sso can execute
		 * on the backend 
		 * */
		$app = JFactory::getApplication();
		if($app->isAdmin()) {
			if(!$this->params->get('backend',0)) return false;
			$options['action'] = 'core.login.admin';
		}
		
		/* lets check for the url bypass */
		$bypass = $this->params->get('url_bypass', null);
		if(!is_null($bypass) && $bypass!="") {
			if(JRequest::getVar($bypass, 0, 'get'))
				return false;
		}
		
		$options['autoregister'] = $this->params->get('autocreate',false);
		$sso = new JSSOAuthentication();
		$sso->login($options);

	}
}
