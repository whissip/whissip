<?php
/**
 * This file implements the msg_menu_link_Widget class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2014 by Francois Planque - {@link http://fplanque.com/}
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
 * @author efy-asimo: Attila Simo
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( 'widgets/model/_widget.class.php', 'ComponentWidget' );

global $msg_menu_link_widget_link_types;
$msg_menu_link_widget_link_types = array(
		'messages' => T_( 'Messages' ),
		'contacts' => T_( 'Contacts' ),
	);

/**
 * ComponentWidget Class
 *
 * A ComponentWidget is a displayable entity that can be placed into a Container on a web page.
 *
 * @package evocore
 */
class msg_menu_link_Widget extends ComponentWidget
{
	/**
	 * Constructor
	 */
	function msg_menu_link_Widget( $db_row = NULL )
	{
		// Call parent constructor:
		parent::ComponentWidget( $db_row, 'core', 'msg_menu_link' );
	}


	/**
	 * Get name of widget
	 */
	function get_name()
	{
		return T_('Messaging Menu link');
	}


	/**
	 * Get a very short desc. Used in the widget list.
	 */
	function get_short_desc()
	{
		global $msg_menu_link_widget_link_types;

		$this->load_param_array();

		if( !empty($this->param_array['link_type']) )
		{	// Messaging or Contacts
			return sprintf( T_( '%s link' ), $msg_menu_link_widget_link_types[$this->param_array['link_type']] );
		}

		return $this->get_name();
	}


	/**
	 * Get short description
	 */
	function get_desc()
	{
		return T_('Messages or Contacts menu entry/link');
	}


	/**
	 * Get definitions for editable params
	 *
	 * @see Plugin::GetDefaultSettings()
	 * @param local params like 'for_editing' => true
	 */
	function get_param_definitions( $params )
	{
		global $msg_menu_link_widget_link_types;

		$r = array_merge( array(
				'link_type' => array(
					'label' => T_( 'Link Type' ),
					'note' => T_('What do you want to link to?'),
					'type' => 'select',
					'options' => $msg_menu_link_widget_link_types,
					'defaultvalue' => 'messages',
					'onchange' => '
						var curr_link_type = this.value;
						var allow_blockcache = jQuery("[id$=\'_set_allow_blockcache\']");
						var show_badge = jQuery("[id$=\'_set_show_badge\']");
						var allow_blockcache_note = allow_blockcache.find(".notes");
						if( curr_link_type == "messages" )
						{
							allow_blockcache.removeAttr(\'checked\');
							allow_blockcache.attr( \'disabled\', \'disabled\' );
							allow_blockcache_note.html("'.T_('The current configuration prevents caching this widget in the block cache.').'");
							show_badge.removeAttr(\'disabled\');
							show_badge.attr( \'checked\', \'checked\' );
						}
						else
						{
							allow_blockcache.removeAttr(\'disabled\');
							show_badge.attr( \'disabled\', \'disabled\' );
							allow_blockcache.attr( \'checked\', \'checked\' );
							allow_blockcache_note.html("'.T_('Uncheck to prevent this widget from ever being cached in the block cache. (The whole page may still be cached.) This is only needed when a widget is poorly handling caching and cache keys.').'");
							show_badge.removeAttr(\'checked\');
						};'
				),
				'link_text' => array(
					'label' => T_( 'Link text' ),
					'note' => T_('Text to use for the link (leave empty for default).'),
					'type' => 'text',
					'size' => 20,
					'defaultvalue' => '',
				),
				'show_to' => array(
					'label' => T_( 'Show to' ),
					'note' => '',
					'type' => 'radio',
					'options' => array( array( 'any', T_( 'All users') ),
										array( 'loggedin', T_( 'Logged in users' ) ),
										array( 'perms', T_( 'Users with messaging permissions only' ) ) ),
					'defaultvalue' => 'perms',
				),
				'show_badge' => array(
					'label' => T_( 'Show Badge' ),
					'note' => T_( 'Show a badge with the count of unread messages.' ),
					'type' => 'checkbox',
					'defaultvalue' => true,
				),
			), parent::get_param_definitions( $params )	);

		// Do not modify anything during update because the editing form contains all of the required modifications
		if( !isset( $params['for_updating'] ) )
		{ // Not called from the update process
			// Turn off allow blockcache by default, because it is forbidden in case of messages
			// Note: we may call $this->get_param() only if this function was not called from there. This way we prevent infinite recursion/loop.
			$link_type = ( empty( $this->params ) || isset( $params['infinite_loop'] ) ) ? 'messages' : $this->get_param( 'link_type', true );
			if( $link_type == 'contacts' )
			{
				$r['show_badge']['defaultvalue'] = false;
				$r['show_badge']['disabled'] = 'disabled';
			}
			else
			{
				$r['allow_blockcache']['defaultvalue'] = false;
				$r['allow_blockcache']['disabled'] = 'disabled';
				$r['allow_blockcache']['note'] = T_('The current configuration prevents caching this widget in the block cache.');
			}
		}

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

		global $current_User, $unread_messages_count;
		global $disp;

		$this->init_display( $params );

		switch( $this->disp_params['show_to'] )
		{
			case 'any':
				break;
			case 'loggedin':
				if( !is_logged_in() )
				{
					return false;
				}
				break;
			case 'perms':
				if( !is_logged_in() || !$current_User->check_perm( 'perm_messaging', 'reply', false ) )
				{
					return false;
				}
				break; // display
			case 'default':
				debug_die( 'Invalid params!' );
		}

		// Default link class
		$link_class = $this->disp_params['link_default_class'];

		switch(	$this->disp_params[ 'link_type' ] )
		{
			case 'messages':
				$url = get_dispctrl_url( 'threads' );
				$text = T_( 'Messages' );
				// set allow blockcache to 0, this way make sure block cache is never allowed for messages
				$this->disp_params[ 'allow_blockcache' ] = 0;
				// Is this the current display?
				if( $disp == 'threads' )
				{	// The current page is currently displaying the messages:
					// Let's display it as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;

			case 'contacts':
				$url = get_dispctrl_url( 'contacts' );
				$text = T_( 'Contacts' );
				// set show badge to 0, this way make sure badge won't be displayed
				$this->disp_params[ 'show_badge' ] = 0;
				// Is this the current display?
				if( $disp == 'contacts' )
				{	// The current page is currently displaying the contacts:
					// Let's display it as selected
					$link_class = $this->disp_params['link_selected_class'];
				}
				break;
		}

		if( !empty( $this->disp_params[ 'link_text' ] ) )
		{
			$text = $this->disp_params[ 'link_text' ];
		}

		if( ( $this->disp_params[ 'show_badge' ] ) && ( $unread_messages_count > 0 ) )
		{
			$badge = ' <span class="badge">'.$unread_messages_count.'</span>';
			if( isset( $this->BlockCache ) )
			{ // Do not cache if bage is displayed because the number of unread messages are always changing
				$this->BlockCache->abort_collect();
			}
		}
		else
		{
			$badge = '';
		}

		echo $this->disp_params['block_start'];
		echo $this->disp_params['list_start'];

		echo $this->disp_params['item_start'];
		echo '<a href="'.$url.'" class="'.$link_class.'">'.$text.$badge.'</a>';
		echo $this->disp_params['item_end'];

		echo $this->disp_params['list_end'];
		echo $this->disp_params['block_end'];

		return true;
	}
}

?>