<?php
/**
 * This file implements the Admin UI class.
 * Alternate admin skins should derive from this class.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}.
 * Parts of this file are copyright (c)2005 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * {@internal Open Source relicensing agreement:
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package admin-skin
 * @subpackage evo
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * Includes
 */
require_once dirname(__FILE__).'/../_adminUI_general.class.php';


/**
 * We define a special template for the main menu.
 *
 * @package admin-skin
 * @subpackage evo
 */
class AdminUI extends AdminUI_general
{
	/**
	 * This function should init the templates - like adding Javascript through the {@link add_headline()} method.
	 */
	function init_templates()
	{
		// This is included before controller specifc require_css() calls:
		require_css( 'skins_adm/chicago/rsc/css/chicago.css', true );
	}


	/**
	 * GLOBAL HEADER - APP TITLE, LOGOUT, ETC.
	 *
	 * @return string
	 */
	function get_page_head()
	{
		global $UserSettings, $current_User;

		$r = '';
		if( $UserSettings->get( 'show_breadcrumbs', $current_User->ID ) ) {
			$r = $this->breadcrumbpath_get_html();
		}

		if( $UserSettings->get( 'show_menu', $current_User->ID) )
		{
			$r .= '
			<div id="header">'
				// Display MAIN menu:
				.$this->get_html_menu().'
			</div>
			';
		}

		return $r;
	}


	/**
	 *
	 *
	 * @return string
	 */
	function get_body_top()
	{
		global $Messages;

		$r = '<div class="wrapper">';

		$r .= $this->get_page_head();

		$r .= $this->get_bloglist_buttons();

		$r .= '<div id="panelbody" class="panelbody">'
			."\n\n";

		// Display info & error messages
		$r .= $Messages->display( NULL, NULL, false, 'action_messages' );

		return $r;
	}


	/**
	 * Get the end of the HTML <body>. Close open divs, etc...
	 *
	 * @return string
	 */
	function get_body_bottom()
	{
		return "\n</div>\n</div>\n";
	}


	/**
	 * Get the footer text
	 */
	function get_footer_contents()
	{
		global $app_footer_text, $copyright_text;
		global $adminskins_url;

		global $Hit;

		$r = '<div class="footer">';

		if( $Hit->is_winIE() )
		{
		 $r .= '<!--[if lt IE 7]>
<div style="text-align:center; color:#f00; font-weight:bold;">'.
			T_('WARNING: Internet Explorer 6 may not able to display this admin skin properly. We strongly recommend you upgrade to IE 7 or Firefox.').'</div>
<![endif]-->';
		}

		$r .= '<div class="copyright">';

		$r .= $app_footer_text.' &ndash; '.$copyright_text."</div></div>\n\n";

		return $r;
	}


