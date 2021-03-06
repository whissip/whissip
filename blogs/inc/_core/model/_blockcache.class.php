<?php
/**
 * This file implements the BlockCache class, which caches HTML blocks/snippets genereated by the app.
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
 * }}
 *
 * @package evocore
 *
 * @version $Id$ }}}
 *
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Block Cache.
 *
 * @package evocore
 */
class BlockCache
{
	var $type;
	var $keys;
	var $serialized_keys = '';

	/**
	 * After how many bytes should we output sth live while collecting cache content:
	 */
	var $output_chunk_size = 2000;

	/**
	 * Progressively caching the content of the current page:
	 */
	var $cached_page_content = '';
	/**
	 * Are we currently recording cache contents
	 */
	var $is_collecting = false;


	/**
	 * Constructor
	 */
	function BlockCache( $type, $keys )
	{
		$this->type = $type;

		// Make sure keys are always in the same order:
		ksort( $keys );
		$this->keys = $keys;

		$this->serialized_keys = $type;
		foreach( $keys as $key => $val )
		{
			$this->serialized_keys .= '+'.$key.'='.$val;
		}

		// echo $this->serialized_keys;
	}



	/**
	 * Invalidate a special key.
	 *
	 * All we do is store the timestamp of the invalidation.
	 *
	 * @todo dh> can this get done on shutdown? (and only once for each key). Use case: updating 1000+ items from the same blog should not invalidate that blog's key 1000 times.
	 */
	function invalidate_key( $key, $val )
	{
		global $Debuglog, $servertimenow;

		$lastchanged_key_name = 'last_changed+'.$key.'='.$val;

		// Invalidate using the real time (seconds may have elapsed since $servertimenow).
		// Add 1 second because of the granularity that's down to the second.
		// Worst case scenario: content will be collected/cached several times for a whole second (as well as the first request after the end of that second).
		BlockCache::cacheproviderstore( $lastchanged_key_name, time()+1 );

		$Debuglog->add( 'Invalidated: '.$lastchanged_key_name.' @ '.(time()+1), 'blockcache' );
	}


	/**
	 * Check if cache contents are available, otherwise start collecting output to be cached
	 *
	 * Basically we get all the invalidation dates we need, then we get the
	 * data and then we check if some invalidation occured after the data was cached.
	 * If an invalidation date is missing we consider the cache to be
	 * obsolete but we generate a new invalidation date for next time we try to retrieve.
	 *
	 * @return true if we found and have echoed content from the cache
	 */
	function check()
	{
		global $Debuglog, $servertimenow;

		$missing_date = false;
		$most_recent_invalidation_ts = 0;
		$most_recent_invaliating_key = '';
		foreach( $this->keys as $key => $val )
		{
			$lastchanged_key_name = 'last_changed+'.$key.'='.$val;
			$last_changed_ts = $this->cacheproviderretrieve( $lastchanged_key_name, $success );
			if( ! $success )
			{	// We have lost the key! Recreate and keep going for other keys:
				$Debuglog->add( 'Missing: '.$lastchanged_key_name, 'blockcache' );
				$missing_date = true;
				$this->cacheproviderstore( $lastchanged_key_name, $servertimenow );
				continue;
			}

			if( $last_changed_ts > $most_recent_invalidation_ts )
			{	// This is the new most recent invalidation date.
				$most_recent_invalidation_ts = $last_changed_ts;
				$most_recent_invaliating_key = $lastchanged_key_name;
			}
		}

		if( !$missing_date && $this->retrieve( $most_recent_invalidation_ts, $most_recent_invaliating_key ) )
		{ // cache was not invalidated yet and we could retrieve:
			return true;
		}

		$this->is_collecting = true;

		$Debuglog->add( 'Collecting: '.$this->serialized_keys, 'blockcache' );

		ob_start( array( & $this, 'output_handler'), $this->output_chunk_size );

		return false;

	}


	/**
	 * Retrieve and output cache
	 *
	 * @param integer oldest acceptable timestamp
	 * @return boolean true if we could retrieve
	 */
	function retrieve( $oldest_acceptable_ts = NULL, $most_recent_invaliating_key = '' )
	{
		global $Debuglog;
		global $servertimenow;

		// return false;

		$content = $this->cacheproviderretrieve( $this->serialized_keys, $success );

		if( ! $success )
		{
			return false;
		}

		if( !is_null($oldest_acceptable_ts) )
		{ // We want to do timestamp checking:


			//if( ! preg_match( '/^([0-9]+) (.*)$/ms', $content, $matches ) )
			if( ! $pos = strpos( $content, ' ' ) )
			{	// Could not find timestamp
				$Debuglog->add( 'MISSING TIMESTAMP on retrieval of: '.$this->serialized_keys, 'blockcache' );
				return false;
			}

			// if( $matches[1] < $oldest_acceptable_ts )
			if( ($cache_ts = substr( $content, 0, $pos )) < $oldest_acceptable_ts )
			{	// Timestamp too old (there has been an invalidation in between)
				$Debuglog->add( 'Retrieved INVALIDATED cached content: '.$this->serialized_keys
					.' (invalidated by '.$most_recent_invaliating_key.' - '.$cache_ts.' < '.$oldest_acceptable_ts.')', 'blockcache' );
				return false;
			}

			// OK, we have content that is still valid:
			// $content = $matches[2];
			$content = substr( $content, $pos+1 );
		}

		$Debuglog->add( 'Retrieved: '.$this->serialized_keys, 'blockcache' );

		// SEND CONTENT!
		echo $content;

		return true;
	}


