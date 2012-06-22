<?php
/**
 * @author      Shaun Maunder <shaun@shmanic.com>
 * @package     Shmanic
 * @subpackage  Ldap
 *
 * @copyright	Copyright (C) 2011 Shaun Maunder. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

jimport('joomla.version');
jimport('shmanic.jldap2');

/**
 * Holds the parameter settings for the jmapmyldap class.
 *
 * @package		Shmanic
 * @subpackage	Ldap
 * @since		1.0
 */
class JMapMyParameters
{
	/**
	 * Name of the authentication plugin to use for auth parameters
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $auth_plugin = null;

	/**
	 * Synchronise fullname with joomla database
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $sync_name = false;

	/**
	 * Synchronise email with joomla database
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $sync_email = false;

	/**
	 * Use group mapping
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $group_map_enabled = false;

	/**
	 * Allow joomla group additions to users
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $group_map_addition = false;

	/**
	 * Allow joomla group removal from users and default state
	 * of groups
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $group_map_removal = null;

	/**
	 * Unmanaged joomla group IDs seperated by a semi-colon
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $group_map_unmanage = null;

	/**
	 * Public group ID
	 *
	 * @var    integer
	 * @since  1.0
	 */
	protected $group_map_public = 1;

	/**
	 * Holds the entries for the group mapping list
	 *
	 * @var    array
	 * @since  1.0
	 */
	protected $group_map_list = array();

	/**
	 * Lookup type (reverse or forward)
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $lookup_type = null;

	/**
	 * Ldap attribute for the lookup (i.e. groupMembership, memberOf)
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $lookup_attribute = null;

	/**
	 * The user attribute to be used for group member lookup (i.e. dn, uid)
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $lookup_member = null;

	/**
	 * Use recursion
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $recursive = false;

	/**
	 * The dn attribute key name
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $dn_attribute = null;

	/**
	 * Max depth for recursion
	 *
	 * @var    integer
	 * @since  1.0
	 */
	protected $recursion_depth = null;

	/**
	 * Class constructor.
	 *
	 * @param  JRegistry  &$parameters  JRegistry parameters for use in this library. This
	 *                      can normally be found from loading in the user plugin's parameters
	 *
	 * @since   1.0
	 */
	function __construct(&$parameters)
	{
		//if creating your own plugin - you can override the validation methods if they are in a different mask/format

		$classVars = get_class_vars(get_class($this));
		foreach (array_keys($classVars) as $classVar) { //we will loop through all the declared variables in this class
			if(!is_null($parameters->get($classVar))) {
				$method = 'validate_' . $classVar;
				if(method_exists($this, $method)) {
					$this->$classVar = $this->$method($parameters->get($classVar));
				} else {
					$this->$classVar = $parameters->get($classVar);
				}
			}
		}
	}

	/**
	 * Returns the value of a parameter name if it is set.
	 *
	 * @param  boolean  $var  Parameter name
	 *
	 * @return  mixed  Value of parameter
	 * @since   1.0
	 */
	public function get($var)
	{
		return isset($this->$var) ? $this->$var : null;
	}

	/**
	 * Validate unmanaged groups parameter by splitting them into semi-colons,
	 * then removing white space and lastly check for a numeric value.
	 *
	 * @param  string  $in  Raw parameter string
	 *
	 * @return  array  Array of split unmanaged group IDs
	 * @since   1.0
	 */
	protected function validate_group_map_unmanage($in)
	{
		//validate the unmanaged groups by splitting them into semi colons, removing white space then checking for numeric values
		$unmanaged = array();
		$tmp = explode(';', $in);
		foreach($tmp as $entry) {
			$entry = trim($entry);
			if(is_numeric($entry)) {
				$unmanaged[] = $entry;
			}
		}
		return $unmanaged;
	}

	/**
	 * Validate the group mapping list parameter by splitting them into newlines,
	 * then ensuring that each entry contains a colon.
	 *
	 * @param  string  $in  Raw parameter string
	 *
	 * @return  array  Array of split group mappings
	 * @since   1.0
	 */
	protected function validate_group_map_list($in)
	{
		$list = array();
		$tmp = explode("\n", $in);
		foreach($tmp as $entry) {
			if($entry != "" && strrpos($entry, ':') > 0) {
				$list[] = $entry;
			}
		}
		return $list;
	}
}

