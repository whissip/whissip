<?php
/**
 * This file implements the File class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
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
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


// DEBUG: (Turn switch on or off to log debug info for specified category)
$GLOBALS['debug_files'] = false;


load_class( '_core/model/dataobjects/_dataobject.class.php', 'DataObject' );

/**
 * Represents a file or folder on disk. Optionnaly stores meta data from DB.
 *
 * Use {@link FileCache::get_by_root_and_path()} to create an instance.
 * This is based on {@link DataObject} for the meta data.
 *
 * @package evocore
 */
class File extends DataObject
{
	/**
	 * Have we checked for meta data in the DB yet?
	 * @var string
	 */
	var $meta = 'unknown';

	/**
	 * Meta data: Long title
	 * @var string
	 */
	var $title;

	/**
	 * Meta data: ALT text for images
	 * @var string
	 */
	var $alt;

	/**
	 * Meta data: Description
	 * @var string
	 */
	var $desc;

	/**
	 * Meta data: Hash value of file path
	 * @var string
	 */
	var $hash;

	/**
	 * FileRoot of this file
	 * @var Fileroot
	 * @access protected
	 */
	var $_FileRoot;

	/**
	 * Posix subpath for this file/folder, relative the associated root (No trailing slash)
	 * @var string
	 * @access protected
	 */
	var $_rdfp_rel_path;

	/**
	 * Full path for this file/folder, WITHOUT trailing slash.
	 * @var string
	 * @access protected
	 */
	var $_adfp_full_path;

	/**
	 * Directory path for this file/folder, including trailing slash.
	 * @var string
	 * @see get_dir()
	 * @access protected
	 */
	var $_dir;

	/**
	 * Name of this file/folder, without path.
	 * @var string
	 * @access protected
	 */
	var $_name;

	/**
	 * MD5 hash of full pathname.
	 *
	 * This is useful to refer to files in hidden form fields, but might be replaced by the root_ID+relpath.
	 *
	 * @todo fplanque>> get rid of it
	 *
	 * @var string
	 * @see get_md5_ID()
	 * @access protected
	 */
	var $_md5ID;

	/**
	 * Does the File/folder exist on disk?
	 * @var boolean
	 * @see exists()
	 * @access protected
	 */
	var $_exists;

	/**
	 * Is the File a directory?
	 * @var boolean
	 * @see is_dir()
	 * @access protected
	 */
	var $_is_dir;

	/**
	 * File size in bytes.
	 * @var integer
	 * @see get_size()
	 * @access protected
	 */
	var $_size;

	/**
	 * Recursive directory size in bytes.
	 * @var integer
	 * @see get_recursive_size()
	 * @access protected
	 */
	var $_recursive_size;

	/**
	 * UNIX timestamp of last modification on disk.
	 * @var integer
	 * @see get_lastmod_ts()
	 * @see get_lastmod_formatted()
	 * @access protected
	 */
	var $_lastmod_ts;

	/**
	 * Filesystem file permissions.
	 * @var integer
	 * @see get_perms()
	 * @access protected
	 */
	var $_perms;

	/**
	 * File owner. NULL if unknown
	 * @var string
	 * @see get_fsowner_name()
	 * @access protected
	 */
	var $_fsowner_name;

	/**
	 * File group. NULL if unknown
	 * @var string
	 * @see get_fsgroup_name()
	 * @access protected
	 */
	var $_fsgroup_name;

	/**
	 * Is the File an image? NULL if unknown
	 * @var boolean
	 * @see is_image()
	 * @access protected
	 */
	var $_is_image;

	/**
	 * Is the File an audio file? NULL if unknown
	 * @var boolean
	 * @see is_audio()
	 * @access protected
	 */
	var $_is_audio;

	/**
	 * Extension, Mime type, icon, viewtype and 'allowed extension' of the file
	 * @access protected
	 * @see File::get_Filetype
	 * @var Filetype
	 */
	var $Filetype;


	/**
	 * Constructor, not meant to be called directly. Use {@link FileCache::get_by_root_and_path()}
	 * instead, which provides caching and checks that only one object for
	 * a unique file exists (references).
	 *
	 * @param string Root type: 'user', 'group', 'collection' or 'absolute'
	 * @param integer ID of the user, the group or the collection the file belongs to...
	 * @param string Posix subpath for this file/folder, relative to the associated root (no trailing slash)
	 * @param boolean check for meta data?
	 * @return mixed false on failure, File object on success
	 */
	function File( $root_type, $root_ID, $rdfp_rel_path, $load_meta = false )
	{
		global $Debuglog;

		$Debuglog->add( "new File( $root_type, $root_ID, $rdfp_rel_path, load_meta=$load_meta)", 'files' );

		// Call parent constructor
		parent::DataObject( 'T_files', 'file_', 'file_ID', '', '', '', '' );

		$this->delete_restrictions = array(
				array( 'table'=>'T_links', 'fk'=>'link_file_ID', 'field' => 'link_itm_ID', 'msg'=>T_('%d linked items') ),
				array( 'table'=>'T_links', 'fk'=>'link_file_ID', 'field' => 'link_cmt_ID', 'msg'=>T_('%d linked comments') ),
				array( 'table'=>'T_users', 'fk'=>'user_avatar_file_ID', 'msg'=>T_('%d linked users (profile pictures)') ),
			);

		$this->delete_cascades = array(
				array( 'table'=>'T_files__vote', 'fk'=>'fvot_file_ID', 'msg'=>T_('%d votes') ),
			);

		// Memorize filepath:
		$FileRootCache = & get_FileRootCache();
		$this->_FileRoot = & $FileRootCache->get_by_type_and_ID( $root_type, $root_ID );

		// If there's a valid file root, handle extra stuff. This should not get done when the FileRoot is invalid.
		if( $this->_FileRoot )
		{
			$this->_rdfp_rel_path = no_trailing_slash(str_replace( '\\', '/', $rdfp_rel_path ));
			$this->_adfp_full_path = $this->_FileRoot->ads_path.$this->_rdfp_rel_path;
			$this->_name = basename( $this->_adfp_full_path );
			$this->_dir = dirname( $this->_adfp_full_path ).'/';
			$this->_md5ID = md5( $this->_adfp_full_path );

			// Initializes file properties (type, size, perms...)
			$this->load_properties();

			if( $load_meta )
			{ // Try to load DB meta info:
				$this->load_meta();
			}
		}
	}


	/**
	 * Attempt to load meta data.
	 *
	 * Will attempt only once and cache the result.
	 *
	 * @param boolean create meta data in DB if it doesn't exist yet? (generates a $File->ID)
	 * @param object database row containing all fields needed to initialize meta data
	 * @return boolean true if meta data has been loaded/initialized.
	 */
	function load_meta( $force_creation = false, $row = NULL )
	{
		global $DB, $Debuglog;

		if( $this->meta == 'unknown' )
		{ // We haven't tried loading yet:
			if( is_null( $row )	)
			{	// No DB data has been provided:
				$row = $DB->get_row( "
					SELECT * FROM T_files
					 WHERE file_root_type = '".$this->_FileRoot->type."'
					   AND file_root_ID = ".$this->_FileRoot->in_type_ID."
					   AND file_path = ".$DB->quote($this->_rdfp_rel_path),
					OBJECT, 0, 'Load file meta data' );
			}

			// We check that we got something AND that the CASE matches (because of case insensitive collations on MySQL)
			if( $row && $row->file_path == $this->_rdfp_rel_path )
			{ // We found meta data
				$Debuglog->add( "Loaded metadata for {$this->_FileRoot->ID}:{$this->_rdfp_rel_path}", 'files' );
				$this->meta  = 'loaded';
				$this->ID    = $row->file_ID;
				$this->title = $row->file_title;
				$this->alt   = $row->file_alt;
				$this->desc  = $row->file_desc;
				if( isset( $row->file_hash ) )
				{
					$this->hash  = $row->file_hash;
				}

				// Store this in the FileCache:
				$FileCache = & get_FileCache();
				$FileCache->add( $this );
			}
			else
			{ // No meta data...
				$Debuglog->add( sprintf('No metadata could be loaded for %d:%s', $this->_FileRoot ? $this->_FileRoot->ID : 'FALSE', $this->_rdfp_rel_path), 'files' );
				$this->meta = 'notfound';

				if( $force_creation )
				{	// No meta data, we have to create it now!
					$this->dbinsert();
				}
			}
		}

		return ($this->meta == 'loaded');
	}


