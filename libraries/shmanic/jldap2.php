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

/**
 * An LDAP authentication and modification class for all LDAP operations.
 * Forked from the inbuilt Joomla LDAP for enhanced search and increased
 * functionality with no backward compatibility included.
 *
 * @package		Shmanic
 * @subpackage	Ldap
 * @since		1.0
 */
class JLDAP2 extends JObject
{
	/**
	 * Use LDAP version 3
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	public $use_ldapV3 = null;

	/**
	 * Negotiate TLS (encrypted communications)
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	public $negotiate_tls = null;

	/**
	 * No referrals (server transfers)
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	public $no_referrals = null;

	/**
	 * Hostname of LDAP server (multiple values supported; see PHP documentation)
	 *
	 * @var    string
	 * @since  1.0
	 */
	public $host = null;

	/**
	 * Port of LDAP server
	 *
	 * @var    integer
	 * @since  1.0
	 */
	public $port = null;

	/**
	 * If true then use search to find user
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	public $use_search = false;

	/**
	 * Connect username (used for SSO and search)
	 *
	 * @var    string
	 * @since  1.0
	 */
	public $connect_username = null;

	/**
	 * Connect password (used for SSO and search)
	 *
	 * @var    string
	 * @since  1.0
	 */
	public $connect_password = null;

	/**
	 * Base DN to use for searching (e.g. dc=acme,dc=local / o=company)
	 *
	 * @var    string
	 * @since  1.0
	 */
	public $base_dn = null;

	/**
	 * User DN or Filter Query (e.g. (sAMAccountName=[username]) / cn=[username],dc=acme,dc=local)
	 *
	 * @var    string
	 * @since  1.0
	 */
	public $user_qry = null;

	/**
	 * Fullname attribute (e.g. fullname / name)
	 *
	 * @var    string
	 * @since  1.0
	 */
	public $ldap_fullname = null;

	/**
	 * Email attribute (e.g. mail)
	 *
	 * @var    string
	 * @since  1.0
	 */
	public $ldap_email = null;

	/**
	 * UID attribute (e.g. uid / sAMAccountName)
	 *
	 * @var    string
	 * @since  1.0
	 */
	public $ldap_uid = null;

	/**
	 * LDAP DS handler
	 *
	 * @var    ldap object
	 * @since  1.0
	 */
	protected $ds = null;

	/**
	 * Class constructor.
	 *
	 * @param   JRegistry  &$parameters  JRegistry parameters for use in this library. This
	 *                       can normally be found from loading in the authentication plugin's parameters
	 *
	 * @since   1.0
	 */
	function __construct(&$parameters)
	{
		$lang = JFactory::getLanguage();
		$lang->load('lib_jldap2', JPATH_SITE); //for errors

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
	 * Attempt connection to an LDAP server and returns result.
	 *
	 * @return  Mixed  True on successful connection, or JException on error
	 * @since   1.0
	 */
	public function connect()
	{

		if (!$this->host) {
			return new JException(JText::_('LIB_JLDAP2_ERROR_NO_HOST_PARAM'));
		}

		/* in most cases, even if we cannot connect, we won't
		 * be able to find out until we have done our first
		 * bind! Annoying as.
		 */
		$this->ds = @ ldap_connect($this->host, $this->port);
		if (!$this->ds) {
			return new JException(JText::sprintf('LIB_JLDAP2_ERROR_FAILED_CONNECT', $this->getErrorMsg()));
		}

		if ($this->use_ldapV3) {
			if (!@ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, 3)) {
				return new JException(JText::_('LIB_JLDAP2_ERROR_V3_FAIL'));
			}
		}

		if (!@ldap_set_option($this->ds, LDAP_OPT_REFERRALS, intval($this->no_referrals))) {
			return new JException(JText::_('LIB_JLDAP2_ERROR_REFERRALS_FAIL'));
		}

		if ($this->negotiate_tls) {
			if (!@ldap_start_tls($this->ds)) {
				return new JException(JText::_('LIB_JLDAP2_ERROR_TLS_START_FAIL'));
			}
		}

		return true;
	}

	/**
	 * Compare an entry and return the result
	 *
	 * @param   string  $dn         The DN which contains the attribute you want to compare
	 * @param   string  $attribute  The attribute whose value you want to compare
	 * @param   string  $value      The value you want to check against the LDAP attribute
	 *
	 * @return  mixed   Result of comparison (true, false, -1 on error)
	 * @since   1.0
	 */
	public function compare($dn, $attribute, $value)
	{
		return @ldap_compare($this->ds, $dn, $attribute, $value);
	}