/**
 * Holds each Ldap entry with its associated Joomla group. This
 * class also contains methods for comparing entries.
 *
 * @package		Shmanic
 * @subpackage	Ldap
 * @since		1.0
 */
class JMapMyEntry extends JObject
{

	/**
	* An array of RDNs to form the DN
	*
	* @var    array
	* @since  1.0
	*/
	protected $rdn 		= array();

	/**
	* The original unaltered dn
	*
	* @var    string
	* @since  1.0
	*/
	protected $dn 		= null;

	/**
	* Valid entry
	*
	* @var    boolean
	* @since  1.0
	*/
	public $valid		= false;

	/**
	* Contains either ldap group memberships or joomla group id's
	* depending on this instance
	*
	* @var    array
	* @since  1.0
	*/
	protected $groups	= array();

	/**
	 * Class constructor.
	 *
	 * @param  string  $dn      The dn thats to hold the associated groups
	 * @param  array   $groups  The assocaited groups of the dn
	 *
	 * @since   1.0
	 */
	function __construct($dn = null, $groups = array())
	{
		$this->dn = 'INVALID'; //we just default to anything to ensure we've something later on

		$explode 		=  ldap_explode_dn($dn, 0);
		if(isset($explode['count']) && $explode['count']>0) {
			$this->rdn 		= array_map('strToLower', $explode); //break up the dn into an array and lowercase it
			$this->dn 		= $dn; //store the original dn string
			$this->groups 	= array_map('strToLower', $groups);
			$this->valid	= true;
		}

	}

	/**
	 * Return the groups class variable
	 *
	 * @return  array  Array of groups
	 * @since   1.0
	 */
	public function getGroups()
	{
		return $this->groups;
	}

	/**
	 * Return the rdn class variable
	 *
	 * @return  array  Array of RDNs to form the DN
	 * @since   1.0
	 */
	public function getRDN()
	{
		return $this->rdn;
	}

	/**
	 * Return the dn class variable
	 *
	 * @return  string  The unaltered full dn
	 * @since   1.0
	 */
	public function getDN()
	{
		return $this->dn;
	}

	/**
	 * Compares all the group mapping entries to all the ldap user
	 * groups and returns an array of JMapMyEntry parameters that
	 * match.
	 *
	 * @param  Array        &$params      An array of JMapMyEntry's for the group mapping list
	 * @param  JMapMyEntry  &$ldapGroups  A JMapMyEntry object to the ldap user groups
	 *
	 * @return  Array  An array of JMapMyEntry parameters that match
	 * @since   1.0
	 */
	public static function compareGroups(&$params, &$ldapGroups)
	{
		$return = array();
		//compare an entire array of DNs in $entries
		foreach($params as $parameter) { //this is the set of group mapping that the user set
			if(self::compareGroup($parameter, $ldapGroups)) {
				$return[] = $parameter;
			}
		}

		return $return;
	}

	/**
	 * Compare the DN in the parameter against the groups in
	 * the ldap groups. This is used to compare if one of the
	 * group mapping list dn entries matches any of the ldap user
	 * groups and if so returns true.
	 *
	 * @param  JMapMyEntry  $parameter    A JMapMyEntry object to the group mapping list parameters
	 * @param  JMapMyEntry  &$ldapGroups  A JMapMyEntry object to the ldap user groups
	 *
	 * @return  Boolean  Returns if this parameter entry is in the ldap user group
	 * @since   1.0
	 */
	public static function compareGroup($parameter, &$ldapGroups)
	{
		$matches 	= array();

		if($parameter->dn=='INVALID' || $ldapGroups->dn=='INVALID') {
			return false; //we only get here if our DN was invalid syntax
		}

		foreach($ldapGroups->groups as $ldapGroup) {
			//we need to convert to lower because escape characters return with uppercase hex ascii codes
			$explode = array_map('strToLower', ldap_explode_dn($ldapGroup,0));
			if(count($explode)) {
				if(self::compareDN($parameter->rdn, $explode)) {
					return true;
				}
			}
		}
	}

