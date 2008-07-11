<?php
/**
 * This file implements the UI for file browsing.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
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
 * {@internal Open Source relicensing agreement:
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var Filelist
 */
global $fm_Filelist;
/**
 * fp> Temporary. I need this for NuSphere debugging.
 * @var File
 */
global $lFile;
/**
 * @var string
 */
global $fm_flatmode;
/**
 * @var User
 */
global $current_User;
/**
 * @var UserSettings
 */
global $UserSettings;
/**
 * @var Log
 */
global $Messages;
/**
 * @var Filelist
 */
global $selected_Filelist;
/**
 * @var Item
 */
global $edited_Item;

global $Blog;

global $fm_hide_dirtree, $create_name, $ads_list_path;


$Form = & new Form( NULL, 'FilesForm', 'post', 'none' );
$Form->begin_form();
	$Form->hidden_ctrl();

	$Form->hidden( 'confirmed', '0' );
	$Form->hidden( 'md5_filelist', $fm_Filelist->md5_checksum() );
	$Form->hidden( 'md5_cwd', md5($fm_Filelist->get_ads_list_path()) );
	$Form->hiddens_by_key( get_memorized('fm_selected') ); // 'fm_selected' gets provided by the form itself
?>
<table class="filelist">
	<thead>
	<?php
		/*****************  Col headers  ****************/

		echo '<tr>';

		// "Go to parent" icon
		echo '<th class="firstcol">';
		if( empty($fm_Filelist->_rds_list_path) )
		{ // cannot go higher
			echo '&nbsp;';	// for IE
		}
		else
		{
			echo action_icon( T_('Go to parent folder'), 'folder_parent', regenerate_url( 'path', 'path='.$fm_Filelist->_rds_list_path.'..' ) );
		}
		echo '</th>';

		echo '<th class="nowrap">';
		if( $UserSettings->get( 'fm_imglistpreview' ) )
		{ // Image file preview:
			$col_title = T_('Icon/Type');
		}
		else
		{
			$col_title = /* TRANS: short for (file)Type */ T_(' T ');
		}
		echo $fm_Filelist->get_sort_link( 'type', $col_title );
		echo '</th>';

		if( $fm_flatmode )
		{
			echo '<th>'.$fm_Filelist->get_sort_link( 'path', /* TRANS: file/directory path */ T_('Path') ).'</th>';
		}

		echo '<th class="nowrap">'.$fm_Filelist->get_sort_link( 'name', /* TRANS: file name */ T_('Name') ).'</th>';

		if( $UserSettings->get('fm_showtypes') )
		{ // Show file types column
			echo '<th class="nowrap">'.$fm_Filelist->get_sort_link( 'type', /* TRANS: file type */ T_('Type') ).'</th>';
		}

		echo '<th class="nowrap">'.$fm_Filelist->get_sort_link( 'size', /* TRANS: file size */ T_('Size') ).'</th>';

		if( $UserSettings->get('fm_showdate') != 'no' )
		{ // Show last mod column
			echo '<th class="nowrap">'.$fm_Filelist->get_sort_link( 'lastmod', /* TRANS: file's last change / timestamp */ T_('Last change') ).'</th>';
		}

		if( $UserSettings->get('fm_showfsperms') )
		{ // Show file perms column
			echo '<th class="nowrap">'.$fm_Filelist->get_sort_link( 'perms', /* TRANS: file's permissions (short) */ T_('Perms') ).'</th>';
		}

		if( $UserSettings->get('fm_showfsowner') )
		{ // Show file owner column
			echo '<th class="nowrap">'.$fm_Filelist->get_sort_link( 'fsowner', /* TRANS: file owner */ T_('Owner') ).'</th>';
		}

		if( $UserSettings->get('fm_showfsgroup') )
		{ // Show file group column
			echo '<th class="nowrap">'.$fm_Filelist->get_sort_link( 'fsgroup', /* TRANS: file group */ T_('Group') ).'</th>';
		}

		echo '<th class="lastcol nowrap">'. /* TRANS: file actions; edit, rename, copy, .. */ T_('Actions').'</th>';
		echo '</tr>';
	?>
	</thead>

	<tbody>
	<?php
	param( 'checkall', 'integer', 0 );  // Non-Javascript-CheckAll

	/***********************************************************/
	/*                    MAIN FILE LIST:                      */
	/***********************************************************/
	$countFiles = 0;
	while( $lFile = & $fm_Filelist->get_next() )
	{ // Loop through all Files:
		echo '<tr class="'.( $countFiles%2 ? 'odd' : 'even' ).'">';


		/********************    Checkbox:    *******************/

		echo '<td class="checkbox firstcol">';
		echo '<span name="surround_check" class="checkbox_surround_init">';
		echo '<input title="'.T_('Select this file').'" type="checkbox" class="checkbox"
					name="fm_selected[]" value="'.rawurlencode($lFile->get_rdfp_rel_path()).'" id="cb_filename_'.$countFiles.'"';
		global $checkall;
		if( $checkall || $selected_Filelist->contains( $lFile ) )
		{
			echo ' checked="checked"';
		}
		echo ' />';
		echo '</span>';

		/***********  Hidden info used by Javascript:  ***********/

		global $mode;
		if( $mode == 'upload' )
		{	// This mode allows to insert img tags into the post...
			// Hidden info used by Javascript:
			echo '<input type="hidden" name="img_tag_'.$countFiles.'" id="img_tag_'.$countFiles
			    .'" value="'.format_to_output( $lFile->get_tag(), 'formvalue' ).'" />';
		}

		echo '</td>';


		/********************  Icon / File type:  *******************/

		echo '<td class="icon_type">';
		if( $UserSettings->get( 'fm_imglistpreview' ) )
		{	// Image preview OR full type:
			if( $lFile->is_dir() )
			{ // Navigate into Directory
				echo '<a href="'.$lFile->get_view_url().'" title="'.T_('Change into this directory').'">'.$lFile->get_icon().' '.T_('Directory').'</a>';
			}
			else
			{
				echo $lFile->get_preview_thumb( 'fulltype' );
			}
		}
		else
		{	// No image preview, small type:
 			if( $lFile->is_dir() )
			{ // Navigate into Directory
				echo '<a href="'.$lFile->get_view_url().'" title="'.T_('Change into this directory').'">'.$lFile->get_icon().'</a>';
			}
			else
			{ // File
				echo $lFile->get_view_link( $lFile->get_icon(), NULL, $lFile->get_icon() );
			}
		}
		echo '</td>';

		/*******************  Path (flatmode): ******************/

		if( $fm_flatmode )
		{
			echo '<td class="filepath">';
			echo dirname($lFile->get_rdfs_rel_path()).'/';
			echo '</td>';
		}


		echo '<td class="fm_filename">';

			/*************  Invalid filename warning:  *************/

			if( !$lFile->is_dir() )
			{
				if( $error_filename = validate_filename( $lFile->get_name() ) )
				{ // TODO: Warning icon with hint
					echo get_icon( 'warning', 'imgtag', array( 'class' => 'filenameIcon', 'title' => $error_filename ) );
				}
			}
			elseif( $error_dirname = validate_dirname( $lFile->get_name() ) )
			{ // TODO: Warning icon with hint
				echo get_icon( 'warning', 'imgtag', array( 'class' => 'filenameIcon', 'title' => $error_dirname ) );
			}

			/****  Open in a new window  (only directories)  ****/

			if( $lFile->is_dir() )
			{ // Directory
				$browse_dir_url = $lFile->get_view_url();
				$popup_url = url_add_param( $browse_dir_url, 'mode=popup' );
				$target = 'evo_fm_'.$lFile->get_md5_ID();

				echo '<a href="'.$browse_dir_url.'" target="'.$target.' " class="filenameIcon"
							title="'.T_('Open in a new window').'" onclick="'
							."return pop_up_window( '$popup_url', '$target' )"
							.'">'.get_icon( 'window_new' ).'</a>';
			}

			/***************  Link ("chain") icon:  **************/

			if( isset($edited_Item) && $current_User->check_perm( 'item_post!CURSTATUS', 'edit', false, $edited_Item ) )
			{	// Offer option to link the file to an Item (or anything else):
				$link_attribs = array();
				$link_action = 'link';
				if( $mode == 'upload' )
				{	// We want the action to happen in the post attachments iframe:
					$link_attribs['target'] = 'attachmentframe';	// TODO: fp> if multiple posts are opened for edit, frames should get unique names
					$link_action = 'link_inpost';
				}
				echo action_icon( T_('Link this file!'), 'link',
							regenerate_url( 'fm_selected', 'action='.$link_action.'&amp;fm_selected[]='.rawurlencode($lFile->get_rdfp_rel_path()) ),
							NULL, NULL, NULL, $link_attribs );
				echo ' ';
			}

			/********************  Filename  ********************/

			if( $lFile->is_dir() )
			{ // Directory
				// Link to open the directory in the curent window
				echo '<a href="'.$browse_dir_url.'">'.$lFile->dget('name').'</a>';
			}
			else
			{ // File
				if( $view_link = $lFile->get_view_link( $lFile->get_name(), NULL, NULL ) )
				{
					echo $view_link;
				}
				else
				{ // File extension unrecognized
					echo $lFile->dget('name');
				}
			}

			/***************  File meta data:  **************/

			echo '<span class="filemeta">';
			// Optionally display IMAGE pixel size:
			if( $UserSettings->get( 'fm_getimagesizes' ) )
			{
				echo ' ('.$lFile->get_image_size( 'widthxheight' ).')';
			}
			// Optionnaly display meta data title:
			if( $lFile->meta == 'loaded' )
			{	// We have loaded meta data for this file:
				echo ' - '.$lFile->title;
			}
			echo '</span>';

		echo '</td>';

		/*******************  File type  ******************/

		if( $UserSettings->get('fm_showtypes') )
		{ // Show file types
			echo '<td class="type">'.$lFile->get_type().'</td>';
		}

		/*******************  File size  ******************/

		echo '<td class="size">'.$lFile->get_size_formatted().'</td>';

		/****************  File time stamp  ***************/

		if( $UserSettings->get('fm_showdate') != 'no' )
		{ // Show last modified datetime (always full in title attribute)
			$lastmod_date = $lFile->get_lastmod_formatted( 'date' );
			$lastmod_time = $lFile->get_lastmod_formatted( 'time' );
			echo '<td class="timestamp" title="'.$lastmod_date.' '.$lastmod_time.'">';
			if( $UserSettings->get('fm_showdate') == 'long' )
			{
				echo '<span class="date">'.$lastmod_date.'</span> ';
				echo '<span class="time">'.$lastmod_time.'</span>';
			}
			else
			{	// Compact format
				echo $lFile->get_lastmod_formatted( 'compact' );
			}
			echo '</td>';
		}

		/****************  File pemissions  ***************/

		if( $UserSettings->get('fm_showfsperms') )
		{ // Show file perms
			echo '<td class="perms">';
			$fm_permlikelsl = $UserSettings->param_Request( 'fm_permlikelsl', 'fm_permlikelsl', 'integer', 0 );

			if( $current_User->check_perm( 'files', 'edit' ) )
			{ // User can edit:
				echo '<a title="'.T_('Edit permissions').'" href="'.regenerate_url( 'fm_selected,action', 'action=edit_perms&amp;fm_selected[]='.rawurlencode($lFile->get_rdfp_rel_path()) ).'">'
							.$lFile->get_perms( $fm_permlikelsl ? 'lsl' : '' ).'</a>';
			}
			else
			{
				echo $lFile->get_perms( $fm_permlikelsl ? 'lsl' : '' );
			}
			echo '</td>';
		}

		/****************  File owner  ********************/

		if( $UserSettings->get('fm_showfsowner') )
		{ // Show file owner
			echo '<td class="fsowner">';
			echo $lFile->get_fsowner_name();
			echo '</td>';
		}

		/****************  File group *********************/

		if( $UserSettings->get('fm_showfsgroup') )
		{ // Show file owner
			echo '<td class="fsgroup">';
			echo $lFile->get_fsgroup_name();
			echo '</td>';
		}

		/*****************  Action icons  ****************/

		echo '<td class="actions lastcol">';

		if( $current_User->check_perm( 'files', 'edit' ) )
		{ // User can edit:
			if( $lFile->is_editable( $current_User->check_perm( 'files', 'all' ) ) )
			{
				echo action_icon( T_('Edit file...'), 'edit', regenerate_url( 'fm_selected', 'action=edit_file&amp;fm_selected[]='.rawurlencode($lFile->get_rdfp_rel_path()) ) );
			}
			else
			{
				echo get_icon( 'edit', 'noimg' );
			}
		}

		echo action_icon( T_('Edit properties...'), 'properties', regenerate_url( 'fm_selected', 'action=edit_properties&amp;fm_selected[]='.rawurlencode($lFile->get_rdfp_rel_path()) ) );

		if( $current_User->check_perm( 'files', 'edit' ) )
		{ // User can edit:
			echo action_icon( T_('Rename'), 'file_rename', regenerate_url( 'fm_selected', 'action=rename&amp;fm_selected[]='.rawurlencode($lFile->get_rdfp_rel_path()) ) );
			echo action_icon( T_('Move'), 'file_move', regenerate_url( 'fm_mode,fm_sources,fm_sources_root', 'fm_mode=file_move&amp;fm_sources[]='.rawurlencode( $lFile->get_rdfp_rel_path() ).'&amp;fm_sources_root='.$fm_Filelist->_FileRoot->ID ) );
			echo action_icon( T_('Copy'), 'file_copy', regenerate_url( 'fm_mode,fm_sources,fm_sources_root', 'fm_mode=file_copy&amp;fm_sources[]='.rawurlencode( $lFile->get_rdfp_rel_path() ).'&amp;fm_sources_root='.$fm_Filelist->_FileRoot->ID ) );
			echo action_icon( T_('Delete'), 'file_delete', regenerate_url( 'fm_selected', 'action=delete&amp;fm_selected[]='.rawurlencode( $lFile->get_rdfp_rel_path() ) ) );
		}
		echo '</td>';

		echo '</tr>';

		$countFiles++;
	}
	// / End of file list..


	/**
	 * @global integer Number of cols for the files table, 6 is minimum.
	 */
	$filetable_cols = 5
		+ (int)$fm_flatmode
		+ (int)$UserSettings->get('fm_showtypes')
		+ (int)($UserSettings->get('fm_showdate') != 'no')
		+ (int)$UserSettings->get('fm_showfsperms')
		+ (int)$UserSettings->get('fm_showfsowner')
		+ (int)$UserSettings->get('fm_showfsgroup')
		+ (int)$UserSettings->get('fm_imglistpreview');


	if( $countFiles == 0 )
	{ // Filelist errors or "directory is empty"
		?>

		<tr>
			<td class="firstcol">&nbsp;</td> <?php /* blueyed> This empty column is needed so that the defaut width:100% style of the main column below makes the column go over the whole screen */ ?>
			<td class="lastcol" colspan="<?php echo $filetable_cols - 1 ?>" id="fileman_error">
				<?php
					if( ! $Messages->count('fl_error') )
					{ // no Filelist errors, the directory must be empty
						$Messages->add( T_('No files found.')
							.( $fm_Filelist->is_filtering() ? '<br />'.T_('Filter').': &laquo;'.$fm_Filelist->get_filter().'&raquo;' : '' ), 'fl_error' );
					}
					$Messages->display( '', '', true, 'fl_error', 'log_error' );
				?>
			</td>
		</tr>

		<?php
	}
	else
	{
		// -------------
		// Footer with "check all", "with selected: ..":
		// --------------
		?>
		<tr class="listfooter firstcol lastcol">
			<td colspan="<?php echo $filetable_cols ?>">

			<?php
			echo $Form->check_all();

			$field_options = array();

			if( $mode != 'upload' && ($fm_Filelist->get_root_type() == 'collection' || !empty($Blog)) )
			{	// We are browsing files for a collection:
				// fp> TODO: use current as default but let user choose into which blog he wants to post
				$field_options['make_post'] = T_('Make one post (including all images)');
				$field_options['make_posts'] = T_('Make multiple posts (1 per image)');
			}

			if( $current_User->check_perm( 'files', 'edit' ) )
			{ // User can edit:
				$field_options['rename'] = T_('Rename files...');
				$field_options['delete'] = T_('Delete files...');
				// NOTE: No delete confirmation by javascript, we need to check DB integrity!
			}

			// BROKEN ?
			$field_options['download'] = T_('Download files as ZIP archive...');

			/* Not fully functional:
			$field_options['file_copy'] = T_('Copy the selected files...');
			$field_options['file_move'] = T_('Move the selected files...');

			// This is too geeky! Default perms radio options and unchecked radio groups! NO WAY!
			// If you want this feature to be usable by average users you must only have one line per file OR one file for all. You can't mix both.
			// The only way to have both is to have 2 spearate forms: 1 titled "change perms for all files simultaneously"-> submit  and another 1 title "change perms for each file individually" -> another submit
			// POST PHOENIX
			// fplanque>> second thought: changing perms for multiple files at once is useful. BUT assigning different perms to several files with ONE form is trying to solve a problem that not even geeks can face once in a lifetime.
			// This has to be simplified to ONE single set of permissions for all selected files. (If you need different perms, click again)
			$field_options['file_perms'] = T_('Change permissions for the selected files...');
			*/

			$Form->switch_layout( 'none' );
			$Form->select_input_array( 'group_action', $action, $field_options, ' &mdash; <strong>'.T_('With selected files').'</strong>' );
			$Form->submit_input( array( 'name'=>'actionArray[group_action]', 'value'=>T_('Go!') ) );
			$Form->switch_layout( NULL );


			if( $mode == 'upload' )
			{	// We are uploading in a popup opened by an edit screen
				?>
				&mdash;
				<input class="ActionButton"
					title="<?php echo T_('Insert IMG or link tags for the selected files, directly into the post text'); ?>"
					name="actionArray[img_tag]"
					value="<?php echo T_('Insert IMG/link into post') ?>"
					type="submit"
					onclick="insert_tag_for_selected_files(); return false;" />
				<?php
			}
			?>
			</td>
		</tr>
		<?php
	}
	?>
	</tbody>