	/**
	 * Close the LDAP connection
	 *
	 * @return  void
	 * @since   1.0
	 */
	public function close()
	{
		@ldap_close($this->ds);
	}

	/**
	 * Search directory and subtrees using a dn base and a filter then returns
	 * the attributes in an array.
	 *
	 * @param  string  $dn          A base DN (if null, defaults to class base_dn value)
	 * @param  string  $filter      LDAP filter to restirct results (if null, defaults to (objectclass=*))
	 * @param  array   $attributes  Array of attributes to return (if null, returns all)
	 *
	 * @return  array  Array of attributes and corresponding values
	 * @since   1.0
	 */
	public function search($dn=null, $filter=null, $attributes=array()) {
		//will search the directory from the dn including all subtrees
		if(is_null($dn)) $dn = $this->base_dn;
		if(is_null($filter)) $filter = '(objectclass=*)'; //we have to use a filter

		$result = @ldap_search($this->ds, $dn, $filter, $attributes);

		if($result) return $this->getEntries($result);
	}

	/**
	 * Read directory using given dn and filter then returns the attributes in an array.
	 *
	 * @param  string  $dn          DN of object to read (if null, defaults to class base_dn value)
	 * @param  string  $filter      LDAP filter to restirct results (if null, defaults to (objectclass=*))
	 * @param  array   $attributes  Array of attributes to return (if null, returns all)
	 *
	 * @return  array  Array of attributes and corresponding values
	 * @since   1.0
	 */
	public function read($dn=null, $filter=null, $attributes=array())
	{
		//will search the directory from the dn NOT including subtrees
		//should be used when we know the dn - less of an overhead than search
		if(is_null($dn)) $dn = $this->base_dn;
		if(is_null($filter)) $filter = '(objectclass=*)'; //we have to use a filter

		$result = @ldap_read($this->ds, $dn, $filter, $attributes);

		if($result) return $this->getEntries($result);
	}

	/**
	 * Process result object (usually from a search or read) then return the
	 * result entries.
	 *
	 * @param  Result  $result  The result object from a returned search or read
	 *
	 * @return  array  An array of entries
	 * @since   1.0
	 */
	public function getEntries($result)
	{
		//get the entries from the result
		//we are not going to use ldap_get_entries as its got a limit of 1000
		$entries = array();

		for($entry=@ldap_first_entry($this->ds, $result); $entry!=false; $entry=@ldap_next_entry($this->ds, $entry)) {
			$entries[] = array(); //new entry, new array

			$attributes = @ldap_get_attributes($this->ds, $entry);

			foreach($attributes as $name=>$value) {
				if(is_array($value) && $value['count']>0) {
					unset($value['count']); //we do not want the count really
					$entries[count($entries)-1][$name] = $value;
				}
			}

			$entries[count($entries)-1]['dn'] = @ldap_get_dn($this->ds, $entry);
		}

		return $entries;

	}

	/**
	 * Binds to the LDAP directory and returns the operation result.
	 *
	 * @param  string  $username  Bind username (anonymous bind attempted if left blank)
	 * @param  string  $password  Bind password
	 *
	 * @return  boolean  Result of bind operation
	 * @since   1.0
	 */
	public function bind($username = null, $password = null)
	{
		return @ldap_bind($this->ds, $username, $password);
	}

	/**
	 * Returns the PHP LDAP error message.
	 *
	 * @return  string  Error message (if no error then "success" is returned)
	 * @since   1.0
	 */
	public function getErrorMsg()
	{
		return @ldap_error($this->ds);
	}

	/**
	 * Modifies an LDAP entry's attributes.
	 *
	 * @param  string  $dn          The dn which contains the attributes to modify
	 * @param  array   $attributes  An array of attribute values to modify
	 *
	 * @return  boolean  Result of modification operation
	 * @since   2.0
	 */
	public function modify($dn, $attributes)
	{
		return @ldap_modify($this->ds, $dn, $attributes);
	}

	/**
	 * Add one or more attributes to a already existing specified dn.
	 *
	 * @param  string  $dn          The dn which to add the attributes
	 * @param  array   $attributes  An array of attributes to add
	 *
	 * @return  boolean  Result of add operation
	 * @since   2.0
	 */
	public function addAttributes($dn, $attributes)
	{
		return @ldap_mod_add($this->ds, $dn, $attributes);
	}

