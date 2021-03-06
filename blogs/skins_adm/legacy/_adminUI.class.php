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
	 * Get a template by name and depth.
	 *
	 * @param string The template name ('main', 'sub').
	 * @return array
	 */
	function get_template( $name, $depth = 0 )
	{
		switch( $name )
		{
			case 'main':
				switch( $depth )
				{
					default: // just one level for now (might provide dropdown later)
						return array(
							'before' => '<ul class="tabs">',
							'after' => '</ul>',
							'beforeEach' => '<li>',
							'afterEach' => '</li>',
							'beforeEachSel' => '<li class="current">',
							'afterEachSel' => '</li>',
						);
				}
				break;

			case 'CollectionList':
				// Template for a list of Collections (Blogs)
				return array(
						'before' => '',
						'after' => '',
						'select_start' => '<div class="collection_select">',
						'select_end' => '</div>',
						'buttons_start' => '',
						'buttons_end' => '',
						'beforeEach' => '',
						'afterEach' => '',
						'beforeEachSel' => '',
						'afterEachSel' => '',
					);

			default:
				// Delegate to parent class:
				return parent::get_template( $name, $depth );
		}
	}


	/**
	 * This function should init the templates - like adding Javascript through the {@link add_headline()} method.
	 */
	function init_templates()
	{
		// This is included before controller specifc require_css() calls:
		global $adminskins_path;

		require_css ( 'skins_adm/legacy/rsc/css/variation.css', true, 'Variation' );
		require_css ( 'skins_adm/legacy/rsc/css/desert.css', true, 'Desert' );
		require_css ( 'skins_adm/legacy/rsc/css/legacy.css', true, 'Legacy' );

		if( is_file( $adminskins_path.'/legacy/rsc/css/custom.css' ) )
		{
			require_css ( 'skins_adm/legacy/rsc/css/custom.css', true, 'Custom' );
		}

		// Style switcher:
		require_js( 'styleswitcher.js' );
	}


	/**
	 * GLOBAL HEADER - APP TITLE, LOGOUT, ETC.
	 *
	 * @return string
	 */
	function get_page_head()
	{
		global $htsrv_url_sensitive, $baseurl, $admin_url, $rsc_url, $Blog;
		global $app_shortname, $app_version;

		$r = '
		<div id="header">
			<div id="headfunctions">
				'.$app_shortname.' v <strong>'.$app_version.'</strong> &middot;
				'.T_('Color:').'
				<a href="#" onclick="StyleSwitcher.setActiveStyleSheet(\'Variation\'); return false;" title="Variation (Default)">V</a>'
				.'&middot;<a href="#" onclick="StyleSwitcher.setActiveStyleSheet(\'Desert\'); return false;" title="Desert">D</a>'
				.'&middot;<a href="#" onclick="StyleSwitcher.setActiveStyleSheet(\'Legacy\'); return false;" title="Legacy">L</a>'
				.( is_file( dirname(__FILE__).'/rsc/css/custom.css' ) ? '&middot;<a href="#" onclick="StyleSwitcher.setActiveStyleSheet(\'Custom\'); return false;" title="Custom">C</a>' : '' )
				.'
			</div>'

			// Display MAIN menu:
			.$this->get_html_menu().'
		</div>
		';

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

		$r = '';

		$r .= $this->get_page_head();

		$blog_buttons = $this->get_bloglist_buttons( '<strong>'.$this->get_title_for_titlearea().'</strong> ' );
		if( ! empty($blog_buttons) )
		{
			$r .= '
				<div id="TitleArea">
					<h1>'.$blog_buttons.'</h1>
				</div>';
		}

		$r .= '<div id="panelbody" class="panelbody">'
			."\n\n";

		// Display info & error messages
		$r .= $Messages->display( NULL, NULL, false, 'all', NULL, NULL, 'action_messages' );

		return $r;
	}


	/**
	 * Close open div.
	 *
	 * @return string
	 */
	function get_body_bottom()
	{
		return "\n</div>\n";
	}

	/**
	 * Get colors for page elements that can't be controlled by CSS (charts)
	 */
	function get_color( $what )
	{
		switch( $what )
		{
			case 'payload_background':
				return 'efede0';
				break;
		}
		debug_die( 'unknown color' );
	}

}

/*
 * $Log$
 * Revision 1.34  2010/02/08 17:56:49  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.33  2009/10/12 23:03:33  blueyed
 * Fix displaying of Messages in $mode windows (e.g. file uploads) and enable
 * them in the attachment iframe.
 *
 * Revision 1.32  2009/10/12 20:49:14  blueyed
 * legacy admin skin: display TitleArea only if there are bloglist buttons.
 *
 * Revision 1.31  2009/08/31 17:21:32  fplanque
 * minor
 *
 * Revision 1.30  2009/05/18 02:59:16  fplanque
 * Skins can now have an item.css file to specify content formats. Used in TinyMCE.
 * Note there are temporarily too many CSS files.
 * Two ways of solving is: smart resource bundles and/or merge files that have only marginal benefit in being separate
 *
 * Revision 1.29  2009/03/08 23:57:59  fplanque
 * 2009
 *
 * Revision 1.28  2008/12/30 23:00:41  fplanque
 * Major waste of time rolling back broken black magic! :(
 * 1) It was breaking the backoffice as soon as $admin_url was not a direct child of $baseurl.
 * 2) relying on dynamic argument decoding for backward comaptibility is totally unmaintainable and unreliable
 * 3) function names with () in log break searches big time
 * 4) complexity with no purpose (at least as it was)
 *
 * Revision 1.26  2008/01/22 14:31:06  fplanque
 * minor
 *
 */
?>