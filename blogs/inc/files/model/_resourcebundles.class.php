<?php
/**
 * This file implements the ResourceBundles class.
 *
 * EXPERIMENTAL: while this has been tested with a default install, there
 *               are quite some TODOs and BUGs left.
 *
 * The class depends on {@link http://csstidy.sourceforge.net/ CSSTidy}
 * (Version 1.4 (unreleased)) and
 * {@link http://code.google.com/p/jsmin-php/ JSmin}.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2008 by Daniel HAHLER - {@link http://daniel.hahler.de/}.
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
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * ResourceBundles hold a set of resource file bundles of CSS and JS files.
 *
 * A CSS bundle contains a list of added CSS files and combines them into a
 * single file. Apart from minimizing the file size this also means fewer
 * HTTP requests (e.g. 1 for a CSS bundle instead of 10 of the single files
 * accumulated in there).
 *
 * This is meant to be used like this:
 * <code>
 * $rb = new ResourceBundles();
 * $rb->add_file('css', $css_file);
 * $rb->add_inline('css', 'div.note { background:#666 }');
 * [...]
 * echo implode("\n", $rb->get_html_headlines());
 * </code>
 *
 * NOTE: file modification time is only considered for the outermost file,
 *       e.g. "style.css", but not the included/imported ones (e.g. "basic.css"),
 *       since that would require to parse/analyze files completely, instead
 *       of only checking if they are bundled already (with the same freshness).
 * TODO: in debug mode (or via special config), check all css dependendencies for changes.
 *
 * TODO: if e.g. get_js_file_contents() fails, the bundle part
 *       should get removed from the key. OTOH this would try to
 *       recreate the bundle everytime!
 * TODO: no reason to include jquery.min.js anymore!
 */
class ResourceBundles
{
	/**
	 * Disable bundling, useful for debugging.
	 * This behaves the same as with a non-writable cache directory.
	 */
	const disabled = false;


	/**
	 * Resource bundles, grouped by type ("js"/"css") and attributes
	 * (serialized).
	 * This contains a list of arrays, with "content_type" and "content" keys.
	 * "content_type" might be "file" or "inline", while "content" holds the
	 * filename or inline content.
	 */
	protected $bundle_parts = array();

	/**
	 * @see ResourceBundles::set_cache_url()
	 */
	protected $cache_url;

	/**
	 * @see ResourceBundles::set_cache_path()
	 */
	protected $cache_path;

	protected $replace_basedir_stack = array();
	protected $replace_attribs_stack = array();

	/**
	 * List of CSS files by attribs key of already imported files.
	 * This is used to prevent duplicate contents to show up in bundles, when
	 * e.g. two files import the same base css file.
	 */
	protected $css_imported_already = array();

	/**
	 * @see ResourceBundles::is_cache_writable()
	 */
	protected $is_cache_writable;

	/**
	 * List of paths/URLs for file lookups.
	 * It also gets used for CSS imports ("@import").
	 * This is required e.g. for absolute imports (beginning with slash) or
	 * files relative to base.
	 * @see ResourceBundles::set_file_base_dirs()
	 */
	protected $file_base_dirs = null;


	/**
	 * Add file's basename to bundle ID?
	 * This is useful to see what a bundle includes, but causes long filenames,
	 * which may cause write failure on Windows (where the file limit appears
	 * to be 233 chars on NTFS/XP).
	 * @var boolean
	 */
	public $add_filenames_to_bundle_id = null;


	/**
	 * Add references to origin into the bundle? (filename for files or
	 * "inline snippet" for inline code).
	 * @var boolean
	 */
	public $add_refs_to_origin = true;


	/**
	 * Use file locking.
	 * @var boolean
	 */
	public $use_locking = true;



	/**
	 * Should bundle part names get added to the bundle id / filename?
	 *
	 * Do not add filenames to bundle on windows (may become too long).
	 * It's not necessery on *nix either, but good for debugging.
	 *
	 * {@internal This is a getter, so that there's no need to
	 * require _misc.funcs.php just for having is_windows() in the
	 * constructor already.}}
	 *
	 * @return boolean
	 */
	public function get_add_filenames_to_bundle_id()
	{
		if( ! isset($this->add_filenames_to_bundle_id) )
		{
			global $debug;
			$this->add_filenames_to_bundle_id = ($debug && ! is_windows());
		}

		return $this->add_filenames_to_bundle_id;
	}


	/**
	 * Add a file to the bundle.
	 * @param string Type ("js" or "css")
	 * @param string Path of the file to add
	 * @param array Optional attribs (e.g. "media" for CSS).
	 *              A useful attribute is "rsc_pack", which will not
	 *              get put into the resulting HTML headline, but can
	 *              be used to have a sitewide bundle (no "rsc_pack"
	 *              given) and page specific CSS (e.g. "rsc_pack"=>"page").
	 *              This will produce two resource bundles and not poison
	 *              the cache with a single huge bundle for different
	 *              pages.
	 * @return boolean False in case $path is empty.
	 */
	public function add_file($type, $path, $attribs = array())
	{
		return $this->add_bundle_part($type, 'file', $path, $attribs);
	}