	/**
	 * Deletes one or more attributes from a specified dn.
	 *
	 * @param   string  $dn          The dn which contains the attributes to remove
	 * @param   array   $attributes  An array of attributes to remove
	 *
	 * @return  boolean  Result of deletion operation
	 * @since   2.0
	 */
	public function deleteAttributes($dn, $attributes)
	{
		return @ldap_mod_del($this->ds, $dn, $attributes);
	}

	/**
	 * Replaces one or more attributes from a specified dn.
	 *
	 * @param  string  $dn          The dn which contains the attributes to replace
	 * @param  array   $attributes  An array of attribute values to replace
	 *
	 * @return  boolean  Result of replacement operation
	 * @since   2.0
	 */
	public function replaceAttributes($dn, $attributes)
	{
		return @ldap_mod_replace($this->ds, $dn, $attributes);
	}

	/**
	 * Add a new entry in the LDAP directory.
	 *
	 * @param  string  $dn          The dn where to put the object
	 * @param  array   $attributes  An array of arrays describing the object to add
	 *
	 * @return  boolean  Result of add operation
	 * @since   2.0
	 */
	public function add($dn, $attributes)
	{
		return @ldap_add($this->ds, $dn, $attributes);
	}

	/**
	 * Delete a entry from the LDAP directory.
	 *
	 * @param  string  $dn  The dn of the object to delete
	 *
	 * @return  boolean  Result of deletion operation
	 * @since   2.0
	 */
	public function delete($dn)
	{
		return @ldap_delete($this->ds, $dn);
	}

	/**
	 * Rename the entry
	 *
	 * @param   string   $dn            The DN of the entry at the moment
	 * @param   string   $newRdn        The RDN of the new entry (e.g. cn=newvalue)
	 * @param   string   $newParent     The full DN of the parent (null by default)
	 * @param   boolean  $deleteOldRdn  Delete the old values (true by default)
	 *
	 * @return  boolean  Result of rename operation
	 * @since   2.0
	 */
	public function rename($dn, $newRdn, $newParent = null, $deleteOldRdn = true)
	{
		return @ldap_rename($this->ds, $dn, $newRdn, $newParent, $deleteOldRdn);
	}

	/**
	 * Get a user's dn with optional bind authentication.
	 *
	 * @param  string   $username      Authenticating username
	 * @param  string   $password      Authenticating password
	 * @param  boolean  $authenticate  Attempt to authenticate the user (i.e.
	 *                   bind the user with the password supplied)
	 *
	 * @return  mixed  On success shall return a string containing users DN, otherwise
	 *                  returns a JException to indicate an error.
	 * @since   1.0
	 */
	public function getUserDN($username = null, $password = null, $authenticate = false)
	{

		if(!$this->user_qry) { //no query specified (filter or dn)
			return new JException(JText::_('LIB_JLDAP2_ERROR_NO_DN_FILTER_PARAM'));
		}

		$DNs = $this->use_search ? $this->getUserDnBySearch($username) : $this->getUserDnDirectly($username);

		if($DNs instanceof Exception) return $DNs; //this is an error not an array

		if(!count($DNs)) { //the username is wrong
			return new JException(JText::sprintf('LIB_JLDAP2_ERROR_USER_DN_FAIL', $this->getErrorMsg(), $username));
		}

		if($authenticate) {
			/* we want to check the password against the dn and if
			 * it succeeds, must be the correct user's dn so return it.
			 */
			foreach($DNs as $dn) {
				//returns the first successfully authenticating dn
				if($this->bind($dn, $password)) return $dn;
			}

			if(count($DNs) && $this->use_search) { //this is a password issue, not a parameter
				return false;
			} elseif(count($DNs)) { //this is either a credentials or user dn parameter issue
				return new JException(JText::sprintf('LIB_JLDAP2_ERROR_USER_DN_FAIL_PASS', $this->getErrorMsg(), $username));
			}

		} else {
			/* if a search is used then we already know if the dn exists
			 * so we will return the first dn in the array. Otherwise,
			 * attempt to bind with connect username and password
			 * then try to read each of the DNs in the directory
			 * until one succeeds. if the connect username and password
			 * fails then it will take the first DN in the array.
			 */
			if($this->use_search) return $DNs[0]; //we will assume this is the correct dn

			if($this->bind($this->connect_username, $this->connect_password)) {
				//first DN to exist will return
				foreach($DNs as $dn) {
					if(count($this->read($dn, null, array('dn')))) return $dn;
				}
			} else {
				//we can't check the directory so we have to assume this is correct
				return $DNs[0];
			}
		}

		return new JException(JText::sprintf('LIB_JLDAP2_ERROR_USER_DN_FAIL', $this->getErrorMsg(), $username));

	}

