<?php
/**
 * This file implements the xyz Widget class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
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

/**
 * ComponentWidget Class
 *
 * A ComponentWidget is a displayable entity that can be placed into a Container on a web page.
 *
 * @package evocore
 */
class coll_tagline_Widget extends ComponentWidget
{
	/**
	 * Constructor
	 */
	function coll_tagline_Widget( $db_row = NULL )
	{
		// Call parent constructor:
		parent::ComponentWidget( $db_row, 'core', 'coll_tagline' );
	}


	/**
	 * Get name of widget
	 */
	function get_name()
	{
		return T_('Blog tagline');
	}


	/**
	 * Get a very short desc. Used in the widget list.
	 */
	function get_short_desc()
	{
		global $Blog;

		return $Blog->dget( 'tagline', 'htmlbody' );
	}


	/**
	 * Get short description
	 */
	function get_desc()
	{
		global $Blog;
		return sprintf( T_('&laquo;%s&raquo; from the blog\'s <a %s>general settings</a>.'),
				'<strong>'.$Blog->dget('tagline').'</strong>', 'href="?ctrl=coll_settings&tab=general&blog='.$Blog->ID.'"' );
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

		// Collection tagline:
		echo $this->disp_params['block_start'];
		// TODO: there appears to be no possibility to wrap the tagline in e.g. "<h2>%s</h2>"
		//       Should there be a widget param for this?  fp> probably yes
		$Blog->disp( 'tagline', 'htmlbody' );
		echo $this->disp_params['block_end'];

		return true;
	}
}


/*
 * $Log$
 * Revision 1.11  2010/02/08 17:54:48  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.10  2009/09/14 13:54:13  efy-arrin
 * Included the ClassName in load_class() call with proper UpperCase
 *
 * Revision 1.9  2009/09/12 11:03:13  efy-arrin
 * Included the ClassName in the loadclass() with proper UpperCase
 *
 * Revision 1.8  2009/03/13 02:32:07  fplanque
 * Cleaned up widgets.
 * Removed stupid widget_name param.
 *
 * Revision 1.7  2009/03/08 23:57:46  fplanque
 * 2009
 *
 * Revision 1.6  2008/07/07 05:59:25  fplanque
 * minor / doc / rollback of overzealous indetation "fixes"
 *
 * Revision 1.5  2008/05/30 17:06:22  blueyed
 * TODO about wrapping tagline
 *
 * Revision 1.4  2008/05/30 16:30:38  blueyed
 * Fix indent
 *
 * Revision 1.3  2008/01/21 09:35:37  fplanque
 * (c) 2008
 *
 * Revision 1.2  2007/12/23 16:16:18  fplanque
 * Wording improvements
 *
 * Revision 1.1  2007/06/25 11:02:21  fplanque
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