	/**
	 * Add inline code to the bundle.
	 * @param string Type ("js" or "css")
	 * @param string Inline code / snippet
	 * @param array Optional attribs (e.g. "media" for CSS)
	 * @return boolean False in case $snippet is empty.
	 */
	public function add_inline($type, $snippet, $attribs = array())
	{
		return $this->add_bundle_part($type, 'inline', $snippet, $attribs);
	}


	/**
	 * Add an entry to $bundle_parts
	 *
	 * @param string Type ("js" / "css")
	 * @param string Content Type ("file" / "inline")
	 * @param string Content (File name or inline code)
	 * @param array Optional attributes (e.g. "media" for CSS)
	 *        All "rsc_*" keys get ignored in the resulting HTML
	 *        headline, but are useful to create several different
	 *        bundles. The following keys are useful:
	 *        - rsc_order: optionally define a order (default: 50).
	 *          So if you need some inline code to be before the
	 *          default bundle, use e.g. 30 here.
	 *        - rsc_pack: Useful to create another bundle, for
	 *          code that changes more frequently, e.g. on
	 *          subsections of your site. You could use "rsc_foo"
	 *          to get the same behavior; it "just" causes another
	 *          bundle key. E.g. "rsc_pack"=>"page"
	 * @return boolean False in case $content is empty.
	 */
	protected function add_bundle_part($type, $content_type, $content, $attribs=array())
	{
		if( ! strlen($content) )
		{
			return false;
		}

		// Transform "rsc_orig_clean_content" attrib to a bundle key.
		if( isset($attribs['rsc_orig_clean_content']) )
		{
			$orig_clean_content = $attribs['rsc_orig_clean_content'];
			unset($attribs['rsc_orig_clean_content']);
		}

		ksort($attribs);
		$attribs_key = serialize($attribs);

		if( ! isset($this->bundle_parts[$type][$attribs_key]) )
		{ // Init array for this group of attributes:
			$this->bundle_parts[$type][$attribs_key] = array();
		}

		$bundle_part = array(
			'content_type' => $content_type,
			'content' => $content,
		);
		if( isset($orig_clean_content) )
		{
			$bundle_part['orig_clean_content'] = $orig_clean_content;
		}
		$this->bundle_parts[$type][$attribs_key][] = $bundle_part;

		return true;
	}


	/**
	 * Get list of all HTML headlines (for each used type).
	 * Sort "css" before "js", so the stylesheets are already loaded when JS runs.
	 * @return array
	 */
	public function get_html_headlines()
	{
		$r = array();
		foreach( array('css', 'js') as $type )
		{
			$r = array_merge($r, $this->get_html_headlines_for_type($type));
		}
		return $r;
	}


	/**
	 * Get headline for use in HTML head section,
	 * referencing the bundle of type $type.
	 *
	 * @param string Type ("css", "js")
	 * @return array
	 */
	public function get_html_headlines_for_type($type)
	{
		$r = array();

		if( empty($this->bundle_parts[$type]) )
		{
			return $r;
		}

		$file_data = $this->get_bundle_file_data($type);
		if( empty($file_data) )
		{
			return $r;
		}

		foreach( $file_data as $attribs_key => $bundle_parts )
		{
			$attribs = unserialize($attribs_key);

			foreach($bundle_parts as $bundle_part)
			{
				$content = $bundle_part['content'];
				if( isset($bundle_part['content_mtime']) )
				{ // add mtime of bundle, so browsers will refetch it on cache rebuilding, with only inner files being updated.
					$content .= '?t='.$bundle_part['content_mtime'];
				}
				$r[] = $this->get_html_rsc_headline($type, $bundle_part['content_type'], $content, $attribs);
			}
		}

		return $r;
	}


	/**
	 * Get HTML headline for CSS/JS resource reference.
	 * @param string Type ("css", "js")
	 * @param string Content type ("file", "inline")
	 * @param string href/content
	 * @param array Optional attributes (e.g. "media" for "css")
	 * @return string
	 */
	public function get_html_rsc_headline($type, $content_type, $content, $attribs=array())
	{
		$attribs_str = '';
		foreach( $attribs as $k => $v )
		{
			if( substr($k, 0, 4) == 'rsc_' )
			{ // skip any rsc_* attribs (e.g. "rsc_pack", "rsc_order")
				continue;
			}
			$attribs_str .= ' '.$k.'="'.htmlspecialchars($v).'"';
		}

		switch( $type )
		{
		case 'css':
			switch($content_type)
			{
			case 'file':
				return '<link rel="stylesheet"'.$attribs_str.' href="'.$content.'" type="text/css" />';
			case 'inline':
				return '<style type="text/css"'.$attribs_str.'>'.$content.'</style>';
			}
		case 'js':
			switch($content_type)
			{
			case 'file':
				return '<script type="text/javascript"'.$attribs_str.' src="'.$content.'"></script>';
			case 'inline':
				return '<script type="text/javascript"'.$attribs_str.'>'.$content.'</script>';
			}
		}
		debug_die('Invalid type ('.$type.'/'.$content_type.') for get_html_rsc_headline()!');
	}