	/**
	 * Get a user's dn by attempting to search for it in the directory.
	 *
	 * @param  string  $username  Authenticating username
	 *
	 * @return  mixed  On success shall return an array containing DNs, otherwise
	 *                  returns a JException to indicate an error.
	 * @since   1.0
	 */
	public function getUserDnBySearch($username)
	{
		//this method uses the query as a filter to find where the user is located in the directory
		$return 	= array();
		$username 	= JLDAPHelper::escape($username); //fixes special usernames and provides protection against filter ldap injections
		$search 	= str_replace('[username]', $username, $this->user_qry);

		/*
		 * A very basic check for a LDAP filter - this doesn't check to
		 * ensure a filter is valid, just used to make sure that a dn
		 * hasn't been entered.
		 */
		if(!preg_match('/\((.)*=(.)*\)/',$search)) {
			return new JException(JText::sprintf('LIB_JLDAP2_ERROR_VALIDATION_FILTER'));
		}

		// search requires a base dn
		if(is_null($this->base_dn)) {
			return new JException(JText::sprintf('LIB_JLDAP2_ERROR_NO_BASE_DN'));
		}

		if($this->bind($this->connect_username, $this->connect_password)) {
			$result = new JLDAPResult($this->search(null, $search, array($this->ldap_uid)));

			for($count=0; $count!=$result->countEntries(); $count++) {
				$return[] = $result->getValue($count,'dn',0);
			}

		} else {
			return new JException(JText::sprintf('LIB_JLDAP2_ERROR_BIND_SEARCH_USER', $this->getErrorMsg()));
		}

		return $return;
	}

	/**
	 * Get a user's dn by attempting to replace the username keyword
	 * in the query, then using the result as the user dn.
	 *
	 * @param  string  $username  Authenticating username
	 *
	 * @return  mixed  On success shall return an array containing DNs, otherwise
	 *                  returns a JException to indicate an error.
	 * @since   1.0
	 */
	public function getUserDnDirectly($username)
	{
		//this method uses the query directly to bind as a user (the query must be a dn)
		$return 	= array();
		$username 	= JLDAPHelper::escape($username, true); //fixes special usernames and provides protection against dn ldap injections
		$search 	= str_replace('[username]', $username, $this->user_qry);
		$DNs 		= explode(';', $search);

		/*
		 * Checks that we have a DN - an invalid DN would cause
		 * an error here.
		 */
		if(!is_array(ldap_explode_dn($search,0))) {
			return new JException(JText::sprintf('LIB_JLDAP2_ERROR_VALIDATION_DN', $this->getErrorMsg()));
		}

		foreach($DNs as $dn) { //we need to find the correct dn from the semi-colon'd delimited list specified
			$dn = trim($dn);
			if($dn) {
				$return[] = $dn;
			}
		}

		return $return;
	}


