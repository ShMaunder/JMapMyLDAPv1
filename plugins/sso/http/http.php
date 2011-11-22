<?php
/**
 * @package     Shmanic.Plugin
 * @subpackage  SSO.HTTP
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

/**
 * Attempts to match a user based on the supplied server variables.
 * 
 * @package     Shmanic.Plugin
 * @subpackage  SSO.HTTP
 */
class plgSSOHTTP extends JPlugin 
{
	/**
	 * This method checks if a value for remote user is present inside 
	 * the $_SERVER array. If so then replace any domain related stuff
	 * to get the username and return it. 
	 * 
	 * @return  string  Username of detected user
	 * @since   1.0
	 */
	public function detectRemoteUser() 
	{
		// Get the $_SERVER key and ensure its lowercase and doesn't filter
		$remote_user = strtolower(
			JRequest::getVar($this->params->get('userkey','REMOTE_USER'), null, 'server', 'string', JREQUEST_ALLOWRAW)
		);
		
		if(is_null($remote_user) || $remote_user=='') return null;
		
		// Get a username replacement parameter in lowercase and split by semi-colons 
		$replace_set = explode(';', strtolower($this->params->get('username_replacement','')));
		
		foreach($replace_set as $replacement) {
			$remote_user = str_replace(trim($replacement),'',$remote_user);
		}
			
		return $remote_user;
	}
}