	/**
	 * This is called every x bytes to provide real time output
	 */
	function output_handler( $buffer )
	{
		$this->cached_page_content .= $buffer;
		return $buffer;
	}


	/**
	 * We are going to output personal data and we want to abort collecting the data for the cache.
	 */
	function abort_collect()
	{
		global $Debuglog;

		if( ! $this->is_collecting )
		{	// We are not collecting anyway
			return;
		}

 		$Debuglog->add( 'Aborting cache data collection...', 'blockcache' );

		ob_end_flush();

		// We are no longer collecting...
		$this->is_collecting = false;
	}


	/**
	 * End collecting output to be cached
	 *
	 * We just concatenate all the individual keys to have a single one
	 * Then we store with the current timestamp
	 */
	function end_collect()
	{
		global $Debuglog, $servertimenow;

		if( ! $this->is_collecting )
		{	// We are not collecting
			return;
		}

		ob_end_flush();

		// We use servertimenow because we may have used data that was loaded at the very start of this page
		$this->cacheproviderstore( $this->serialized_keys, $servertimenow.' '.$this->cached_page_content );
	}


	/**
	 * Store payload/data into the cache provider.
	 *
	 * @todo dh> This method should get removed from here, it's not limited to BlockCache.
	 * @param mixed $key
	 * @param mixed $payload
	 * @param int Time to live in seconds (default: 86400; 0 means "as long as possible")
	 */
	function cacheproviderstore( $key, $payload, $ttl = 86400 )
	{
		return set_to_mem_cache($key, $payload, $ttl);
	}


	/**
	 * Fetch key from the cache provider.
	 *
	 * {@internal JFI: apc_fetch supports fetching an array of keys}}
	 *
	 * @todo dh> This method should get removed from here, it's not limited to BlockCache.
	 * @todo dh> Add $default param, defaulting to NULL. This will be used on lookup failures.
	 * @param mixed $key
	 * @param boolean $success (by reference)
	 */
	function cacheproviderretrieve( $key, & $success )
	{
		return get_from_mem_cache($key, $success);
	}

}


/*
 * $Log$
 * Revision 1.17  2010/04/22 20:56:11  blueyed
 *  - Move/Refactor BlockCache::cacheproviderstore/cacheproviderretrieve into get_from_mem_cache/set_to_mem_cache
 *  - Add unset_from_mem_cache
 *
 * Revision 1.14  2010/04/22 20:31:45  blueyed
 * cacheproviderstore/cacheproviderretrieve: add Debuglog entries when there's no backend available.
 * cacheproviderstore: move $ttl to function arguments.
 *
 * Revision 1.13  2010/04/22 20:29:53  blueyed
 * doc for cacheproviderstore/cacheproviderretrieve
 *
 * Revision 1.12  2010/04/22 20:29:04  blueyed
 * Fix eaccelerator implementation of cacheproviderstore (passing correct argument).
 *
 * Revision 1.11  2010/02/08 17:51:42  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.10  2009/12/22 08:02:12  fplanque
 * doc
 *
 * Revision 1.9  2009/12/22 03:43:10  blueyed
 * todo
 *
 * Revision 1.8  2009/12/22 02:56:35  blueyed
 * BlockCache: add support for xcache and eaccelerator
 *
 * Revision 1.7  2009/12/06 03:24:11  fplanque
 * minor/doc/fixes
 *
 * Revision 1.6  2009/12/01 04:19:25  fplanque
 * even more invalidation dimensions
 *
 * Revision 1.5  2009/12/01 03:33:19  fplanque
 * Improved handling of invalidation dates
 *
 * Revision 1.4  2009/12/01 02:04:45  fplanque
 * minor
 *
 * Revision 1.3  2009/12/01 01:33:21  blueyed
 * Fix install: wrap apc_*
 *
 * Revision 1.2  2009/11/30 23:16:24  fplanque
 * basic cache invalidation is working now
 *
 * Revision 1.1  2009/11/30 04:31:37  fplanque
 * BlockCache Proof Of Concept
 *
 */
?>
