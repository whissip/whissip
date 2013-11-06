<?php
/**
 * This file implements the menu_link_Widget class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
 *
 * {@internal License choice
 * - If you have received this file as part of a package, please find the license.txt file in
 *   the same folder or the closest folder above for complete license terms.
 * - If you have received this file individually (e-g: from http://evocms.cvs.sourceforge.net/)
 *   then you must choose one of the following licenses before using the file:
 *   - GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *   - Mozilla Public License 1.1 (MPL) - http://www.opensource.org/licenses/mozilla1.1.php
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( 'widgets/model/_widget.class.php', 'ComponentWidget' );

global $menu_link_widget_link_types;
$menu_link_widget_link_types = array(
		'home' => T_('Blog home'),
		'search' => T_('Search page'),
		'arcdir' => T_('Archive directory'),
		'catdir' => T_('Category directory'),
		'postidx' => T_('Post index'),
		'mediaidx' => T_('Photo index'),
		'sitemap' => T_('Site Map'),
		'latestcomments' => T_('Latest comments'),

		'ownercontact' => T_('Blog owner contact form'),
		'owneruserinfo' => T_('Blog owner profile'),

		'users' => T_('User directory'),

		'login' => T_('Log in form'),
		'register' => T_('Registration form'),
		'myprofile' => T_('My profile'),
		'profile' => T_('Edit profile'),
		'avatar' => T_('Edit profile picture'),

		'item' => T_('Any item (post, page, etc...)'),
		'url' => T_('Any URL'),

		'postnew' => T_('Write a new post'),
	);

/**
 * ComponentWidget Class
 *
 * A ComponentWidget is a displayable entity that can be placed into a Container on a web page.
 *
 * @todo dh> this needs to implement BlockCaching cache_keys properly:
 *            - "login": depends on $currentUser being set or not
 *            ...
 *
 * @package evocore
 */
class menu_link_Widget extends ComponentWidget
{
	/**
	 * Constructor
	 */
	function menu_link_Widget( $db_row = NULL )
	{
		// Call parent constructor:
		parent::ComponentWidget( $db_row, 'core', 'menu_link' );
	}


	/**
	 * Get name of widget
	 */
	function get_name()
	{
		return T_('Menu link');
	}


	/**
	 * Get a very short desc. Used in the widget list.
	 */
	function get_short_desc()
	{
		global $menu_link_widget_link_types;

		$this->load_param_array();


		if( !empty($this->param_array['link_text']) )
		{	// We have a custom link text:
			return $this->param_array['link_text'];
		}

		if( !empty($this->param_array['link_type']) )
		{	// TRANS: %s is the link type, e. g. "Blog home" or "Log in form"
			return sprintf( T_( '%s link' ), $menu_link_widget_link_types[$this->param_array['link_type']] );
		}

		return $this->get_name();
	}


	/**
	 * Get short description
	 */
	function get_desc()
	{
		return T_('Display a configurable menu entry/link');
	}


	/**
	 * Get definitions for editable params
	 *
	 * @see Plugin::GetDefaultSettings()
	 * @param local params like 'for_editing' => true
	 */
	function get_param_definitions( $params )
	{
		global $menu_link_widget_link_types;

		$r = array_merge( array(
				'link_type' => array(
					'label' => T_( 'Link Type' ),
					'note' => T_('What do you want to link to?'),
					'type' => 'select',
					'options' => $menu_link_widget_link_types,
					'defaultvalue' => 'home',
				),
				'link_text' => array(
					'label' => T_('Link text'),
					'note' => T_( 'Text to use for the link (leave empty for default).' ),
					'type' => 'text',
					'size' => 20,
					'defaultvalue' => '',
				),
				// fp> TODO: ideally we would have a link icon to go click on the destination...
				'item_ID' => array(
					'label' => T_('Item ID'),
					'note' => T_( 'ID of post, page, etc. for "Item" type links.' ),
					'type' => 'text',
					'size' => 5,
					'defaultvalue' => '',
				),
				'link_href' => array(
					'label' => T_('URL'),
					'note' => T_( 'Destination URL for "URL" type links.' ),
					'type' => 'text',
					'size' => 30,
					'defaultvalue' => '',
				),
			), parent::get_param_definitions( $params )	);

		return $r;
	}


