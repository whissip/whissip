<?php
/**
 * This file implements the UI for file viewing.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
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
 * @todo skin compliant header!
 *
 * @version $Id$
 */

/**
 * Load config, init and get the {@link $mode mode param}.
 */
require_once dirname(__FILE__).'/../conf/_config.php';
require_once $inc_path.'/_main.inc.php';


// Check permission (#1):
if( ! isset($current_User) )
{
	debug_die( 'No permissions to view file (not logged in)!', '403 Forbidden' );
}

// We need this param early to check blog perms, if possible
param( 'root', 'string', true, true ); // the root directory from the dropdown box (user_X or blog_X; X is ID - 'user' for current user (default))
if( preg_match( '/^collection_(\d+)$/', $root, $perm_blog ) )
{	// OK, we got a blog ID:
	$perm_blog = $perm_blog[1];
}
else
{	// No blog ID, we will check the global group perm
	$perm_blog = NULL;
}
//pre_dump( $perm_blog );

// Check permission (#2):
$current_User->check_perm( 'files', 'view', true, $perm_blog );


// Load the other params:
param( 'viewtype', 'string', true, true );
param( 'path', 'string', true, true );

if ( false !== strpos( urldecode( $path ), '..' ) )
{
	debug_die( 'Relative pathnames not allowed!' );
}

// Load fileroot infos
$FileRootCache = & get_FileRootCache();
$FileRoot = & $FileRootCache->get_by_ID( $root );

// Create file object
$selected_File = new File( $FileRoot->type , $FileRoot->in_type_ID, $path, true );


headers_content_mightcache( 'text/html' );		// In most situations, you do NOT want to cache dynamic content!
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xml:lang="<?php locale_lang() ?>" lang="<?php locale_lang() ?>" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php echo $selected_File->dget('name').' ('.T_('Preview').')'; ?></title>
	<link href="<?php echo $rsc_url ?>css/viewfile.css" rel="stylesheet" type="text/css" />
</head>

<body>
	<?php

switch( $viewtype )
{
	case 'image':
		/*
		 * Image file view:
		 */
		echo '<div class="img_preview content-type-image">';

		if( $imgSize = $selected_File->get_image_size( 'widthheight' ) )
		{
			echo '<img ';
			if( $alt = $selected_File->dget( 'alt', 'htmlattr' ) )
			{
				echo 'alt="'.$alt.'" ';
			}
			if( $title = $selected_File->dget( 'title', 'htmlattr' ) )
			{
				echo 'title="'.$title.'" ';
			}
			echo 'src="'.$selected_File->get_url().'"'
						.' width="'.$imgSize[0].'" height="'.$imgSize[1].'" />';

			echo '<div class="subline">';
			echo '<p><strong>'.$selected_File->dget( 'title' ).'</strong></p>';
			echo '<p>'.$selected_File->dget( 'desc' ).'</p>';
			echo '<p>'.$selected_File->dget('name').' &middot; ';
			echo $selected_File->get_image_size().' &middot; ';
			echo $selected_File->get_size_formatted().'</p>';
			echo '</div>';

		}
		else
		{
			echo 'error';
		}
		echo '&nbsp;</div>';
		break;

	case 'text':
		echo '<div class="content-type-text">';
 		/*
		 * Text file view:
		 */
		// Display raw file, highlighted

		$showlines = param( 'l', 'integer', 0, true );

		load_funcs( '_ext/geshi/geshi.php' );
		$geshi = new GeSHi();
		$geshi->load_from_file($selected_File->get_full_path()); // autodetects language from ext
		if( $geshi->error == GESHI_ERROR_FILE_NOT_READABLE )
		{
			echo '<p class="error">'.sprintf( T_('The file &laquo;%s&raquo; could not be accessed!'), $selected_File->get_rdfs_rel_path( $selected_File ) ).'</p>';
		}
		else
		{
			// Setup GeSHi
			// TODO: dh> abstract this into some central method, e.g. get_Geshi.
			$geshi->set_header_type(GESHI_HEADER_NONE);
			$geshi->set_tab_width(2);
			if( $showlines ) {
				$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
			}
			$geshi->set_link_target('_blank');
			$geshi->set_line_style('');

			# File header
			echo '<div class="fileheader">';
			echo '<span class="file">';
			echo T_('File').': <strong>'.$selected_File->dget('name').'</strong>';
			echo '</span> ';

			# Get title and description of file - if any
			$info = array();
			foreach( array('title'=>T_('Title'), 'desc'=>T_('Description')) as $k => $kdesc ) {
				$v = $selected_File->dget($k);
				if( ! strlen($v) )
					continue;
				$info[] = sprintf('<span class="file%s">', $k)
					.htmlspecialchars($kdesc).': <strong>'.$selected_File->dget($k).'</strong>';
			}

			# Number of lines
			$info[] = '<span class="filelines"><a href="'.regenerate_url('l', 'l='.(int)!$showlines).'">'
				.( $showlines ? T_('Hide line numbers') : T_('Display line numbers') )
				.'</a></span>';

			# Implode additional info - if any
			if( $info ) {
				echo ' &middot; '.implode(' &middot; ', $info);
			}
			echo '</div>'; // fileheader

			// BODY/CODE:
			echo '<div class="filecode filebody">'.$geshi->parse_code().'</div>';

			echo '<div class="eof">** '.T_('End Of File').' **</div>';
		}
		echo '</div>';
		break;

	default:
		echo '<p class="error">'.sprintf( T_('The file &laquo;%s&raquo; could not be accessed!'), $selected_File->dget('name') ).'</p>';
		break;
}
?>