	/**
	 * Get the file path to the bundle(s).
	 * This may be more than one, depending on which attribs have been used (one for
	 * each set of attribs, e.g. media=print).
	 * This also commits the bundle to the cache (writes it to disk).
	 *
	 * @param string Type ("css", "js")
	 * @return array Array of bundle parts (array of array
	 *         with keys "content", "content_type");
	 *         indexed by serialized attribs.
	 */
	public function get_bundle_file_data($type)
	{
		$GLOBALS['Timer']->resume('ResourceBundles::get_bundle_file_data');

		// Preprocess/Resolve bundle parts.
		$GLOBALS['Timer']->resume('ResourceBundles::resolve_bundle_parts');
		$this->resolve_bundle_parts($type);
		$GLOBALS['Timer']->pause('ResourceBundles::resolve_bundle_parts');

		// Create the bundles.
		foreach($this->bundle_parts[$type] as $attribs_key => $bundle_parts)
		{
			$attribs = unserialize($attribs_key);

			// Skip bundle parts that should not get compressed.
			if( ! $attribs['rsc_compress'] )
			{
				$r[$attribs_key] = $bundle_parts;
				continue;
			}

			$GLOBALS['Timer']->resume('ResourceBundles::get_bundle_filename');
			$bundle_filename = $this->get_bundle_filename($type, $attribs_key, $bundle_parts);
			$GLOBALS['Timer']->pause('ResourceBundles::get_bundle_filename');

			$bundle_path = $this->get_cache_path().$bundle_filename;

			if( self::disabled || ! file_exists($bundle_path) )
			{
				$bundle_contents = $this->get_bundle_file_contents($type, $attribs_key);

				// Save the new bundle:
				if( self::disabled || ! $this->save_bundle_contents($bundle_path, $bundle_contents) )
				{
					if( self::disabled ) {
						$this->debug_log( sprintf('Disabled for bundle (%s).', $bundle_path) );
					} else {
						$this->debug_log( sprintf('Cannot write bundle (%s)!', $bundle_path), 'error' );
					}
					// Restore "content" from "orig_clean_content".
					foreach($bundle_parts as $k => $v)
					{
						if( isset($v['orig_clean_content']) )
						{ // only set for content_type="file"
							$use_content = $this->get_resolved_href_for_path($v['orig_clean_content']);

							$bundle_parts[$k]['content'] = $use_content;
							unset($bundle_parts[$k]['orig_clean_content']);
						}
					}
					$r[$attribs_key] = $bundle_parts;
					continue;
				}
			}

			$r[$attribs_key] = array(
				array(
				'content' => $this->get_cache_url().$bundle_filename,
				'content_mtime' => filemtime($bundle_path),
				'content_type' => 'file',
				) );
		}

		$GLOBALS['Timer']->pause('ResourceBundles::get_bundle_file_data');
		return $r;
	}


	/**
	 * @param string Type ("js", "css")
	 * @param string Attributes key (serialized)
	 * @return string Content of the bundle
	 */
	protected function get_bundle_file_contents($type, $attribs_key)
	{
		// create file:
		$bundle = '';
		foreach($this->bundle_parts[$type][$attribs_key] as $i => $bundle_part)
		{
			if( $this->add_refs_to_origin )
			{ // add reference to original file / inline code for reference:
				if( $bundle_part['content_type'] == 'file' )
				{
					$ref_name = basename($bundle_part['content']);
				}
				elseif( $bundle_part['content_type'] == 'inline' )
				{
					$ref_name = 'inline snippet';
				}
				$bundle .= '/* == bundle entry #'.($i+1).': '.$ref_name." == */\n";
			}

			if( $bundle_part['content_type'] == 'inline' )
			{
				if( $type == 'js' )
				{
					$bundle .= $this->minify_js($bundle_part['content']);
				}
				else
				{
					$bundle .= $this->minify_css($bundle_part['content']);
				}
			}
			else
			{ // files
				switch($type)
				{
				case 'css':
					// Handle each CSS file separately, to resolve imports properly.
					$this->import_recurse_depth = 0;
					$bundle .= $this->get_css_file_contents($bundle_part['content'], $attribs_key);
					break;
				case 'js':
					$js_min = $this->file_get_contents($bundle_part['content']);
					if( substr($bundle_part['content'], -7) != '.min.js' )
					{ // only minify if filename not ending in ".min.js"
						$js_min = $this->minify_js($js_min);
					}
					$bundle .= $js_min;
					break;
				}
			}
			$bundle .= "\n";
		}

		if($type == 'css')
		{	// Per the W3C specification at http://www.w3.org/TR/REC-CSS2/cascade.html#at-import,
			// @import rules must proceed any other style, so we move those to the top.
			$regexp = '/@import[^;]+;/i';
			preg_match_all($regexp, $bundle, $matches);
			if( $matches[0] )
			{
				$bundle = preg_replace($regexp, '', $bundle);
				$bundle = "/* == Unresolved imports (try configuring file_base_dirs): == */\n"
					.implode('', $matches[0])."\n".$bundle;
			}
		}

		return trim($bundle);
	}


