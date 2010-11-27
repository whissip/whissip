<?php
/**
 * This file implements the Timer class.
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
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 * @author blueyed: Daniel HAHLER.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


// DEBUG: (Turn switch on or off to log debug info for specified category)
$GLOBALS['debug_timer'] = true;


/**
 * This is a simple class to allow timing/profiling of code portions.
 */
class Timer
{
	/**
	 * Remember times.
	 *
	 * We store for each category (primary key) the state, start/resume time and the total passed time.
	 *
	 * @access protected
	 */
	var $_times = array();


	/**
	 * @access protected
	 * @var integer Level of internal indentation, used to indent Debuglog messages.
	 */
	var $indent = 0;


	/**
	 * Timestamp of object creation.
	 * @var float
	 */
	protected $total_start;

	/**
	 * Timestamp of last Debuglog entry; used for relative measurement.
	 * @var float
	 */
	protected $last_log_ts;


	/**
	 * Constructor.
	 *
	 * @param string|NULL If a category is given the timer starts right away.
	 */
	function Timer( $category = NULL )
	{
		$this->total_start = $this->get_current_microtime();
		if( is_string($category) )
		{
			$this->start( $category );
		}
	}


	/**
	 * Reset a timer category.
	 */
	function reset( $category )
	{
		$this->_times[$category] = array( 'total' => 0, 'count' => 0, 'resumed' => array() );
	}


	/**
	 * Start a timer.
	 */
	function start( $category, $log = true )
	{
		$this->reset( $category );
		$this->resume( $category, $log );
	}


	/**
	 * Stops a timer category. It may me resumed later on, see {@link resume()}. This is an alias for {@link pause()}.
	 *
	 * @param string Category
	 * @param boolean|string Add info about this to Debuglog. String gets used as additional message.
	 * @return boolean false, if the timer had not been started.
	 */
	function stop( $category, $log = true )
	{
		global $Debuglog;

		if( ! $this->pause( $category, $log ) )
			return false;

		$this->debug_log( $category, 'stopped', $log );

		return true;
	}


	/**
	 * Pauses a timer category. It may me resumed later on, see {@link resume()}.
	 *
	 * NOTE: The timer needs to be started, either through the {@link Timer() Constructor} or the {@link start()} method.
	 *
	 * @param string Category
	 * @param boolean|string Add info about this to Debuglog. String gets used as additional message.
	 * @return boolean false, if the timer had not been started.
	 */
	function pause( $category, $log = true )
	{
		global $Debuglog;

		if( $log )
		{
			if( $this->indent ) $this->indent--;
			$this->debug_log( $category, 'paused', $log );
		}
		if( $this->get_state($category) != 'running' )
		{ // Timer is not running!
			$Debuglog->add("Warning: tried to pause already paused '$category'.<br />".debug_get_backtrace(), 'timer');
			return false;
		}

		$since_resume = $this->get_current_microtime() - array_pop($this->_times[$category]['resumed']);

		$this->_times[$category]['total'] += $since_resume;
		if( count($this->_times[$category]['resumed']) < 1 )
		{ // innermost level paused, change state of category to "paused".
			$this->_times[$category]['state'] = 'paused';
		}

		return true;
	}


	/**
	 * Resumes the timer on a category.
	 * @param string Category
	 * @param boolean|string Add info about this to Debuglog. String gets used as additional message.
	 */
	function resume( $category, $log = true )
	{
		global $Debuglog;

		if( !isset($this->_times[$category]['total']) )
		{
			$this->start( $category, $log );
			return;
		}

		$this->_times[$category]['resumed'][] = $this->get_current_microtime();
		$this->_times[$category]['count']++;

		$this->_times[$category]['state'] = 'running';

		if( $log )
		{
			$this->debug_log( $category, 'resumed', $log );
			$this->indent++;
		}
	}


	/**
	 * Get the duration for a given category.
	 *
	 * @param string Category name
	 * @param integer Number of decimals after dot.
	 * @return string
	 */
	function get_duration( $category, $decimals = 3 )
	{
		return number_format( $this->get_microtime($category), $decimals ); // TODO: decimals/seperator by locale!
	}


	/**
	 * Get number of timer resumes (includes start).
	 *
	 * @return integer
	 */
	function get_count( $category )
	{
		if( isset( $this->_times[$category] ) )
		{
			return $this->_times[$category]['count'];
		}

		return false;
	}