</table>
<?php
	$Form->end_form();

	if( $countFiles )
	{{{ // include JS
		// TODO: remove these javascript functions to an external .js file and include them through add_headline()
		?>
		<script type="text/javascript">
			<!--
			/**
			 * Check if files are selected.
			 *
			 * This should be used as "onclick" handler for "With selected" actions (onclick="return check_if_selected_files();").
			 * @return boolean true, if something is selected, false if not.
			 */
			function check_if_selected_files()
			{
				elems = document.getElementsByName( 'fm_selected[]' );
				var checked = 0;
				for( i = 0; i < elems.length; i++ )
				{
					if( elems[i].checked )
					{
						checked++;
					}
				}
				if( !checked )
				{
					alert( '<?php echo TS_('Nothing selected.') ?>' );
					return false;
				}
				else
				{
					return true;
				}
			}

			/**
			 * Insert IMG tags into parent window for selected files:
			 */
			function insert_tag_for_selected_files()
			{
				var elems = document.getElementsByName( 'fm_selected[]' );
				var snippet = '';
				for( i = 0; i < elems.length; i++ )
				{
					if( elems[i].checked )
					{
						id = elems[i].id.substring( elems[i].id.lastIndexOf('_')+1, elems[i].id.length );
						img_tag_info_field = document.getElementById( 'img_tag_'+id );
						snippet += img_tag_info_field.value + ' ';
					}
				}
				if( ! snippet.length )
				{
					alert( '<?php echo TS_('You must select at least one file!') ?>' );
					return false;
				}
				else
				{
					if (! (window.focus && window.opener))
					{
						return true;
					}
					window.opener.focus();
					textarea_wrap_selection( window.opener.document.getElementById("itemform_post_content"), snippet, '', 1, window.opener.document );
					return true;
				}
			}
			// -->
		</script>
		<?php
	}}}