	/**
	 * Get filename for a given bundle.
	 * @param string Type ("css", "js")
	 * @param string Attributes key (serialized attribs)
	 * @param array Bundle parts
	 * @return string
	 */
	public function get_bundle_filename($type, $attribs_key, $bundle_parts)
	{
		// Create ID for this bundle:
		$id = '';
		$mtime = 0;
		$hash = '';

		// Add key from attribs to the ID:
		$id .= 'a'.substr(md5($attribs_key), 0, 5).'-';

		foreach( $bundle_parts as $bundle_part )
		{
			if( $bundle_part['content_type'] == 'file' )
			{
				$file = $bundle_part['content'];
				if( $this->get_add_filenames_to_bundle_id() )
				{
					$id .= basename($file, '.'.$type).'+';
				}
				// Get file's modification time and adjust bundles mtime.
				$fmtime = filemtime($file);
				$mtime = max($mtime, $fmtime);
				// Add filename and file modification time to hash.
				$hash = md5($file.$fmtime.$hash);
			}
			elseif( $bundle_part['content_type'] == 'inline' )
			{
				if( $this->get_add_filenames_to_bundle_id() )
				{
					$id .= 'i_'.substr(md5($bundle_part['content']), 0, 5).'+';
				}
				$hash = md5($bundle_part['content'].$hash);
			}
			else
			{
				debug_die('Invalid content_type ('.$bundle_part['content_type'].')');
			}
		}

		if( $this->get_add_filenames_to_bundle_id() )
		{
			// Limit filename length (this will result in a total of 255):
			if( strlen($id) > 190 )
			{
				$id = substr($id, 0, 190);
			}
			else
			{ // remove last "+"
				$id = substr($id, 0, -1);
			}
			$id .= '-';
		}

		// add hash, so "style.css" files don't collide:
		$id .= md5($hash);
		// Add time of last modification.
		$id .= '-'.$mtime;

		return 'bundle__'.$id.'.'.$type;
	}


	/**
	 * Resolve current bundle parts.
	 * This checks for duplicates and normalized paths.
	 * @param string Type ("css"/"js")
	 * @return array Normalized bundles (also updated in ResourceBundles::$bundle_parts)
	 */
	public function resolve_bundle_parts($type)
	{
		$r = array();

		if( empty($this->bundle_parts[$type]) )
		{
			return $r;
		}

		// Clean bundle parts.
		//  - Resolve paths (this is done late, so e.g. the page's base_path is known)
		//  - Remove duplicates
		// We cache this result for 1 hour, if possible.
		$mc_key = 'resourcebundles_cache_'.$type.'_'
			.md5(serialize($this->bundle_parts[$type])).'_'
			.$_SERVER['SERVER_NAME'].'_'
			.filemtime(__FILE__); // use this file's mtime in the cache key (since logic might have changed)
		$mc_cache_info = get_from_mem_cache($mc_key, /* by ref: */ $success);
		if( $mc_cache_info )
		{
			$this->bundle_parts[$type] = $mc_cache_info;
		}
		else
		{
			$unclean_bundle_parts = $this->bundle_parts[$type];
			$this->bundle_parts[$type] = array();
			foreach( $unclean_bundle_parts as $attribs_key => $bundle_parts )
			{
				// Go through bundle parts, resolve paths and remove duplicates.
				$orig_attribs = unserialize($attribs_key);
				foreach( $bundle_parts as $k => $v )
				{
					// Handle attribs for each bundle part alone.
					// E.g., some might not get compressed, while others can.
					$attribs = $orig_attribs;
					if( $v['content_type'] == 'file' )
					{
						$path = $v['content'];
						// Get clean path and href (as fallback).
						list($clean_path, $clean_href) = $this->get_resolved_filepath($path);

						// Adjust "compress this bundle" setting.
						if( ! $clean_path )
						{
							$attribs['rsc_compress'] = false;
							ksort($attribs);
							if( $clean_href )
							{
								$path = $clean_href;
							}
						}
						else
						{
							$path = $clean_path;
							if( ! isset($attribs['rsc_compress']) )
							{
								$attribs['rsc_compress'] = true;
								ksort($attribs);
							}
						}

						// Check if it has been added already.
						$attribs_key = serialize($attribs);
						if( isset($this->bundle_parts[$type][$attribs_key]) )
						{ // there are entries with the same attributes:
							foreach( $this->bundle_parts[$type][$attribs_key] as $compare_bundle_part )
							{
								if( $compare_bundle_part['content_type'] == 'file'
									&& $compare_bundle_part['content'] == $path )
								{ // already added.
									continue 2;
								}
							}
						}

						// Save orig 'content' value, in case the bundle cannot
						// be written.
						$attribs['rsc_orig_clean_content'] = $clean_href ? $clean_href : $v['content'];
						$this->add_bundle_part($type, 'file', $path, $attribs);
					}
					else
					{ // Just bypass inline snippets as-is.
						if( ! isset($attribs['rsc_compress']) )
						{
							$attribs['rsc_compress'] = true;
						}
						$this->add_bundle_part($type, 'inline', $v['content'], $attribs);
					}
				}
			}

			// Sort by optional "rsc_order":
			$this->bundle_parts[$type] = $this->sort_by_order($this->bundle_parts[$type]);

			// Store info in memory cache.
			set_to_mem_cache($mc_key, $this->bundle_parts[$type], 3600);
		}

		return $this->bundle_parts[$type];
	}