	/**
	 * Get the time in microseconds that was spent in the given category.
	 *
	 * @return float
	 */
	function get_microtime( $category )
	{
		switch( $this->get_state($category) )
		{
			case 'running':
				// The timer is running, we need to return the additional time since the last resume.
				$resumed = array_pop(array_slice($this->_times[$category]['resumed'], -1));
				return $this->_times[$category]['total'] + $this->get_current_microtime() - $resumed;

			case 'paused':
				return $this->_times[$category]['total'];

			default:
				return (float)0;
		}
	}


	/**
	 * Get the state a category timer is in.
	 *
	 * @return string 'unknown', 'not initialised', 'running', 'paused'
	 */
	function get_state( $category )
	{
		if( !isset($this->_times[$category]) )
		{
			return 'unknown';
		}

		if( !isset($this->_times[$category]['state']) )
		{
			return 'not initialised';
		}

		return $this->_times[$category]['state'];
	}


	/**
	 * Get a list of used categories.
	 *
	 * @return array
	 */
	function get_categories()
	{
		return array_keys( $this->_times );
	}


	/**
	 * Get the current time in microseconds.
	 *
	 * @return float
	 */
	function get_current_microtime()
	{
		list($usec, $sec) = explode(' ', microtime());
		return ((float)$usec + (float)$sec);
	}


	/**
	 * Wrapper around {@link $Debuglog} to prefix messages with relative timestamp.
	 * @param string Category
	 * @param string Status message ("stopped", "resumed", "paused")
	 * @param boolean|string Add info about this to Debuglog? String gets used as additional message.
	 */
	function debug_log($category, $state, $log)
	{
		global $Debuglog;

		if( ! $log ) {
			return;
		}

		$lcount = count($this->_times[$category]['resumed']);
		if( $lcount > 1 ) {
			$category .= '('.$lcount.')';
		}

		// build message
		$msg = str_repeat('&nbsp;', $this->indent*4).$category.' '.$state;
		if( is_string($log) ) {
			$msg .= ': '.$log;
		}

		$cur = $this->get_current_microtime();

		$dur_rel = $cur - $this->total_start;
		$dur_prev = ( isset($this->last_log_ts) ? $cur - $this->last_log_ts : 0 );

		$msg = sprintf( '<span title="Relative total time">%.2f</span> (<span title="Time since last log message">+%.4f</span>) (<span title="Total time in category">%.4f</span>): %s', $dur_rel, $dur_prev, $this->get_microtime($category), $msg );
		$Debuglog->add($msg, 'timer');

		$this->last_log_ts = $cur;
	}
}


/**
 * This is an implementation of {@link Timer}, which does nothing
 * (no-operation).
 * {@link $Timer} will get derived from this, if not running in
 * {@link $debug debug mode}.
 */
class Timer_noop
{
	function Timer( $category = NULL ) {}
	function reset( $category ) {}
	function start( $category, $log = true ) {}
	function stop( $category ) {}
	function pause( $category ) {}
	function resume( $category ) {}
	function get_duration( $category, $decimals = 3 ) {}
	function get_count( $category ) {}
	function get_microtime( $category ) {}
	function get_state( $category ) {}
	function get_categories() {}
	function get_current_microtime() {}
}



/*
 * $Log$
 * Revision 1.10  2010/07/26 06:52:15  efy-asimo
 * MFB v-4-0
 *
 * Revision 1.9  2010/04/28 20:43:52  blueyed
 * Timer: add warning to Debuglog when trying to pause already paused cat.
 *
 * Revision 1.8  2010/04/28 20:41:10  blueyed
 * Timer: fix indenting when pause gets called several times / recursivly (and it was already paused).
 *
 * Revision 1.7  2010/04/27 19:43:24  blueyed
 * Timer: indent debuglog messages according to their nesting. Also log relative time since resuming when pausing.
 *
 * Revision 1.6  2010/02/08 17:51:48  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.5  2009/11/30 00:22:04  fplanque
 * clean up debug info
 * show more timers in view of block caching
 *
 * Revision 1.4  2009/09/20 16:55:14  blueyed
 * Performance boost: add Timer_noop class and use it when not in debug mode.
 *
 * Revision 1.3  2009/03/08 23:57:40  fplanque
 * 2009
 *
 * Revision 1.2  2008/01/21 09:35:24  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 10:58:55  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.6  2007/04/26 00:11:08  fplanque
 * (c) 2007
 *
 * Revision 1.5  2006/11/24 18:27:27  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 */
?>
