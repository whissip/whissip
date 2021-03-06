<?php
/**
 * This file implements the UI view for the widgets installed on a blog.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * @package admin
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $Blog;

global $container_Widget_array;

global $container_list;

if(	$current_User->check_perm( 'options', 'edit', false ) )
{
	echo '<div class="floatright small">'.action_icon( TS_('Reload containers!'), 'reload',
	                        '?ctrl=widgets&amp;blog='.$Blog->ID.'&amp;action=reload&amp;'.url_crumb('widget'), T_('Reload containers!') ).'</div>';
}

// Load widgets for current collection:
$WidgetCache = & get_WidgetCache();
$container_Widget_array = & $WidgetCache->get_by_coll_ID( $Blog->ID );

/**
 * @param string Title of the container. This gets passed to T_()!
 * @param string Suffix of legend
 */
function display_container( $container, $legend_suffix = '' )
{
	global $Blog;
	global $Session;

	$Table = new Table();

	$Table->title = '<span class="container_name">'.T_($container).'</span>'.$legend_suffix;

	// Table ID - fp> needs to be handled cleanly by Table object
	$table_id = str_replace( ' ', '_', $container ); // fp> Using the container name which has special chars is a bad idea. Counter would be better

	$Table->global_icon( T_('Add a widget...'), 'new',
			regenerate_url( '', 'action=new&amp;container='.rawurlencode($container) ), /* TRANS: ling used to add a new widget */ T_('Add widget').' &raquo;', 3, 4, array( 'id' => 'add_new_'.$table_id ) );

	$Table->cols = array(
			array(
				'th' => /* TRANS: shortcut for enabled */ T_( 'En' ),
				'th_class' => 'shrinkwrap',
				'td_class' => 'shrinkwrap' ),
			array( 'th' => T_('Widget') ),
			array( 'th' => T_('Type') ),
			array(
				'th' => T_('Move'),
				'th_class' => 'shrinkwrap',
				'td_class' => 'shrinkwrap' ),
			array(
				'th' => T_('Actions'),
				'th_class' => 'shrinkwrap',
				'td_class' => 'shrinkwrap' ),
		);
	//enable fadeouts here
	$Table->display_init( NULL, array('fadeouts' => true) );
	// add ID for jQuery
	// TODO: fp> Awfully dirty. This should be handled by the Table object
	$Table->params['head_title'] = str_replace( '<table', '<table id="'.$table_id.'"', $Table->params['head_title'] );

	/*
	if( $legend_suffix )
	{	// add jQuery no-drop -- fp> what do we need this one for?
		$Table->params['head_title'] = str_replace( 'class="grouped"', 'class="grouped no-drop"', $Table->params['head_title'] );
	}
	*/

	$Table->display_list_start();

	// TITLE / COLUMN HEADERS:
	$Table->display_head();

	// BODY START:
	$Table->display_body_start();

	/**
	 * @var WidgetCache
	 */
	$WidgetCache = & get_WidgetCache();
	$Widget_array = & $WidgetCache->get_by_coll_container( $Blog->ID, $container );

	if( empty($Widget_array) )
	{	// TODO: cleanup
		$Table->display_line_start( true );
		$Table->display_col_start( array( 'colspan' => 5 ) );
		echo '<span class="new_widget">'.T_('There is no widget in this container yet.').'</span>';
		$Table->display_col_end();
		$Table->display_line_end();
	}
	else
	{
		$widget_count = 0;
		foreach( $Widget_array as $ComponentWidget )
		{
			$widget_count++;
			$enabled = $ComponentWidget->get( 'enabled' );

			$fadeout_id = $Session->get( 'fadeout_id' );
			if( isset($fadeout_id) && $ComponentWidget->ID == $fadeout_id )
			{
				$fadeout = true;
				$Session->delete( 'fadeout_id' );
			}
			else
			{
				$fadeout = false;
			}

			$Table->display_line_start( false, $fadeout );

			$Table->display_col_start();
			if ( $enabled )
			{
				// Indicator for the JS UI:
				echo '<span class="widget_is_enabled">';
				echo get_icon( 'enabled', 'imgtag', array( 'title' => T_( 'The widget is enabled.' ) ) );
				echo '</span>';
			}
			else
			{
				echo get_icon( 'disabled', 'imgtag', array( 'title' => T_( 'The widget is disabled.' ) ) );
			}
			$Table->display_col_end();

			$Table->display_col_start();
			$ComponentWidget->init_display( array() );
			echo '<a href="'.regenerate_url( 'blog', 'action=edit&amp;wi_ID='.$ComponentWidget->ID).'" class="widget_name">'
						.$ComponentWidget->get_desc_for_list().'</a>';
			$Table->display_col_end();

			// Note: this is totally useless, but we need more cols for the screen to feel "right":
			$Table->display_col_start();
			echo $ComponentWidget->type;
			$Table->display_col_end();

			// Move
			$Table->display_col_start();
			//echo $ComponentWidget->order.' ';
			if( $widget_count > 1 )
			{
				echo action_icon( T_('Move up!'), 'move_up', regenerate_url( 'blog', 'action=move_up&amp;wi_ID='.$ComponentWidget->ID.'&amp;'.url_crumb('widget') ) );
			}
			else
			{
				echo get_icon( 'nomove', 'imgtag', array( 'class'=>'action_icon' ) );
			}
			if( $widget_count < count($Widget_array))
			{
				echo action_icon( T_('Move down!'), 'move_down', regenerate_url( 'blog', 'action=move_down&amp;wi_ID='.$ComponentWidget->ID.'&amp;'.url_crumb('widget') ) );
			}
			else
			{
				echo get_icon( 'nomove', 'imgtag', array( 'class'=>'action_icon' ) );
			}
			$Table->display_col_end();

			// Actions
			$Table->display_col_start();
			if ( $enabled )
			{
				echo action_icon( T_( 'Disable this widget!' ), 'deactivate', regenerate_url( 'blog', 'action=toggle&amp;wi_ID='.$ComponentWidget->ID.'&amp;'.url_crumb('widget') ) );
			}
			else
			{
				echo action_icon( T_( 'Enable this widget!' ), 'activate', regenerate_url( 'blog', 'action=toggle&amp;wi_ID='.$ComponentWidget->ID.'&amp;'.url_crumb('widget') ) );
			}
			echo '<span class="edit_icon_hook">'.action_icon( T_('Edit widget settings!'), 'edit', regenerate_url( 'blog', 'action=edit&amp;wi_ID='.$ComponentWidget->ID ) ).'</span>';
			echo '<span class="delete_icon_hook">'.action_icon( T_('Remove this widget!'), 'delete', regenerate_url( 'blog', 'action=delete&amp;wi_ID='.$ComponentWidget->ID.'&amp;'.url_crumb('widget') ) ).'</span>';
			$Table->display_col_end();

			$Table->display_line_end();
		}
	}

	// BODY END:
	$Table->display_body_end();

	$Table->display_list_end();
}