	/**
	 * @return string Path to the cache directory for bundle files
	 */
	public function get_cache_path()
	{
		if( ! isset($this->cache_path) )
		{
			$this->set_cache_path($GLOBALS['media_path'].'cache/rscbundles/');
		}
		return $this->cache_path;
	}


	/**
	 * @return string URL to the cache directory for bundle files
	 */
	public function get_cache_url()
	{
		if( ! isset($this->cache_url) )
		{
			$this->set_cache_url($GLOBALS['media_url'].'cache/rscbundles/');
		}
		return $this->cache_url;
	}


	/**
	 * Set the base file path where resource bundles get stored.
	 * @param string
	 */
	public function set_cache_path($path)
	{
		$this->cache_path = get_canonical_path($path);

		// Create it, if it does not exist yet.
		if( ! is_dir($this->cache_path) )
		{
			if( @mkdir_r($this->cache_path) )
			{
				global $Settings;
				$chmod = $Settings->get('fm_default_chmod_dir');
				if( ! empty($chmod) )
				{
					chmod( $this->cache_path, octdec($chmod) );
				}
			}
			else
			{
				$this->debug_log(sprintf('Could not create cache path (%s)!', $this->cache_path), 'error');
			}
		}
	}


	/**
	 * Set the base URL where resource bundles get served from.
	 * @param string
	 */
	public function set_cache_url($url)
	{
		$this->cache_url = $url;
	}


	/**
	 * Set base directories for path resolving, e.g. CSS imports.
	 * @param array List of base directories (keys) and their URL (value).
	 */
	public function set_file_base_dirs(array $base_dirs)
	{
		$this->file_base_dirs = array();
		$this->add_file_base_dirs($base_dirs);
	}


	/**
	 * Add base directories for path resolving, e.g. CSS imports.
	 * @param array List of base directories (keys) and their URL (value).
	 */
	function add_file_base_dirs(array $base_dirs)
	{
		$this->file_base_dirs = $this->get_file_base_dirs(); // implicit init with defaults
		foreach($base_dirs as $path => $url)
		{
			if( ! ($path = get_canonical_path($path)) )
			{
				debug_die('add_file_base_dirs: Invalid path ('.var_export($path, true).').');
			}
			// NOTE: $url might need to get handled by get_canonical_url / which respects "//" in protocol.

			if( isset($this->file_base_dirs[$path]) ) {
				// only use the first one
				continue;
			}

			$this->file_base_dirs[$path] = $url;
		}
	}


	/**
	 * Get base dirs that get looked up for relative paths.
	 * @return array
	 */
	public function get_file_base_dirs()
	{
		if( ! isset($this->file_base_dirs) )
		{
			global $basepath, $baseurl;
			$this->set_file_base_dirs(array($basepath=>$baseurl));
		}
		return $this->file_base_dirs;
	}


	/**
	 * Test if cache dir is writable, otherwise we fail on add().
	 *
	 * @return boolean
	 */
	public function is_cache_writable()
	{
		if( is_null($this->is_cache_writable) )
		{
			$cache_path = $this->get_cache_path();
			$test_file = $cache_path.'bundle__test_write';

			@touch( $test_file );
			$this->is_cache_writable = file_exists($test_file);
			if( ! $this->is_cache_writable ) {
				$this->debug_log( sprintf('Cache dir (%s) is not writable or does not exist. Please fix this.', $cache_path), 'error' );
			}
			@unlink( $test_file );

		}
		return $this->is_cache_writable;
	}


	/**
	 * Cleanup cache dir.
	 *
	 * This clears all cached files, which will get recreated on next
	 * access.
	 *
	 * @todo Implement regular maintenance, i.e. remove obsolete bundles
	 *       (which exist with a fresher filemtime)
	 */
	public function cleanup_cache_dir()
	{
		// Get list of bundles (and their revisions):
		$bundles = array();
		foreach( $this->get_cache_dir_files() as $filepath )
		{
			unlink($filepath);
		}
	}


	/**
	 * Get info about the cache dir.
	 *
	 * This gets used in the "Misc" section in "Tools" to display
	 * the cache size.
	 *
	 * @return array Array with keys "files" and "bytes"
	 */
	public function get_cache_dir_info()
	{
		$r = array(
			'files' => 0,
			'bytes' => 0,
		);
		foreach( $this->get_cache_dir_files() as $filepath )
		{
			$r['files']++;
			$r['bytes'] += filesize($filepath);
		}
		return $r;
	}


	/**
	 * Get a list of all files in the cache
	 *
	 * @return array
	 */
	public function get_cache_dir_files()
	{
		$r = array();

		$filenames = glob($this->get_cache_path().'bundle__*.{css,js}', GLOB_BRACE); // might be false on some systems
		if( $filenames ) foreach( $filenames as $filename )
		{
			if( ! preg_match('~^bundle__(.*)\-\d+\.(css|js)$~', basename($filename), $match) )
			{
				continue;
			}
			$r[] = $filename;
		}
		return $r;
	}