	/**
	 * Compare a exploded DN array to another DN array to see if
	 * it matches. Source is suppose to be a parameter whereas
	 * compare is suppose to be a ldap group. This is used to
	 * compare if a dn in the group mapping list matches a dn
	 * from the Ldap directory.
	 *
	 * @param  Array  $source   The source dn (e.g. group mapping list parameter entry)
	 * @param  Array  $compare  The comparasion dn (e.g. ldap group)
	 *
	 * @return  Boolean  Returns the comparasion result
	 * @since   1.0
	 */
	public static function compareDN($source, $compare)
	{
		if(count($source)==0 || count($compare)==0 || $source['count']>$compare['count']) {
			return false;
		}

		/* lets start checking each RDN from left to right to see
		 * if it matches. This would have to be changed if we
		 * wanted to also check from right to left.
		 */
		for($i=0; $i<$source['count']; $i++) {
			if($source[$i]!=$compare[$i]) {
				return false;
			}
		}

		return true;
	}
}

/**
 * A Ldap group mapping class to initiate and commit group mappings.
 *
 * @package		Shmanic
 * @subpackage	Ldap
 * @since		1.0
 */
class JMapMyLDAP extends JObject
{
	/**
	* Holds a reference to the JMapMyParameters class
	*
	* @var    JMapMyParameters
	* @since  1.0
	*/
	public $parameters		= null;

	/**
	* Holds an array of managed joomla IDs
	*
	* @var    array
	* @since  1.0
	*/
	public $managed			= array();

	/**
	 * Class constructor.
	 *
	 * @param   JRegistry  &$parameters  JRegistry parameters for use in this library. This
	 *                   can normally be found from loading in the user plugin's parameters
	 *
	 * @since   1.0
	 */
	function __construct(&$parameters)
	{
		$lang = JFactory::getLanguage();
		$lang->load('lib_jmapmyldap', JPATH_SITE); //for errors

		$this->parameters = new JMapMyParameters($parameters);
	}

	/**
	 * Commit the mapping depending on the parameters specified. This includes
	 * processing the group mapping list, adding joomla groups and removing
	 * joomla groups.
	 *
	 * @param  JUser        &$joomlaUser  A JUser object for the joomla user to be processed
	 * @param  JMapMyEntry  $ldapUser     A JMapMyEntry object for the source ldap user (includes attributes)
	 *
	 * @return  mixed  Returns true on success or JException on error
	 * @since   1.0
	 */
	public function doMap(&$joomlaUser, $ldapUser)
	{
		$joomlaGroups 		= $this->getJoomlaGroups(); //get all the joomla groups

		$paramMapList		= $this->processMapList($joomlaGroups); //process the map list parameter
		if(!(count($paramMapList))) //no map list parameter
			return new JException(JText::_('LIB_JMAPMYLDAP_ERROR_NO_MAPPING_PARAMETERS'));

		$mapLists 			= JMapMyEntry::compareGroups($paramMapList, $ldapUser);

		if($this->parameters->get('group_map_addition')) { //lets add groups
			$toAdd = $this->getGroupsToAdd($joomlaUser, $mapLists);
			foreach($toAdd as $group) self::addUserToGroup($joomlaUser, $group);
		}

		if($this->parameters->get('group_map_removal')!="no") { //lets remove groups
			$toRemove = $this->getGroupsToRemove($joomlaUser, $mapLists);
			foreach($toRemove as $group) self::removeUserFromGroup($joomlaUser, $group);
		}

		if(!count($joomlaUser->get('groups'))) {
			/* no group mappings - we must add the public
			 * group otherwise joomla won't save the changes.
			 */
			self::addUserToGroup($joomlaUser, $this->parameters->get('group_map_public'));
		}

		return true;
	}

