<?php
/**
 * This file implements the facebook like plugin
 * 
 * This file is part of the b2evolution project - {@link http://b2evolution.net/}
 *
 * @copyright (c)2003-2011 by Francois Planque - {@link http://fplanque.com/}
 * 
 * @package plugins
 * 
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author asimo: Evo Factory - Attila Simo
 * 
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * Facebook Plugin
 *
 * This plugin displays
 */
class facebook_plugin extends Plugin
{
	/**
	 * Variables below MUST be overriden by plugin implementations,
	 * either in the subclass declaration or in the subclass constructor.
	 */

	var $name;
	var $code = 'evo_facebook';
	var $priority = 20;
	var $version = '1.0';
	var $author = 'The b2evo Group';
	var $group = 'widget';

	/**
	 * Init
	 */
	function PluginInit( & $params )
	{
		$this->name = T_( 'Facebook Like Widget' );
		$this->short_desc = T_('This skin tag displays a Facebook Like button.');
		$this->long_desc = T_('Shows how many users like the current page.');
	}

	/**
	 * Event handler: SkinTag (widget)
	 *
	 * @param array Associative array of parameters.
	 * @return boolean did we display?
	 */
	function SkinTag( $params )
	{
		/**
		 * Default params:
		 */
		// This is what will enclose the block in the skin:
		if(!isset($params['block_start']) || empty($params['block_start'])) $params['block_start'] = '<div class="bSideItem">';
		if(!isset($params['block_end']) || empty($params['block_end'])) $params['block_end'] = "</div>\n";

		global $baseurlroot;
		//$test_url = url_absolute( regenerate_url( '', '', '', '&' ), 'http://127.0.0.1' );
		$current_url = url_absolute( regenerate_url( '', '', '', '&' ), $baseurlroot );

		echo $params['block_start'];
		echo '<iframe src="http://www.facebook.com/plugins/like.php?href='.urlencode($current_url)
					.'&amp;layout=standard&amp;show_faces=true&amp;width=190&amp;action=like&amp;font=arial&amp;colorscheme=light&amp;height=66" 
					scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:190px; height:66px;" 
					allowTransparency="true"></iframe>';
		echo $params['block_end'];

		return true;
	}
}


/**
 * $Log$
 * Revision 1.3  2011/09/04 22:13:23  fplanque
 * copyright 2011
 *
 * Revision 1.2  2011/09/04 20:59:40  fplanque
 * cleanup
 *
 * Revision 1.1  2010/09/03 07:45:27  efy-asimo
 * Facebook like button
 *
 */
?>