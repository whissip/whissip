<?php
/**
 * This is the template that dipatches display of the main area, based on the disp param
 *
 * This file is not meant to be called directly.
 * It is meant to be called by an include in the main.page.php template.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2006 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package evoskins
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

if( $disp != 'posts' && $disp != 'single' )
{ // We must display a sub template:
	$disp_handlers = array(
			'arcdir'   => '_arcdir.php',
			'catdir'   => '_catdir.disp.php',
			'comments' => '_lastcomments.php',
			'msgform'  => '_msgform.php',
			'profile'  => '_profile.php',
			'subs'     => '_subscriptions.php',
		);

	if( empty( $disp_handlers[$disp] ) )
	{
		debug_die( 'Unhandled disp type ['.$disp.']' );
	}
		
	$disp_handler = $disp_handlers[$disp];
		
	if( file_exists( $ads_current_skin_path.$disp_handler ) )
	{	// The skin has a customized handler for this display:
		require $ads_current_skin_path.$disp_handler;
	}
	else
	{	// Use the default handler from the skins dir:
		require $skins_path.$disp_handler;
	}
}


/*
 * $Log$
 * Revision 1.5  2007/03/18 01:39:55  fplanque
 * renamed _main.php to main.page.php to comply with 2.0 naming scheme.
 * (more to come)
 *
 * Revision 1.4  2007/03/04 21:42:49  fplanque
 * category directory / albums
 *
 * Revision 1.3  2007/01/28 17:50:18  fplanque
 * started moving towards 2.0 skin structure
 *
 * Revision 1.2  2006/08/16 23:50:17  fplanque
 * moved credits to correct place
 *
 * Revision 1.1  2006/07/24 00:05:44  fplanque
 * cleaned up skins
 *
 */
?>