	/**
	 * Commit the one way synchronisation to the Joomla user. This
	 * method in future versions will be expanded to offer more sync options.
	 *
	 * @param  JUser        &$joomlaUser  A JUser object for the joomla user to be processed
	 * @param  JMapMyEntry  $ldapUser     A JMapMyEntry object for the source ldap user (includes attributes)
	 *
	 * @return  void
	 * @since   1.0
	 */
	public function doSync(&$joomlaUser, $ldapUser)
	{
		if($this->parameters->get('sync_name') && $ldapUser->get('fullname')) {
			$name = $ldapUser->get('fullname');
			if(isset($name[0])) if($name[0]!="") $joomlaUser->set('name', $name[0]);
		}
		if($this->parameters->get('sync_email') && $ldapUser->get('email')) {
			$email = $ldapUser->get('email');
			if(isset($email[0])) if($email[0]!="") $joomlaUser->set('email', $email[0]);
		}

		return true;
	}

	/**
	 * Get all Joomla groups from the database.
	 *
	 * @return  array  An array of all the Joomla groups
	 * @since   1.0
	 */
	public function getJoomlaGroups()
	{
		$joomlaGroups 	= array();
		$db = JFactory::getDbo();

		//build a basic return of joomla group id's
		$query = $db->getQuery(true);
		$query->select('usrgrp.id, usrgrp.title')
			->from('#__usergroups AS usrgrp')
			->order('usrgrp.id');


		$db->setQuery($query);
		$joomlaGroups = $db->loadAssocList('id');

		return $joomlaGroups;
	}

	/**
	 * Process the group mapping list by splitting each entry from the
	 * format DN:1;2;3;* into a JMapMyEntry which then is added to a
	 * returned array.
	 *
	 * @param  array  $joomlaGroups  An array of all the Joomla groups
	 *
	 * @return  array  An array of JMapMyEntry's
	 * @since   1.0
	 */
	public function processMapList($joomlaGroups)
	{
		$unmanaged			= $this->parameters->get('group_map_unmanage');
		$defaultManaged		= $this->parameters->get('group_map_removal');
		$mapList			= $this->parameters->get('group_map_list');
		$list				= array();

		foreach ($mapList as $entry) {
			$entry				= trim($entry);
			$colonPosition 		= strrpos($entry, ':');
			$entryDN			= substr($entry, 0, $colonPosition);
			$entryGroups		= explode(',',substr($entry, $colonPosition+1)); //put joomla group id's for the ldap group dn in array

			$groups 	= array();
			foreach($entryGroups as $group) {
				$group = trim($group);
				if(is_numeric($group) && isset($joomlaGroups[$group])) {
					$groups[] = $group;
				}
			}

			if(count($groups)>0 && strpos($entryDN, '=')>0) {
				$this->addManagedGroups($groups, $joomlaGroups); //if there isn't one valid group then there will never ever be any managed groups
				$newEntry = new JMapMyEntry($entryDN, $groups);
				if($newEntry->valid) {
					$list[] = $newEntry;
				}
			}

		}
		return $list;
	}

	/**
	 * Add a managed group to the managed class variable if the
	 * 'default all' option is not enabled. If the 'default all'
	 * option is enabled then add all the joomla groups to the
	 * variable (checks are done to ensure this is only done once).
	 *
	 * @param  array  $groups        An array of joomla group IDs to be managed
	 * @param  array  $joomlaGroups  An array of all the Joomla groups
	 *
	 * @return  void
	 * @since   1.0
	 */
	public function addManagedGroups($groups, $joomlaGroups)
	{
		$managed		= $this->parameters->get('group_map_removal');
		$unmanaged		= $this->parameters->get('group_map_unmanage');

		if($managed=="yesdefault" && count($this->managed)==0) { //lets just add them all
			foreach($joomlaGroups as $group) {
				if(!in_array($group['id'], $unmanaged)) $this->managed[] = ($group['id']);
			}
		} elseif($managed=="yes") {
			foreach($groups as $group) {
				if(!in_array($group, $unmanaged) && !in_array($group, $this->managed)) {
					$this->managed[] = $group; //this is a managed group
				}
			}
		}
	}

