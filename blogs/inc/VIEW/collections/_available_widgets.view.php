<?php
/**
 * This file implements the UI view for the Available widgets.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * @package admin
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $container;

echo '<h2><span class="right_icons">'.action_icon( T_('Cancel!'), 'close', regenerate_url( 'container' ) ).'</span>'
	.sprintf(T_('Widgets available for insertion into &laquo;%s&raquo;'), $container ).'</h2>';

echo '<ul>';

$core_componentwidget_defs = array(
		'colls_list_public',
		'colls_list_owner',
		'coll_logo',
		'coll_title',
		'coll_tagline',
		'coll_longdesc',
		'coll_common_links',
		'coll_page_list',
		'coll_post_list',
		'coll_search_form',
		'coll_xml_feeds',
		'user_tools',
	);
foreach( $core_componentwidget_defs as $code )
{
	load_class( 'MODEL/widgets/_'.$code.'.widget.php' );
	$classname = $code.'_Widget';
	$ComponentWidget = & new $classname( NULL, 'core', $code );

	echo '<li>';
	echo '<a href="'.regenerate_url( '', 'action=create&amp;type=core&amp;code='.$ComponentWidget->code ).'" title="'.T_('Add this widget to the container').'">';
	echo get_icon( 'new' ).$ComponentWidget->get_name();
	echo '</a></li>';
}
echo '</ul>';


// Now, let's try to get the Plugins that implement a skintag...
// TODO: at some point we may merge them with the above, but alphabetical order probably isn't the best solution

/**
 * @var Plugins
 */
global $Plugins;

$Plugin_array = $Plugins->get_list_by_event( 'SkinTag' );
if( ! empty($Plugin_array) )
{ // We have some plugins
	// echo '<h3>'.T_('Plugins').'</h3>';
	echo '<ul>';
	foreach( $Plugin_array as $ID => $Plugin )
	{
		echo '<li>';
		echo '<a href="'.regenerate_url( '', 'action=create&amp;type=plugin&amp;code='.$Plugin->code ).'" title="'.T_('Add this widget to the container').'">';
		echo get_icon( 'new' ).$Plugin->name;
		echo '</a></li>';
	}
	echo '</ul>';
}


/*
 * $Log$
 * Revision 1.7  2007/06/18 21:25:48  fplanque
 * one class per core widget
 *
 * Revision 1.6  2007/04/26 00:11:05  fplanque
 * (c) 2007
 *
 * Revision 1.5  2007/01/14 01:32:11  fplanque
 * more widgets supported! :)
 *
 * Revision 1.4  2007/01/12 21:53:12  blueyed
 * Probably fixed Plugins::get_list_by_* methods: the returned references were always the one to the last Plugin
 *
 * Revision 1.3  2007/01/12 21:38:42  blueyed
 * doc
 *
 * Revision 1.2  2007/01/12 05:17:15  fplanque
 * $Plugins->get_list_by_event() returns crap :((
 *
 * Revision 1.1  2007/01/08 21:55:42  fplanque
 * very rough widget handling
 *
 */
?>