	/**
	 * Create the file/folder on disk, if it does not exist yet.
	 *
	 * Also sets file permissions.
	 * Also inserts meta data into DB (if file/folder was successfully created).
	 *
	 * @param string type ('dir'|'file')
	 * @param string optional permissions (octal format), otherwise the default from {@link $Settings} gets used
	 * @return boolean true if file/folder was created, false on failure
	 */
	function create( $type = 'file', $chmod = NULL )
	{
		if( $type == 'dir' )
		{ // Create an empty directory:
			$success = @mkdir( $this->_adfp_full_path );
			$this->_is_dir = true; // used by chmod
		}
		else
		{ // Create an empty file:
			$success = touch( $this->_adfp_full_path );
			$this->_is_dir = false; // used by chmod
		}
		$this->chmod( $chmod ); // uses $Settings for NULL

		if( $success )
		{	// The file/folder has been successfully created:

			// Initializes file properties (type, size, perms...)
			$this->load_properties();

			// If there was meta data for this file in the DB:
			// (maybe the file had existed before?)
			// Let's recycle it! :
			if( ! $this->load_meta() )
			{ // No meta data could be loaded, let's make sure localization info gets recorded:
				$this->set( 'root_type', $this->_FileRoot->type );
				$this->set( 'root_ID', $this->_FileRoot->in_type_ID );
				$this->set( 'path', $this->_rdfp_rel_path );
			}

			// Record to DB:
			$this->dbsave();
		}

		return $success;
	}


	/**
	 * Initializes or refreshes file properties (type, size, perms...)
	 */
	function load_properties()
	{
		// Unset values that will be determined (and cached) upon request
		$this->_is_image = NULL;
		$this->_is_audio = NULL;
		$this->_lastmod_ts = NULL;
		$this->_exists = NULL;
		$this->_perms = NULL;
		$this->_size = NULL;
		$this->_recursive_size = NULL;

		if( is_dir( $this->_adfp_full_path ) )
		{	// The File is a directory:
			$this->_is_dir = true;
		}
		else
		{	// The File is a regular file:
			$this->_is_dir = false;
		}
	}


	/**
	 * Does the File/folder exist on disk?
	 *
	 * @return boolean true, if the file or dir exists; false if not
	 */
	function exists()
	{
		if( ! isset($this->_exists) )
		{
			$this->_exists = file_exists( $this->_adfp_full_path );
		}
		return $this->_exists;
	}


	/**
	 * Is the File a directory?
	 *
	 * @return boolean true if the object is a directory, false if not
	 */
	function is_dir()
	{
		return $this->_is_dir;
	}


	/**
	 * Is the File an image?
	 *
	 * Tries to determine if it is and caches the info.
	 *
	 * @return boolean true if the object is an image, false if not
	 */
	function is_image()
	{
		if( is_null( $this->_is_image ) )
		{	// We don't know yet
			$this->_is_image = ( $this->get_image_size() !== false );
		}

		return $this->_is_image;
	}

	/**
	 * Is the File an audio file?
	 *
	 * Tries to determine if it is and caches the info.
	 *
	 * @return boolean true if the object is an audio file, false if not
	 */
	function is_audio()
	{
		if ( is_null( $this->_is_audio ) )
		{
			$this->_is_audio = in_array($this->get_ext(), array('mp3', 'oga'));
		}
		return $this->_is_audio;
	}


	/**
	 * Is the file editable?
	 *
	 * @param mixed true/false allow locked file types? NULL value means that FileType will decide
	 */
	function is_editable( $allow_locked = NULL )
	{
		if( $this->is_dir() )
		{ // we cannot edit dirs
			return false;
		}

		$Filetype = & $this->get_Filetype();
		if( empty($Filetype) || $this->Filetype->viewtype != 'text' )	// we can only edit text files
		{
			return false;
		}

		// user can edit only allowed file types
		return $Filetype->is_allowed( $allow_locked );
	}


	/**
	 * Get the File's Filetype object (or NULL).
	 *
	 * @return Filetype The Filetype object or NULL
	 */
	function & get_Filetype()
	{
		if( ! isset($this->Filetype) )
		{
			// Create the filetype with the extension of the file if the extension exist in database
			if( $ext = $this->get_ext() )
			{ // The file has an extension, load filetype object
				$FiletypeCache = & get_FiletypeCache();
				$this->Filetype = & $FiletypeCache->get_by_extension( strtolower( $ext ), false );
			}

			if( ! $this->Filetype )
			{ // remember as being retrieved.
				$this->Filetype = false;
			}
		}
		$r = $this->Filetype ? $this->Filetype : NULL;
		return $r;
	}


	/**
	 * Get the File's ID (MD5 of path and name)
	 *
	 * @return string
	 */
	function get_md5_ID()
	{
		return $this->_md5ID;
	}


	/**
	 * Get a member param by its name
	 *
	 * @param mixed Name of parameter
	 * @return mixed Value of parameter
	 */
	function get( $parname )
	{
		switch( $parname )
		{
			case 'name':
				return $this->_name;

			default:
				return parent::get( $parname );
		}
	}


	/**
	 * Get the File's name.
	 *
	 * @return string
	 */
	function get_name()
	{
		return $this->_name;
	}


	/**
	 * Get the name prefixed either with "Directory" or "File".
	 *
	 * Returned string is localized.
	 *
	 * @return string
	 */
	function get_prefixed_name()
	{
		if( $this->is_dir() )
		{
			return sprintf( T_('Directory &laquo;%s&raquo;'), $this->_name );
		}
		else
		{
			return sprintf( T_('File &laquo;%s&raquo;'), $this->_name );
		}
	}


	/**
	 * Get the File's directory.
	 *
	 * @return string
	 */
	function get_dir()
	{
		return $this->_dir;
	}


	/**
	 * Get the file posix path relative to it's root (no trailing /)
	 *
	 * @return string full path
	 */
	function get_rdfp_rel_path()
	{
		return $this->_rdfp_rel_path;
	}


	/**
	 * Get the file path relative to it's root, WITH trailing slash.
	 *
	 * @return string full path
	 */
	function get_rdfs_rel_path()
	{
		return $this->_rdfp_rel_path.( $this->_is_dir ? '/' : '' );
	}


	/**
	 * Get the full path (directory and name) to the file.
	 *
	 * If the File is a directory, the Path ends with a /
	 *
	 * @return string full path
	 */
	function get_full_path()
	{
		return $this->_adfp_full_path.( $this->_is_dir ? '/' : '' );
	}


	/**
	 * Get the absolute file url if the file is public
	 * Get the getfile.php url if we need to check permission before delivering the file
	 */
	function get_url()
	{
		global $public_access_to_media, $htsrv_url;

		if( $this->is_dir() )
		{ // Directory
			if( $public_access_to_media )
			{ // Public access: full path
				$url = $this->_FileRoot->ads_url.$this->get_rdfs_rel_path().'?mtime='.$this->get_lastmod_ts();
			}
			else
			{ // No Access
				// TODO: dh> why can't this go through the FM, preferably opening in a popup, if the user has access?!
				//           (see get_view_url)
				// fp> the FM can do anything as long as this function does not send back an URL to something that is actually private.
				debug_die( 'Private directory! ');
			}
		}
		else
		{ // File
			if( $public_access_to_media )
			{ // Public Access : full path
				$url = $this->_FileRoot->ads_url.no_leading_slash($this->_rdfp_rel_path).'?mtime='.$this->get_lastmod_ts();
			}
			else
			{ // Private Access: doesn't show the full path
				$url = $this->get_getfile_url();
			}
		}
		return $url;
	}


	/**
	 * Get location of file with its root (for display)
	 */
	function get_root_and_rel_path()
	{
		return $this->_FileRoot->name.':'.$this->get_rdfs_rel_path();
	}


	/**
	 * Get the File's FileRoot.
	 *
	 * @return FileRoot
	 */
	function & get_FileRoot()
	{
		return $this->_FileRoot;
	}


	/**
	 * Get the file's extension.
	 *
	 * @return string the extension
	 */
	function get_ext()
	{
		if( preg_match('/\.([^.]+)$/', $this->_name, $match) )
		{
			return $match[1];
		}
		else
		{
			return '';
		}
	}