	/**
	 * Get the Ldap user details using the JLDAP2 library.
	 *
	 * @param  JLDAP2  &$ldap      A active JLDAP2 object
	 * @param  string  $username   The Ldap username to get
	 *
	 * @return  mixed  A JMapMyEntry object on success, otherwise JException on error
	 * @since   1.0
	 */
	public function getLdapUser(&$ldap, $username)
	{
		$attributes = self::getAttributes($this->parameters);

		if($ldap instanceof JLDAP2) { //the new ldap library
			$dn = $ldap->getUserDN($username, null, false);

			if(JError::isError($dn)) return $dn;
			if(!$dn) return new JException(JText::_('LIB_JMAPMYLDAP_ERROR_USER_DN_FAIL'));

			$details = $ldap->getUserDetails($dn, $attributes);

			if(isset($details['dn']) && $details['dn']!="") {
				if(isset($details[$attributes['lookupKey']])) {
					$ldapUser = new JMapMyEntry($details['dn'], $details[$attributes['lookupKey']]);
				} else {
					$ldapUser = new JMapMyEntry($details['dn'], array());
				}
				return $ldapUser;
			}
		}

		return new JException(JText::_('LIB_JMAPMYLDAP_ERROR_LIB_UNEXPECT'));
	}

	/**
	 * Get the an array of the required attributes to be processed by
	 * the Ldap server.
	 *
	 * @param  JMapMyParameters  $parameters  A JMapMyParameters object from the user plugin
	 *
	 * @return  array  An array of the attributes required from the Ldap server
	 * @since   1.0
	 */
	public static function getAttributes($parameters)
	{
		$return = array('lookupType','lookupKey','lookupMember','recurseDepth','dnAttribute','extras');
		$return = array_fill_keys($return, null); //lets get our result ready

		if($parameters->get('group_map_enabled', 0)) {
			$return['lookupKey'] 	= $parameters->get('lookup_attribute', 'groupMembership');
			$return['lookupType'] 	= $parameters->get('lookup_type', 'forward');
			$return['lookupMember'] = $parameters->get('lookup_member', 'dn');
			if($parameters->get('recursive', 0)) {
				$return['recurseDepth'] = $parameters->get('recursion_depth', 0);
				$return['dnAttribute'] 	= $parameters->get('dn_attribute');
			}
		}

		return $return;
	}

	/**
	 * Get the authentication plugin parameters and a active JLDAP2 instance.
	 *
	 * @param  JRegistry  $authParams  (optional) Override JRegistry authentication plugin parameters
	 *
	 * @return  mixed  A JLDAP2 object on success, otherwise JException on error
	 * @since   1.0
	 */
	public function getActiveLdap($authParams = array())
	{
		$pluginName = $this->parameters->get('auth_plugin');

		if(is_null($pluginName) && !count($authParams)) {
			return new JException(JText::_('LIB_JMAPMYLDAP_ERROR_NO_AUTH_PLUGIN'));
		}

		if(!count($authParams))
			$authParams 	= $this->getAuthPluginParams($pluginName);


		return $this->initialiseLdap($authParams);

	}

	/**
	 * Initalise a LDAP2 instance.
	 *
	 * @param  JRegistry  $authParams  JRegistry authentication plugin parameters
	 *
	 * @return  mixed  A JLDAP2 object on success, otherwise JException on error
	 * @since   1.0
	 */
	public function initialiseLdap($authParams)
	{
		$ldap			= null;

		if(!$authParams || JError::isError($authParams)) {
			return $authParams;
		}

		$ldapClass = 'JLDAP2';

		if(!class_exists($ldapClass)) { //checks for the required library
			return JERROR::raiseWarning('SOME_ERROR_CODE', JText::sprintf('LIB_JMAPMYLDAP_ERROR_LIB_JLDAP', $ldapClass));
		}

		$ldap = new $ldapClass($authParams);

		if(!$ldap->connect()) {
			return new JException(JText::sprintf('LIB_JMAPMYLDAP_ERROR_LDAP_CONNECT', $ldap->getErrorMsg()));
		}

		return $ldap;
	}

