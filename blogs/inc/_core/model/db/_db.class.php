<?php
/**
 * This file implements the DB class.
 *
 * Based on ezSQL - Class to make it very easy to deal with MySQL database connections.
 * b2evo Additions:
 * - nested transactions
 * - symbolic table names
 * - query log
 * - get_list
 * - dynamic extension loading
 * - Debug features (EXPLAIN...)
 * and more...
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}.
 * Parts of this file are copyright (c)2004 by Justin Vincent - {@link http://php.justinvincent.com}
 * Parts of this file are copyright (c)2004-2005 by Daniel HAHLER - {@link http://thequod.de/contact}.
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
 * {@internal Origin:
 * This file is based on the following package (excerpt from ezSQL's readme.txt):
 * =======================================================================
 * Author:  Justin Vincent (justin@visunet.ie)
 * Web: 	 http://php.justinvincent.com
 * Name: 	 ezSQL
 * Desc: 	 Class to make it very easy to deal with database connections.
 * License: FREE / Donation (LGPL - You may do what you like with ezSQL - no exceptions.)
 * =======================================================================
 * A $10 donation has been made to Justin VINCENT on behalf of the b2evolution team.
 * The package has been relicensed as GPL based on
 * "You may do what you like with ezSQL - no exceptions."
 * 2004-10-14 (email): Justin VINCENT grants Francois PLANQUE the right to relicense
 * this modified class under other licenses. "Just include a link to where you got it from."
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
 * @author blueyed: Daniel HAHLER
 * @author fplanque: Francois PLANQUE
 * @author Justin VINCENT
 *
 * @version $Id$
 * @todo transaction support
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * ezSQL Constants
 */
define( 'EZSQL_VERSION', '1.25' );
define( 'OBJECT', 'OBJECT', true );
define( 'ARRAY_A', 'ARRAY_A', true);
define( 'ARRAY_N', 'ARRAY_N', true);


/**
 * The Main Class
 *
 * @package evocore
 */
class DB
{
	/**
	 * Show/Print errors?
	 * @var boolean
	 */
	var $show_errors = true;
	/**
	 * Halt on errors?
	 * @var boolean
	 */
	var $halt_on_error = true;
	/**
	 * Log errors using {@link error_log()}?
	 * There's no reason to disable this, apart from when you are expecting
	 * to get an error, like with {@link get_db_version()}.
	 * @var boolean
	 */
	var $log_errors = true;
	/**
	 * Has an error occured?
	 * @var boolean
	 */
	var $error = false;
	/**
	 * Number of done queries.
	 * @var integer
	 */
	var $num_queries = 0;
	/**
	 * last query SQL string
	 * @var string
	 */
	var $last_query = '';
	/**
	 * last DB error string
	 * @var string
	 */
	var $last_error = '';

	/**
	 * Last insert ID
	 * @var integer
	 */
	var $insert_id = 0;

	/**
	 * Last query's resource
	 * @access protected
	 * @var object
	 */
	var $result;

	/**
	 * Number of rows in result set
	 */
	var $num_rows = 0;

	/**
	 * Number of rows affected by insert, delete, update or replace
	 */
	var $rows_affected = 0;

	/**
	 * Aliases that will be replaced in queries:
	 */
	var $dbaliases = array();
	/**
	 * Strings that will replace the aliases in queries:
	 */
	var $dbreplaces = array();

	/**
	 * CREATE TABLE options.
	 *
	 * This gets appended to every "CREATE TABLE" query.
	 *
	 * Edit those if you have control over you MySQL server and want a more professional
	 * database than what is commonly offered by popular hosting providers.
	 *
	 * @todo dh> If the query itself uses already e.g. "CHARACTER SET latin1" it should not get overridden..
	 * @var string
	 */
	var $table_options = '';

	/**
	 * Use transactions in DB?
	 *
	 * You need to use InnoDB in order to enable this.  See the {@link $db_config "table_options" key}.
	 */
	var $use_transactions = false;

	/**
	 * How many transactions are currently nested?
	 */
	var $transaction_nesting_level = 0;

	/**
	 * Rememeber if we have to rollback at the end of a nested transaction construct
	 */
	var $rollback_nested_transaction = false;

	/**
	 * MySQL Database handle
	 * @var object mysqli
	 */
	var $dbhandle;

	/**
	 * Database username
	 * @var string
	 */
	var $dbuser;

	/**
	 * Database username's password
	 * @var string
	 */
	var $dbpassword;

	/**
	 * Database name
	 * @var string
	 * @see select()
	 */
	var $dbname;

	/**
	 * Database hostname
	 * @var string
	 */
	var $dbhost = 'localhost';


	// DEBUG:

	/**
	 * Do we want to log queries?
	 * If null, it gets set according to {@link $debug}.
	 * A subclass may set it by default (e.g. DbUnitTestCase_DB).
	 * This requires {@link $debug} to be true.
	 * @var boolean
	 */
	var $log_queries;

	/**
	 * Log of queries:
	 * @var array
	 */
	var $queries = array();

	/**
	 * Do we want to explain joins?
	 * This requires {@link DB::$log_queries} to be true.
	 *
	 * @todo fp> we'd probably want to group all the advanced debug vars under a single setting now. We might even auto enable it when $debug=2. (And we might actually want to include a $debug="cookie" mode for easy switching with bookmarks or a bookmarklet)
	 *
	 * @var boolean
	 */
	var $debug_explain_joins = true;

	/**
	 * Do we want to profile queries?
	 * This requires {@link DB::$log_queries} to be true.
	 *
	 * This sets "profiling=1" for the session and queries "SHOW PROFILE" after
	 * each query.
	 *
	 * @var boolean
	 */
	var $debug_profile_queries = false;

	/**
	 * Do we want to output a function backtrace for every query?
	 * Number of stack entries to show (from last to first) (Default: 0); true means 'all'.
	 *
	 * This requires {@link DB::$log_queries} to be true.
	 *
	 * @var integer
	 */
	var $debug_dump_function_trace_for_queries = true;

	/**
	 * Number of rows we want to dump in debug output (0 disables it)
	 * This requires {@link DB::$log_queries} to be true.
	 * @var integer
	 */
	var $debug_dump_rows = 0;

	/**
	 * Time in seconds that is considered a fast query (green).
	 * @var float
	 * @see dump_queries()
	 */
	var $query_duration_fast = 0.05;

	/**
	 * Time in seconds that is considered a slow query (red).
	 * @var float
	 * @see dump_queries()
	 */
	var $query_duration_slow = 0.3;