	/**
	 * Get the file type as a descriptive localized string.
	 *
	 * @return string localized type name or 'Directory' or 'Unknown'
	 */
	function get_type()
	{
		if( isset( $this->_type ) )
		{ // The type is already cached for this object:
			return $this->_type;
		}

		if( $this->is_dir() )
		{
			$this->_type = T_('Directory');
			return $this->_type;
		}

		$Filetype = & $this->get_Filetype();
		if( isset( $Filetype->mimetype ) )
		{
			$this->_type = $Filetype->name;
			return $this->_type;
		}

		$this->_type = T_('Unknown');
		return $this->_type;
	}


	/**
	 * Get file/dir size in bytes.
	 *
	 * For the recursive size of a directory see {@link get_recursive_size()}.
	 *
	 * @return integer bytes
	 */
	function get_size()
	{
		if( ! isset($this->_size) )
		{
			$this->_size = @filesize( $this->_adfp_full_path );
		}
		return $this->_size;
	}


	/**
	 * Get timestamp of last modification.
	 *
	 * @return integer Timestamp
	 */
	function get_lastmod_ts()
	{
		if( ! isset($this->_lastmod_ts) )
		{
			$this->_lastmod_ts = @filemtime( $this->_adfp_full_path );
		}
		return $this->_lastmod_ts;
	}


	/**
	 * Get date/time of last modification, formatted.
	 *
	 * @param string date format or 'date' or 'time' for default locales.
	 * @return string locale formatted date/time
	 */
	function get_lastmod_formatted( $format = '#' )
	{
		global $localtimenow;

		$lastmod_ts = $this->get_lastmod_ts();

		switch( $format )
		{
			case 'date':
				return date_i18n( locale_datefmt(), $lastmod_ts );

			case 'time':
				return date_i18n( locale_timefmt(), $lastmod_ts );

			case 'compact':
				$age = $localtimenow - $lastmod_ts;
				if( $age < 3600 )
				{	// Less than 1 hour: return full time
					return date_i18n( 'H:i:s', $lastmod_ts );
				}
				if( $age < 86400 )
				{	// Less than 24 hours: return compact time
					return date_i18n( 'H:i', $lastmod_ts );
				}
				if( $age < 31536000 )
				{	// Less than 365 days: Month and day
					return date_i18n( 'M, d', $lastmod_ts );
				}
				// Older: return yeat
				return date_i18n( 'Y', $lastmod_ts );
				break;

			case '#':
				default:
				$format = locale_datefmt().' '.locale_timefmt();
				return date_i18n( $format, $lastmod_ts );
		}
	}


	/**
	 * Get permissions
	 *
	 * Possible return formats are:
	 *   - 'raw'=integer
	 *   - 'lsl'=string like 'ls -l'
	 *   - 'octal'=3 digits
	 *
	 * Default value:
	 *   - 'r'/'r+w' for windows
	 *   - 'octal' for other OS
	 *
	 * @param string type, see desc above.
	 * @return mixed permissions
	 */
	function get_perms( $type = NULL )
	{
		if( ! isset($this->_perms) )
		{
			$this->_perms = @fileperms( $this->_adfp_full_path );
		}
		switch( $type )
		{
			case 'raw':
				return $this->_perms;

			case 'lsl':
				$sP = '';

				if(($this->_perms & 0xC000) == 0xC000)     // Socket
					$sP = 's';
				elseif(($this->_perms & 0xA000) == 0xA000) // Symbolic Link
					$sP = 'l';
				elseif(($this->_perms & 0x8000) == 0x8000) // Regular
					$sP = '&minus;';
				elseif(($this->_perms & 0x6000) == 0x6000) // Block special
					$sP = 'b';
				elseif(($this->_perms & 0x4000) == 0x4000) // Directory
					$sP = 'd';
				elseif(($this->_perms & 0x2000) == 0x2000) // Character special
					$sP = 'c';
				elseif(($this->_perms & 0x1000) == 0x1000) // FIFO pipe
					$sP = 'p';
				else                                   // UNKNOWN
					$sP = 'u';

				// owner
				$sP .= (($this->_perms & 0x0100) ? 'r' : '&minus;') .
				       (($this->_perms & 0x0080) ? 'w' : '&minus;') .
				       (($this->_perms & 0x0040) ? (($this->_perms & 0x0800) ? 's' : 'x' )
				                                 : (($this->_perms & 0x0800) ? 'S' : '&minus;'));

				// group
				$sP .= (($this->_perms & 0x0020) ? 'r' : '&minus;') .
				       (($this->_perms & 0x0010) ? 'w' : '&minus;') .
				       (($this->_perms & 0x0008) ? (($this->_perms & 0x0400) ? 's' : 'x' )
				                                 : (($this->_perms & 0x0400) ? 'S' : '&minus;'));

				// world
				$sP .= (($this->_perms & 0x0004) ? 'r' : '&minus;') .
				       (($this->_perms & 0x0002) ? 'w' : '&minus;') .
				       (($this->_perms & 0x0001) ? (($this->_perms & 0x0200) ? 't' : 'x' )
				                                 : (($this->_perms & 0x0200) ? 'T' : '&minus;'));
				return $sP;

			case NULL:
				if( is_windows() )
				{
					if( $this->_perms & 0x0080 )
					{
						return 'r+w';
					}
					else return 'r';
				}

			case 'octal':
				return substr( sprintf('%o', $this->_perms), -3 );
		}

		return false;
	}


	/**
	 * Get the owner name of the file.
	 *
	 * @todo Can this be fixed for windows? filegroup() might only return 0 or 1 nad posix_getgrgid() is not available..
	 * @return NULL|string
	 */
	function get_fsgroup_name()
	{
		if( ! isset( $this->_fsgroup_name ) )
		{
			$gid = @filegroup( $this->_adfp_full_path ); // might spit a warning for a dangling symlink

			if( $gid !== false
					&& function_exists( 'posix_getgrgid' ) ) // func does not exist on windows
			{
				$posix_group = posix_getgrgid( $gid );
				if( is_array($posix_group) )
				{
					$this->_fsgroup_name = $posix_group['name'];
				}
				else
				{ // fallback to gid:
					$this->_fsgroup_name = $gid;
				}
			}
		}

		return $this->_fsgroup_name;
	}


	/**
	 * Get the owner name of the file.
	 *
	 * @todo Can this be fixed for windows? fileowner() might only return 0 or 1 nad posix_getpwuid() is not available..
	 * @return NULL|string
	 */
	function get_fsowner_name()
	{
		if( ! isset( $this->_fsowner_name ) )
		{
			$uid = @fileowner( $this->_adfp_full_path ); // might spit a warning for a dangling symlink
			if( $uid !== false
					&& function_exists( 'posix_getpwuid' ) ) // func does not exist on windows
			{
				$posix_user = posix_getpwuid( $uid );
				if( is_array($posix_user) )
				{
					$this->_fsowner_name = $posix_user['name'];
				}
				else
				{ // fallback to uid:
					$this->_fsowner_name = $uid;
				}
			}
		}

		return $this->_fsowner_name;
	}


	/**
	 * Get icon for this file.
	 *
	 * Looks at the file's extension.
	 *
	 * @uses get_icon()
	 * @return string img tag
	 */
	function get_icon()
	{
		if( $this->is_dir() )
		{ // Directory icon:
			$icon = 'folder';
		}
		else
		{
			$Filetype = & $this->get_Filetype();
			if( isset( $Filetype->icon ) && $Filetype->icon )
			{ // Return icon for known type of the file
					return $Filetype->get_icon();
			}
			else
			{ // Icon for unknown file type:
				$icon = 'file_unknown';
			}
		}
		// Return Icon for a directory or unknown type file:
		return get_icon( $icon, 'imgtag', array( 'alt'=>$this->get_ext(), 'title'=>$this->get_type() ) );
	}


	/**
	 * Get size of an image or false if not an image
	 *
	 * @todo cache this data (NOTE: we have different params here! - imgsize() does caching already!)
	 *
	 * @uses imgsize()
	 * @param string {@link imgsize()}
	 * @return false|mixed false if the File is not an image, the requested data otherwise
	 */
	function get_image_size( $param = 'widthxheight' )
	{
		return imgsize( $this->_adfp_full_path, $param );
	}


	/**
	 * Get size of the file, formatted to nearest unit (kb, mb, etc.)
	 *
	 * @uses bytesreadable()
	 * @return string size as b/kb/mb/gd; or '&lt;dir&gt;'
	 */
	function get_size_formatted()
	{
		if( $this->is_dir() )
		{
			return /* TRANS: short for '<directory>' */ T_('&lt;dir&gt;');
		}
		else
		{
			return bytesreadable( $this->get_size() );
		}
	}