	/**
	 * Get the contents of a CSS file.
	 * Imports ("@import") are resolved recursively and replaced by
	 * their contents.
	 *
	 * Compression of the CSS output is done using CSSTidy.
	 *
	 * @uses csstidy
	 * @param string File path
	 * @param string Attributes key, used to not include the same file twice.
	 * @return string|false False if imported/processed already
	 */
	public function get_css_file_contents($path, $attribs_key = NULL)
	{
		if( $attribs_key && isset($this->css_imported_already[$attribs_key][$path]) )
		{ // this has been imported already, skip it
			return false;
		}

		$r = $this->file_get_contents($path);
		if( $r === false )
		{
			return $r;
		}
		if( $attribs_key ) {
			$this->css_imported_already[$attribs_key][$path] = true;
		}
		return $this->get_css_content_processed($r, $path, $attribs_key);
	}


	/**
	 * Process $css: minifies it, resolves imports and fixes relative paths.
	 * @param string CSS
	 * @param string Path
	 * @param string Attributes key, used to not include the same file twice.
	 * @return string
	 */
	public function get_css_content_processed($css, $path, $attribs_key = NULL)
	{
		// NOTE: dh> minifying *.min.css, too: it sanitizes the code (e.g. imports),
		//           which is required for further processing and *.min.css is not
		//           as common (and as much) as *.min.js.
		$r = $this->minify_css($css);

		// basedir of this CSS file, used to resolve relative path names of
		// imports and fix url() (therefore it needs to be absolute):
		$replace_basedir = realpath(dirname($path));
		// TODO: remove this debug/safety-net-code..
		assert('$replace_basedir !== false');
		array_unshift($this->replace_basedir_stack, get_canonical_path($replace_basedir));
		array_unshift($this->replace_attribs_stack, $attribs_key);

		// Adjust paths for url(..), but not imports.
		$r = preg_replace_callback( '~(url\()(.+?)(\))~', array($this, 'callback_fix_css_url'), $r );

		// Match @imports (as compressed by CSSTidy) and include them recursivly.
		// This is different from callback_fix_css_url, since we want to catch
		// absolute references (to file_base_dirs), too.
		$r = preg_replace_callback( '~@import \'(.*?)\';~',
			array($this, 'get_css_file_contents_callback'), $r );

		// Adjust paths for any missed @imports.
		$r = preg_replace_callback( '~(@import \')(.+?)(\')~', array($this, 'callback_fix_css_url'), $r );

		array_shift($this->replace_basedir_stack);
		array_shift($this->replace_attribs_stack);

		return $r;
	}


	/**
	 * Fix/adjust URL references in CSS files.
	 *
	 * @param array Match array.
	 *        $m[1]: part before URL/path
	 *        $m[2]: URL/path
	 *        $m[3]: part after URL/path
	 * @return string
	 */
	protected function callback_fix_css_url($m)
	{
		$url = $m[2];

		if( preg_match('~^(\w+:|/)~', $url) ) // match "http://", "//", "/", "data:"
		{ // absolute URL, keep it:
			return $m[0];
		}

		// Get relative path from the cache path to the URL.
		// (relative to where the bundle gets stored)
		$from_dir = $this->get_cache_path();
		$to_path = substr(get_canonical_path($this->replace_basedir_stack[0].$url), 0, -1);

		// Check if path is below document root
		$docroot = $_SERVER['DOCUMENT_ROOT'];
		if( substr($to_path, 0, strlen($docroot)) !== $docroot )
		{ // the target path is not below DOCUMENT_ROOT: we cannot make it relative
			foreach( $this->get_file_base_dirs() as $file_path => $file_url )
			{
				if( substr($to_path, 0, strlen($file_path)) == $file_path ) {
					return $m[1].$file_url.substr($to_path, strlen($file_path)).$m[3];
				}
			}
			$this->debug_log('Could not make resource relative to path outside of document root ('.htmlspecialchars($url).').', 'error');
			// TODO: should make an error bubble up (and maybe prevent the file from being compressed)
		}

		$rel_url = $this->get_relative_path($from_dir, $to_path);

		return $m[1].$rel_url.$m[3];
	}


	/**
	 * Get contents of a CSS file (relative path in $m[1]).
	 *
	 * This gets used as callback.
	 *
	 * @param array Match array ($m[1] contains file reference)
	 * @param string Attributes key, used to not include the same file twice.
	 * @return string
	 */
	protected function get_css_file_contents_callback($m)
	{
		$file_href = $m[1];

		// This should work for relative imports, i.e. relative
		// to the file where the import gets used:
		$base_dirs = array_merge(
			array($this->replace_basedir_stack[0]=>$this->replace_basedir_stack[0]),
			$this->get_file_base_dirs() );
		list($clean_path, $clean_href) = $this->get_resolved_filepath($file_href, $base_dirs);

		if( $clean_path )
		{
			$this->import_recurse_depth++;

			// recurse:
			$recurse_r = $this->get_css_file_contents($clean_path, $this->replace_attribs_stack[0]);

			if( $recurse_r === false ) {
				// this had been imported already
				return '';
			}

			$r = str_repeat("\t", $this->import_recurse_depth)
				."/* ===> bundle import: ".basename($file_href)." === */\n"
				.$recurse_r."\n";

			$this->import_recurse_depth--;
			return $r;
		}
		else
		{	// We cannot resolve the imported file, so leave the @import as is.
			return $m[0];
		}
	}


