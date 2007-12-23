<?php
/**
 * This file implements the xyz Widget class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}
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

load_class( 'widgets/model/_widget.class.php' );

/**
 * ComponentWidget Class
 *
 * A ComponentWidget is a displayable entity that can be placed into a Container on a web page.
 *
 * @package evocore
 */
class coll_title_Widget extends ComponentWidget
{
	/**
	 * Constructor
	 */
	function coll_title_Widget( $db_row = NULL )
	{
		// Call parent constructor:
		parent::ComponentWidget( $db_row, 'core', 'coll_title' );
	}


	/**
	 * Get name of widget
	 */
	function get_name()
	{
		return T_('Blog title');
	}


  /**
	 * Get short description
	 */
	function get_desc()
	{
		global $Blog;
		return sprintf( T_('&laquo;%s&raquo; from the blog\'s <a %s>general settings</a>.'),
				'<strong>'.$Blog->dget('name').'</strong>', 'href="?ctrl=coll_settings&tab=general&blog='.$Blog->ID.'"' );
	}


  /**
   * Get definitions for editable params
   *
	 * @see Plugin::GetDefaultSettings()
	 * @param local params like 'for_editing' => true
	 */
	function get_param_definitions( $params )
	{
		global $Blog;

		$r = parent::get_param_definitions( $params );

		$r['widget_name']['defaultvalue'] = $Blog->dget('name');

		return $r;

	}


	/**
	 * Display the widget!
	 *
	 * @param array MUST contain at least the basic display params
	 */
	function display( $params )
	{
		global $Blog;

		$this->init_display( $params );

		// Collection title:
		echo $this->disp_params['block_start'];

		$title = '<a href="'.$Blog->get( 'url', 'raw' ).'">'
							.$Blog->dget( 'name', 'htmlbody' )
							.'</a>';
		$this->disp_title( $title );

		echo $this->disp_params['block_end'];

		return true;
	}
}


/*
 * $Log$
 * Revision 1.2  2007/12/23 16:16:18  fplanque
 * Wording improvements
 *
 * Revision 1.1  2007/06/25 11:02:22  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.2  2007/06/20 21:42:13  fplanque
 * implemented working widget/plugin params
 *
 * Revision 1.1  2007/06/18 21:25:47  fplanque
 * one class per core widget
 *
 */
?>