	/**
	 * Get a complete tag (IMG or A HREF) pointing to this file.
	 *
	 * @param string
	 * @param string NULL for no legend
	 * @param string
	 * @param string
	 * @param string
	 * @param string
	 * @param string
	 * @param string rel attribute of link, usefull for jQuery libraries selecting on rel='...', e-g: lighbox
	 * @param string image class
	 * @param string image align
	 * @param string image rel
	 * @param string image caption/description
	 */
	function get_tag( $before_image = '<div class="image_block">',
	                  $before_image_legend = '<div class="image_legend">', // can be NULL
	                  $after_image_legend = '</div>',
	                  $after_image = '</div>',
	                  $size_name = 'original',
	                  $image_link_to = 'original',
	                  $image_link_title = '',	// can be text or #title# or #desc#
	                  $image_link_rel = '',
	                  $image_class = '',
	                  $image_align = '',
	                  $image_alt = '',
	                  $image_desc = '#' )
	{
		if( $this->is_dir() )
		{	// We can't reference a directory
			return '';
		}

		$this->load_meta();

		if( $this->is_image() )
		{ // Make an IMG link:
			$r = $before_image;

			if( $image_class != '' )
			{
				$image_class = ' class="'.$image_class.'"';
			}

			if( $image_align != '' )
			{
				$image_align =' align="'.$image_align.'"';
			}

			$img_attribs = $this->get_img_attribs($size_name);

			if( $img_attribs['alt'] == '' )
			{
				$img_attribs['alt'] = $image_alt;
			}

			$img = '<img'.get_field_attribs_as_string($img_attribs).$image_class.$image_align.' />';

			if( $image_link_to == 'original' )
			{	// special case
				$image_link_to = $this->get_url();
			}
			if( !empty( $image_link_to ) )
			{
				$a = '<a href="'.$image_link_to.'"';

				if( $image_link_title == '#title#' )
					$image_link_title = $this->title;
				elseif( $image_link_title == '#desc#' )
					$image_link_title = $this->desc;

				if( !empty($image_link_title) )
				{
					$a .= ' title="'.htmlspecialchars($image_link_title).'"';
				}
				if( !empty($image_link_rel) )
				{
					$a .= ' rel="'.htmlspecialchars($image_link_rel).'"';
				}
				$a .= ' id="f'.$this->ID.'"';
				$img = $a.'>'.$img.'</a>';
			}
			$r .= $img;

			if( $image_desc == '#' )
			{
				$image_desc = $this->dget('desc');
			}
			if( !empty( $image_desc ) && !is_null( $before_image_legend ) )
			{
				$r .= $before_image_legend
							.$image_desc		// If this needs to be changed, please document.
							.$after_image_legend;
			}
			$r .= $after_image;
		}
		else
		{	// Make an A HREF link:
			$r = '<a href="'.$this->get_url().'"'
						// title
						.( $this->get('desc') ? ' title="'.$this->dget('desc', 'htmlattr').'"' : '' ).'>'
						// link text
						.( $this->get('title') ? $this->dget('title') : $this->dget('name') ).'</a>';
		}

		return $r;
	}


	/*
	 * Get gallery for code for a directory
	 *
	 * @param array of params
	 * @return string gallery HTML code
	 */
	function get_gallery( $params )
	{
		$params = array_merge( array(
				'before_gallery'        => '<div class="bGallery">',
				'after_gallery'         => '</div>',
				'gallery_image_size'    => 'crop-80x80',
				'gallery_image_limit'   => 1000,
				'gallery_colls'         => 5,
				'gallery_order'			=> '', // 'ASC', 'DESC', 'RAND'
			), $params );

		if( ! $this->is_dir() )
		{	// Not a directory
			return '';
		}
		if( ! $FileList = $this->get_gallery_images( $params['gallery_image_limit'], $params['gallery_order'] ) )
		{	// No images in this directory
			return '';
		}

		$r = $params['before_gallery'];
		$r .= '<table cellpadding="0" cellspacing="3" border="0" class="image_index">';

		$count = 0;
		foreach( $FileList as $l_File )
		{
			// We're linking to the original image, let lighbox (or clone) quick in:
			$link_title = '#title#'; // This title will be used by lightbox (colorbox for instance)
			$link_rel = 'lightbox[g'.$this->ID.']'; // Make one "gallery" per directory.

			$img_tag = $l_File->get_tag( '', NULL, '', '', $params['gallery_image_size'], 'original', $link_title, $link_rel );

			if( $count % $params['gallery_colls'] == 0 ) $r .= "\n<tr>";
			$count++;
			$r .= "\n\t".'<td valign="top">';
			// ======================================
			// Individual table cell

			$r .= '<div class="bGallery-thumbnail">'.$img_tag.'</div>';

			// ======================================
			$r .= '</td>';
			if( $count % $params['gallery_colls'] == 0 ) $r .= "\n</tr>";
		}
		if( $count && ( $count % $params['gallery_colls'] != 0 ) ) $r .= "\n</tr>";

		$r .= '</table>';
		$r .= $params['after_gallery'];

		return $r;
	}


	/*
	 * Get all images in a directory (no recursion)
	 *
	 * @param integer how many images to return
	 * @param string filenames order ASC DESC RAND or empty string
	 * @return array of instantiated File objects or false
	 */
	function get_gallery_images( $limit = 1000, $order = '' )
	{
		if( $filenames = $this->get_directory_files('relative') )
		{
			$FileCache = & get_FileCache();

			switch( strtoupper($order) )
			{
				case 'ASC':
					sort($filenames);
					break;

				case 'DESC':
					rsort($filenames);
					break;

				case 'RAND':
					shuffle($filenames);
					break;
			}

			$i = 1;
			foreach( $filenames as $filename )
			{
				if( $i > $limit )
				{	// We've got enough images
					break;
				}

				/*
				sam2kb> TODO: we may need to filter files by extension first, it doesn't make sence
						to query the database for every single .txt or .zip file.
						The best solution would be to have file MIME type field in DB
				*/
				$l_File = & $FileCache->get_by_root_and_path( $this->_FileRoot->type, $this->_FileRoot->in_type_ID, $filename );
				$l_File->load_meta();

				if( ! $l_File->is_image() )
				{	// Not an image
					continue;
				}
				$Files[] = $l_File;

				$i++;
			}
			if( !empty($Files) )
			{
				return $Files;
			}
		}
		return false;
	}


	/*
	 * Get all files in a directory (no recursion)
	 *
	 * @param string what part of file name to return
	 *		'basename' return file name only e.g. 'bus-stop-ahead.jpg'
	 * 		'ralative' file path relative to '_adfp_full_path' e.g. 'monument-valley/bus-stop-ahead.jpg'
	 *		'absolute' full file path e.g. '/home/user/html/media/shared/global/monument-valley/bus-stop-ahead.jpg'
	 * @return array of files
	 */
	function get_directory_files( $path_type = 'relative' )
	{
		global $Settings;

		$path = trailing_slash( $this->_adfp_full_path );

		if( $dir = @opendir($path) )
		{	// Scan directory and list all files
			$filenames = array();
			while( ($file = readdir($dir)) !== false )
			{
				if( $file == '.' || $file == '..' || $file == $Settings->get('evocache_foldername') )
				{	// Invalid file
					continue;
				}

				// sam2kb> TODO: Do we need to process directories recursively?
				if( ! is_dir($path.$file) )
				{
					switch( $path_type )
					{
						case 'basename':
							$filenames[] = $file;
							break;

						case 'relative':
							$filenames[] = trailing_slash($this->_rdfp_rel_path).$file;
							break;

						case 'absolute':
							$filenames[] = $path.$file;
							break;
					}
				}
			}
			closedir($dir);

			if( !empty($filenames) )
			{
				return $filenames;
			}
		}
		return false;
	}


	/**
	 * Get the "full" size of a file/dir (recursive for directories).
	 * This is used by the FileList.
	 * @return integer Recursive size of the dir or the size alone for a file.
	 */
	function get_recursive_size()
	{
		if( ! isset($this->_recursive_size) )
		{
			if( $this->is_dir() )
				$this->_recursive_size = get_dirsize_recursive( $this->get_full_path() );
			else
				$this->_recursive_size = $this->get_size();
		}
		return $this->_recursive_size;
	}


