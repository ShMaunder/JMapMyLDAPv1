<?php
/**
 * @version		$Id: edirldap.php 00000 2011-06-21 00:00:00Z shmaunder $
 * @copyright	(C) 2008 Toowoomba Regional Council/Sam Moffatt. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 *
 * The JSSOMySite extensions uses the JAuthTools framework for SSO plugins and therefore,
 * all SSO plugins are a fork of the original works of JAuthTools by Samual Moffatt 
 * http://joomlacode.org/gf/project/jauthtools
 */

jimport('joomla.plugin.plugin');

/**
 * SSO eDirectory Source
 * Attempts to match a user based on their network address attribute (IP Address)
 * @package JSSOMySite
 * @subpackage SSO
 */
class plgSSOEDirLDAP extends JPlugin
{
	public function detectRemoteUser()
	{
		// Import languages for frontend errors
		$this->loadLanguage();

		$authName = $this->params->get('auth_plugin','jmapmyldap');
		$authParams = $this->getAuthPluginParams($authName);

		if(!$authParams) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::sprintf('PLG_EDIR_ERROR_INCORRECT_AUTH', $authName));
			return;
		}

		$ldapuid = $authParams->get('ldap_uid','uid');

		// This will be broken for inbuilt plugin and jmmLDAP 2
		jimport('shmanic.jldap2');
		$ldap = new JLDAP2($authParams);

		if(!$ldap->connect()) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::sprintf('PLG_EDIR_ERROR_LDAP_CONNECT', $ldap->host));
			return;
		}

		// Lets try to bind using proxy user
		if (!$bind = $ldap->bind($ldap->connect_username, $ldap->connect_password))
		{
			JError::raiseWarning('SOME_ERROR_CODE', JText::_('PLG_EDIR_ERROR_LDAP_BIND'));
			return;
		}

		// Get IP of client machine
		$myip = JRequest::getVar('REMOTE_ADDR', 0, 'server');

		// Convert this to some net address thing that edir likes
		$na = JLDAPHelper::ipToNetAddress($myip);

		// Find the network address and return the uid for it
		$filter = "(networkAddress=$na)";

		$dn = $authParams->get('base_dn');

		// Do the LDAP filter search now
		$result = new JLDAPResult($ldap->search($dn, $filter, array($ldapuid)));
		$ldap->close();

		if ($value = $result->getValue(0, $ldapuid, 0))
		{
			// Username was found logged in on this client machine
			return $value;
		}

	}

	public function getAuthPluginParams($name)
	{
		$pluginParams = new JRegistry;
		$authPlugin = JPluginHelper::getPlugin('authentication', $name);

		if (count($authPlugin))
		{
			$pluginParams->loadString($authPlugin->params);
			return $pluginParams;
		}
	}
}