	/**
	 * Get a template by name and depth.
	 *
	 * Templates can handle multiple depth levels
	 *
	 * This is a method (and not a member array) to allow dynamic generation and T_()
	 *
	 * @param string Name of the template ('main', 'sub')
	 * @param integer Nesting level (start at 0)
	 * @return array Associative array which defines layout and optionally properties.
	 */
	function get_template( $name, $depth = 0 )
	{
		global $rsc_url;

		$pb_begin1 = '<div class="pblock">';
		$pb_begin2 = '<div class="pan_left"><div class="pan_right"><div class="pan_top"><div class="pan_tl"><div class="pan"><div class="panelblock">';
		$pb_end = '</div></div></div></div></div></div>
								<div class="pan_bot"><div class="pan_bl"><div class="pan_br"></div></div></div></div>';

		switch( $name )
		{
			case 'sub':
				// a payload block with embedded submenu
				return array(
						'before' => $pb_begin1
							.'<span style="float:right">$global_icons$</span>'
							.'<table class="tabs" cellspacing="0"><tr>'
							.'<td class="first"></td>',

						'after' => '<td class="last"></td>'
							."</tr></table>\n"
							.$pb_begin2,

						'empty' => $pb_begin1.$pb_begin2,

						'beforeEach' => '<td class="option">',
						'afterEach'  => '</td>',
						'beforeEachSel' => '<td class="current">',
						'afterEachSel' => '</td>',

						'end' => $pb_end, // used to end payload block that opened submenu
					);


			case 'block':
				// an additional payload block, anywhere after the one with the submenu. Used by disp_payload_begin()/disp_payload_end()
				return array(
						'begin' => $pb_begin1.$pb_begin2,
						'end' => $pb_end,
					);


			case 'Results':
				// Results list:
				return array(
					'page_url' => '', // All generated links will refer to the current page
					'before' => '<div class="results">',
					'header_start' => '<div class="results_nav">',
						'header_text' => '<strong>'.T_('Pages').'</strong>: $prev$ $first$ $list_prev$ $list$ $list_next$ $last$ $next$',
						'header_text_single' => '',
					'header_end' => '</div>',
					'list_start' => '',
						'head_start' => '',
							'head_title' => '<div class="fieldset_title"><div class="fieldset_title_right"><div class="fieldset_title_bg">
																	<span style="float:right">$global_icons$</span>$title$
																</div></div></div>'
															."\n\n"
															.'<table class="grouped" cellspacing="0">'
							                ."\n<thead>\n",
							'filters_start' => '<tr class="filters"><td colspan="$nb_cols$">',
							'filters_end' => '</td></tr>',
							'line_start_head' => '<tr class="clickable_headers">',  // TODO: fusionner avec colhead_start_first; mettre a jour admin_UI_general; utiliser colspan="$headspan$"
							'colhead_start' => '<th $class_attrib$>',
							'colhead_start_first' => '<th class="firstcol $class$">',
							'colhead_start_last' => '<th class="lastcol $class$">',
							'colhead_end' => "</th>\n",
							'sort_asc_off' => '<img src="../admin/img/grey_arrow_up.gif" alt="A" title="'.T_('Ascending order')
							                    .'" height="12" width="11" />',
							'sort_asc_on' => '<img src="../admin/img/black_arrow_up.gif" alt="A" title="'.T_('Ascending order')
							                    .'" height="12" width="11" />',
							'sort_desc_off' => '<img src="../admin/img/grey_arrow_down.gif" alt="D" title="'.T_('Descending order')
							                    .'" height="12" width="11" />',
							'sort_desc_on' => '<img src="../admin/img/black_arrow_down.gif" alt="D" title="'.T_('Descending order')
							                    .'" height="12" width="11" />',
							'basic_sort_off' => '',
							'basic_sort_asc' => get_icon( 'ascending' ),
							'basic_sort_desc' => get_icon( 'descending' ),
						'head_end' => "</thead>\n\n",
						'tfoot_start' => "<tfoot>\n",
						'tfoot_end' => "</tfoot>\n\n",
						'body_start' => "<tbody>\n",
							'line_start' => '<tr class="even">'."\n",
							'line_start_odd' => '<tr class="odd">'."\n",
							'line_start_last' => '<tr class="even lastline">'."\n",
							'line_start_odd_last' => '<tr class="odd lastline">'."\n",
								'col_start' => '<td $class_attrib$>',
								'col_start_first' => '<td class="firstcol $class$">',
								'col_start_last' => '<td class="lastcol $class$">',
								'col_end' => "</td>\n",
							'line_end' => "</tr>\n\n",
							'grp_line_start' => '<tr class="group">'."\n",
							'grp_line_start_odd' => '<tr class="odd">'."\n",
							'grp_line_start_last' => '<tr class="lastline">'."\n",
							'grp_line_start_odd_last' => '<tr class="odd lastline">'."\n",
										'grp_col_start' => '<td $class_attrib$ $colspan_attrib$>',
										'grp_col_start_first' => '<td class="firstcol $class$" $colspan_attrib$>',
										'grp_col_start_last' => '<td class="lastcol $class$" $colspan_attrib$>',
								'grp_col_end' => "</td>\n",
							'grp_line_end' => "</tr>\n\n",
						'body_end' => "</tbody>\n\n",
						'total_line_start' => '<tr class="total">'."\n",
							'total_col_start' => '<td $class_attrib$>',
							'total_col_start_first' => '<td class="firstcol $class$">',
							'total_col_start_last' => '<td class="lastcol $class$">',
							'total_col_end' => "</td>\n",
						'total_line_end' => "</tr>\n\n",
					'list_end' => "</table>\n\n",
					'footer_start' => '<div class="results_nav">',
					'footer_text' => '<strong>'.T_('Pages').'</strong>: $prev$ $first$ $list_prev$ $list$ $list_next$ $last$ $next$'
					                  /* T_('Page $scroll_list$ out of $total_pages$   $prev$ | $next$<br />'. */
					                  /* '<strong>$total_pages$ Pages</strong> : $prev$ $list$ $next$' */
					                  /* .' <br />$first$  $list_prev$  $list$  $list_next$  $last$ :: $prev$ | $next$') */,
					'footer_text_single' => '',
					'footer_text_no_limit' => '', // Text if theres no LIMIT and therefor only one page anyway
						'prev_text' => T_('Previous'),
						'next_text' => T_('Next'),
						'no_prev_text' => '',
						'no_next_text' => '',
						'list_prev_text' => T_('...'),
						'list_next_text' => T_('...'),
						'list_span' => 11,
						'scroll_list_range' => 5,
					'footer_end' => "</div>\n\n",
					'no_results_start' => '<div class="fieldset_title"><div class="fieldset_title_right"><div class="fieldset_title_bg">
																		<span style="float:right">$global_icons$</span>$title$
																	</div></div></div>'."\n\n"
																.'<table class="grouped" cellspacing="0">'."\n",
					'no_results_end'   => '<tr class="lastline"><td class="firstcol lastcol">$no_results$</td></tr>'
								                .'</table>'."\n\n",
				'after' => '</div>',
				'sort_type' => 'basic'
				);


			case 'compact_form':
				// Compact Form settings:
				return array(
					'layout' => 'chicago',		// Temporary dirty hack
					'formstart' => '<div class="fieldset_title"><div class="fieldset_title_right">',

					'title_fmt' => '<div class="fieldset_title_bg" $title_attribs$><span style="float:right">$global_icons$</span>$title$</div></div></div><fieldset>'."\n",
					'no_title_fmt' => '<div class="fieldset_title_bg" $title_attribs$><span style="float:right">$global_icons$</span>&nbsp;</div></div></div><fieldset>'."\n",
					'fieldset_begin' => '<div class="fieldset_wrapper$class$" id="$id$"><h2 $title_attribs$>$fieldset_title$</h2>',
					'fieldset_end' => '</div>',
					'fieldstart' => '<fieldset $ID$>'."\n",
					'labelstart' => '<div class="label">',
					'labelend' => "</div>\n",
					'labelempty' => '<div class="label"></div>', // so that IE6 aligns DIV.input correcctly
					'inputstart' => '<div class="input">',
					'infostart' => '<div class="info">',
					'inputend' => "</div>\n",
					'fieldend' => "</fieldset>\n\n",
					'buttonsstart' => '<fieldset><div class="input">',
					'buttonsend' => "</div></fieldset>\n\n",
					'formend' => '</fieldset>'."\n",
				);


			case 'Form':
				// Default Form settings:
				return array(
					'layout' => 'chicago',		// Temporary dirty hack
					'formstart' => '',
					'title_fmt' => '<span style="float:right">$global_icons$</span><h2>$title$</h2>'."\n",
					'no_title_fmt' => '<span style="float:right">$global_icons$</span>'."\n",
					'fieldstart' => '<fieldset $ID$>'."\n",
					'fieldset_begin' => '<div class="fieldset_wrapper$class$" id="fieldset_wrapper_$id$"><div class="fieldset_title"><div class="fieldset_title_right">
						<div class="fieldset_title_bg" $title_attribs$>$fieldset_title$</div></div></div>
						<fieldset $fieldset_attribs$>'."\n", // $fieldset_attribs will contain ID
					'fieldset_end' => '</fieldset></div>'."\n",
					'labelstart' => '<div class="label">',
					'labelend' => "</div>\n",
					'labelempty' => '<div class="label"></div>', // so that IE6 aligns DIV.input correcctly
					'inputstart' => '<div class="input">',
					'infostart' => '<div class="info">',
					'inputend' => "</div>\n",
					'fieldend' => "</fieldset>\n\n",
					'buttonsstart' => '<fieldset><div class="input">',
					'buttonsend' => "</div></fieldset>\n\n",
					'formend' => '',
				);


			case 'file_browser':
				return array(
						'block_start' => '<div class="block_item_wrap"><div class="fieldset_title"><div class="fieldset_title_right"><div class="fieldset_title_bg">
																		<span style="float:right">$global_icons$</span>$title$
																	</div></div></div>',
						'block_end' => '</div>',
					);

			case 'block_item':
				return array(
						'block_start' => '<div class="block_item_wrap"><div class="fieldset_title"><div class="fieldset_title_right"><div class="fieldset_title_bg">
																		<span style="float:right">$global_icons$</span>$title$
																	</div></div></div>
																	<div class="block_item">',
						'block_end' => '</div></div>',
					);

			case 'dash_item':
				return array(
						'block_start' => '<div class="block_item_wrap"><div class="fieldset_title"><div class="fieldset_title_right"><div class="fieldset_title_bg">
																		<span style="float:right">$global_icons$</span>$title$
																	</div></div></div>
																	<div class="dash_item">',
						'block_end' => '</div></div>',
					);

			case 'side_item':
				return array(
						'block_start' => '<div class="browse_side_item_wrap"><div class="fieldset_title"><div class="fieldset_title_right"><div class="fieldset_title_bg">
																		<span style="float:right">$global_icons$</span>$title$
																	</div></div></div>
																	<div class="browse_side_item">',
						'block_end' => '</div></div>',
					);

			default:
				// Delegate to parent class:
				return parent::get_template( $name, $depth );
		}
	}