	/**
	 * Rewrite the file paths, because one the parent folder name was changed - recursive function
	 *
	 * This function should be used just after a folder rename
	 *
	 * @access should be private
	 * @param string relative path for this file's parent directory
	 * @param string full path for this file's parent directory
	 */
	function modify_path ( $rel_dir, $full_dir )
	{
		if( $this->is_dir() )
		{
			$new_rel_dir = $rel_dir.$this->_name.'/';
			$new_full_dir = $full_dir.$this->_name.'/';

			$temp_Filelist = new Filelist( $this->_FileRoot, $this->_adfp_full_path );
			$temp_Filelist->load();

			while ( $temp_File = $temp_Filelist->get_next() )
			{
				$temp_File->modify_path( $new_rel_dir, $new_full_dir );
			}
		}

		$this->load_meta();
		$this->_rdfp_rel_path = $rel_dir.$this->_name;
		$this->_dir = $full_dir;
		$this->_adfp_full_path = $this->_dir.$this->_name;
		$this->_md5ID = md5( $this->_adfp_full_path );

		if( $this->meta == 'loaded' )
		{	// We have meta data, we need to deal with it:
			// unchanged : $this->set( 'root_type', $this->_FileRoot->type );
			// unchanged : $this->set( 'root_ID', $this->_FileRoot->in_type_ID );
			$this->set( 'path', $this->_rdfp_rel_path );
			// Record to DB:
			$this->dbupdate();
		}
		else
		{	// There might be some old meta data to *recycle* in the DB...
			$this->load_meta();
		}
	}


	/**
	 * Rename the file in its current directory on disk.
	 *
	 * Also update meta data in DB.
	 *
	 * @access public
	 * @param string new name (without path!)
	 * @return boolean true on success, false on failure
	 */
	function rename_to( $newname )
	{
		// rename() will fail if newname already exists on windows
		// if it doesn't work that way on linux we need the extra check below
		// but then we have an integrity issue!! :(
		if( file_exists($this->_dir.$newname) )
		{
			return false;
		}

		global $DB;
		$DB->begin();

		$oldname = $this->get_name();

		if( $this->is_dir() )
		{ // modify folder content file paths in db
			$rel_dir = dirname( $this->_rdfp_rel_path ).'/';
			if( $rel_dir == './' )
			{
				$rel_dir = '';
			}
			$rel_dir = $rel_dir.$newname.'/';
			$full_dir = $this->_dir.$newname.'/';

			$temp_Filelist = new Filelist( $this->_FileRoot, $this->_adfp_full_path );
			$temp_Filelist->load();

			while ( $temp_File = $temp_Filelist->get_next() )
			{
				$temp_File->modify_path ( $rel_dir, $full_dir, $paths );
			}
		}

		if( ! @rename( $this->_adfp_full_path, $this->_dir.$newname ) )
		{ // Rename will fail if $newname already exists (at least on windows)
			$DB->rollback();
			return false;
		}

		// Delete thumb caches for old name:
		// Note: new name = new usage : there is a fair chance we won't need the same cache sizes in the new loc.
		$this->rm_cache();

		// Get Meta data (before we change name) (we may need to update it later):
		$this->load_meta();

		$this->_name = $newname;
		$this->Filetype = NULL; // depends on name

		$rel_dir = dirname( $this->_rdfp_rel_path ).'/';
		if( $rel_dir == './' )
		{
			$rel_dir = '';
		}
		$this->_rdfp_rel_path = $rel_dir.$this->_name;

		$this->_adfp_full_path = $this->_dir.$this->_name;
		$this->_md5ID = md5( $this->_adfp_full_path );

		if( $this->meta == 'loaded' )
		{	// We have meta data, we need to deal with it:
			// unchanged : $this->set( 'root_type', $this->_FileRoot->type );
			// unchanged : $this->set( 'root_ID', $this->_FileRoot->in_type_ID );
			$this->set( 'path', $this->_rdfp_rel_path );
			// Record to DB:
			if ( ! $this->dbupdate() )
			{	// Update failed, try to rollback the rename on disk:
				if( ! @rename( $this->_adfp_full_path, $this->_dir.$oldname ) )
				{ // rename failed
					$DB->rollback();
					return false;
				}
				// Maybe needs a specific error message here, the db and the disk is out of sync
				return false;
			}
		}
		else
		{	// There might be some old meta data to *recycle* in the DB...
			// This can happen if there has been a file in the same location in the past and if that file
			// has been manually deleted or moved since then. When the new file arrives here, we'll recover
			// the zombie meta data and we don't reset it on purpose. Actually, we consider that the meta data
			// has been *accidentaly* lost and that the user is attempting to recover it by putting back the
			// file where it was before. Of course the logical way would be to put back the file manually, but
			// experience proves that users are inconsistent!
			$this->load_meta();
		}

		$DB->commit();

		return true;
	}


	/**
	 * Move the file to another location
	 *
	 * Also updates meta data in DB
	 *
	 * @param string Root type: 'user', 'group', 'collection' or 'absolute'
	 * @param integer ID of the user, the group or the collection the file belongs to...
	 * @param string Subpath for this file/folder, relative the associated root (no trailing slash)
	 * @return boolean true on success, false on failure
	 */
	function move_to( $root_type, $root_ID, $rdfp_rel_path )
	{
		// echo "relpath= $rel_path ";

		$rdfp_rel_path = str_replace( '\\', '/', $rdfp_rel_path );
		$FileRootCache = & get_FileRootCache();

		$new_FileRoot = & $FileRootCache->get_by_type_and_ID( $root_type, $root_ID, true );
		$adfp_posix_path = $new_FileRoot->ads_path.$rdfp_rel_path;

		if( ! @rename( $this->_adfp_full_path, $adfp_posix_path ) )
		{
			return false;
		}

		// Delete thumb caches from old location:
		// Note: new location = new usage : there is a fair chance we won't need the same cache sizes in the new loc.
		$this->rm_cache();

		// Get Meta data (before we change name) (we may need to update it later):
		$this->load_meta();

		// Memorize new filepath:
		$this->_FileRoot = & $new_FileRoot;
		$this->_rdfp_rel_path = $rdfp_rel_path;
		$this->_adfp_full_path = $adfp_posix_path;
		$this->_name = basename( $this->_adfp_full_path );
		$this->Filetype = NULL; // depends on name
		$this->_dir = dirname( $this->_adfp_full_path ).'/';
		$this->_md5ID = md5( $this->_adfp_full_path );

		if( $this->meta == 'loaded' )
		{	// We have meta data, we need to deal with it:
			$this->set( 'root_type', $this->_FileRoot->type );
			$this->set( 'root_ID', $this->_FileRoot->in_type_ID );
			$this->set( 'path', $this->_rdfp_rel_path );
			// Record to DB:
			$this->dbupdate();
		}
		else
		{	// There might be some old meta data to *recycle* in the DB...
			// This can happen if there has been a file in the same location in the past and if that file
			// has been manually deleted or moved since then. When the new file arrives here, we'll recover
			// the zombie meta data and we don't reset it on purpose. Actually, we consider that the meta data
			// has been *accidentaly* lost and that the user is attempting to recover it by putting back the
			// file where it was before. Of course the logical way would be to put back the file manually, but
			// experience proves that users are inconsistent!
			$this->load_meta();
		}

		return true;
	}


 	/**
	 * Copy this file to a new location
	 *
	 * Also copy meta data in Object
	 *
	 * @param File the target file (expected to not exist)
	 * @return boolean true on success, false on failure
	 */
	function copy_to( & $dest_File )
	{
		if( ! $this->exists() || $dest_File->exists() )
		{
			return false;
		}

		// TODO: fp> what happens if someone else creates the destination file right at this moment here?
		//       dh> use a locking mechanism.

		if( ! @copy( $this->get_full_path(), $dest_File->get_full_path() ) )
		{	// Note: unlike rename() (at least on Windows), copy() will not fail if destination already exists
			// this is probably a permission problem
			return false;
		}

		// Initializes file properties (type, size, perms...)
		$dest_File->load_properties();

		// Meta data...:
		if( $this->load_meta() )
		{	// We have source meta data, we need to copy it:
			// Try to load DB meta info for destination file:
			$dest_File->load_meta();

			// Copy meta data:
			$dest_File->set( 'title', $this->title );
			$dest_File->set( 'alt'  , $this->alt );
			$dest_File->set( 'desc' , $this->desc );

			// Save meta data:
			$dest_File->dbsave();
		}

		return true;
	}


