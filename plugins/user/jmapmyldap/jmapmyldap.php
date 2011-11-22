<?php
/**
 * @author      Shaun Maunder <shaun@shmanic.com>
 * @package     Shmanic.Plugin
 * @subpackage  User.JMapMyLDAP
 * 
 * @copyright	Copyright (C) 2011 Shaun Maunder. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');
jimport('shmanic.jldap2');

/**
 * LDAP User Plugin
 *
 * @package     Shmanic.Plugin
 * @subpackage  User.JMapMyLDAP
 * @since       1.0
 */
class plgUserJMapMyLDAP extends JPlugin 
{	
	/**
	 * This method fires off the onlogin method for setting
	 * the user session and user groups.
	 *
	 * @param  array  $user     Holds the user data, and the ldapUser entry by auth plugin
	 * @param  array  $options  Array holding options (remember, autoregister, group)
	 *
	 * @return  boolean  True on success
	 * @since   1.0
	 */
	public function onUserLogin($user, $options = array()) 
	{
		//load up the front end lanuages (used for errors)
		$this->loadLanguage();
		
		if($user['type'] != 'LDAP') {
			return true; //the authentication protocol is not comptable
		}
		
		jimport('shmanic.jmapmyldap');
		if(!class_exists('JMapMyLDAP')) { //checks for the required library
			return JERROR::raiseWarning('SOME_ERROR_CODE', JText::_('PLG_JMAPMYLDAP_ERROR_LIB_JMAPMYLDAP_MISSING'));
		}

		$maper = new JMapMyLDAP($this->params);
		
		// Autoregistration with optional override
		$autoRegister = $this->params->get('autoregister', 1);
		if($autoRegister == '0' || $autoRegister == '1') {
			
			// inherited registration
			$options['autoregister'] = isset($options['autoregister']) ? $options['autoregister'] : $autoRegister;
			
		} else {

			// override registration
			$options['autoregister'] = ($autoRegister == 'override1') ? 1 : 0;

		}
		
		jimport('joomla.user.helper');
		$instance = JMapMyLDAP::getUser($user, $options); //get authenticating user...
		if(!$instance || $instance->get('error')) {
			return false;
		}
		
		/* this may have been set in the authentication plugin
		 * and therefore would contain all the attributes we
		 * need to map this authenticating user. as a result
		 * we wouldn't require any ldap connections.
		 */
		if(isset($user['jmapmyentry'])) {
			$ldapUser =& $user['jmapmyentry']; //some other plug-in has already set everything
			
		} else {
			$ldap = $maper->getActiveLdap(); 
			if(JError::isError($ldap))
				return $this->_reportError($ldap); 
			
			$ldapUser = $maper->getLdapUser($ldap, $instance->get('username'));
			$ldap->close();
			
		}

		if(JError::isError($ldapUser)) { //cannot get ldap attributes for user
			return $this->_reportError($ldapUser); 
		} 

		if($this->params->get('group_map_enabled')) {
			$result = $maper->doMap($instance, $ldapUser); //lets do the mapping and report back on any errors
			if(JError::isError($result)) {
				return $this->_reportError($result); 
			}
		}
		
		$maper->doSync($instance, $ldapUser); //lets do the userfield sync
		
		if(!JMapMyLDAP::saveUser($instance)) { //our own method to bypass the super user security checks
			return $this->_reportError(new JException(JText::_('PLG_JMAPMYLDAP_ERROR_JUSER_SAVE')));
		}

		//check the user can login.
		$authorised	= $instance->authorise($options['action']);
		if(!$authorised) {
			return JError::raiseWarning(401, JText::_('JERROR_LOGIN_DENIED'));
		}
		
		// Mark the user as logged in
		$instance->set('guest', 0);
	
		// Register the needed session variables
		$session = JFactory::getSession();
		$session->set('user', $instance);

		return true;
	}
	
	/**
	 * Reports an error to the screen and log. If debug mode is on 
	 * then it displays the specific error on screen, if debug mode 
	 * is off then it displays a generic error.
	 *
	 * @param  JException  $exception  The authentication error
	 * 
	 * @return  JError  Error based on comment from exception
	 * @since   1.0
	 */
	protected function _reportError($exception = null) 
	{
		/*
		* The mapping was not successful therefore
		* we should report what happened to the logger
		* for admin inspection and user should be informed
		* all is not well.
		*/
		$comment = is_null($exception) ? JText::_('PLG_JMAPMYLDAP_ERROR_UNKNOWN') : $exception;
		
		$errorlog = array('status'=>'JMapMyLDAP Fail: ', 'comment'=>$comment);
		
		jimport('joomla.error.log');
		$log = JLog::getInstance();
		$log->addEntry($errorlog);
		
		if(JDEBUG) {
			return JERROR::raiseWarning('SOME_ERROR_CODE', $comment);
		}
		
		return JERROR::raiseWarning('SOME_ERROR_CODE', JText::_('PLG_JMAPMYLDAP_ERROR_GENERAL'));
		
	}

}