	/**
	 * Get colors for page elements that can't be controlled by CSS (charts)
	 */
	function get_color( $what )
	{
		switch( $what )
		{
			case 'payload_background':
				return 'f1f6f8';
				break;
		}
		debug_die( 'unknown color' );
	}


	/**
	 * Display skin specific options
	 */
	function display_skin_settings( $Form, $user_ID )
	{
		global $UserSettings, $current_User;
		$Form->begin_fieldset( T_( 'Admin skin settings' ), array( 'id' => 'admin_skin_settings' ) );
		parent::display_skin_settings( $Form, $user_ID );

		$user_admin_skin = $UserSettings->get( 'admin_skin', $user_ID );
		if( $UserSettings->get( 'admin_skin', $current_User->ID ) == $user_admin_skin )
		{
			$Form->checklist( array(
						array( 'show_evobar', 1, T_('Show evobar'), $UserSettings->get( 'show_evobar', $user_ID ) ),
						array( 'show_breadcrumbs', 1, T_('Show breadcrumbs path'), $UserSettings->get( 'show_breadcrumbs', $user_ID ) ),
						array( 'show_menu', 1, T_('Show Menu'), $UserSettings->get( 'show_menu', $user_ID ) ) ),
					'chicago_settings', T_('Chicago skin settings') );
		}
		else
		{
			$Form->info( '', sprintf( T_( 'Admin skin settings for this user cannot be edited because this user is using a different admin skin (%s)' ), $user_admin_skin ) );
		}

		$Form->end_fieldset();

		// JavaScript code to dynamically change display settings. show_evobar or show_menu always have to be checked
		?>
		<script type="text/javascript">
		jQuery( '[name = show_evobar], [name = show_menu]' ).click( function()
		{
			if( ! ( jQuery( '[name = show_evobar]' ).attr( 'checked' ) || jQuery( '[name = show_menu]' ).attr( 'checked' ) ) )
			{
				jQuery( '[name = show_evobar]' ).attr( 'checked', true );
			}
		} );
		</script>
		<?php
	}