	/**
	 * Unlink/Delete the file or folder from disk.
	 *
	 * Also removes meta data from DB.
	 *
	 * @access public
	 * @return boolean true on success, false on failure
	 */
	function unlink()
	{
		global $DB;

		$DB->begin();

		// Check if there is meta data to be removed:
		if( $this->load_meta() )
		{ // remove meta data from DB:
			$this->dbdelete();
		}

		// Remove thumb cache:
		$this->rm_cache();

		// Physically remove file from disk:
		if( $this->is_dir() )
		{
			$unlinked =	@rmdir( $this->_adfp_full_path );
		}
		else
		{
			$unlinked =	@unlink( $this->_adfp_full_path );
		}

		if( !$unlinked )
		{
			$DB->rollback();

			return false;
		}

		$this->_exists = false;

		$DB->commit();

		return true;
	}


	/**
	 * Change file permissions on disk.
	 *
	 * @access public
	 * @param string chmod (octal three-digit-format, eg '777'), uses {@link $Settings} for NULL
	 *                    (fm_default_chmod_dir, fm_default_chmod_file)
	 * @return mixed new permissions on success (octal format), false on failure
	 */
	function chmod( $chmod = NULL )
	{
		if( $chmod === NULL )
		{
			global $Settings;

			$chmod = $this->is_dir()
				? $Settings->get( 'fm_default_chmod_dir' )
				: $Settings->get( 'fm_default_chmod_file' );
		}

		if( @chmod( $this->_adfp_full_path, octdec( $chmod ) ) )
		{
			clearstatcache();
			// update current entry
			$this->_perms = fileperms( $this->_adfp_full_path );

			return $this->_perms;
		}
		else
		{
			return false;
		}
	}


	/**
	 * Insert object into DB based on previously recorded changes
	 *
	 * @return boolean true on success, false on failure
	 */
	function dbinsert( )
	{
		global $Debuglog;

		if( $this->meta == 'unknown' )
		{
			debug_die( 'cannot insert File if meta data has not been checked before' );
		}

		if( ($this->ID != 0) || ($this->meta != 'notfound') )
		{
			debug_die( 'Existing file object cannot be inserted!' );
		}

		$Debuglog->add( 'Inserting meta data for new file into db', 'files' );

		// Let's make sure the bare minimum gets saved to DB:
		$this->set_param( 'root_type', 'string', $this->_FileRoot->type );
		$this->set_param( 'root_ID', 'number', $this->_FileRoot->in_type_ID );
		$this->set_param( 'path', 'string', $this->_rdfp_rel_path );
		if( ! $this->is_dir() )
		{ // create hash value only for files but not for folders
			$this->set_param( 'hash', 'string', md5_file( $this->get_full_path() ) );
		}

		// Let parent do the insert:
		$r = parent::dbinsert();

		// We can now consider the meta data has been loaded:
		$this->meta  = 'loaded';

		return $r;
	}


	/**
	 * Update the DB based on previously recorded changes
	 *
	 * @return boolean true on success, false on failure / no changes
	 */
	function dbupdate( )
	{
		if( $this->meta == 'unknown' )
		{
			debug_die( 'cannot update File if meta data has not been checked before' );
		}

		global $DB;

		$DB->begin();

		// Let parent do the update:
		if( ( $r = parent::dbupdate() ) !== false )
		{
			// Update field 'last_touched_ts' of each item that has a link with this edited file
			$LinkCache = & get_LinkCache();
			$links = $LinkCache->get_by_file_ID( $this->ID );
			foreach( $links as $Link )
			{
				$LinkOwner = & $Link->get_LinkOwner();
				if( $LinkOwner != NULL )
				{
					$LinkOwner->item_update_last_touched_date();
				}
			}

			$DB->commit();
		}
		else
		{
			$DB->rollback();
		}

		return $r;
	}


	/**
	 * Get URL to view the file (either with viewer of with browser, etc...)
	 */
	function get_view_url( $always_open_dirs_in_fm = true )
	{
		global $htsrv_url, $public_access_to_media;

		// Get root code
		$root_ID = $this->_FileRoot->ID;

		if( $this->is_dir() )
		{ // Directory
			if( $always_open_dirs_in_fm || ! $public_access_to_media )
			{ // open the dir in the filemanager:
				// fp>> Note: we MUST NOT clear mode, especially when mode=upload, or else the IMG button disappears when entering a subdir
				return regenerate_url( 'root,path', 'root='.$root_ID.'&amp;path='.$this->get_rdfs_rel_path() );
			}
			else
			{ // Public access: direct link to folder:
				return $this->get_url();
			}
		}
		else
		{ // File
			$Filetype = & $this->get_Filetype();
			if( !isset( $Filetype->viewtype ) )
			{
				return NULL;
			}
			switch( $Filetype->viewtype )
			{
				case 'image':
					return  $htsrv_url.'viewfile.php?root='.$root_ID.'&amp;path='.$this->_rdfp_rel_path.'&amp;viewtype=image';

				case 'text':
					return $htsrv_url.'viewfile.php?root='.$root_ID.'&amp;path='.$this->_rdfp_rel_path.'&amp;viewtype=text';

				case 'download':	 // will NOT open a popup and will insert a Content-disposition: attachment; header
					return $this->get_getfile_url();

				case 'browser':		// will open a popup
				case 'external':  // will NOT open a popup
				default:
					return $this->get_url();
			}
		}
	}


	/**
	 * Get Link to view the file (either with viewer of with browser, etc...)
	 */
	function get_view_link( $text = NULL, $title = NULL, $no_access_text = NULL )
	{
		if( is_null( $text ) )
		{	// Use file root+relpath+name by default
			$text = $this->get_root_and_rel_path();
		}

		if( is_null( $title ) )
		{	// Default link title
			$this->load_meta();
			$title = $this->title;
		}

		if( is_null( $no_access_text ) )
		{	// Default text when no access:
			$no_access_text = $text;
		}

		// Get the URL for viewing the file/dir:
		$url = $this->get_view_url( false );

		if( empty($url) )
		{
			return $no_access_text;
		}

		$Filetype = & $this->get_Filetype();
		if( $Filetype && in_array( $Filetype->viewtype, array( 'external', 'download' ) ) )
		{ // Link to open in the curent window
			return '<a href="'.$url.'" title="'.$title.'">'.$text.'</a>';
		}
		else
		{ // Link to open in a new window
			$target = 'evo_fm_'.$this->get_md5_ID();

			// onclick: we unset target attrib and return the return value of pop_up_window() to make the browser not follow the regular href link (at least FF 1.5 needs the target reset)
			return '<a href="'.$url.'" target="'.$target.'"
				title="'.T_('Open in a new window').'" onclick="'
				."this.target = ''; return pop_up_window( '$url', '$target', "
				.(( $width = $this->get_image_size( 'width' ) ) ? ( $width + 100 ) : 750 ).', '
				.(( $height = $this->get_image_size( 'height' ) ) ? ( $height + 150 ) : 550 ).' )">'.$text.'</a>';
		}
	}


	/**
	 * Get link to edit linked file.
	 *
	 * @param string link type ( item, comment )
	 * @param integer ID of the object to link to => will open the FM in link mode
	 * @param string link text
	 * @param string link title
	 * @param string text to display if access denied
	 * @param string page url for the edit action
	 */
	function get_linkedit_link( $link_type = NULL, $link_obj_ID = NULL, $text = NULL, $title = NULL, $no_access_text = NULL,
											$actionurl = '#', $target = '' )
	{
		global $dispatcher;

		if( $actionurl == '#' )
		{
			$actionurl = $dispatcher.'?ctrl=files';
		}

		if( is_null( $text ) )
		{	// Use file root+relpath+name by default
			$text = $this->get_root_and_rel_path();
		}

		if( is_null( $title ) )
		{	// Default link title
			$this->load_meta();
			$title = $this->title;
		}

		if( is_null( $no_access_text ) )
		{	// Default text when no access:
			$no_access_text = $text;
		}

		$url = $this->get_linkedit_url( $link_type, $link_obj_ID, $actionurl );

		if( !empty($target) )
		{
			$target = ' target="'.$target.'"';
		}

		return '<a href="'.$url.'" title="'.$title.'"'.$target.'>'.$text.'</a>';
	}


