<?php
/**
 * This file implements the recursive chapter list.
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
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );
//____________________ Callbacks functions to display categories list _____________________

global $Blog;

global $GenericCategoryCache;

global $line_class;

global $permission_to_edit;

global $subset_ID;

global $result_fadeout;

$line_class = 'odd';


/**
 * Generate category line when it has children
 *
 * @param Chapter generic category we want to display
 * @param int level of the category in the recursive tree
 * @return string HTML
 */
function cat_line( $Chapter, $level )
{
	global $line_class, $result_fadeout, $permission_to_edit, $current_User, $Settings;
	global $GenericCategoryCache;


	$line_class = $line_class == 'even' ? 'odd' : 'even';

	$r = '<tr id="tr-'.$Chapter->ID.'"class="'.$line_class.
					// Fadeout?
					( in_array( $Chapter->ID, $result_fadeout[$GenericCategoryCache->dbIDname] ) ? ' fadeout-ffff00': '' ).'">
					<td class="firstcol shrinkwrap">'.
						$Chapter->ID.'
				</td>';

	if( $permission_to_edit )
	{	// We have permission permission to edit:
		$edit_url = regenerate_url( 'action,'.$Chapter->dbIDname, $Chapter->dbIDname.'='.$Chapter->ID.'&amp;action=edit' );
		$r .= '<td>
						<strong style="padding-left: '.($level).'em;"><a href="'.$edit_url.'" title="'.T_('Edit...').'">'.$Chapter->dget('name').'</a></strong>
					 </td>';
	}
	else
	{
		$r .= '<td>
						 <strong style="padding-left: '.($level).'em;">'.$Chapter->dget('name').'</strong>
					 </td>';
	}

	$r .= '<td>'.$Chapter->dget('urlname').'</td>';


	$r .= '<td class="lastcol shrinkwrap">';
	if( $permission_to_edit )
	{	// We have permission permission to edit, so display action column:
		$r .=  action_icon( T_('New...'), 'new', regenerate_url( 'action,cat_ID,cat_parent_ID', 'cat_parent_ID='.$Chapter->ID.'&amp;action=new' ) )
					.action_icon( T_('Edit...'), 'edit', $edit_url );
		if( $Settings->get('allow_moving_chapters') )
		{ // If moving cats between blogs is allowed:
			$r .= action_icon( T_('Move to a different blog...'), 'file_move', regenerate_url( 'action,cat_ID', 'cat_ID='.$Chapter->ID.'&amp;action=move' ), T_('Move') );
		}
		$r .= action_icon( T_('Delete...'), 'delete', regenerate_url( 'action,cat_ID', 'cat_ID='.$Chapter->ID.'&amp;action=delete' ) );
	}
	$r .= '</td>';
	$r .=	'</tr>';

	return $r;
}


/**
 * Generate category line when it has no children
 *
 * @param Chapter generic category we want to display
 * @param int level of the category in the recursive tree
 * @return string HTML
 */
function cat_no_children( $Chapter, $level )
{
	return '';
}


/**
 * Generate code when entering a new level
 *
 * @param int level of the category in the recursive tree
 * @return string HTML
 */
function cat_before_level( $level )
{
	return '';
}

/**
 * Generate code when exiting from a level
 *
 * @param int level of the category in the recursive tree
 * @return string HTML
 */
function cat_after_level( $level )
{
	return '';
}


$callbacks = array(
	'line' 			 	 => 'cat_line',
	'no_children'  => 'cat_no_children',
	'before_level' => 'cat_before_level',
	'after_level'	 => 'cat_after_level'
);

//____________________________________ Display generic categories _____________________________________

$Table = & new Table();

$Table->title = T_('Categories for blog:').' '.$Blog->dget('name');

$Table->global_icon( T_('Add an element...'), 'new', regenerate_url( 'action,'.$GenericCategoryCache->dbIDname, 'action=new' ), T_('Add element'), 3, 4  );

$Table->cols[] = array(
						'th' => T_('ID'),
					);
$Table->cols[] = array(
						'th' => T_('Name'),
					);
$Table->cols[] = array(
						'th' => T_('URL name'),
					);
if( $permission_to_edit )
{	// We have permission permission to edit, so display action column:
	$Table->cols[] = array(
							'th' => T_('Actions'),
						);
}

$Table->display_init( NULL, $result_fadeout );

$Table->display_list_start();

$Table->display_head();

$Table->display_body_start();

echo $GenericCategoryCache->recurse( $callbacks, $subset_ID );

$Table->display_body_end();

$Table->display_list_end();


/* fp> TODO: maybe... (a general group move of posts would be more useful actually)
echo '<p class="note">'.T_('<strong>Note:</strong> Deleting a category does not delete posts from that category. It will just assign them to the parent category. When deleting a root category, posts will be assigned to the oldest remaining category in the same collection (smallest category number).').'</p>';
*/

global $Settings;
if( ! $Settings->get('allow_moving_chapters') )
{	// TODO: check perm
	echo '<p class="note">'.sprintf( T_('<strong>Note:</strong> Moving categories across blogs is currently disabled in the %sglobal settings%s.'), '<a href="admin.php?ctrl=features#categories">', '</a>' ).'</p> ';
}


/*
 * $Log$
 * Revision 1.3  2007/09/04 13:47:48  fplanque
 * fixed fadeout
 *
 * Revision 1.2  2007/09/04 13:23:18  fplanque
 * Fixed display for category screen.
 *
 * Revision 1.1  2007/06/25 10:59:27  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.10  2007/04/26 00:11:05  fplanque
 * (c) 2007
 *
 * Revision 1.9  2007/01/07 05:28:15  fplanque
 * i18n wording
 *
 * Revision 1.8  2006/12/11 00:32:26  fplanque
 * allow_moving_chapters stting moved to UI
 * chapters are now called categories in the UI
 *
 * Revision 1.7  2006/12/10 02:07:09  fplanque
 * doc
 *
 * Revision 1.6  2006/12/10 01:52:27  fplanque
 * old cats are now officially dead :>
 *
 * Revision 1.5  2006/12/09 17:59:34  fplanque
 * started "moving chapters accross blogs" feature
 *
 * Revision 1.4  2006/12/09 02:37:44  fplanque
 * Prevent user from creating loops in the chapter tree
 * (still needs a check before writing to DB though)
 *
 * Revision 1.3  2006/11/24 18:27:25  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>