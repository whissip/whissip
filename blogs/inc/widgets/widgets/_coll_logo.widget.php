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
class coll_logo_Widget extends ComponentWidget
{
	/**
	 * Constructor
	 */
	function coll_logo_Widget( $db_row = NULL )
	{
		// Call parent constructor:
		parent::ComponentWidget( $db_row, 'core', 'coll_logo' );
	}


	/**
	 * Get name of widget
	 */
	function get_name()
	{
		return T_('Image / Blog logo');
	}


	/**
	 * Get a very short desc. Used in the widget list.
	 *
	 * MAY be overriden by core widgets. Example: menu link widget.
	 */
	function get_short_desc()
	{
		$this->load_param_array();
		if( !empty($this->param_array['logo_file'] ) )
		{
			return $this->param_array['logo_file'];
		}
		else
		{
			return $this->get_name();
		}
	}


  /**
	 * Get short description
	 */
	function get_desc()
	{
		return T_('Include an image/logo from the blog\'s file root.');
	}


  /**
   * Get definitions for editable params
   *
	 * @see Plugin::GetDefaultSettings()
	 * @param local params like 'for_editing' => true
	 */
	function get_param_definitions( $params )
	{
		$r = array_merge( array(
				'logo_file' => array(
					'label' => T_('Image filename'),
					'note' => T_('The image/logo file must be uploaded to the root of the Blog\'s media dir'),
					'defaultvalue' => 'logo.png',
					'valid_pattern' => array( 'pattern'=>'�^[a-z0-9_\-][a-z0-9_.\-]*$�i',
																		'error'=>T_('Invalid filename.') ),
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
		global $Blog;

		$this->init_display( $params );

		// Collection logo:
		echo $this->disp_params['block_start'];

		$title = '<a href="'.$Blog->get( 'url', 'raw' ).'">'
							.'<img src="'.$Blog->get_media_url().$this->disp_params['logo_file'].'" alt="'.$Blog->dget( 'name', 'htmlattr' ).'" />'
							.'</a>';
		$this->disp_title( $title );

		echo $this->disp_params['block_end'];

		return true;
	}
}


/*
 * $Log$
 * Revision 1.10  2010/02/08 17:54:48  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.9  2009/09/14 13:54:13  efy-arrin
 * Included the ClassName in load_class() call with proper UpperCase
 *
 * Revision 1.8  2009/09/12 11:03:13  efy-arrin
 * Included the ClassName in the loadclass() with proper UpperCase
 *
 * Revision 1.7  2009/03/08 23:57:46  fplanque
 * 2009
 *
 * Revision 1.6  2008/02/13 07:37:55  fplanque
 * renamed Blog Logo widget
 *
 * Revision 1.5  2008/01/21 09:35:37  fplanque
 * (c) 2008
 *
 * Revision 1.4  2007/12/23 17:47:59  fplanque
 * fixes
 *
 * Revision 1.3  2007/12/23 14:14:25  fplanque
 * Enhanced widget name display
 *
 * Revision 1.2  2007/12/22 19:55:00  yabs
 * cleanup from adding core params
 *
 * Revision 1.1  2007/06/25 11:02:11  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.3  2007/06/20 21:42:13  fplanque
 * implemented working widget/plugin params
 *
 * Revision 1.2  2007/06/20 00:48:17  fplanque
 * some real life widget settings
 *
 * Revision 1.1  2007/06/18 21:25:47  fplanque
 * one class per core widget
 *
 */
?>