	/**
	 * Get link edit url for a link object
	 *
	 * @param string link type ( item, comment )
	 * @param integer ID of link object to link to => will open the FM in link mode
	 * @return string
	 */
	function get_linkedit_url( $link_type = NULL, $link_obj_ID = NULL, $actionurl = '#' )
	{
		global $dispatcher;

		if( $actionurl == '#' )
		{
			$actionurl = $dispatcher.'?ctrl=files';
		}

		if( $this->is_dir() )
		{
			$rdfp_path = $this->_rdfp_rel_path;
		}
		else
		{
			$rdfp_path = dirname( $this->_rdfp_rel_path );
		}

		$url_params = 'root='.$this->_FileRoot->ID.'&amp;path='.$rdfp_path.'/';

		if( ! is_null($link_obj_ID) )
		{	// We want to open the filemanager in link mode:
			$url_params .= '&amp;fm_mode=link_object&amp;link_type='.$link_type.'&amp;link_object_ID='.$link_obj_ID;
		}

		// Add param to make the file list highlight this (via JS).
		$url_params .= '&amp;fm_highlight='.rawurlencode($this->_name);

		$url = url_add_param( $actionurl, $url_params );

		return $url;
	}


	/**
	 * Get the thumbnail URL for this file
	 *
	 * @param string
	 */
	function get_thumb_url( $size_name = 'fit-80x80', $glue = '&amp;' )
	{
		global $public_access_to_media, $htsrv_url;

		if( ! $this->is_image() )
		{ // Not an image
			debug_die( 'Can only thumb images');
		}

		if( $public_access_to_media )
		{
			$af_thumb_path = $this->get_af_thumb_path( $size_name, NULL, false );
			if( $af_thumb_path[0] != '!' )
			{ // If the thumbnail was already cached, we could publicly access it:
				if( @is_file( $af_thumb_path ) )
				{	// The thumb IS already in cache! :)
					// Let's point directly into the cache:
					global $Settings;
					$url = $this->_FileRoot->ads_url.dirname($this->_rdfp_rel_path).'/'.$Settings->get( 'evocache_foldername' ).'/'.$this->_name.'/'.$size_name.'.'.$this->get_ext().'?mtime='.$this->get_lastmod_ts();
					return $url;
				}
			}
		}

		// No thumbnail available (at least publicly), we need to go through getfile.php!
		$url = $this->get_getfile_url($glue).$glue.'size='.$size_name;

		return $url;
	}


	/**
	 * Get the URL to access a file through getfile.php.
	 * @return string
	 */
	function get_getfile_url( $glue = '&amp;' )
	{
		global $htsrv_url;
		return $htsrv_url.'getfile.php/'
			// This is for clean 'save as':
			.rawurlencode( $this->_name )
			// This is for locating the file:
			.'?root='.$this->_FileRoot->ID.$glue.'path='.$this->_rdfp_rel_path
			.$glue.'mtime='.$this->get_lastmod_ts(); // TODO: dh> use salt here?!
	}


	/**
	 * Generate the IMG THUMBNAIL tag with all the alt & title if available.
	 * @return string
	 */
	function get_thumb_imgtag( $size_name = 'fit-80x80', $class = '', $align = '', $title = '' )
	{
		global $use_strict;

		if( ! $this->is_image() )
		{ // Not an image
			return '';
		}

		$img_attribs = $this->get_img_attribs($size_name, $title);
		// pre_dump( $img_attribs );

		if( $class )
		{ // add class
			$img_attribs['class'] = $class;
		}

		if( !$use_strict && $align )
		{ // add align
			$img_attribs['align'] = $align;
		}

		return '<img'.get_field_attribs_as_string($img_attribs).' />';
	}


	/**
	 * Returns an array of things like:
	 * - src
	 * - title
	 * - alt
	 * - width
	 * - height
	 *
	 * @param string what size do we want src to link to, can be "original" or a thumnbail size
	 * @param string
	 * @param string
	 * @return array List of HTML attributes for the image.
	 */
	function get_img_attribs( $size_name = 'fit-80x80', $title = NULL, $alt = NULL )
	{
		$img_attribs = array(
				'title' => isset($title) ? $title : $this->get('title'),
				'alt'   => isset($alt) ? $alt : $this->get('alt'),
			);

		if( ! isset($img_attribs['alt']) )
		{ // use title for alt, too
			$img_attribs['alt'] = $img_attribs['title'];
		}
		if( ! isset($img_attribs['alt']) )
		{ // always use empty alt
			$img_attribs['alt'] = '';
		}

		if( $size_name == 'original' )
		{	// We want src to link to the original file
			$img_attribs['src'] = $this->get_url();
			if( ( $size_arr = $this->get_image_size('widthheight_assoc') ) )
			{
				$img_attribs += $size_arr;
			}
		}
		else
		{ // We want src to link to a thumbnail
			$img_attribs['src'] = $this->get_thumb_url( $size_name, '&' );
			$thumb_path = $this->get_af_thumb_path($size_name, NULL, true);
			if( substr($thumb_path, 0, 1) != '!'
				&& ( $size_arr = imgsize($thumb_path, 'widthheight_assoc') ) )
			{ // no error, add width and height attribs
				$img_attribs += $size_arr;
			}
		}

		return $img_attribs;
	}


	/**
	 * Displays a preview thumbnail which is clickable and opens a view popup
	 *
	 * @param string what do do with files that are not images? 'fulltype'
	 * @param boolean TRUE - to init colorbox plugin for images
	 * @return string HTML to display
	 */
	function get_preview_thumb( $format_for_non_images = '', $preview_image = false )
	{
		if( $this->is_image() )
		{	// Ok, it's an image:
			$type = $this->get_type();
			$img_attribs = $this->get_img_attribs( 'fit-80x80', $type, $type );
			$img = '<img'.get_field_attribs_as_string( $img_attribs ).' />';

			if( $preview_image )
			{	// Create link to preview image by colorbox plugin
				$link = '<a href="'.$this->get_url().'" rel="lightbox" id="f'.$this->ID.'">'.$img.'</a>';
			}
			else
			{	// Get link to view the file (fallback to no view link - just the img):
				$link = $this->get_view_link( $img );
			}

			if( ! $link )
			{	// no view link available:
				$link = $img;
			}

			return $link;
		}

		// Not an image...
		switch( $format_for_non_images )
		{
			case 'fulltype':
				// Full: Icon + File type:
				return $this->get_view_link( $this->get_icon() ).' '.$this->get_type();
				break;
		}

		return '';
	}


	/**
	 * Get the full path to the thumbnail cache for this file.
	 *
	 * ads = Absolute Directory Slash
	 *
	 * @param boolean shall we create the dir if it doesn't exist?
	 * @return string absolute path or !error
	 */
	function get_ads_evocache( $create_if_needed = false )
	{
		global $Settings;
		if( strpos( $this->_dir, '/'.$Settings->get( 'evocache_foldername' ).'/' ) !== false )
		{	// We are already in an evocache folder: refuse to go further!
			return '!Recursive caching not allowed';
		}

		$adp_evocache = $this->_dir.$Settings->get( 'evocache_foldername' ).'/'.$this->_name;

		if( $create_if_needed && !is_dir( $adp_evocache ) )
		{	// Create the directory:
			if( ! mkdir_r( $adp_evocache ) )
			{	// Could not create
				return '!'.$Settings->get( 'evocache_foldername' ).' folder read/write error! Check filesystem permissions.';
			}
		}

		return $adp_evocache.'/';
	}


	/**
	 * Delete cache for a file
	 */
	function rm_cache()
	{
		global $Messages, $Settings;

		// Remove cached elts for teh current file:
		$ads_filecache = $this->get_ads_evocache( false );
		if( $ads_filecache[0] == '!' )
		{
			// This creates unwanted noise
			// $Messages->add( 'Cannot remove '.$Settings->get( 'evocache_foldername' ).' for file. - '.$ads_filecache, 'error' );
		}
		else
		{
			rmdir_r( $ads_filecache );

			// In case cache is now empty, delete the folder:
			$adp_evocache = $this->_dir.$Settings->get( 'evocache_foldername' );
			@rmdir( $adp_evocache );
		}
	}


