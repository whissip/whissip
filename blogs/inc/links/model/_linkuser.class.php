<?php
/**
 * This file implements the LinkUser class, which is a wrapper class for User class to handle linked files.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author efy-asimo: Attila Simo.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * LinkUser Class
 *
 * @package evocore
 */
class LinkUser extends LinkOwner
{
	/**
	 * @var User
	 */
	var $User;

	/**
	 * Constructor
	 */
	function LinkUser( $User )
	{
		// call parent contsructor
		parent::LinkOwner( $User, 'user' );
		$this->User = & $this->link_Object;

		$this->_trans = array(
			'Link this image to your owner' => NT_( 'Link this image to the user.' ),
			'Link this file to your owner' => NT_( 'Link this file to the user.'),
			'View this owner...' => NT_( 'View this user...' ),
			'Edit this owner...' => NT_( 'Edit this user...' ),
			'Click on link %s icons below to link additional files to $ownerTitle$.' => NT_( 'Click on link %s icons below to link additional files to <strong>user</strong>.' ),
			'Link files to current owner' => NT_( 'Link files to current user' ),
			'Link has been deleted from $ownerTitle$.' => NT_( 'Link has been deleted from &laquo;user&raquo;.' ),
		);
	}

	/**
	 * Check current User users permission
	 *
	 * @param string permission level
	 * @param boolean true to assert if user dosn't have the required permission
	 */
	function check_perm( $permlevel, $assert = false )
	{
		global $current_User;
		return $current_User->ID == $this->User->ID || $current_User->check_perm( 'users', $permlevel, $assert );
	}

	/**
	 * Get all positions ( key, display ) pairs where link can be displayed
	 *
	 * @return array
	 */
	function get_positions()
	{
		return array();
	}

	/**
	 * Load all links of owner User if it was not loaded yet
	 */
	function load_Links()
	{
		if( is_null( $this->Links ) )
		{ // Links have not been loaded yet:
			$LinkCache = & get_LinkCache();
			$this->Links = $LinkCache->get_by_user_ID( $this->User->ID );
		}
	}

	/**
	 * Add new link to owner User
	 *
	 * @param integer file ID
	 * @param integer link position ( 'teaser', 'aftermore' )
	 * @param int order of the link
	 */
	function add_link( $file_ID, $position, $order = 1 )
	{
		global $current_User;

		$edited_Link = new Link();
		$edited_Link->set( 'usr_ID', $this->User->ID );
		$edited_Link->set( 'file_ID', $file_ID );
		$edited_Link->set( 'position', $position );
		$edited_Link->set( 'order', $order );

		if( empty( $current_User ) )
		{ // Current User not is set because probably we are creating links from upgrade script. Set the owner as creator and last editor.
			$edited_Link->set( 'creator_user_ID', $this->User->ID );
			$edited_Link->set( 'lastedit_user_ID', $this->User->ID );
		}
		$edited_Link->dbinsert();
	}

	/**
	 * Set Blog
	 */
	function load_Blog()
	{
		// User has no blog
	}

	/**
	 * Get where condition for select query to get User links
	 */
	function get_where_condition() {
		return 'link_usr_ID = '.$this->User->ID;
	}

	/**
	 * Get User parameter
	 *
	 * @param string parameter name to get
	 */
	function get( $parname )
	{
		switch( $parname )
		{
			case 'name':
				return 'user';
			case 'title':
				return $this->User->login;
		}
		return parent::get( $parname );
	}

	/**
	 * Get User edit url
	 */
	function get_edit_url()
	{
		return '?ctrl=user&amp;user_tab=avatar&amp;user_ID='.$this->User->ID;
	}

	/**
	 * Get User view url
	 */
	function get_view_url()
	{
		return '?ctrl=user&amp;user_tab=profile&amp;user_ID='.$this->User->ID;
	}
}

?>