	/**
	 * Set skin specific options
	 */
	function set_skin_settings( $user_ID )
	{
		global $UserSettings;
		$show_menu = param( 'show_menu', 'boolean' );
		// evobar or menu must be visible. If menu is not visible, show_evobar must be set to true.
		$show_evobar = ( $show_menu ? param( 'show_evobar', 'boolean' ) : true );

		$UserSettings->set( 'show_evobar', $show_evobar, $user_ID );
		$UserSettings->set( 'show_breadcrumbs', param( 'show_breadcrumbs', 'boolean' ), $user_ID );
		$UserSettings->set( 'show_menu', $show_menu, $user_ID );
		// It will be saved by the user.ctrl
		// $UserSettings->dbupdate();
	}


	/**
	 * Get show evobar setting
	 * @return boolean 
	 */
	function get_show_evobar()
	{
		global $UserSettings, $current_User;
		return $UserSettings->get( 'show_evobar', $current_User->ID );
	}
}


/*
 * $Log$
 * Revision 1.36  2010/12/06 13:15:41  efy-asimo
 * Admin skin preferences, show evobar - fix
 *
 * Revision 1.35  2010/11/25 15:16:35  efy-asimo
 * refactor $Messages
 *
 * Revision 1.34  2010/11/22 13:44:33  efy-asimo
 * Admin skin preferences update
 *
 * Revision 1.33  2010/11/18 13:56:06  efy-asimo
 * admin skin preferences
 *
 * Revision 1.32  2010/05/06 18:58:14  blueyed
 * Admin: skin: chicago: fix duplicate ID within fieldset_begin. Use fieldset_wrapper_ID for the wrapper.
 *
 * Revision 1.31  2010/02/08 17:56:48  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.30  2010/01/23 00:30:09  fplanque
 * no message
 *
 * Revision 1.27  2009/12/11 03:01:16  fplanque
 * breadcrumbs improved
 *
 * Revision 1.26  2009/12/06 22:55:20  fplanque
 * Started breadcrumbs feature in admin.
 * Work in progress. Help welcome ;)
 * Also move file settings to Files tab and made FM always enabled
 *
 * Revision 1.25  2009/11/22 18:20:08  fplanque
 * Dashboard CSS enhancements
 *
 * Revision 1.24  2009/10/12 23:03:32  blueyed
 * Fix displaying of Messages in $mode windows (e.g. file uploads) and enable
 * them in the attachment iframe.
 *
 * Revision 1.23  2009/08/31 17:21:31  fplanque
 * minor
 *
 * Revision 1.22  2009/07/02 00:18:06  fplanque
 * no message
 *
 * Revision 1.21  2009/06/09 11:59:55  yabs
 * bug fix
 *
 * Revision 1.20  2009/06/09 07:41:30  yabs
 * added replacement vars for class && id for fieldset_begin()
 *
 * Revision 1.19  2009/05/18 02:59:16  fplanque
 * Skins can now have an item.css file to specify content formats. Used in TinyMCE.
 * Note there are temporarily too many CSS files.
 * Two ways of solving is: smart resource bundles and/or merge files that have only marginal benefit in being separate
 *
 * Revision 1.18  2009/04/13 20:51:03  fplanque
 * long overdue cleanup of "no results" display: putting filter sback in right position
 *
 * Revision 1.17  2009/03/08 23:57:56  fplanque
 * 2009
 *
 * Revision 1.16  2009/03/07 21:33:54  blueyed
 * Fix indent, nuke globals.
 *
 * Revision 1.15  2009/03/04 00:10:43  blueyed
 * Make Hit constructor more lazy.
 *  - Move referer_dom_ID generation/fetching to own method
 *  - wrap Debuglog additons with "debug"
 *  - Conditionally call detect_useragent, if required. Move
 *    vars to methods for this
 *  - get_user_agent alone does not require detect_useragent
 * Feel free to revert it (since it changed all the is_foo vars
 * to methods - PHP5 would allow to use __get to handle legacy
 * access to those vars however), but please consider also
 * removing this stuff from HTML classnames, since that is kind
 * of disturbing/unreliable by itself).
 *
 * Revision 1.14  2008/12/30 23:00:41  fplanque
 * Major waste of time rolling back broken black magic! :(
 * 1) It was breaking the backoffice as soon as $admin_url was not a direct child of $baseurl.
 * 2) relying on dynamic argument decoding for backward comaptibility is totally unmaintainable and unreliable
 * 3) function names with () in log break searches big time
 * 4) complexity with no purpose (at least as it was)
 *
 * Revision 1.12  2008/04/14 19:50:51  fplanque
 * enhanced attachments handling in post edit mode
 *
 * Revision 1.11  2008/02/14 02:19:55  fplanque
 * cleaned up stats
 *
 * Revision 1.10  2008/01/22 14:31:05  fplanque
 * minor
 *
 */
?>