?>
<!-- End of detailed file list -->
<?php
/*
 * $Log$
 * Revision 1.9  2008/07/11 23:23:19  blueyed
 * Always display full last modification date+time in title of lastmod TD in filelist
 *
 * Revision 1.8  2008/05/31 00:12:13  fplanque
 * wording
 *
 * Revision 1.7  2008/04/14 19:50:51  fplanque
 * enhanced attachments handling in post edit mode
 *
 * Revision 1.6  2008/04/14 17:39:54  fplanque
 * create 1 post with all images attached
 *
 * Revision 1.5  2008/04/14 17:03:52  fplanque
 * "with selected files" cleanup
 *
 * Revision 1.4  2008/04/03 22:03:08  fplanque
 * added "save & edit" and "publish now" buttons to edit screen.
 *
 * Revision 1.3  2008/01/28 20:17:44  fplanque
 * better display of image file linking while in 'upload' mode
 *
 * Revision 1.2  2008/01/21 09:35:29  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 11:00:02  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.8  2007/06/24 22:35:57  fplanque
 * cleanup
 *
 * Revision 1.7  2007/04/26 00:11:10  fplanque
 * (c) 2007
 *
 * Revision 1.6  2007/04/20 01:42:32  fplanque
 * removed excess javascript
 *
 * Revision 1.5  2007/01/26 02:12:06  fplanque
 * cleaner popup windows
 *
 * Revision 1.4  2007/01/25 03:45:49  fplanque
 * deactivated broken feature
 *
 * Revision 1.3  2007/01/25 03:17:00  fplanque
 * visual cleanup for average users
 * geeky stuff preserved as options
 *
 * Revision 1.2  2007/01/25 02:42:01  fplanque
 * cleanup
 *
 * Revision 1.1  2007/01/24 07:18:22  fplanque
 * file split
 *
 * Revision 1.42  2007/01/24 05:57:55  fplanque
 * cleanup / settings
 *
 * Revision 1.41  2007/01/24 03:45:29  fplanque
 * decrap / removed a lot of bloat...
 *
 * Revision 1.40  2007/01/24 02:35:42  fplanque
 * refactoring
 *
 * Revision 1.39  2007/01/24 01:40:14  fplanque
 * Upload tab now stays in context
 *
 * Revision 1.38  2007/01/23 22:30:14  fplanque
 * empty icons cleanup
 *
 * Revision 1.37  2007/01/09 00:55:16  blueyed
 * fixed typo(s)
 *
 * Revision 1.36  2007/01/07 18:42:35  fplanque
 * cleaned up reload/refresh icons & links
 *
 * Revision 1.35  2006/12/24 00:52:57  fplanque
 * Make posts with images - Proof of concept
 *
 * Revision 1.34  2006/12/23 22:53:10  fplanque
 * extra security
 *
 * Revision 1.33  2006/12/22 00:17:05  fplanque
 * got rid of dirty globals
 * some refactoring
 *
 * Revision 1.32  2006/12/14 02:18:23  fplanque
 * fixed navigation
 *
 * Revision 1.31  2006/12/14 01:46:29  fplanque
 * refactoring / factorized image preview display
 *
 * Revision 1.30  2006/12/14 00:33:53  fplanque
 * thumbnails & previews everywhere.
 * this is getting good :D
 *
 * Revision 1.29  2006/12/13 18:10:22  fplanque
 * thumbnail resampling proof of concept
 *
 * Revision 1.28  2006/12/13 03:08:28  fplanque
 * thumbnail implementation design demo
 *
 * Revision 1.27  2006/12/12 19:39:07  fplanque
 * enhanced file links / permissions
 *
 * Revision 1.26  2006/12/12 18:04:53  fplanque
 * fixed item links
 *
 * Revision 1.25  2006/12/07 20:03:32  fplanque
 * Woohoo! File editing... means all skin editing.
 *
 * Revision 1.24  2006/12/07 15:23:42  fplanque
 * filemanager enhanced, refactored, extended to skins directory
 *
 * Revision 1.23  2006/11/24 18:27:25  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>