	/**
	 * Get an array of user detail attributes for the user. By default this method will return the
	 * mapping uid, fullname and email fields. It will also try to get all the attributes required
	 * for group mapping, though this is optional.
	 *
	 * @param  string  $dn       The dn of the user
	 * @param  array   $options  An optional array of options (
	 *   string  $lookupType   => Type of group lookup to perform on user (i.e. forward or reverse)
	 *   string  $lookupKey    => Group attribute for group lookup (i.e. groupMembership or member)
	 *   string  $lookupMember => User attribute to use when searching for group membership (i.e. dn or uid)
	 *   string  $dnAttribute  => Set the recursive forward lookup attribute (i.e. distinguishedName)
	 *   string  $recurseDepth => Define the max depth for recursion (i.e. set to 0 for unlimited OR set to null for no recursion)
	 *   array   $extras       => Any extra user attributes to get
	 * )
	 * @return  mixed  On success shall return an array of attributes, otherwise
	 *                   returns a JException to indicate an error.
	 * @since   1.0
	 */
	public function getUserDetails($dn, $options = array())
	{

		$dnAttribute 	= isset($options['dnAttribute']) ? $options['dnAttribute'] : 'distinguishedName';
		$lookupMember 	= isset($options['lookupMember']) ? $options['lookupMember'] : 'dn';
		$attributes = array('dn', $lookupMember, $this->ldap_fullname, $this->ldap_uid);
		if(strpos($this->ldap_email, '[username]')===false) { //check for a 'fake' email
			$attributes[] = $this->ldap_email;
		}

		isset($options['extras']) ? $extras = $options['extras'] : $extras = array();
		$attributes		= (array_unique(array_merge($attributes, $extras)));
		$attributes 	= array_values($attributes);

		$return 	= $attributes;
		$lookupKey 	= null;
		$lookupType = null;

		//check if we need to do group mapping
		if(isset($options['lookupKey']) && isset($options['lookupType']) && !is_null($options['lookupKey']) && !is_null($options['lookupType'])) {
			$lookupType		= $options['lookupType'];
			$lookupKey 		= $options['lookupKey'];

			if($lookupType=='forward') {
				$attributes[] 	= $options['lookupKey'];
				$return 		= $attributes;

			} elseif($lookupType=='reverse') {
				$return[] 		= $options['lookupKey'];
			}
		}

		$return = array_fill_keys($return, null); //lets get our result ready

		//get our ldap user attributes and check we have a valid result
		$result	= new JLDAPResult($this->read($dn, null, $attributes));
		if(is_null($result->getValue(0,'dn',0))) {
			return new JException(JText::_('LIB_JLDAP2_ERROR_USER_ATTRIBUTE_FAIL'));
		}

		$groups = array();


		if(!is_null($lookupKey)) {
			//need to process first level user groups from the ldap result
			if($lookupType=='forward') {
				$groups		= $result->getAttribute(0,$lookupKey, true);
			} elseif($lookupType=='reverse') {

				$lookupValue = $result->getValue(0,$lookupMember,0); //we need to get the right attribute
				$search = $lookupKey . '=' . $lookupValue;
				$search = JLDAPHelper::buildFilter(array($search));
				$reverse = new JLDAPResult($this->search(null, $search, array('dn')));
				for($i=0;$i<$reverse->countEntries();$i++) {
					$groups[] = $reverse->getValue($i,'dn',0); //extract the group from the result
				}
			}

			//need to process the recursion using the first level user groups as a basis
			$depth = isset($options['recurseDepth']) ? $options['recurseDepth'] : null;
			if(!is_null($depth)) { // Lets do our recursion
				$outcome 	= array();

				if($lookupType == 'reverse') { //we need to override the lookup attribute for reverse recursion
					$this->getRecursiveGroups($groups, $depth, $outcome, 'dn', $lookupKey);
				} elseif($lookupType == 'forward') {
					$this->getRecursiveGroups($groups, $depth, $outcome, $lookupKey, $dnAttribute);
				}

				// we need to merge them back together without duplicates
				$groups = array_unique(array_merge($groups, $outcome));
			}

		}

		//lets store everything we've done
		foreach($return as $attribute=>$rubbish) {

			$return[$attribute] = $result->getAttribute(0, $attribute);

			if($attribute == $lookupKey) { //we want to override the group as we have this stored somewhere else
				$return[$attribute] = $groups;
			}
		}

		//if we used a fake email, then lets insert it
		if(strpos($this->ldap_email, '[username]')!==false) { //check for a 'fake' email
			$email = str_replace('[username]', $return[$this->ldap_uid][0], $this->ldap_email);
			$return[$this->ldap_email] = array($email);
		}

		return $return;

	}