	/**
	 * Save the attributes of the specified JUser to the database. This
	 * method has been adapted from the JUser::save() method to bypass
	 * ACL checks for super users.
	 *
	 * @param  JUser  &$instance  The JUser object to save
	 *
	 * @return  mixed  True on success, otherwise either false or Exception on error
	 * @since   1.0
	 */
	public static function saveUser(&$instance)
	{
		//we have to have a group if they're none
		$table			= $instance->getTable();
		$table->bind($instance->getProperties());

		// Check and store the object.
		if (!$table->check()) {
			return false;
		}

		$my = JFactory::getUser();

		//we aren't allowed to create new users return
		if(empty($instance->id)) {
			return true;
		}

		// Store the user data in the database
		if (!$table->store()) {
			throw new Exception($table->getError());
		}

		// Set the id for the JUser object in case we created a new user.
		if (empty($instance->id)) {
			$instance->id = $table->get('id');
		}

		if ($my->id == $table->id) {
			$registry = new JRegistry;
			$registry->loadString($table->params);
			$my->setParameters($registry);
		}

		return true;
	}

	/**
	 * Get the authentication plugin parameters.
	 *
	 * @param  string  $name  Authentication plugin name
	 *
	 * @return  mixed  JRegistry on success, otherwise JException on error
	 * @since   1.0
	 */
	public function getAuthPluginParams($name)
	{
		$pluginParams = new JRegistry;
		$authPlugin = JPluginHelper::getPlugin('authentication',$name);

		if(!count($authPlugin)) {
			return new JException(JText::_('LIB_JMAPMYLDAP_ERROR_AUTH_PLUGIN_PARAMETER'));
		}

		$pluginParams->loadJSON($authPlugin->params);

		return $pluginParams;
	}

	/**
	 * Get the Joomla groups to remove.
	 *
	 * @param  JUser  $JUser    The JUser to remove groups from
	 * @param  array  $mapList  An array of matching group mapping entries
	 *
	 * @return  array  An array of Joomla IDs to remove
	 * @since   1.0
	 */
	public function getGroupsToRemove( $JUser, $mapList )
	{
		$groupsToRemove = array();

		foreach($JUser->groups as $jUserGroup) {
			if(in_array($jUserGroup, $this->managed)) { //check its in our managed pool
				$this->groupsToRemoveHelper($mapList, $groupsToRemove, $jUserGroup);
			}
		}

		return $groupsToRemove;

	}

	/**
	 * Before adding to the remove list, check its not already on
	 * the remove list and that its not in the mapping list.
	 *
	 * @param  array    $mapList          An array of matching group mapping entries
	 * @param  array    &$groupsToRemove  Groups to remove (byref)
	 * @param  integer  $jUserGroup       The Joomla group ID up for trial to be removed
	 *
	 * @return  void
	 * @since   1.0
	 */
	protected function groupsToRemoveHelper($mapList, &$groupsToRemove, $jUserGroup)
	{
		if(in_array($jUserGroup, $groupsToRemove)) //check if we've already got this on our remove list
			return false;

		foreach($mapList as $item) {
			if(in_array($jUserGroup, $item->getGroups()))
				return false;
		}

		$groupsToRemove[] = $jUserGroup; //add it

	}

	/**
	 * Get the Joomla groups to add.
	 *
	 * @param  JUser  $JUser    The JUser to add groups to
	 * @param  array  $mapList  An array of matching group mapping entries
	 *
	 * @return  array  An array of Joomla IDs to add
	 * @since   1.0
	 */
	public function getGroupsToAdd( $JUser, $mapList )
	{
		$groupsToAdd = array();

		foreach($mapList as $item) {
			foreach($item->getGroups() as $group) {
				$this->groupsToAddHelper($JUser, $groupsToAdd, $group);
			}
		}

		return $groupsToAdd;
	}