	/**
	 * Get the full path to the thumbnail for this file.
	 *
	 * af = Absolute File
	 *
	 * @param string size name (e.g. "fit-80x80")
	 * @param string mimetype of thumbnail (NULL if we're ready to take whatever is available)
	 * @param boolean shall we create the dir if it doesn't exist?
	 * @return string absolute filename or !error
	 */
	function get_af_thumb_path( $size_name, $thumb_mimetype = NULL, $create_evocache_if_needed = false )
	{
		$Filetype = & $this->get_Filetype();
		if( isset($Filetype) )
		{
			if( empty($thumb_mimetype) )
			{
				$thumb_mimetype = $Filetype->mimetype;
			}
			elseif( $thumb_mimetype != $Filetype->mimetype )
			{
				debug_die( 'Not supported. For now, thumbnails have to have same mime type as their parent file.' );
				// TODO: extract prefered extension of filetypes config
			}
		}
		elseif( !empty($thumb_mimetype) )
		{
			debug_die( 'Not supported. Can\'t generate thumbnail for unknow parent file.' );
		}

		// Get the filename of the thumbnail
		$ads_evocache = $this->get_ads_evocache( $create_evocache_if_needed );
		if( $ads_evocache[0] != '!' )
		{	// Not an error
			return $ads_evocache.$size_name.'.'.$this->get_ext();
		}

		// error
		return $ads_evocache;
	}


	/**
	 * Save thumbnail for file
	 *
	 * @param resource
	 * @param string size name
	 * @param string mimetype of thumbnail
	 * @param integer JPEG image quality
	 */
	function save_thumb_to_cache( $thumb_imh, $size_name, $thumb_mimetype, $thumb_quality = 90 )
	{
		global $Plugins;

		$Plugins->trigger_event( 'BeforeThumbCreate', array(
			  'imh' => & $thumb_imh,
			  'size' => & $size_name,
			  'mimetype' => & $thumb_mimetype,
			  'quality' => & $thumb_quality,
			  'File' => & $this,
			  'root_type' => $this->_FileRoot->type,
			  'root_type_ID' => $this->_FileRoot->in_type_ID,
		  ) );

		$af_thumb_path = $this->get_af_thumb_path( $size_name, $thumb_mimetype, true );
		if( $af_thumb_path[0] != '!' )
		{	// We obtained a path for the thumbnail to be saved:
			return save_image( $thumb_imh, $af_thumb_path, $thumb_mimetype, $thumb_quality );
		}

		return $af_thumb_path;	// !Error code
	}


	/**
	 * Output previously saved thumbnail for file
	 *
	 * @param string size name
	 * @param string mimetype of thumbnail
	 * @param int Modified time of the file (should have been provided as GET param)
	 * @return mixed NULL on success, otherwise string ("!Error code")
	 */
	function output_cached_thumb( $size_name, $thumb_mimetype, $mtime = NULL )
	{
		global $servertimenow;

		$af_thumb_path = $this->get_af_thumb_path( $size_name, $thumb_mimetype, false );
		//pre_dump($af_thumb_path);
		if( $af_thumb_path[0] != '!' )
		{	// We obtained a path for the thumbnail to be saved:
			if( ! file_exists( $af_thumb_path ) )
			{	// The thumbnail was not found...
				global $Settings;
				return '!Thumbnail not found in'.$Settings->get( 'evocache_foldername' ); // WARNING: exact wording match on return
			}

			if( ! is_readable( $af_thumb_path ) )
			{
				return '!Thumbnail read error! Check filesystem permissions.';
			}

			header('Content-Type: '.$thumb_mimetype );
			header('Content-Length: '.filesize( $af_thumb_path ) );
			header('Last-Modified: ' . date("r",$this->get_lastmod_ts()));

			// dh> if( $mtime && $mtime == $this->get_lastmod_ts() )
			// fp> I don't think mtime changes anything to the cacheability of the data
			//header_noexpire(); // Static image
			// attila> set expires on 30 days
			header('Expires: ' . date("r", $servertimenow + 2592000/* 60*60*24*30 = 30 days */ ));

			// Output the content of the file
			readfile( $af_thumb_path );
			return NULL;
		}

		return $af_thumb_path;	// !Error code
	}


	/**
	 * @param LinkOwner
	 */
	function link_to_Object( & $LinkOwner )
	{
		global $DB;

		// Automatically determine default position.
		// First image becomes "teaser", otherwise "aftermore".

		$order = 1;
		$position =  $LinkOwner->get_default_position( $this->is_image() );
		$existing_Links = & $LinkOwner->get_Links();

		// Find highest order
		foreach( $existing_Links as $loop_Link )
		{
			if( $loop_Link->file_ID == $this->ID )
			{ // The file is already linked to this owner
				return;
			}
			$existing_order = $loop_Link->get('order');
			if( $existing_order >= $order )
				$order = $existing_order+1;
		}

		$DB->begin();

		// Load meta data AND MAKE SURE IT IS CREATED IN DB:
		$this->load_meta( true );

		$DB->commit();

		// Let's make the link!
		$LinkOwner->add_link( $this->ID, $position, $order );
	}


	/**
	 * Get link to restricted object
	 *
	 * Used when try to delete a file, which is attached to a post, or to a user
	 *
	 * @param array restriction
	 * @return string message with links to objects
	 */
	function get_restriction_link( $restriction )
	{
		global $DB, $admin_url;

		switch( $restriction['table'] )
		{ // can be restricted to different tables
			case 'T_links':
				switch( $restriction['field'] )
				{
					case 'link_itm_ID': // Items
						$object_ID = 'post_ID';			// related table object ID
						$object_name = 'post_title';	// related table object name

						// link to object
						$link = '<a href="'.$admin_url.'?ctrl=items&action=edit&p=%d">%s</a>';
						$object_query = 'SELECT post_ID, post_title FROM T_items__item'
											.' WHERE post_ID IN'
											.' (SELECT '.$restriction['field']
											.' FROM '.$restriction['table']
											.' WHERE '.$restriction['fk'].' = '.$this->ID.')';
						break;

					case 'link_cmt_ID': // Comments
						$object_ID = 'comment_ID';			// related table object ID
						$object_name = 'comment_ID';	// related table object name

						// link to object
						$link = '<a href="'.$admin_url.'?ctrl=comments&action=edit&comment_ID=%d">'.T_('Comment ').'#%s</a>';
						$object_query = 'SELECT comment_ID, comment_ID FROM T_comments'
											.' WHERE comment_ID IN'
											.' (SELECT '.$restriction['field']
											.' FROM '.$restriction['table']
											.' WHERE '.$restriction['fk'].' = '.$this->ID.')';
						break;

					default:
						// not defined restriction
						debug_die ( 'unhandled restriction field:' . htmlspecialchars ( $restriction['table'].' - '.$restriction['field'] ) );
				}
			break;

			case 'T_users': // Users
				$object_ID = 'user_ID';			// related table object ID
				$object_name = 'user_login';	// related table object name

				// link to object
				$link = '<a href="'.$admin_url.'?ctrl=user&user_tab=avatar&user_ID=%d">%s</a>';
				$object_query = 'SELECT user_ID, user_login FROM T_users'
									.' WHERE '.$restriction['fk'].' = '.$this->ID;
				break;

			default:
				// not defined restriction
				debug_die ( 'unhandled restriction:' . htmlspecialchars ( $restriction['table'] ) );
		}

		$result_link = '';
		$query_result = $DB->get_results( $object_query );
		foreach( $query_result as $row )
		{ // create links for each related object
			$result_link .= '<br/>'.sprintf( $link, $row->$object_ID, $row->$object_name );
		}

		if( ( $count = count($query_result) ) > 0 )
		{ // there are restrictions
			return sprintf( $restriction['msg'].$result_link, $count );
		}
		// no restriction
		return '';
	}


	/**
	 * Get icon with link to go to file browser where this file is highlighted
	 *
	 * @return string Link
	 */
	function get_target_icon()
	{
		global $current_User;

		$r = '';
		if( $current_User->check_perm( 'files', 'view', false, $this->get_FileRoot() ) )
		{	// Check permission
			if( $this->is_dir() )
			{	// Dir
				$title = T_('Locate this directory!');
			}
			else
			{	// File
				$title = T_('Locate this file!');
			}
			$url = $this->get_linkedit_url();
			$r .= '<a href="'.$url.'" title="'.$title.'">'.get_icon( 'locate', 'imgtag', array( 'title' => $title ) ).'</a> ';
		}

		return $r;
	}
}


/*
 * $Log$
 * Revision 1.98  2013/11/06 08:04:08  efy-asimo
 * Update to version 5.0.1-alpha-5
 *
 */
?>