	/**
	 * Get an array of all the nested groups through the use of group recursion.
	 * This is required usually only for Active Directory, however there could
	 * be other LDAP directories that cannot pick up nested groups.
	 *
	 * @param  array   $searchDNs       Initial user groups (or ones that have already been discovered)
	 * @param  string  $depth           How far to search down until it should give up (0 means unlimited)
	 * @param  array   &$result         Holds the result of every alliteration (this is a byreference)
	 * @param  string  $attribute       The LDAP attribute to store after each ldap search
	 * @param  string  $queryAttribute  The LDAP filter attribute to query
	 *
	 * @return  array  All user groups including the initial user groups
	 * @since   1.0
	 */
	public function getRecursiveGroups($searchDNs, $depth = 0, &$result, $attribute, $queryAttribute = null)
	{
		//recurse through the search dn
		$depth--;
		$search			= null;
		$next			= array();
		$filters		= array();

		if(!isset($searchDNs)) { return $result; }

		foreach($searchDNs as $dn)
				$filters[] = 	$queryAttribute . '=' . $dn;

		if(!count($filters)) { return $result; }

		$search = JLDAPHelper::buildFilter($filters, '|'); //build a filter using the OR operator
		$results = new JLDAPResult($this->search(null, $search, array($attribute)));

		$entryCount = $results->countEntries();
		for($i=0; $i<$entryCount; $i++) { //will process each record that the searched container found

			$dn = $results->getValue($i, 'dn', 0);

			if(!in_array($dn, $result)) { //has this container already been processed previously
				$result[] = $dn;
				$valueCount = $results->countValues($i, $attribute);
				for($j=0; $j<$valueCount; $j++) {
					$value = $results->getValue($i, $attribute, $j);
					$next[] = $value; //we want to process this object
				}
			}
		}

		/*
		 * Only start the recursion when we have something to process
		 * next otherwise we would loop forever.
		 */
		if(count($next) && $depth!=0) {
			$this->getRecursiveGroups($next, $depth, $result, $attribute, $queryAttribute);
		}

		return $result;
	}

}

/**
 * An LDAP helper class. All methods are static and don't require any
 * new instance of the class.
 *
 * @package		Shmanic
 * @subpackage	Ldap
 * @since		1.0
 */
class JLDAPHelper
{

	/**
	 * Escape characters based on the type of query (DN or Filter). This
	 * method follows the RFC2254 guidelines.
	 * Adapted from source: http://www.php.net/manual/en/function.ldap-search.php#90158
	 *
	 * @param  string   $inn   Input string to escape
	 * @param  boolean  $isDn  Set the type of query; true for DN; false for filter (default false)
	 *
	 * @return  string  An escaped string
	 * @since   1.0
	 */
	public static function escape($inn, $isDn = false)
	{
		$metaChars = $isDn ? array(',','=', '+', '<','>',';', '\\', '"', '#') :
			array('*', '(', ')', '\\', chr(0));

		$quotedMetaChars = array();
		foreach ($metaChars as $key => $value) {
			$quotedMetaChars[$key] = '\\'.str_pad(dechex(ord($value)), 2, '0');
		}

		return str_replace($metaChars, $quotedMetaChars, $inn);

	}

	/**
	 * Escape the filter characters and build the filter with brackets
	 * using the operator specified.
	 *
	 * @param  array   $filters   An array of inner filters (i.e. array(uid=shaun, cn=uk))
	 * @param  string  $operator  Set operator to carry out (null by default for no operator)
	 *
	 * @return  string  An escaped filter with filter operation
	 * @since   1.0
	 */
	public static function buildFilter($filters, $operator = null)
	{
		$return = null;

		if(!count($filters)) return $return;

		$string = null;
		foreach($filters as $filter) {
			$filter = JLDAPHelper::escape($filter);
			$string .= '(' . $filter . ')';
		}

		$return = is_null($operator) ? $string : '(' . $operator . $string . ')';

		return $return;
	}

	/**
	 * Converts a dot notation IP address to net address (e.g. for Netware).
	 * Taken from the inbuilt Joomla LDAP library.
	 *
	 * @param   string  $ip  An IP address to convert (e.g. xxx.xxx.xxx.xxx)
	 *
	 * @return  string  Net address
	 * @since   1.0
	 */
	public static function ipToNetAddress($ip)
	{
		$parts = explode('.', $ip);
		$address = '1#';

		foreach ($parts as $int) {
			$tmp = dechex($int);
			if (strlen($tmp) != 2) {
				$tmp = '0' . $tmp;
			}
			$address .= '\\' . $tmp;
		}
		return $address;
	}

}

/**
 * An LDAP result class to help get results from ldap searches. It
 * doesn't offer any extra functionality as such but does mean we
 * do not need to manage the results array.
 *
 * @package		Shmanic
 * @subpackage	Ldap
 * @since		1.0
 */
class JLDAPResult
{

	/**
	 * Holds the LDAP results array
	 *
	 * @var    array
	 * @since  1.0
	 */
	protected $_attributes = array();