	/**
	 * Minify given CSS.
	 *
	 * @param string CSS
	 * @return string
	 */
	public function minify_css($s)
	{
		$GLOBALS['Timer']->resume('ResourceBundles::CSSTidy');
		load_class('_ext/csstidy/class.csstidy.php', 'csstidy');
		$csstidy = new csstidy();
		$csstidy->load_template('highest_compression');
		$csstidy->set_cfg('remove_last_;', true);
		$csstidy->set_cfg('remove_bslash', false);
		$csstidy->parse($s);
		$r = $csstidy->print->plain();
		$GLOBALS['Timer']->pause('ResourceBundles::CSSTidy');
		return $r;
	}


	/**
	 * Minify given Javascript.
	 *
	 * @param string Javascript source code
	 * @return string
	 */
	public function minify_js($s)
	{
		$GLOBALS['Timer']->resume('ResourceBundles::JSMin');
		load_class('_ext/jsmin/jsmin.php', 'JSMin');
		try {
			$r = JSMin::minify($s);
		} catch (JSMinException $e) {
			$this->debug_log('Failed to minify JS! Error "'.htmlspecialchars($e).'". JS: ['.htmlspecialchars(var_export($s, true)).']', 'error');
			$r = $s; // use not minified code
		}
		$GLOBALS['Timer']->pause('ResourceBundles::JSMin');
		return trim($r);
	}


	/**
	 * Helper method to get the proper reference for a given file or URL.
	 *
	 * The filename gets checked in this order:
	 *  - absolute URL
	 *  - absolute filename (kept as is)
	 *  - paths relative to base
	 *  - $rsc_path/$rsc_type/ (e.g. 'rsc/js/')
	 *  - $basepath
	 *
	 * @param string File path / href
	 * @param array|null List of base dirs to try $path on (defaults to ResourceBundles::$file_base_dirs)
	 * @return string|false Existing filepath or false.
	 */
	protected function get_resolved_filepath($path, $base_dirs = null)
	{
		global $basepath, $baseurl;
		global $basehost, $baseport, $basesubpath;
		global $base_tag_set, $basesubpath;
		global $rsc_path, $rsc_url;

		$clean_href = $path;
		$clean_path = false;

		// Map $baseurl (but protocol-independent) to file system ($basepath).
		// This will handle require_css/require_js for ("local") URLs.
		// TODO: handle $plugins_url (and $rsc_url etc) differently/before?! (most common use case)
		// TODO: use base_dirs (mechanism) for this list
		$clean_path = preg_replace('~^(https?:)?//'.preg_quote($basehost.($baseport?':'.$baseport:null).$basesubpath, '~').'~', $basepath, $path);

		// Remove any query string, like "?version=2":
		if( $pos = strpos($clean_path, '?') )
		{
			$clean_path = substr($clean_path, 0, $pos);
		}

		load_funcs('_core/_url.funcs.php');
		if( is_absolute_url($clean_path) )
		{
			$clean_path = false;
		}
		// If it's an absolute pathname, check its existence.
		// If it does not exist, it gets checked later in $base_dirs.
		elseif( is_absolute_pathname($clean_path) )
		{
			if( ! file_exists($clean_path) )
			{
				// Try to provide a "clean href" at least.
				$resolved_href = $this->get_resolved_href_for_path($clean_path);
				if( $resolved_href != $clean_path )
				{
					$clean_href = $resolved_href;
				}

				$clean_path = false;
			}
		}
		else
		{
			// "relative to base"?
			if( isset($base_tag_set)
				&& ($samehost = url_rel_to_same_host($base_tag_set, $baseurl))
				&& $samehost != $base_tag_set )
			{ // could be made relative to $baseurl
				$basetag_subpath = substr($base_tag_set, strlen($baseurl));
				$clean_path = $basepath.$basetag_subpath.$path;
			}
			else
			{
				// Remove subpath from $basepath and append $rsc_file there:
				$clean_path = substr($basepath, 0, 0-strlen($basesubpath)+1).$path;
			}
		}

		if( ! $clean_path || ! @file_exists($clean_path) ) /* made silent to prevent open_basedir restriction warnings */
		{
			$clean_path = false;
			if( is_null($base_dirs) )
			{
				$base_dirs = $this->get_file_base_dirs();
			}
			// Add "$rsc_path/$type" to $file_base_dirs:
			// Get file extension (should be "js" or "css").
			$type = substr(basename($path), strrpos(basename($path), '.')+1);
			if( $type )
			{
				$base_dirs = array_merge($base_dirs,
					array($rsc_path.$type.'/' => $rsc_url.$type.'/'));
			}
			foreach( $base_dirs as $include_path => $include_url )
			{
				$try_path = substr(get_canonical_path($include_path.$path), 0, -1);
				if( file_exists($try_path) )
				{
					$clean_path = $try_path;
					if( $include_url == '/' && $path[0] == '/' )
					{ // Do not create protocol-relative absolute URL.
						$clean_href = $path;
					}
					else
					{
						$clean_href = $include_url.$path;
					}
					break;
				}
			}
			if( ! $clean_path ) {
				$this->debug_log('Could not resolve filepath for '.htmlspecialchars($path).': '.htmlspecialchars(var_export($clean_path, true)));
			}
		}
		return array($clean_path, $clean_href);
	}


