<?php
/**
 * This file implements the Link class, which manages extra links on items.
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
 * {@internal Open Source relicensing agreement:
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

load_class( '_core/model/dataobjects/_dataobject.class.php', 'DataObject' );

/**
 * Item Link
 *
 * @package evocore
 */
class Link extends DataObject
{
	var $ltype_ID = 0;
	var $Item;
	var $Comment;
	/**
	 * @access protected Use {@link get_File()}
	 */
	var $File;


	/**
	 * Constructor
	 *
	 * @param table Database row
	 */
	function Link( stdClass $db_row = NULL )
	{
		// Call parent constructor:
		parent::DataObject( 'T_links', 'link_', 'link_ID',
													'datecreated', 'datemodified', 'creator_user_ID', 'lastedit_user_ID' );

		if( $db_row != NULL )
		{
			$this->ID       = $db_row->link_ID;
			$this->ltype_ID = $db_row->link_ltype_ID;

			// source of link:
			if( $db_row->link_itm_ID != NULL )
			{ // Item
				$ItemCache = & get_ItemCache();
				$this->Item = & $ItemCache->get_by_ID( $db_row->link_itm_ID );
				$this->Comment = NULL;
			}
			else
			{ // Comment
				$CommentCache = & get_CommentCache();
				$this->Comment = $CommentCache->get_by_ID( $db_row->link_cmt_ID );
				$this->Item = NULL;
			}

			$this->file_ID = $db_row->link_file_ID;

			// TODO: dh> deprecated, check where it's used, and fix it.
			$this->File = & $this->get_File();

			$this->position = $db_row->link_position;
			$this->order = $db_row->link_order;
		}
		else
		{	// New object:

		}
	}


	/**
	 * Get {@link File} of the link.
	 *
	 * @return File
	 */
	function & get_File()
	{
		if( ! isset($this->File) )
		{
			if( isset($GLOBALS['files_Module']) )
			{
				$FileCache = & get_FileCache();
				// fp> do not halt on error. For some reason (ahem bug) a file can disappear and if we fail here then we won't be
				// able to delete the link
				$this->File = & $FileCache->get_by_ID( $this->file_ID, false, false );
			}
			else
			{
				$this->File = NULL;
			}
		}
		return $this->File;
	}


	/**
	 * Return type of target for this Link:
	 *
	 * @todo incomplete
	 */
	function target_type()
	{
 		if( !is_null($this->File) )
		{
			return 'file';
		}


		return 'unkown';
	}

}

/*
 * $Log$
 * Revision 1.12  2011/03/03 12:47:29  efy-asimo
 * comments attachments
 *
 * Revision 1.11  2010/11/03 19:44:15  sam2kb
 * Increased modularity - files_Module
 * Todo:
 * - split core functions from _file.funcs.php
 * - check mtimport.ctrl.php and wpimport.ctrl.php
 * - do not create demo Photoblog and posts with images (Blog A)
 *
 * Revision 1.10  2010/02/08 17:53:16  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.9  2009/10/11 03:00:11  blueyed
 * Add "position" and "order" properties to attachments.
 * Position can be "teaser" or "aftermore" for now.
 * Order defines the sorting of attachments.
 * Needs testing and refinement. Upgrade might work already, be careful!
 *
 * Revision 1.8  2009/10/05 23:25:06  blueyed
 * Add Item:get_File, to lazy-instantiate the link's File object; todo
 *
 * Revision 1.7  2009/09/26 12:00:43  tblue246
 * Minor/coding style
 *
 * Revision 1.6  2009/09/25 07:32:52  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.5  2009/09/14 13:17:28  efy-arrin
 * Included the ClassName in load_class() call with proper UpperCase
 *
 * Revision 1.4  2009/03/08 23:57:44  fplanque
 * 2009
 *
 * Revision 1.3  2008/01/21 09:35:31  fplanque
 * (c) 2008
 *
 * Revision 1.2  2007/09/28 02:15:45  fplanque
 * fix
 *
 * Revision 1.1  2007/06/25 11:00:28  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.6  2007/04/26 00:11:12  fplanque
 * (c) 2007
 *
 * Revision 1.5  2006/11/24 18:27:24  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>