	/**
	 * Class constructor.
	 *
	 * @param   array  $attributes  An array of attributes holding the LDAP results
	 *
	 * @since   1.0
	 */
	function __construct($attributes)
	{
		if(isset($attributes)) {
			$this->_attributes = $attributes;
		}
	}

	/**
	 * Return the entire results array. However, if array has no elements
	 * then return the same level of arrays if it did -
	 * array(array(array())).
	 *
	 * @return  array  Attributes holding the LDAP result
	 * @since   1.0
	 */
	public function getResults()
	{
		return count($this->_attributes) ? $this->_attributes : array(array(array()));
	}

	/**
	 * Return the number of entries in the results array.
	 *
	 * @return  integer  Number of entries in the result
	 * @since   1.0
	 */
	public function countEntries()
	{
		return isset($this->_attributes) ? count($this->_attributes) : 0;
	}

	/**
	 * Returns the results array at the entry. However, if array has no
	 * elements then return the same level of arrays if it did -
	 * array(array()).
	 *
	 * @param  integer  $entry  Entry number to return
	 *
	 * @return  array  Attributes for the specific entry
	 * @since   1.0
	 */
	public function getEntry($entry)
	{
		if($entry >= 0 && $this->countEntries() >= $entry+1 &&
			isset($this->_attributes[$entry]) && count($this->_attributes[$entry])) {
			return $this->_attributes[$entry];
		}

		return array(array());
	}

	/**
	 * Returns the number of attributes at the entry.
	 *
	 * @param  integer  $entry  Entry number to return
	 *
	 * @return  integer  Number of attributes at the entry
	 * @since   1.0
	 */
	public function countAttributes($entry)
	{
		if($entry >= 0 && $this->countEntries() >= $entry+1 && isset($this->_attributes[$entry])) {
			return count($this->_attributes[$entry]);
		}

		return 0;
	}

	/**
	 * Returns the values at the specified attribute. However, if array has no
	 * elements then return the same level of arrays if it did -
	 * array().
	 *
	 * @param  integer  $entry      Entry number to return
	 * @param  string   $attribute  Name of attribute to return within the entry
	 * @param  boolean  $wrap       Wrap the array as an array of a array if its not already (default false)
	 *
	 * @return  array  Array of entries for the specific attribute
	 * @since   1.0
	 */
	public function getAttribute($entry, $attribute, $wrap = false)
	{
		if($attribute != '' && $this->countAttributes($entry)) {
			if(isset($this->_attributes[$entry][$attribute])) {
				if(!is_array($this->_attributes[$entry][$attribute]) && $wrap)
					return array($this->_attributes[$entry][$attribute]);

				return $this->_attributes[$entry][$attribute];
			}
		}

		return array();
	}

	/**
	 * Returns the number of values at the specified attribute.
	 *
	 * @param  integer  $entry      Entry number to return
	 * @param  string   $attribute  Name of attribute to return within the entry
	 *
	 * @return  integer  Number of values at attribute
	 * @since   1.0
	 */
	public function countValues($entry, $attribute)
	{
		if($attribute != '' && $this->countAttributes($entry)) {
			if(isset($this->_attributes[$entry][$attribute])) {
				if(is_array($this->_attributes[$entry][$attribute])) {
					return count($this->_attributes[$entry][$attribute]);
				} else {
					return 1; //we have values that are not in an array!
				}
			}
		}

		return 0;
	}

	/**
	 * Returns the value at the specified attribute value.
	 *
	 * @param  integer  $entry      Entry number to return
	 * @param  string   $attribute  Name of attribute to return within the entry
	 * @param  integer  $value      Value number to return within the attribute
	 * @param  boolean  $wrap       Wrap the array as an array of a array if its not already (default false)
	 *
	 * @return  string  Value at the specified attribute value
	 * @since   1.0
	 */
	public function getValue($entry, $attribute, $value, $wrap = false)
	{
		//returns a specific value
		$values = $this->countValues($entry, $attribute);
		if($value >= 0 && $values >= $value+1) {
			if($values==1 && !is_array($this->_attributes[$entry][$attribute])) {
				if($wrap) return array($this->_attributes[$entry][$attribute]);
				else return $this->_attributes[$entry][$attribute];
			}

			if(isset($this->_attributes[$entry][$attribute][$value])) {
				return $this->_attributes[$entry][$attribute][$value];
			}
		}

		return null;
	}
}