	/**
	 * Sort a given list of resource bundles by the optional "rsc_order" attribs value.
	 *
	 * This is a poor man's bubblesort(?) function, to keep the current order
	 * in case the comparison is equal (something that usort() does not provide!)
	 *
	 * @param array List of arrays
	 * @return array List of arrays, sorted by "order" key
	 */
	private function sort_by_order($arr)
	{
		$r = array();

		for( $i = 0, $n = count($arr); $i < $n; $i++ )
		{
			$smallest = array();

			// Find the smallest entry in the remaining part to sort:
			foreach($arr as $cmp_k => $cmp_v)
			{
				if( ! $smallest )
				{
					$smallest = array($cmp_k, $cmp_v);
				}

				$attribs_a = unserialize($cmp_k);
				$attribs_smallest = unserialize($smallest[0]);

				$order_a = is_array($attribs_a) && isset($attribs_a['rsc_order']) ? $attribs_a['rsc_order'] : 50;
				$order_b = is_array($attribs_smallest) && isset($attribs_smallest['rsc_order']) ? $attribs_smallest['rsc_order'] : 50;

				if( $order_a - $order_b < 0 )
				{
					$smallest = array($cmp_k, $cmp_v);
				}
			}
			// Unset the smallest and append it to the result:
			unset($arr[$smallest[0]]);
			$r[$smallest[0]] = $smallest[1];
		}
		return $r;
	}


	/**
	 * Get relative path from $from_dir to $to_path.
	 * @param string "From" directory
	 * @param string "To" path
	 * @return string Relative path or $to_path, if it could not be made relative.
	 */
	public function get_relative_path($from_dir, $to_path)
	{
		// Check windows drive roots. If different, return.
		if( isset($from_dir[1]) && $from_dir[1] == ':'
			&& isset($to_path[1]) && $to_path[1] == ':'
			&& strtolower($from_dir[1]) != strtolower($to_path[1]) )
		{ // different windows roots:
			return $to_path;
		}

		$from_dirs = preg_split('~/~', $from_dir, null, PREG_SPLIT_NO_EMPTY);
		$to_dirs = preg_split('~/~', $to_path, null, PREG_SPLIT_NO_EMPTY);

		$length = min( count($from_dirs), count($to_dirs) );

		$last_common_root = -1;

		for( $i = 0; $i < $length; $i++ )
		{
			if( $from_dirs[$i] != $to_dirs[$i] )
				break;
			$last_common_root = $i;
		}

		if( $last_common_root == -1 )
		{
			return $to_path;
		}

		$relative_path = array();
		for( $i = $last_common_root + 1; $i < count($from_dirs); $i++ )
		{
			$relative_path[] = '..';
		}
		for( $i = $last_common_root + 1; $i < count($to_dirs); $i++ )
		{
			$relative_path[] = $to_dirs[$i];
		}

		return implode('/', $relative_path);
	}


	/**
	 * Try to resolve absolute pathnames to URL.
	 * This is used in case the bundle is not writable (or it should/cannot get compressed).
	 * @param string Filepath / URL
	 * @return string
	 */
	function get_resolved_href_for_path($content)
	{
		load_funcs('_core/_url.funcs.php');
		if( is_absolute_url($content) ) // this handles protocol relative URLs, e.g. "//example.com"
			return $content;

		if( is_absolute_pathname($content) )
		{
			$content = substr(get_canonical_path($content), 0, -1);
			$found_url = false;
			foreach($this->get_file_base_dirs() as $base_path => $base_url)
			{
				if( strpos($content, $base_path) === 0 )
				{
					$found_url = true;
					$content = $base_url.substr($content, strlen($base_path));
					break;
				}
			}
			/*
			// TODO: fallback to include the file inline?! (Very bad!)
			if( ! $found_url )
			{
				$use_content = file_get_contents($use_content);
				$bundle_parts[$k]['content_type'] = 'inline';
			}
			*/
		}
		return $content;
	}


	/**
	 * Get file contents, and remove any BOM markers at the beginning.
	 * @param string File name
	 * @return string
	 */
	function file_get_contents($f)
	{
		$r = file_get_contents($f);
		if( substr($r, 0, 3) == pack("CCC",0xef,0xbb,0xbf) ) {
			$r = substr($r, 3);
		}
		return $r;
	}


	/**
	 * Save bundle contents.
	 * @param string $id cache id (e.g. a filename)
	 * @param string $data
	 * @return bool success
	 */
	protected function save_bundle_contents($path, $contents)
	{
		$flag = $this->use_locking ? LOCK_EX : null;

		if( is_file($path) )
		{
			unlink($path);
		}
		// NOTE: flag LOCK_EX support requires PHP 5.1.
		if( ! @file_put_contents($path, $contents, $flag) )
		{
			return false;
		}
		$this->debug_log(sprintf('Created bundle &laquo;%s&raquo; (%d bytes).', htmlspecialchars($path), strlen($contents)));
		return true;
	}


	/**
	 * @param string
	 * @param string|array
	 */
	function debug_log($msg, $classes = array())
	{
		global $Debuglog;

		if( ! is_array($classes) ) {
			$classes = array($classes);
		}
		$classes += array('rscbundles');

		$Debuglog->add($msg, $classes);
	}
}

?>
