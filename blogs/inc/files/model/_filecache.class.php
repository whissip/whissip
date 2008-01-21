<?php
/**
 * This file implements the FileCache class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}
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
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class('_core/model/dataobjects/_dataobjectcache.class.php');

/**
 * FileCache Class
 *
 * @package evocore
 */
class FileCache extends DataObjectCache
{
	/**
	 * Cache for 'root_type:root_in_type_ID:relative_path' -> File object reference
	 * @access private
	 * @var array
	 */
	var $cache_root_and_path = array();

	/**
	 * Constructor
	 */
	function FileCache()
	{
		parent::DataObjectCache( 'File', false, 'T_files', 'file_', 'file_ID' );
	}


	/**
	 * Instantiate a DataObject from a table row and then cache it.
	 *
	 * @param Object Database row
	 * @return Object
	 */
	function & instantiate( & $db_row )
	{
		// Get ID of the object we'ere preparing to instantiate...
		$obj_ID = $db_row->{$this->dbIDname};

 		if( !empty($obj_ID) )
		{	// If the object ID is valid:
	 		if( !isset($this->cache[$obj_ID]) )
			{	// If not already cached:
				// Instantiate a File object for this line:
				$current_File = new File( $db_row->file_root_type, $db_row->file_root_ID, $db_row->file_path ); // COPY!
				// Flow meta data into File object:
				$current_File->load_meta( false, $db_row );
				$this->add( $current_File );
			}
			else
			{	// Already cached:
				$current_File = & $this->cache[$obj_ID];
				// Flow meta data into File object:
				$current_File->load_meta( false, $db_row );
			}
		}

		return $this->cache[$obj_ID];
	}


  /**
	 * Creates an object of the {@link File} class, while providing caching
	 * and making sure that only one reference to a file exists.
	 *
	 * @param string Root type: 'user', 'group' or 'collection'
	 * @param integer ID of the user, the group or the collection the file belongs to...
	 * @param string Subpath for this file/folder, relative the associated root, including trailing slash (if directory)
	 * @param boolean check for meta data?
	 * @return File a {@link File} object
	 */
	function & get_by_root_and_path( $root_type, $root_in_type_ID, $rel_path, $load_meta = false )
	{
		global $Debuglog, $cache_File;

		if( is_windows() )
		{
			$rel_path = strtolower(str_replace( '\\', '/', $rel_path ));
		}

		// Generate cache key for this file:
		$cacheindex = $root_type.':'.$root_in_type_ID.':'.$rel_path;

		if( isset( $this->cache_root_and_path[$cacheindex] ) )
		{	// Already in cache
			$Debuglog->add( 'File retrieved from cache: '.$cacheindex, 'files' );
			$File = & $this->cache_root_and_path[$cacheindex];
			if( $load_meta )
			{	// Make sure meta is loaded:
				$File->load_meta();
			}
		}
		else
		{	// Not in cache
			$Debuglog->add( 'File not in cache: '.$cacheindex, 'files' );
			$File = new File( $root_type, $root_in_type_ID, $rel_path, $load_meta ); // COPY !!
			$this->cache_root_and_path[$cacheindex] = & $File;
		}
		return $File;
	}


}

/*
 * $Log$
 * Revision 1.2  2008/01/21 09:35:28  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 10:59:54  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.7  2007/04/26 00:11:10  fplanque
 * (c) 2007
 *
 * Revision 1.6  2007/01/25 05:09:53  fplanque
 * bugfix
 *
 * Revision 1.5  2006/12/07 20:03:32  fplanque
 * Woohoo! File editing... means all skin editing.
 *
 * Revision 1.4  2006/11/24 18:27:24  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>