</body>
</html>

<?php
/*
 * $Log$
 * Revision 1.30  2010/04/22 18:32:14  blueyed
 * viewfile.php: Add content-type-foo classes, namely content-type-image and content-type-text
 *
 * Revision 1.29  2010/04/22 18:28:26  blueyed
 * getfile.php: Memorize root, viewtype and path params.
 *
 * Revision 1.28  2010/02/08 17:51:18  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.27  2010/01/30 18:55:15  blueyed
 * Fix "Assigning the return value of new by reference is deprecated" (PHP 5.3)
 *
 * Revision 1.26  2009/12/04 23:27:49  fplanque
 * cleanup Expires: header handling
 *
 * Revision 1.25  2009/09/25 07:32:52  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.24  2009/08/29 12:23:55  tblue246
 * - SECURITY:
 * 	- Implemented checking of previously (mostly) ignored blog_media_(browse|upload|change) permissions.
 * 	- files.ctrl.php: Removed redundant calls to User::check_perm().
 * 	- XML-RPC APIs: Added missing permission checks.
 * 	- items.ctrl.php: Check permission to edit item with current status (also checks user levels) for update actions.
 * - XML-RPC client: Re-added check for zlib support (removed by update).
 * - XML-RPC APIs: Corrected method signatures (return type).
 * - Localization:
 * 	- Fixed wrong permission description in blog user/group permissions screen.
 * 	- Removed wrong TRANS comment
 * 	- de-DE: Fixed bad translation strings (double quotes + HTML attribute = mess).
 * - File upload:
 * 	- Suppress warnings generated by move_uploaded_file().
 * 	- File browser: Hide link to upload screen if no upload permission.
 * - Further code optimizations.
 *
 * Revision 1.23  2009/03/08 23:57:38  fplanque
 * 2009
 *
 * Revision 1.22  2009/01/13 22:51:28  fplanque
 * rollback / normalized / MFB
 *
 * Revision 1.21  2008/09/28 08:06:03  fplanque
 * Refactoring / extended page level caching
 *
 * Revision 1.20  2008/07/07 05:59:26  fplanque
 * minor / doc / rollback of overzealous indetation "fixes"
 *
 * Revision 1.18  2008/02/19 11:11:16  fplanque
 * no message
 *
 * Revision 1.17  2008/01/21 09:35:23  fplanque
 * (c) 2008
 *
 * Revision 1.16  2007/09/23 18:55:17  fplanque
 * attempting to debloat. The Log class is insane.
 *
 * Revision 1.15  2007/04/26 00:11:14  fplanque
 * (c) 2007
 *
 * Revision 1.14  2006/12/23 22:53:11  fplanque
 * extra security
 *
 * Revision 1.13  2006/12/07 15:23:42  fplanque
 * filemanager enhanced, refactored, extended to skins directory
 *
 * Revision 1.12  2006/11/24 18:27:22  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.11  2006/10/18 00:15:32  blueyed
 * Major rewrite of styleswitcher.js, started out with a single fix and fixed some more.
 *
 * Revision 1.10  2006/08/19 07:56:29  fplanque
 * Moved a lot of stuff out of the automatic instanciation in _main.inc
 *
 * Revision 1.9  2006/05/19 18:15:04  blueyed
 * Merged from v-1-8 branch
 *
 * Revision 1.8.2.1  2006/05/19 15:06:23  fplanque
 * dirty sync
 *
 * Revision 1.8  2006/04/29 01:24:04  blueyed
 * More decent charset support;
 * unresolved issues include:
 *  - front office still forces the blog's locale/charset!
 *  - if there's content in utf8, it cannot get displayed with an I/O charset of latin1
 *
 * Revision 1.7  2006/04/19 20:13:48  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.6  2006/04/04 22:20:29  blueyed
 * "Gracefully" die, if no $current_User set
 *
 * Revision 1.5  2006/03/12 23:08:53  fplanque
 * doc cleanup
 *
 * Revision 1.4  2006/03/12 03:03:32  blueyed
 * Fixed and cleaned up "filemanager".
 *
 * Revision 1.3  2006/02/23 21:11:47  fplanque
 * File reorganization to MVC (Model View Controller) architecture.
 * See index.hml files in folders.
 * (Sorry for all the remaining bugs induced by the reorg... :/)
 *
 * Revision 1.2  2005/12/16 13:50:49  blueyed
 * FileRoot::get_by_ID() from post-phoenix
 *
 * Revision 1.1  2005/12/14 19:36:16  fplanque
 * Enhanced file management
 *
 * Revision 1.17  2005/11/21 18:33:19  fplanque
 * Too many undiscussed changes all around: Massive rollback! :((
 * As said before, I am only taking CLEARLY labelled bugfixes.
 *
 */
?>