	/**
	 * Before adding to the add list, check its not already on
	 * the add list and that the user doesn't already have it.
	 *
	 * @param  JUser    $JUser         The JUser to add groups to
	 * @param  array    &$groupsToAdd  Groups to add (byref)
	 * @param  integer  $paramGroup    The Joomla group ID up for trial to be added
	 *
	 * @return  void
	 * @since   1.0
	 */
	protected function groupsToAddHelper($JUser, &$groupsToAdd, $paramGroup)
	{
		if(in_array($paramGroup, $groupsToAdd)) //check if we've already got this on our add list
			return false;

		if(in_array($paramGroup, $JUser->groups)) //check if the user already has this
			return false;

		$groupsToAdd[] = $paramGroup; //add it
	}

	/**
	 * Add a group to a user.
	 *
	 * @param  JUser    $user      The JUser for the group addition
	 * @param  integer  $groupId   The Joomla group ID to add
	 *
	 * @return  mixed  JException on errror
	 * @since   1.0
	 */
	public static function addUserToGroup(&$user, $groupId)
	{
		// Add the user to the group if necessary.
		if (!in_array($groupId, $user->groups)) {
			// Get the title of the group.
			$db	= JFactory::getDbo();
			$query = $db->getQuery(true);

			$query->select('title')
				->from('#__usergroups')
				->where($query->qn('id') . '=' . $query->q((int) $groupId));

			$db->setQuery($query);

			$title = $db->loadResult();

			// Check for a database error.
			if ($db->getErrorNum()) {
				return new JException($db->getErrorMsg());
			}

			// If the group does not exist, return an exception.
			if (!$title) {
				return new JException(JText::_('JLIB_USER_EXCEPTION_ACCESS_USERGROUP_INVALID'));
			}

			// Add the group data to the user object.
			$user->groups[$title] = $groupId;
		}

	}

	/**
	 * Remove a group to a user.
	 *
	 * @param  JUser    $user      The JUser for the group removal
	 * @param  integer  $groupId   The Joomla group ID to remove
	 *
	 * @return  void
	 * @since   1.0
	 */
	public static function removeUserFromGroup(&$user, $groupId)
	{
		// Remove the user from the group if necessary.
		$key = array_search($groupId, $user->groups);
		if ($key !== false) {
			// Remove the user from the group.
			unset($user->groups[$key]);
		}

	}

	/**
	 * This method returns a user object. If options['autoregister'] is true,
	 * and if the user doesn't exist yet then it'll be created.
	 *
	 * Dear Joomla, can you please put this into a library for people to use.
	 *
	 * @param  array  $user     Holds the user data.
	 * @param  array  $options  Array holding options (remember, autoregister, group).
	 *
	 * @return  JUser  A JUser object containing the user
	 * @since   1.0
	 */
	public static function &getUser($user, $options = array())
	{
		$instance = JUser::getInstance();
		if($id = intval(JUserHelper::getUserId($user['username'])))  {
			$instance->load($id);
			return $instance;
		}

		jimport('joomla.application.component.helper');
		$config	= JComponentHelper::getParams('com_users');
		// Default to Registered.
		$defaultUserGroup = $config->get('new_usertype', 2);

		$acl = JFactory::getACL();

		$instance->set('id'			, 0);
		$instance->set('name'		, $user['fullname']);
		$instance->set('username'	, $user['username']);
		$instance->set('password_clear'	, $user['password_clear']);
		$instance->set('email'		, $user['email']);	// Result should contain an email (check)
		$instance->set('usertype'	, 'deprecated');
		$instance->set('groups'		, array($defaultUserGroup));

		//If autoregister is set let's register the user
		$autoregister = isset($options['autoregister']) ? $options['autoregister'] : true;

		if($autoregister) {
			if(!$instance->save()) {
				JERROR::raiseWarning('SOME_ERROR_CODE', $instance->getError());
				$instance->set('error' , 1);
			}
		} else {
			//we don't want to proceed if autoregister is not enabled
			JERROR::raiseWarning('SOME_ERROR_CODE', JTEXT::_('JGLOBAL_AUTH_NO_USER'));
			$instance->set('error' , 1);
		}

		return $instance;
	}
}