	/**
	 * Display the widget!
	 *
	 * @param array MUST contain at least the basic display params
	 */
	function display( $params )
	{
		/**
		* @var Blog
		*/
		global $Blog;
		global $disp;

		$this->init_display( $params );

		// Default link class
		$link_class = $this->disp_params['link_default_class'];

		switch(	$this->disp_params['link_type'] )
		{
			case 'search':
				$url = $Blog->get('searchurl');
				$text = T_('Search');
				// Is this the current display?
				if( $disp == 'search' )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'arcdir':
				$url = $Blog->get('arcdirurl');
				$text = T_('Archives');
				if( $disp == 'arcdir' )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'catdir':
				$url = $Blog->get('catdirurl');
				$text = T_('Categories');
				if( $disp == 'catdir' )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'postidx':
				$url = $Blog->get('postidxurl');
				$text = T_('Post index');
				if( $disp == 'postidx' )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'mediaidx':
				$url = $Blog->get('mediaidxurl');
				$text = T_('Photo index');
				if( $disp == 'mediaidx' )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'sitemap':
				$url = $Blog->get('sitemapurl');
				$text = T_('Site map');
				if( $disp == 'sitemap' )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'latestcomments':
				if( !$Blog->get_setting( 'comments_latest' ) )
				{ // This page is disabled
					return false;
				}
				$url = $Blog->get('lastcommentsurl');
				$text = T_('Latest comments');
				if( $disp == 'comments' )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'owneruserinfo':
				$url = url_add_param( $Blog->get('userurl'), 'user_ID='.$Blog->owner_user_ID );
				$text = T_('Owner details');
				// Is this the current display?
				global $User;
				if( $disp == 'user' && !empty($User) && $User->ID == $Blog->owner_user_ID )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'ownercontact':
				if( ! $url = $Blog->get_contact_url( true ) )
				{ // user does not allow contact form:
					return;
				}
				$text = T_('Contact');
				// Is this the current display?
				if( $disp == 'msgform' )
				{	// Let's display the link as selected
					// fp> I think it's interesting to select this link , even if the recipient ID is different from the owner
					// odds are there is no other link to highlight in this case
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'login':
				if( is_logged_in() ) return false;
				$url = get_login_url( 'menu link' );
				if( isset($this->BlockCache) )
				{	// Do NOT cache because some of these links are using a redirect_to param, which makes it page dependent.
					// so this will be cached by the PageCache; there is no added benefit to cache it in the BlockCache
					// (which could have been shared between several pages):
					$this->BlockCache->abort_collect();
				}
				$text = T_('Log in');
				// Is this the current display?
				if( $disp == 'login' )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'register':
				if( ! $url = get_user_register_url( NULL, 'menu link' ) )
				{
					return false;
				}
				if( isset($this->BlockCache) )
				{	// Do NOT cache because some of these links are using a redirect_to param, which makes it page dependent.
					// Note: also beware of the source param.
					// so this will be cached by the PageCache; there is no added benefit to cache it in the BlockCache
					// (which could have been shared between several pages):
					$this->BlockCache->abort_collect();
				}
				$text = T_('Register');
				// Is this the current display?
				if( $disp == 'register' )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'profile':
				if( ! is_logged_in() ) return false;
				$url = get_user_profile_url();
				$text = T_('Edit profile');
				// Is this the current display?  (Edit my Profile)
				if( in_array( $disp, array( 'profile', 'avatar', 'pwdchange', 'userprefs', 'subs' ) ) )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'avatar':
				if( ! is_logged_in() ) return false;
				$url = get_user_avatar_url();
				$text = T_('Profile picture');
				// Note: we never highlight this, it will always highlight 'profile' instead
				break;

			case 'users':
				global $Settings;
				if( ! is_logged_in() && ! $Settings->get( 'allow_anonymous_user_list' ) )
				{	// Don't allow anonymous users to see users list
					return false;
				}
				$url = $Blog->get( 'usersurl' );
				$text = T_('User directory');
				// Is this the current display?
				// Note: If $user_ID is not set, it means we are viewing "My Profile" instead
				global $user_ID;
				if( $disp == 'users' || ($disp == 'user' && !empty($user_ID)) )
				{	// Let's display the link as selected
					// Note: we also highlight this for any user profile that is displayed
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'item':
				$ItemCache = & get_ItemCache();
				/**
				* @var Item
				*/
				$item_ID = (integer)($this->disp_params['item_ID']);
				$disp_Item = & $ItemCache->get_by_ID( $item_ID, false, false );
				if( empty($disp_Item) )
				{	// Item not found
					return false;
				}
				$url = $disp_Item->get_permanent_url();
				$text = $disp_Item->title;
				// Is this the current item?
				global $Item;
				if( !empty($Item) && $disp_Item->ID == $Item->ID )
				{	// The current page is currently displaying the Item this link is pointing to
					// Let's display it as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'url':
				$url = $this->disp_params['link_href'];
				$text = '[URL]';	// should normally be overriden below...
				// Note: we never highlight this link
				break;

			case 'postnew':
				if( ! check_item_perm_create() )
				{	// Don't allow users to create a new post
					return false;
				}
				$url = url_add_param( $Blog->get( 'url' ), 'disp=edit' );
				$text = T_('Write a new post');
				// Is this the current display?
				if( $disp == 'edit' )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'myprofile':
				if( ! is_logged_in() )
				{	// Don't show this link for not logged in users
					return false;
				}
				$url = url_add_param( $Blog->get( 'url' ), 'disp=user' );
				$text = T_('My profile');
				// Is this the current display?  (Edit my Profile)
				global $user_ID, $current_User;
				// If $user_ID is not set, it means we will fall back to the current user, so it's ok
				// If $user_ID is set, it means we are browsing the directory instead
				if( $disp == 'user' && empty($user_ID) )
				{	// Let's display the link as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'home':
			default:
				$url = $Blog->get('url');
				$text = T_('Home');
		}

		// Override default link text?
		if( !empty($this->param_array['link_text']) )
		{	// We have a custom link text:
			$text = $this->param_array['link_text'];
		}

		echo $this->disp_params['block_start'];
		echo $this->disp_params['list_start'];

		echo $this->disp_params['item_start'];
		echo '<a href="'.$url.'" class="'.$link_class.'">'.$text.'</a>';
		echo $this->disp_params['item_end'];

		echo $this->disp_params['list_end'];
		echo $this->disp_params['block_end'];

		return true;
	}


	/**
	 * Maybe be overriden by some widgets, depending on what THEY depend on..
	 *
	 * @return array of keys this widget depends on
	 */
	function get_cache_keys()
	{
		global $Blog, $current_User;

		$keys = array(
				'wi_ID'   => $this->ID,					// Have the widget settings changed ?
				'set_coll_ID' => $Blog->ID			// Have the settings of the blog changed ? (ex: new owner, new skin)
			);

		switch( $this->disp_params['link_type'] )
		{
			case 'login':  		/* This one will probably abort caching by itself anyways */
			case 'register':	/* This one will probably abort caching by itself anyways */
			case 'profile':		// This can be cached
			case 'avatar':
				// This link also depends on whether or not someone is logged in:
				$keys['loggedin'] = (is_logged_in() ? 1 : 0);

		}

		return $keys;
	}
}


/*
 * $Log$
 * Revision 1.30  2013/11/06 09:09:09  efy-asimo
 * Update to version 5.0.2-alpha-5
 *
 */
?>