// fp> what browser do we need a fielset for?
echo '<fieldset id="current_widgets">'."\n"; // fieldsets are cool at remembering their width ;)

// Display containers for current skin:
foreach( $container_list as $container )
{
	display_container( $container );
}

// Display containers not in current skin:
foreach( $container_Widget_array as $container=>$dummy )
{
	if( !in_array( $container, $container_list ) )
	{
		display_container( $container, ' '.T_('[NOT INCLUDED IN SELECTED SKIN!]') );
	}
}

global $rsc_url;

echo '<!--[if IE]><img src="'.$rsc_url.'img/blank.gif" width="1" height="1" alt="" /><![endif]-->';

echo '</fieldset>'."\n";


echo '<img src="'.$rsc_url.'/img/blank.gif" alt="" class="clear" />';


/*
 * $Log$
 * Revision 1.25  2010/02/08 17:54:47  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.24  2010/01/30 18:55:35  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.23  2010/01/21 18:16:49  efy-yury
 * update: fadeouts
 *
 * Revision 1.22  2010/01/03 13:10:57  fplanque
 * set some crumbs (needs checking)
 *
 * Revision 1.21  2009/12/20 20:38:34  fplanque
 * Enhanced skin containers reload for skin developers
 *
 * Revision 1.20  2009/12/07 23:00:17  blueyed
 * TODO, the 2nd. Please refactor. Users table wants an ID, too. Every results table should have one, makes finding the source for it a lot easier, too.
 *
 * Revision 1.19  2009/10/12 22:11:28  blueyed
 * Fix blank.gif some: use conditional comments, where marked as being required for IE. Add ALT tags and close tags.
 *
 * Revision 1.18  2009/09/26 21:23:02  tblue246
 * Non-JS widgets screen: Use proper colspan for "No widgets" message.
 *
 * Revision 1.17  2009/09/25 07:33:30  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.16  2009/03/14 21:50:46  fplanque
 * still cleaning up...
 *
 * Revision 1.15  2009/03/13 02:32:08  fplanque
 * Cleaned up widgets.
 * Removed stupid widget_name param.
 *
 * Revision 1.14  2009/03/08 23:57:46  fplanque
 * 2009
 *
 * Revision 1.13  2009/02/05 21:33:34  tblue246
 * Allow the user to enable/disable widgets.
 * Todo:
 * 	* Fix CSS for the widget state bullet @ JS widget UI.
 * 	* Maybe find a better solution than modifying get_Cache() to get only enabled widgets... :/
 * 	* Buffer JS requests when toggling the state of a widget??
 *
 * Revision 1.12  2008/10/05 03:35:43  fplanque
 * comments for yabba
 *
 * Revision 1.11  2008/07/03 09:53:08  yabs
 * widget UI
 *
 * Revision 1.10  2008/01/21 09:35:37  fplanque
 * (c) 2008
 *
 * Revision 1.9  2008/01/05 17:17:36  blueyed
 * Fix output of rsc_url
 *
 * Revision 1.8  2007/12/23 15:07:07  fplanque
 * Clean widget name
 *
 * Revision 1.7  2007/12/23 14:14:25  fplanque
 * Enhanced widget name display
 *
 * Revision 1.6  2007/12/22 23:24:45  yabs
 * cleanup from adding core params
 *
 * Revision 1.5  2007/12/22 16:57:01  yabs
 * adding core parameters for css id/classname and widget list title
 *
 * Revision 1.4  2007/09/29 08:18:21  yabs
 * UI - added edit to actions
 *
 * Revision 1.3  2007/09/08 20:23:04  fplanque
 * action icons / wording
 *
 * Revision 1.2  2007/09/03 19:36:06  fplanque
 * chicago admin skin
 *
 * Revision 1.1  2007/06/25 11:02:01  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.9  2007/06/18 21:25:48  fplanque
 * one class per core widget
 *
 * Revision 1.8  2007/06/11 22:01:54  blueyed
 * doc fixes
 *
 * Revision 1.7  2007/04/26 00:11:05  fplanque
 * (c) 2007
 *
 * Revision 1.6  2007/03/26 17:12:41  fplanque
 * allow moving of widgets
 *
 * Revision 1.5  2007/01/11 20:44:19  fplanque
 * skin containers proof of concept
 * (no params handling yet though)
 *
 * Revision 1.4  2007/01/11 02:57:25  fplanque
 * implemented removing widgets from containers
 *
 * Revision 1.3  2007/01/11 02:25:06  fplanque
 * refactoring of Table displays
 * body / line / col / fadeout
 *
 * Revision 1.2  2007/01/08 23:45:48  fplanque
 * A little less rough widget manager...
 * (can handle multiple instances of same widget and remembers order)
 *
 * Revision 1.1  2007/01/08 21:55:42  fplanque
 * very rough widget handling
 */
?>