	/**
	 * DB Constructor
	 *
	 * Connects to the server and selects a database.
	 *
	 * @param array An array of parameters.
	 *   Manadatory:
	 *    - 'user': username to connect with
	 *    - 'password': password to connect with
	 *    OR
	 *    - 'handle': a MySQLi database handle/object (from a previous {@link mysqli_connect()})
	 *   Optional:
	 *    - 'name': the name of the default database, see {@link DB::select()}
	 *    - 'host': host of the database; Default: 'localhost'
	 *    - 'show_errors': Display SQL errors? (true/false); Default: don't change member default ({@link $show_errors})
	 *    - 'halt_on_error': Halt on error? (true/false); Default: don't change member default ({@link $halt_on_error})
	 *    - 'table_options': sets {@link $table_options}
	 *    - 'use_transactions': sets {@link $use_transactions}
	 *    - 'aliases': Aliases for tables (array( alias => table name )); Default: no aliases.
	 *    - 'new_link': create a new link to the DB, even if there was a mysqli_connect() with
	 *       the same params before. (requires PHP 4.2)
	 *    - 'client_flags': optional settings like compression or SSL encryption. See {@link http://www.php.net/manual/en/ref.mysql.php#mysql.client-flags}.
	 *       (requires PHP 4.3)
	 *    - 'strict_mode': use MySQL strict mode (SET sql_mode="TRADITIONAL") (Default: false)
	 *    - 'log_queries': should queries get logged internally? (follows $debug by default, and requires it to be enabled otherwise)
	 *      This is a requirement for the following options:
	 *    - 'debug_dump_rows': Number of rows to dump
	 *    - 'debug_explain_joins': Explain JOINS? (calls "EXPLAIN $query")
	 *    - 'debug_profile_queries': Profile queries? (calls "SHOW PROFILE" after each query)
	 *    - 'debug_dump_function_trace_for_queries': Collect call stack for queries? (showing where queries have been called)
	 */
	function DB( $params )
	{
		global $debug;

		// Mandatory parameters:
		if( isset( $params['handle'] ) )
		{ // DB-Link provided:
			$this->dbhandle = $params['handle'];
		}
		else
		{
			$this->dbuser = $params['user'];
			$this->dbpassword = $params['password'];
		}

		// Optional parameters (Allow overriding through $params):
		if( isset($params['name']) ) $this->dbname = $params['name'];
		if( isset($params['host']) ) $this->dbhost = $params['host'];
		if( isset($params['show_errors']) ) $this->show_errors = $params['show_errors'];
		if( isset($params['halt_on_error']) ) $this->halt_on_error = $params['halt_on_error'];
		if( isset($params['table_options']) ) $this->table_options = $params['table_options'];
		if( isset($params['use_transactions']) ) $this->use_transactions = $params['use_transactions'];
		if( isset($params['debug_dump_rows']) ) $this->debug_dump_rows = $params['debug_dump_rows']; // Nb of rows to dump
		if( isset($params['debug_explain_joins']) ) $this->debug_explain_joins = $params['debug_explain_joins'];
		if( isset($params['debug_profile_queries']) ) $this->debug_profile_queries = $params['debug_profile_queries'];
		if( isset($params['debug_dump_function_trace_for_queries']) ) $this->debug_dump_function_trace_for_queries = $params['debug_dump_function_trace_for_queries'];
		if( isset($params['log_queries']) )
		{
			$this->log_queries = $debug && $params['log_queries'];
		}
		elseif( isset($debug) && ! isset($this->log_queries) )
		{ // $log_queries follows $debug and respects subclasses, which may define it:
			$this->log_queries = (bool)$debug;
		}

		if( ! extension_loaded('mysql') )
		{ // The mysql extension is not loaded, try to dynamically load it:
			$mysql_ext_file = is_windows() ? 'php_mysqli.dll' : 'mysqli.so';
			if( function_exists('dl') )
			{
				$php_errormsg = null;
				$old_track_errors = ini_set('track_errors', 1);
				$old_html_errors = ini_set('html_errors', 0);
				@dl( $mysql_ext_file );
				$error_msg = $php_errormsg;
				if( $old_track_errors !== false ) ini_set('track_errors', $old_track_errors);
				if( $old_html_errors !== false ) ini_set('html_errors', $old_html_errors);
			}
			else
			{
				$error_msg = 'The PHP mysql extension is not installed and we cannot load it dynamically.';
			}
			if( ! extension_loaded('mysql') )
			{ // Still not loaded:
				$this->print_error( 'The PHP MySQLi module could not be loaded.', '
					<p><strong>Error:</strong> '.$error_msg.'</p>
					<p>You probably have to edit your php configuration (php.ini) and enable this module ('.$mysql_ext_file.').</p>
					<p>You may have to install it first (e.g. php5-mysql on Debian/Ubuntu).</p>
					<p>Do not forget to restart your webserver (if necessary) after editing the PHP conf.</p>', false );
				return;
			}
		}

		if( isset($params['new_link']) && ! $params['new_link'] ) {
			debug_die('DB: new_link=false is not supported anymore.');
		}
		if( isset($params['client_flags']) ) {
			debug_die('DB: client_flags is not supported anymore.');
		}
		#$new_link = isset( $params['new_link'] ) ? $params['new_link'] : false;
		#$client_flags = isset( $params['client_flags'] ) ? $params['client_flags'] : 0;

		if( ! $this->dbhandle )
		{ // Connect to the Database:
			// echo "mysqli_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags )";
			// mysqli_error() is tied to an established connection
			// if the connection fails we need a different method to get the error message
			$php_errormsg = null;
			$old_track_errors = ini_set('track_errors', 1);
			$old_html_errors = ini_set('html_errors', 0);
			#$this->dbhandle = mysqli_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags );
			$this->dbhandle = mysqli_connect( $this->dbhost, $this->dbuser, $this->dbpassword );
			$mysql_error = $php_errormsg;
			if( $old_track_errors !== false ) ini_set('track_errors', $old_track_errors);
			if( $old_html_errors !== false ) ini_set('html_errors', $old_html_errors);
		}

		if( ! $this->dbhandle )
		{
			$this->print_error( 'Error establishing a database connection!',
				( $mysql_error ? '<p>('.$mysql_error.')</p>' : '' ).'
				<ol>
					<li>Are you sure you have typed the correct user/password?</li>
					<li>Are you sure that you have typed the correct hostname?</li>
					<li>Are you sure that the database server is running?</li>
				</ol>', false );
		}
		elseif( isset($this->dbname) )
		{
			$this->select($this->dbname);
		}


		if( $this->query( 'SET NAMES UTF8' ) === false )
		{
			debug_die( 'Could not "SET NAMES UTF8"! (MySQL error: '.strip_tags($this->last_error).')' );
		}

		/*
		echo '<br />Server: '.$this->get_var( 'SELECT @@character_set_server' );
		echo '<br />Database: '.$this->get_var( 'SELECT @@character_set_database' );
		echo '<br />Connection: '.$this->get_var( 'SELECT @@character_set_connection' );
		echo '<br />Client: '.$this->get_var( 'SELECT @@character_set_client' );
		echo '<br />Results: '.$this->get_var( 'SELECT @@character_set_results' );
		*/


		if( isset($params['aliases']) )
		{ // Prepare aliases for replacements:
			foreach( $params['aliases'] as $dbalias => $dbreplace )
			{
				$this->dbaliases[] = '#\b'.$dbalias.'\b#'; // \b = word boundary
				$this->dbreplaces[] = $dbreplace;
				// echo '<br />'.'#\b'.$dbalias.'\b#';
			}
			// echo count($this->dbaliases);
		}

		if( ! empty($params['strict_mode']) )
		{ // Force MySQL strict mode
			$this->query( 'SET sql_mode = "TRADITIONAL"', 'we do this in DEBUG mode only' );
		}

		if( $this->debug_profile_queries )
		{
			// dh> this will fail, if it is not supported, but has to be enabled manually anyway.
			$this->query('SET profiling = 1'); // Requires 5.0.37.
		}
	}


	/**
	 * Select a DB (if another one needs to be selected)
	 */
	function select($db)
	{
		if( !@mysqli_select_db($this->dbhandle, $db) )
		{
			$this->print_error( 'Error selecting database ['.$db.']!', '
				<ol>
					<li>Are you sure the database exists?</li>
					<li>Are you sure the DB user is allowed to use that database?</li>
					<li>Are you sure there is a valid database connection?</li>
				</ol>', false );
		}
		$this->dbname = $db;
	}


	/**
	 * Format a string correctly for safe insert under all PHP conditions
	 */
	function escape($str)
	{
		return mysqli_real_escape_string($this->dbhandle, $str);
	}


	/**
	 * Quote a value, either in single quotes (and escaped) or if it's NULL as 'NULL'.
	 *
	 * @param string|array|null
	 * @return string Quoted (and escaped) value or 'NULL'.
	 */
	function quote($str)
	{
		if( $str === NULL )
		{
			return 'NULL';
		}

		$type = gettype($str);
		if( $type == 'array' )
		{
			return implode(',', array_map( array('DB', 'quote'), $str )); // TODO: should be 'self' (PHP 5.3?)
		}

		// Add Debuglog warning when quoting integers (not necessary):
		// if( $type == 'integer' )
		// {
		// 	global $Debuglog;
		// 	if( $Debuglog ) {
		// 		$Debuglog->add('DB::quote: quoting integer: '.$str.' (performance drawback) '.debug_get_backtrace(), 'debug');
		// 	}
		// }
		return "'".$this->escape($str)."'";
	}


	/**
	 * @return string Return the given value or 'NULL', if it's === NULL.
	 */
	function null($val)
	{
		if( $val === NULL )
			return 'NULL';
		else
			return $val;
	}


	/**
	 * Returns the correct WEEK() function to get the week number for the given date.
	 *
	 * @link http://dev.mysql.com/doc/mysql/en/date-and-time-functions.html
	 *
	 * @todo disable when MySQL < 4
	 * @param string will be used as is
	 * @param integer 0 for sunday, 1 for monday
	 */
	function week( $date, $startofweek )
	{
		if( $startofweek == 1 )
		{ // Week starts on Monday, week 1 must have a monday in this year:
			return ' WEEK( '.$date.', 5 ) ';
		}

		// Week starts on Sunday, week 1 must have a sunday in this year:
		return ' WEEK( '.$date.', 0 ) ';
	}


	/**
	 * Print SQL/DB error.
	 *
	 * TODO: fp> bloated: it probably doesn't make sense to display errors if we don't stop. Any use case?
	 *       dh> Sure. Local testing (and test cases).
	 *
	 * @param string Short error (no HTML)
	 * @param string Extended description/help for the error (for HTML)
	 * @param string|false Query title; false if {@link DB::$last_query} should not get displayed
	 */
	function print_error( $title = '', $html_str = '', $query_title = '' )
	{
		// All errors go to the global error array $EZSQL_ERROR..
		global $EZSQL_ERROR, $is_cli;

		$this->error = true;

		// If no special error string then use mysql default..
		if( ! strlen($title) )
		{
			if( is_object($this->dbhandle) )
			{ // use mysql_error:
				$this->last_error = mysqli_error($this->dbhandle).'(Errno='.mysqli_errno($this->dbhandle).')';
			}
			else
			{
				$this->last_error = 'Unknown (and no $dbhandle available)';
			}
		}
		else
		{
			$this->last_error = $title;
		}

		// Log this error to the global array..
		$EZSQL_ERROR[] = array(
			'query' => $this->last_query,
			'error_str'  => $this->last_error
		);


		// Send error to PHP's system logger.
		if( $this->log_errors )
		{
			// TODO: dh> respect $log_app_errors? Create a wrapper, e.g. evo_error_log, which can be used later to write into e.g. a DB table?!
			if( isset($_SERVER['REQUEST_URI']) )
			{
				$req_url = ( (isset($_SERVER['HTTPS']) && ( $_SERVER['HTTPS'] != 'off' ) ) ? 'https://' : 'http://' )
					.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			}
			else
			{
				$req_url = '-';
			}
			$error_text = 'SQL ERROR: '. $this->last_error
					. ', QUERY: "'.trim($this->last_query).'"'
					. ', BACKTRACE: '.trim(strip_tags(debug_get_backtrace()))
					. ', URL: '.$req_url;
			error_log( preg_replace( '#\s+#', ' ', $error_text ) );
		}


		if( ! ( $this->halt_on_error || $this->show_errors ) )
		{ // no reason to generate a nice message:
			return;
		}

		if( $this->halt_on_error && ! $this->show_errors )
		{ // do not show errors, just die:
			die();
		}

		if( $is_cli )
		{ // Clean error message for command line interface:
			$err_msg = "MySQL error!\n{$this->last_error}\n";
			if( ! empty($this->last_query) && $query_title !== false )
			{
				$err_msg .= "Your query: $query_title\n";
				$err_msg .= $this->format_query( $this->last_query, false );
			}
		}
		else
		{
			$err_msg = '<p class="error">MySQL error!</p>'."\n";
			$err_msg .= "<div><p><strong>{$this->last_error}</strong></p>\n";
			$err_msg .= $html_str;
			if( !empty($this->last_query) && $query_title !== false )
			{
				$err_msg .= '<p class="error">Your query: '.$query_title.'</p>';
				$err_msg .= '<pre>';
				$err_msg .= $this->format_query( $this->last_query, ! $is_cli );
				$err_msg .= '</pre>';
			}
			$err_msg .= "</div>\n";
		}

		if( $this->halt_on_error )
		{
			if( function_exists('debug_die') )
			{
				debug_die( $err_msg );
			}
			else
			{
				die( $err_msg );
			}
		}
		elseif( $this->show_errors )
		{ // If there is an error then take note of it
			echo '<div class="error">';
			echo $err_msg;
			echo '</div>';
		}
	}


	/**
	 * Kill cached query results
	 */
	function flush()
	{
		$this->result = NULL;
		$this->last_query = NULL;
		$this->num_rows = 0;
		if( $this->result && is_object($this->result) )
		{ // Free last result object
			mysqli_free_result($this->result);
		}
	}


	/**
	 * Get MYSQL version
	 */
	function get_version( $query_title = NULL )
	{
		if( isset( $this->version ) )
		{
			return $this->version;
		}

		$this->save_error_state();
		// Blatantly ignore any error generated by potentially unknown function...
		$this->show_errors = false;
		$this->halt_on_error = false;

		if( ($this->version_long = $this->get_var( 'SELECT VERSION()', 0, 0, $query_title ) ) === NULL )
		{	// Very old version ( < 4.0 )
			$this->version = '';
			$this->version_long = '';
		}
		else
		{
			$this->version = preg_replace( '�-.*�', '', $this->version_long );
		}
		$this->restore_error_state();

		return $this->version;
	}


	/**
	 * Save the vars responsible for error handling.
	 * This can be chained.
	 * @see DB::restore_error_state()
	 */
	function save_error_state()
	{
		$this->saved_error_states[] = array(
			'show_errors'   => $this->show_errors,
			'halt_on_error' => $this->halt_on_error,
			'last_error'    => $this->last_error,
			'error'         => $this->error,
			'log_errors'    => $this->log_errors,
		);
	}

	/**
	 * Call this after {@link save_halt_on_error()} to
	 * restore the previous error state.
	 * This can be chained.
	 * @see DB::save_error_state()
	 */
	function restore_error_state()
	{
		if( empty($this->saved_error_states)
			|| ! is_array($this->saved_error_states) )
		{
			return false;
		}
		$state = array_pop($this->saved_error_states);

		foreach( $state as $k => $v )
			$this->$k = $v;
	}


	/**
	 * Basic Query
	 *
	 * @param string SQL query
	 * @param string title for debugging
	 * @return mixed # of rows affected or false if error
	 */
	function query( $query, $title = '' )
	{
		global $Timer;

		// initialise return
		$return_val = 0;

		// Flush cached values..
		$this->flush();

		// Replace aliases:
		if( ! empty($this->dbaliases) )
		{
			// TODO: this should only replace the table name part(s), not the whole query!
			// blueyed> I've changed it to replace in table name parts for UPDATE, INSERT and REPLACE, because
			//          it corrupted serialized data..
			//          IMHO, a cleaner solution would be to use {T_xxx} in the queries and replace it here. In object properties (e.g. DataObject::$dbtablename), only "T_xxx" would get used and surrounded by "{..}" in the queries it creates.

			if( preg_match( '~^\s*(UPDATE\s+)(.*?)(\sSET\s.*)$~is', $query, $match ) )
			{ // replace only between UPDATE and SET:
				$query = $match[1].preg_replace( $this->dbaliases, $this->dbreplaces, $match[2] ).$match[3];
			}
			elseif( preg_match( '~^\s*(INSERT|REPLACE\s+)(.*?)(\s(VALUES|SET)\s.*)$~is', $query, $match ) )
			{ // replace only between INSERT|REPLACE and VALUES|SET:
				$query = $match[1].preg_replace( $this->dbaliases, $this->dbreplaces, $match[2] ).$match[3];
			}
			else
			{ // replace in whole query:
				$query = preg_replace( $this->dbaliases, $this->dbreplaces, $query );

				if( ! empty($this->table_options) && preg_match( '#^ \s* create \s* table \s #ix', $query) )
				{ // Query is a table creation, we add table options:
					$query = preg_replace( '~;\s*$~', '', $query ); // remove any ";" at the end
					$query .= ' '.$this->table_options;
				}
			}
		}
		elseif( ! empty($this->table_options) )
		{ // No aliases, but table_options:
			if( preg_match( '#^ \s* create \s* table \s #ix', $query) )
			{ // Query is a table creation, we add table options:
				$query = preg_replace( '~;\s*$~', '', $query ); // remove any ";" at the end
				$query .= $this->table_options;
			}
		}
		// echo '<p>'.$query.'</p>';

		// Keep track of the last query for debug..
		$this->last_query = $query;

		// Perform the query via std mysql_query function..
		$this->num_queries++;

		if( $this->log_queries )
		{	// We want to log queries:
			$this->queries[ $this->num_queries - 1 ] = array(
				'title' => $title,
				'sql' => $query,
				'rows' => -1,
				'time' => 'unknown',
				'results' => 'unknown' );
		}

		if( is_object($Timer) )
		{
			// Resume global query timer
			$Timer->resume( 'SQL QUERIES' , false );
			// Start a timer for this particular query:
			$Timer->start( 'sql_query', false );

			// Run query:
			$this->result = mysqli_query( $this->dbhandle, $query );

			if( $this->log_queries )
			{	// We want to log queries:
				// Get duration for last query:
				$this->queries[ $this->num_queries - 1 ]['time'] = $Timer->get_duration( 'sql_query', 10 );
			}

			// Pause global query timer:
			$Timer->pause( 'SQL QUERIES' , false );
		}
		else
		{
			// Run query:
			$this->result = @mysqli_query( $this->dbhandle, $query );
		}

		if( $this->log_queries && $this->debug_dump_function_trace_for_queries )
		{	// Log backtrace, also for invalid queries:
			$this->queries[ $this->num_queries - 1 ]['function_trace'] = debug_get_backtrace( $this->debug_dump_function_trace_for_queries, array( array( 'class' => 'DB' ) ), 1 ); // including first stack entry from class DB
		}

		// If there is an error then take note of it..
		if( is_object($this->dbhandle) && mysqli_error($this->dbhandle) )
		{
			if( is_object($this->result) )
			{
				mysqli_free_result($this->result);
			}
			$this->print_error( '', '', $title );
			return false;
		}

		if( preg_match( '#^\s*(INSERT|DELETE|UPDATE|REPLACE)\s#i', $query, $match ) )
		{ // Query was an insert, delete, update, replace:

			$this->rows_affected = mysqli_affected_rows($this->dbhandle);
			if( $this->log_queries )
			{	// We want to log queries:
				$this->queries[ $this->num_queries - 1 ]['rows'] = $this->rows_affected;
			}

			// Take note of the insert_id, for INSERT and REPLACE:
			$match[1] = strtoupper($match[1]);
			if( $match[1] == 'INSERT' || $match[1] == 'REPLACE' )
			{
				$this->insert_id = mysqli_insert_id($this->dbhandle);
			}

			// Return number of rows affected
			$return_val = $this->rows_affected;
		}
		else
		{ // Query was a select, alter, etc...:
			if( is_object($this->result) )
			{ // It's not a object for CREATE or DROP for example and can even trigger a fatal error (see http://forums.b2evolution.net//viewtopic.php?t=9529)
				$this->num_rows = mysqli_num_rows($this->result);
			}

			if( $this->log_queries )
			{	// We want to log queries:
				$this->queries[ $this->num_queries - 1 ]['rows'] = $this->num_rows;
			}

			// Return number of rows selected
			$return_val = $this->num_rows;
		}
		if( $this->log_queries )
		{	// We want to log queries:
			if( $this->debug_dump_rows && $this->num_rows )
			{
				$this->queries[ $this->num_queries - 1 ]['results'] = $this->debug_get_rows_table( $this->debug_dump_rows );
			}

			// Profile queries
			if( $this->debug_profile_queries )
			{
				// save values:
				$saved_last_result = $this->result;
				$saved_num_rows = $this->num_rows;

				$this->num_rows = 0;

				$this->result = @mysqli_query( $this->dbhandle, 'SHOW PROFILE' );
				$this->num_rows = mysqli_num_rows($this->result);

				if( $this->num_rows )
				{
					$this->queries[$this->num_queries-1]['profile'] = $this->debug_get_rows_table( 100, true );

					// Get time information from PROFILING table (which corresponds to "SHOW PROFILE")
					$this->result = mysqli_query( $this->dbhandle, 'SELECT FORMAT(SUM(DURATION), 6) AS DURATION FROM INFORMATION_SCHEMA.PROFILING GROUP BY QUERY_ID ORDER BY QUERY_ID DESC LIMIT 1' );
					$this->queries[$this->num_queries-1]['time_profile'] = array_shift(mysqli_fetch_row($this->result));
				}

				// Free "PROFILE" result resource:
				mysqli_free_result($this->result);


				// Restore:
				$this->result = $saved_last_result;
				$this->num_rows = $saved_num_rows;
			}
		}
		return $return_val;
	}


	/**
	 * Get one variable from the DB - see docs for more detail
	 *
	 * Note: To be sure that you received NULL from the DB and not "no rows" check
	 *       for {@link $num_rows}.
	 *
	 * @param string Optional query to execute
	 * @param integer Column number (starting at and defaulting to 0)
	 * @param integer Row (defaults to NULL for "next"/"do not seek")
	 * @param string Optional title of query
	 * @return mixed NULL if not found, the value otherwise (which may also be NULL).
	 */
	function get_var( $query = NULL, $x = 0, $y = NULL, $title = '' )
	{
		// If there is a query then perform it if not then use cached results..
		if( $query )
		{
			$this->query($query, $title);
		}

		if( $this->num_rows
			&& ( $y === NULL || mysqli_data_seek($this->result, $y) ) )
		{
			$row = mysqli_fetch_row($this->result);

			if( isset($row[$x]) )
			{
				return $row[$x];
			}
		}

		return NULL;
	}


	/**
	 * Get one row from the DB.
	 *
	 * @param string Query (or NULL for previous query)
	 * @param string Output type ("OBJECT", "ARRAY_A", "ARRAY_N")
	 * @param int Row to fetch (or NULL for next - useful with $query=NULL)
	 * @param string Optional title for $query (if any)
	 * @return mixed
	 */
	function get_row( $query = NULL, $output = OBJECT, $y = NULL, $title = '' )
	{
		// If there is a query then perform it if not then use cached results..
		if( $query )
		{
			$this->query($query, $title);
		}

		if( ! $this->num_rows
			|| ( isset($y) && ! mysqli_data_seek($this->result, $y) ) )
		{
			if( $output == OBJECT )
				return NULL;
			else
				return array();
		}

		// If the output is an object then return object using the row offset..
		switch( $output )
		{
		case OBJECT:
			return mysqli_fetch_object($this->result);

		case ARRAY_A:
			return mysqli_fetch_array($this->result, MYSQL_ASSOC);

		case ARRAY_N:
			return mysqli_fetch_array($this->result, MYSQL_NUM);

		default:
			$this->print_error('DB::get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N', '', false);
			break;
		}
	}


	/**
	 * Function to get 1 column from the cached result set based on X index
	 * see docs for usage and info
	 *
	 * @return array
	 */
	function get_col( $query = NULL, $x = 0, $title = '' )
	{
		// If there is a query then perform it if not then use cached results..
		if( $query )
		{
			$this->query( $query, $title );
		}

		// Extract the column values
		$new_array = array();
		for( $i = 0; $i < $this->num_rows; $i++ )
		{
			$new_array[$i] = $this->get_var( NULL, $x, $i );
		}

		return $new_array;
	}


	/**
	 * Function to get the second column from the cached result indexed by the first column
	 *
	 * @return array [col_0] => col_1
	 */
	function get_assoc( $query = NULL, $title = '' )
	{
		// If there is a query then perform it if not then use cached results..
		if( $query )
		{
			$this->query( $query, $title );
		}

		// Extract the column values
		$new_array = array();
		for( $i = 0; $i < $this->num_rows; $i++ )
		{
			$key = $this->get_var( NULL, 0, $i );

			$new_array[$key] = $this->get_var( NULL, 1, $i );
		}

		return $new_array;
	}


	/**
	 * Return the the query as a result set - see docs for more details
	 *
	 * @return mixed
	 */
	function get_results( $query = NULL, $output = OBJECT, $title = '' )
	{
		// If there is a query then perform it if not then use cached results..
		if( $query )
		{
			$this->query($query, $title);
		}

		$r = array();

		if( $this->num_rows )
		{
			mysqli_data_seek($this->result, 0);
			switch( $output )
			{
			case OBJECT:
				while( $row = mysqli_fetch_object($this->result) )
				{
					$r[] = $row;
				}
				break;

			case ARRAY_A:
				while( $row = mysqli_fetch_array($this->result, MYSQLI_ASSOC) )
				{
					$r[] = $row;
				}
				break;

			case ARRAY_N:
				while( $row = mysqli_fetch_array($this->result, MYSQLI_NUM) )
				{
					$r[] = $row;
				}
				break;
			}
		}
		return $r;
	}


	/**
	 * Function to get column meta data info pertaining to the last query.
	 *
	 * NOTE: not used in whissip/b2evo anymore, but maintained still anyway.
	 *
	 * @param string|NULL Key of info, see {@link http://php.net/mysqli_fetch_field_direct} for a list;
	 *                    empty/NULL for an array with all entries
	 * @param integer Column offset; -1 for all
	 */
	function get_col_info( $info_type = 'name', $col_offset = -1 )
	{
		if( ! is_object($this->result) )
		{ // fp> A function should NEVER FAIL SILENTLY!
			debug_die( 'DB::get_col_info() cannot return a value because no result resource is available!' );
		}

		// Get column info:
		if( $col_offset == -1 )
		{ // all columns:
			$n = mysqli_num_fields($this->result);
			$i = 0;
			while( $i < $n )
			{
				$col_info[$i] = mysqli_fetch_field_direct($this->result, $i);
				$i++;
			}
		}
		else
		{
			$col_info = mysqli_fetch_field_direct($this->result, $col_offset);
		}

		if( empty($info_type) )
		{ // all field properties:
			return $col_info;
		}
		else
		{ // a specific column field property
			if( $col_offset == -1 )
			{
				$new_array = array();
				$i = 0;
				foreach( $col_info as $col )
				{
					$new_array[$i] = $col->{$info_type};
					$i++;
				}
				return $new_array;
			}
			else
			{
				return $col_info->{$info_type};
			}
		}
	}


	/**
	 * Get a table (or "<p>No Results.</p>") for the SELECT query results.
	 *
	 * @return string HTML table or "No Results" if the
	 */
	function debug_get_rows_table( $max_lines, $break_at_comma = false )
	{
		$r = '';

		if( ! $this->result || ! $this->num_rows ) {
			return '<p>No Results.</p>';
		}

		// Get column info:
		$col_info = array();
		$n = mysqli_num_fields($this->result);
		$i = 0;
		while( $i < $n ) {
			$col_info[$i] = mysqli_fetch_field_direct($this->result, $i);
			$i++;
		}

		// =====================================================
		// Results top rows
		$r .= '<table cellspacing="0" summary="Results for query"><tr>';
		for( $i = 0, $count = count($col_info); $i < $count; $i++ )
		{
			$r .= '<th><span class="type">'.$col_info[$i]->type.' '.$col_info[$i]->max_length.'</span><br />'
						.$col_info[$i]->name.'</th>';
		}
		$r .= '</tr>';

		// ======================================================
		// Print main results
		$i=0;
		// Rewind to first row (should be there already).
		mysqli_data_seek($this->result, 0);
		while( $one_row = $this->get_row(NULL, ARRAY_N) )
		{
			$i++;
			if( $i >= $max_lines ) {
				break;
			}
			$r .= '<tr>';
			foreach( $one_row as $item ) {
				if( $i % 2 ) {
					$r .= '<td class="odd">';
				}	else {
					$r .= '<td>';
				}

				if( $break_at_comma ) {
					$r .= str_replace( array(',', ';'), '<br />', htmlspecialchars($item) );
				} else {
					$r .= strmaxlen($item, 100, NULL, 'htmlspecialchars');
				}
				$r .= '</td>';
			}
			$r .= '</tr>';
		}
		// Rewind to first row again.
		mysqli_data_seek($this->result, 0);
		if( $i >= $max_lines ) {
			$r .= '<tr><td colspan="'.(count($col_info)+1).'">Max number of dumped rows has been reached.</td></tr>';
		}

		$r .= '</table>';

		return $r;
	}


	/**
	 * Format a SQL query
	 * @static
	 * @param string SQL
	 * @param boolean Format with/for HTML?
	 */
	function format_query( $sql, $html = true, $maxlen = NULL )
	{
		$sql = trim( str_replace("\t", '  ', $sql ) );
		if( $maxlen )
		{
			$sql = strmaxlen($sql, $maxlen, '...');
		}

		$new = '';
		$word = '';
		$in_comment = false;
		$in_literal = false;
		for( $i = 0, $n = strlen($sql); $i < $n; $i++ )
		{
			$c = $sql[$i];
			if( $in_comment )
			{
				if( $in_comment === '/*' && substr($sql, $i, 2) == '*/' )
					$in_comment = false;
				elseif( $c == "\n" )
					$in_comment = false;
			}
			elseif( $in_literal )
			{
				if( $c == $in_literal )
					$in_literal = false;
			}
			elseif( $c == '#' || ($c == '-' && substr($sql, $i, 3) == '-- ') )
			{
				$in_comment = true;
			}
			elseif( ctype_space($c) )
			{
				$uword = strtoupper($word);
				if( in_array($uword, array('SELECT', 'FROM', 'WHERE', 'GROUP', 'ORDER', 'LIMIT', 'VALUES', 'AND', 'OR', 'LEFT', 'RIGHT', 'INNER')) )
				{
					$new = rtrim($new)."\n".str_pad($word, 6, ' ', STR_PAD_LEFT).' ';
					# Remove any trailing whitespace after keywords
					while( ctype_space($sql[$i+1]) ) {
						++$i;
					}
				}
				else
				{
					$new .= $word.$c;
				}
				$word = '';
				continue;
			}
			$word .= $c;
		}
		$sql = trim($new.$word);

		if( ! $html )
		{
			return $sql;
		}

		if( empty($GLOBALS['db_use_geshi_highlighting']) )
		{ // poor man's indent
			$sql = htmlspecialchars($sql);
			$sql = preg_replace_callback("~^(\s+)~m", create_function('$m', 'return str_replace(" ", "&nbsp;", $m[1]);'), $sql);
			$sql = nl2br($sql);
			return $sql;
		}

		# Parse/Highlight SQL using GeSHi
		static $geshi;
		if( ! isset($geshi) ) {
			load_funcs( '_ext/geshi/geshi.php' );
			$geshi = new GeSHi($sql, 'mysql');
			$geshi->set_header_type(GESHI_HEADER_NONE);
			$geshi->set_tab_width(2);
		} else {
			$geshi->set_source($sql);
		}
		return $geshi->parse_code();
	}


	/**
	 * Displays all queries that have been executed
	 *
	 * @param boolean Use HTML.
	 */
	function dump_queries( $html = true )
	{
		if ( $html )
		{
			echo '<strong>DB queries:</strong> '.$this->num_queries."<br />\n";
		}
		else
		{
			echo 'DB queries: '.$this->num_queries."\n\n";
		}

		if( ! $this->log_queries )
		{ // nothing more to do here..
			return;
		}

		global $Timer;
		if( is_object( $Timer ) )
		{
			$time_queries = $Timer->get_duration( 'SQL QUERIES' , 4 );
		}
		else
		{
			$time_queries = 0;
		}

		$count_queries = 0;
		$count_rows = 0;
		$time_queries_profiled = 0;

		// Javascript function to toggle DIVs (EXPLAIN, results, backtraces).
		if( $html )
		{
			global $rsc_url;
			echo '<script type="text/javascript" src="'.$rsc_url.'js/debug.js"></script>';
		}

		foreach( $this->queries as $i => $query )
		{
			$count_queries++;

			$get_md5_query = create_function( '', '
				static $r; if( isset($r) ) return $r;
				global $query;
				$r = md5(serialize($query))."-".rand();
				return $r;' );

			if ( $html )
			{
				echo '<h4>Query #'.$count_queries.': '.$query['title']."</h4>\n";

				$div_id = 'db_query_sql_'.$i.'_'.$get_md5_query();
				if( strlen($query['sql']) > 512 )
				{
					$sql_short = $this->format_query( $query['sql'], true, 512 );
					$sql = $this->format_query( $query['sql'], true );

					echo '<code id="'.$div_id.'" style="display:none">'.$sql_short.'</code>';
					echo '<code id="'.$div_id.'_full">'.$sql.'</code>';
					echo '<script type="text/javascript">debug_onclick_toggle_div("'.$div_id.','.$div_id.'_full", "Hide full SQL", "Show full SQL");</script>';
				}
				else
				{
					echo '<code>'.$this->format_query( $query['sql'] ).'</code>';
				}
				echo "\n";
			}
			else
			{
				echo '= Query #'.$count_queries.': '.$query['title']." =\n";
				echo $this->format_query( $query['sql'], false )."\n\n";
			}

			// Color-Format duration: long => red, fast => green, normal => black
			if( $query['time'] > $this->query_duration_slow )
			{
				$style_time_text = 'color:red;font-weight:bold;';
				$style_time_graph = 'background-color:red;';
				$plain_time_text = ' [slow]';
			}
			elseif( $query['time'] < $this->query_duration_fast )
			{
				$style_time_text = 'color:green;';
				$style_time_graph = 'background-color:green;';
				$plain_time_text = ' [fast]';
			}
			else
			{
				$style_time_text = '';
				$style_time_graph = 'background-color:black;';
				$plain_time_text = '';
			}

			// Number of rows with time (percentage and graph, if total time available)
			if ( $html )
			{
				echo '<div class="query_info">';
				echo 'Rows: '.$query['rows'];

				echo ' &ndash; Time: ';
			}
			else
			{
				echo 'Rows: '.$query['rows'].' - Time: ';
			}

			if( $html && $style_time_text )
			{
				echo '<span style="'.$style_time_text.'">';
			}
			echo number_format( $query['time'], 4 ).'s';

			if( $time_queries > 0 )
			{ // We have a total time we can use to calculate percentage:
				echo ' ('.number_format( 100/$time_queries * $query['time'], 2 ).'%)';
			}

			if( isset($query['time_profile']) )
			{
				echo ' (real: '.number_format($query['time_profile'], 4).'s)';
				$time_queries_profiled += $query['time_profile'];
			}

			if( $style_time_text || $plain_time_text )
			{
				echo $html ? '</span>' : $plain_time_text;
			}

			if( $time_queries > 0 )
			{ // We have a total time we can use to display a graph/bar:
				$perc = round( 100/$time_queries * $query['time'] );

				if ( $html )
				{
					echo '<div style="margin:0; padding:0; height:12px; width:'.$perc.'%;'.$style_time_graph.'"></div>';
				}
				else
				{	// display an ASCII bar
					printf( "\n".'[%-50s]', str_repeat( '=', $perc / 2 ) );
				}
			}
			echo $html ? '</div>' : "\n\n";

			// EXPLAIN JOINS ??
			if( $this->debug_explain_joins && preg_match( '#^ [\s(]* SELECT \s #ix', $query['sql']) )
			{ // Query was a select, let's try to explain joins...

				$this->result = mysqli_query( $this->dbhandle, 'EXPLAIN '.$query['sql'] );
				if( is_object($this->result) )
				{ // will be false for invalid SQL
					$this->num_rows = mysqli_num_rows($this->result);

					if( $html )
					{
						$div_id = 'db_query_explain_'.$i.'_'.$get_md5_query();
						echo '<div id="'.$div_id.'">';
						echo $this->debug_get_rows_table( 100, true );
						echo '</div>';
						echo '<script type="text/javascript">debug_onclick_toggle_div("'.$div_id.'", "Show EXPLAIN", "Hide EXPLAIN");</script>';
					}
					else
					{ // TODO: dh> contains html.
						echo $this->debug_get_rows_table( 100, true );
					}
					mysqli_free_result($this->result);
				}
			}

			// Profile:
			if( isset($query['profile']) )
			{
				if( $html )
				{
					$div_id = 'db_query_profile_'.$i.'_'.$get_md5_query();
					echo '<div id="'.$div_id.'">';
					echo $query['profile'];
					echo '</div>';
					echo '<script type="text/javascript">debug_onclick_toggle_div("'.$div_id.'", "Show PROFILE", "Hide PROFILE");</script>';
				}
				else
				{ // TODO: dh> contains html.
					echo $this->debug_get_rows_table( 100, true );
				}
			}

			// Results:
			if( $query['results'] != 'unknown' )
			{
				if( $html )
				{
					$div_id = 'db_query_results_'.$i.'_'.$get_md5_query();
					echo '<div id="'.$div_id.'">';
					echo $query['results'];
					echo '</div>';
					echo '<script type="text/javascript">debug_onclick_toggle_div("'.$div_id.'", "Show results", "Hide results");</script>';
				}
				else
				{ // TODO: dh> contains html.
					echo $query['results'];
				}
			}

			// Function trace:
			if( isset($query['function_trace']) )
			{
				if( $html )
				{
					$div_id = 'db_query_backtrace_'.$i.'_'.$get_md5_query();
					echo '<div id="'.$div_id.'">';
					echo $query['function_trace'];
					echo '</div>';
					echo '<script type="text/javascript">debug_onclick_toggle_div("'.$div_id.'", "Show function trace", "Hide function trace");</script>';
				}
				else
				{ // TODO: dh> contains html.
					echo $query['function_trace'];
				}
			}

			echo $html ? '<hr />' : "=============================================\n";

			$count_rows += $query['rows'];
		}

		$time_queries_profiled = number_format($time_queries_profiled, 4);
		$time_diff_percentage = $time_queries_profiled != 0 ? round($time_queries / $time_queries_profiled * 100) : false;
		if ( $html )
		{
			echo "\nTotal rows: $count_rows<br />\n";
			echo "\nMeasured time: {$time_queries}s<br />\n";
			echo "\nProfiled time: {$time_queries_profiled}s<br />\n";
			if( $time_diff_percentage !== false )
			{
				echo "\nTime difference: {$time_diff_percentage}%<br />\n";
			}
		}
		else
		{
			echo 'Total rows: '.$count_rows."\n";
			echo "Measured time: {$time_queries}s\n";
			echo "Profiled time: {$time_queries_profiled}s\n";
			if( $time_diff_percentage !== false )
			{
				echo "Time difference: {$time_diff_percentage}%\n";
			}
		}
	}


	/**
	 * BEGIN A TRANSCATION
	 *
	 * Note:  By default, MySQL runs with autocommit mode enabled.
	 * This means that as soon as you execute a statement that updates (modifies)
	 * a table, MySQL stores the update on disk.
	 * Once you execute a BEGIN, the updates are "pending" until you execute a
	 * {@link DB::commit() COMMIT} or a {@link DB:rollback() ROLLBACK}
	 *
	 * Note 2: standard syntax would be START TRANSACTION but it's not supported by older
	 * MySQL versions whereas BEGIN is...
	 *
	 * Note 3: The default isolation level is REPEATABLE READ.
	 */
	function begin()
	{
		if( $this->use_transactions )
		{
			$this->query( 'BEGIN', 'BEGIN transaction' );

			$this->transaction_nesting_level++;
		}
	}


	/**
	 * Commit current transaction
	 */
	function commit()
	{
		if( $this->use_transactions )
		{
			if( $this->transaction_nesting_level == 1 )
			{ // Only COMMIT if there are no remaining nested transactions:
				if( $this->rollback_nested_transaction )
				{
					$this->query( 'ROLLBACK', 'ROLLBACK transaction because there was a failure somewhere in the nesting of transactions' );
				}
				else
				{
					$this->query( 'COMMIT', 'COMMIT transaction' );
				}
				$this->rollback_nested_transaction = false;
			}
			if( $this->transaction_nesting_level )
			{
				$this->transaction_nesting_level--;
			}
		}
	}


	/**
	 * Rollback current transaction
	 */
	function rollback()
	{
		if( $this->use_transactions )
		{
			if( $this->transaction_nesting_level == 1 )
			{ // Only ROLLBACK if there are no remaining nested transactions:
				$this->query( 'ROLLBACK', 'ROLLBACK transaction' );
				$this->rollback_nested_transaction = false;
			}
			else
			{ // Remember we'll have to roll back at the end!
				$this->rollback_nested_transaction = true;
			}
			if( $this->transaction_nesting_level )
			{
				$this->transaction_nesting_level--;
			}
		}
	}

}


/*
 * $Log$
 * Revision 1.52  2010/05/02 00:02:30  blueyed
 * DB::dump_queries: Fix mysql_free_result when using profiling/explain.
 *
 * Revision 1.51  2010/03/29 19:02:00  blueyed
 * DB class: improve debugging
 *  - Improve format_query parsing
 *  - dump_queries: crop long queries (toggable)
 *  - Move toggle JS to rsc/js/debug.js
 *
 * Revision 1.50  2010/02/08 17:51:51  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.49  2010/01/16 05:21:25  sam2kb
 * Deleted crap text at the bottom
 *
 * Revision 1.48  2010/01/15 18:34:12  blueyed
 * The dl function is deprecated and not available in PHP 5.3. Do not make us produce white pages.
 *
 * Revision 1.47  2009/12/10 20:13:24  blueyed
 * Add log_errors property to DB and set it to false in get_db_version to not
 * log SQL errors which are expected during install.
 *
 * Revision 1.46  2009/12/06 01:52:54  blueyed
 * Add 'htmlspecialchars' type to format_to_output, same as formvalue, but less irritating. Useful for strmaxlen, which is being used in more places now.
 *
 * Revision 1.45  2009/12/01 20:37:11  blueyed
 * DB::select: set dbname
 *
 * Revision 1.44  2009/11/30 00:22:04  fplanque
 * clean up debug info
 * show more timers in view of block caching
 *
 * Revision 1.43  2009/11/16 20:44:07  blueyed
 *  - Use escape in quote (makes mocking in tests easier)
 *  - get_var: add support for $y=NULL (next row)
 *
 * Revision 1.42  2009/10/27 21:57:43  fplanque
 * minor/doc
 *
 * Revision 1.41  2009/10/19 21:56:01  blueyed
 * error_log SQL errors
 *
 * Revision 1.40  2009/10/04 21:10:16  blueyed
 * Merge db-noresultcache via whissip.
 *
 * Revision 1.39  2009/09/20 22:35:56  blueyed
 * whoops.
 *
 * Revision 1.38  2009/09/20 22:05:34  blueyed
 * DB:
 *  - log_queries requires $debug to be enabled, otherwise you won't see
 *    any results, but the performance drawback.
 *  - Save two IFs
 *
 * Revision 1.37  2009/09/16 20:50:52  tblue246
 * Do not divide by zero; style fix
 *
 * Revision 1.36  2009/09/13 21:32:42  blueyed
 * DB: display toggle links below dumped queries inline, saving some screen space.
 *
 * Revision 1.35  2009/09/13 21:32:16  blueyed
 * DB: add "debug_profile_queries" option, which uses MySQL profiling. Info is displayed when dumping queries and total time is compared to measured time.
 *
 * Revision 1.34  2009/09/13 21:29:59  blueyed
 * DB: fix debug_get_rows_table, which returned 'No results' since 1.32. Only display result related info if there are any rows now.
 *
 * Revision 1.33  2009/07/25 00:47:21  blueyed
 * Add log_queries param to DB constructor. Used from tests for performance reason.
 *
 * Revision 1.32  2009/07/25 00:39:00  blueyed
 * DB::debug_get_rows_table: only print result rows, if there is a result.
 *
 * Revision 1.31  2009/07/24 23:36:47  blueyed
 * doc
 *
 * Revision 1.30  2009/07/22 20:51:18  blueyed
 * Only display P and brackets if there is a DB error, which is not the case with wrong user/pass (oddly)
 *
 * Revision 1.29  2009/07/12 23:18:22  fplanque
 * upgrading tables to innodb
 *
 * Revision 1.28  2009/07/10 15:59:04  sam2kb
 * Change DB connection charset only if SET NAMES worked
 *
 * Revision 1.27  2009/07/10 10:54:06  tblue246
 * Doc
 *
 * Revision 1.26  2009/07/09 23:23:40  fplanque
 * Check that DB supports proper charset before installing.
 *
 * Revision 1.25  2009/07/09 22:57:32  fplanque
 * Fixed init of connection_charset, especially during install.
 *
 * Revision 1.24  2009/04/22 19:43:02  blueyed
 * debug_get_rows_table: use get_row (and properly for HEAD, where it does not default to NULL/next row (fixing r1.23)
 *
 * Revision 1.23  2009/04/22 19:27:36  blueyed
 * debug_get_rows_table: use get_row instead of get_results, since it stops after 'max rows'.
 *
 * Revision 1.22  2009/03/08 23:57:40  fplanque
 * 2009
 *
 * Revision 1.21  2009/03/03 00:59:10  fplanque
 * doc
 *
 * Revision 1.20  2009/03/03 00:33:06  fplanque
 * no need to do all that extra processing and html sending by default, even when debug is on.
 *
 * Revision 1.19  2009/03/02 21:36:51  blueyed
 * Add "toggle" links to EXPLAIN, Results and Function trace lists in
 * DB::dump_queries.
 * debug_explain_joins, debug_dump_function_trace_for_queries and
 * debug_dump_rows follow debug/log_queries now, since they are collapsed
 * now and provide valuable info.
 * TODO: those lists contain HTML still, maybe strip tags in them?
 *
 * Revision 1.18  2009/02/22 17:52:03  blueyed
 * Fix indent
 *
 * Revision 1.17  2009/02/11 20:04:42  blueyed
 * Drop usage of $func_call - got only set, but never used.
 *
 * Revision 1.16  2009/02/05 15:09:35  blueyed
 * DB class: fix EXPLAIN for queries starting with (SELECT (e.g. unions)
 *
 * Revision 1.15  2008/11/17 11:41:35  blueyed
 * Fix DB::save_error_state/DB::restore_error_state to also handle $last_error/$error and make it chainable
 *
 * Revision 1.14  2008/11/17 11:20:26  blueyed
 * Remove wrapper for mysql_real_escape_string, if it does not exist - it exists since PHP 4.3
 *
 * Revision 1.13  2008/11/17 11:16:19  blueyed
 * DB::print_error(): do not display errors if $halt_on_error is true, but $show_errors is false
 *
 * Revision 1.12  2008/11/07 23:20:10  tblue246
 * debug_info() now supports plain text output for the CLI.
 *
 * Revision 1.11  2008/10/10 14:00:06  blueyed
 * Improved DB error handling
 *
 * Revision 1.10  2008/09/29 21:31:18  blueyed
 * Add DB::save_error_state()/restore_error_state() to unify ignoring of errors
 *
 * Revision 1.9  2008/09/27 07:54:33  fplanque
 * minor
 *
 * Revision 1.8  2008/04/24 01:56:08  fplanque
 * Goal hit summary
 *
 * Revision 1.7  2008/02/19 11:11:17  fplanque
 * no message
 *
 * Revision 1.6  2008/02/15 12:50:40  waltercruz
 * Verifying if MySQL version is greater than 5.0.2 to set the SQL Mode  to TRADITIONAL
 *
 * Revision 1.5  2008/01/21 09:35:24  fplanque
 * (c) 2008
 *
 * Revision 1.4  2007/12/28 18:59:26  blueyed
 * - Fix for table_options and trailing semicolon
 * - todo about table_options
 *
 * Revision 1.3  2007/12/09 21:25:22  blueyed
 * doc
 *
 * Revision 1.2  2007/10/01 19:02:23  fplanque
 * MySQL version check
 *
 * Revision 1.1  2007/06/25 10:58:58  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.61  2007/06/19 23:17:52  blueyed
 * Force MySQL strict mode, if $debug
 *
 * Revision 1.60  2007/06/19 23:15:08  blueyed
 * doc fixes
 *
 * Revision 1.59  2007/05/14 02:44:14  fplanque
 * allow quoting of arrays
 *
 * Revision 1.58  2007/04/26 00:11:07  fplanque
 * (c) 2007
 *
 * Revision 1.57  2007/03/11 22:30:08  fplanque
 * cleaned up group perms
 *
 * Revision 1.56  2007/02/09 17:28:56  blueyed
 * doc
 *
 * Revision 1.55  2007/01/29 01:21:22  blueyed
 * Do not let $transaction_nesting_level become negative!
 *
 * Revision 1.54  2007/01/25 05:14:13  fplanque
 * rollback
 *
 * Revision 1.52  2006/12/14 00:42:04  fplanque
 * A little bit of windows detection / normalization
 *
 * Revision 1.51  2006/12/07 23:12:21  fplanque
 * @var needs to have only one argument: the variable type
 * Otherwise, I can't code!
 *
 * Revision 1.50  2006/12/03 21:27:21  blueyed
 * Save and reset $error with set_connection_charset(); TODO
 *
 * Revision 1.49  2006/11/28 02:52:26  fplanque
 * doc
 *
 * Revision 1.48  2006/11/28 00:33:01  blueyed
 * Removed DB::compString() (never used) and DB::get_list() (just a macro and better to have in the 4 used places directly; Cleanup/normalization; no extended regexp, when not needed!
 *
 * Revision 1.47  2006/11/27 20:54:07  fplanque
 * doc
 *
 * Revision 1.46  2006/11/27 01:35:47  blueyed
 * Removed get_col_info() and free mysql_result in query() always again
 *
 * Revision 1.45  2006/11/26 11:12:38  fplanque
 * doc / todo
 *
 * Revision 1.44  2006/11/26 03:17:53  blueyed
 * doc about resource freeing and flush() in general
 *
 * Revision 1.43  2006/11/26 02:30:39  fplanque
 * doc / todo
 *
 * Revision 1.42  2006/11/24 18:27:27  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.41  2006/11/23 15:33:58  blueyed
 * Small opt
 *
 * Revision 1.40  2006/11/20 12:23:28  blueyed
 * Optimized col_info handling: obsoleted DB::col_info: use DB::get_col_info() instead (lazy-loading of column info)
 *
 * Revision 1.39  2006/11/19 23:30:38  fplanque
 * made simpletest almost installable by almost bozos almost like me
 *
 * Revision 1.38  2006/11/18 03:44:48  fplanque
 * reverted to optimized col info
 *
 * Revision 1.36  2006/11/17 01:44:38  fplanque
 * A function should NEVER FAIL SILENTLY!
 *
 * Revision 1.35  2006/11/14 17:35:39  blueyed
 * small opt
 *
 * Revision 1.34  2006/11/04 18:39:15  blueyed
 * Normalized
 *
 * Revision 1.33  2006/11/04 18:11:42  fplanque
 * comments
 *
 * Revision 1.32  2006/11/04 01:29:55  blueyed
 * Better error displaying. Fix: use $html_str in print_error()
 *
 * Revision 1.31  2006/11/04 01:22:29  blueyed
 * Proposed fix for users with PHP < 4.3: let them get the PHP error.
 *
 * Revision 1.30  2006/11/03 00:22:21  blueyed
 * $log_queries follows $debug global; Removed dumpvar() and vardump() - use pre_dump()
 *
 * Revision 1.29  2006/11/02 19:49:22  fplanque
 * no message
 *
 * Revision 1.28  2006/10/28 15:05:25  blueyed
 * CLI/non-HTML support for print_error() and format_query()
 *
 * Revision 1.27  2006/10/14 03:05:59  blueyed
 * MFB: fix
 *
 * Revision 1.26  2006/10/10 21:42:42  blueyed
 * Optimization: only collect $col_info, if $log_queries is enabled. TODO.
 *
 * Revision 1.25  2006/10/10 21:24:29  blueyed
 * Fix for the optimization
 *
 * Revision 1.24  2006/10/10 21:21:40  blueyed
 * Fixed possible SQL error, if table_options get used and theres a semicolon at the end of query; +optimization
 *
 * Revision 1.23  2006/10/10 21:17:42  blueyed
 * Fixed possible fatal error while collecting col_info for CREATE and DROP queries
 */
?>
