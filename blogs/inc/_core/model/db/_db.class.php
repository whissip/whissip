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
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}.
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

if( ! function_exists( 'mysql_real_escape_string' ) )
{ // Function only available since PHP 4.3.0
	function mysql_real_escape_string( $unescaped_string )
	{
		return mysql_escape_string( $unescaped_string );
	}
}

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
	 * @var resource
	 */
	var $result;

	/**
	 * Last result's rows
	 * @var array
	 */
	var $last_result;

	/**
	 * Number of rows in result set (after a select)
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
	 * Recommended settings: ' ENGINE=InnoDB '
	 * Development settings: ' ENGINE=InnoDB DEFAULT CHARSET=utf8 '
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
	 * @var object
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

	/**
	 * Current connection charset
	 * @var string
	 * @see DB::set_connection_charset()
	 */
	var $connection_charset;


	// DEBUG:

  /**
   * Do we want to log queries?
	 * This gets set according to {@link $debug}, if it's set.
	 * @todo fp> get rid of this var, use $debug only
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
	 * @var boolean
	 */
	var $debug_explain_joins = false;

	/**
	 * Do we want to output a function backtrace for every query?
	 * Number of stack entries to show (from last to first) (Default: 0); true means 'all'.
	 *
	 * This requires {@link DB::$log_queries} to be true.
	 *
	 * @var integer
	 */
	var $debug_dump_function_trace_for_queries = 0;

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
	 *    - 'handle': a MySQL Database handle (from a previous {@link mysql_connect()})
	 *   Optional:
	 *    - 'name': the name of the default database, see {@link DB::select()}
	 *    - 'host': host of the database; Default: 'localhost'
	 *    - 'show_errors': Display SQL errors? (true/false); Default: don't change member default ({@link $show_errors})
	 *    - 'halt_on_error': Halt on error? (true/false); Default: don't change member default ({@link $halt_on_error})
	 *    - 'table_options': sets {@link $table_options}
	 *    - 'use_transactions': sets {@link $use_transactions}
	 *    - 'aliases': Aliases for tables (array( alias => table name )); Default: no aliases.
	 *    - 'new_link': create a new link to the DB, even if there was a mysql_connect() with
	 *       the same params before. (requires PHP 4.2)
	 *    - 'client_flags': optional settings like compression or SSL encryption. See {@link http://www.php.net/manual/en/ref.mysql.php#mysql.client-flags}.
	 *       (requires PHP 4.3)
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
		if( isset($params['debug_dump_function_trace_for_queries']) ) $this->debug_dump_function_trace_for_queries = $params['debug_dump_function_trace_for_queries'];

		if( isset($debug) && ! isset($this->log_queries) )
		{ // $log_queries follows $debug and respects subclasses, which may define it:
			$this->log_queries = $debug;
		}

		if( ! extension_loaded('mysql') )
		{ // The mysql extension is not loaded, try to dynamically load it:
			$mysql_ext_file = is_windows() ? 'php_mysql.dll' : 'mysql.so';
			@dl( $mysql_ext_file );

			if( ! extension_loaded('mysql') )
			{ // Still not loaded:
				$this->print_error( 'The PHP MySQL module could not be loaded.', '
					<p>You must edit your php configuration (php.ini) and enable this module ('.$mysql_ext_file.').</p>
					<p>Do not forget to restart your webserver (if necessary) after editing the PHP conf.</p>', false );
				return;
			}
		}

		$new_link = isset( $params['new_link'] ) ? $params['new_link'] : false;
		$client_flags = isset( $params['client_flags'] ) ? $params['client_flags'] : 0;

		if( ! isset($params['handle']) )
		{ // Connect to the Database:
			// echo "mysql_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags )";
			$this->dbhandle = @mysql_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags );
		}

		if( ! $this->dbhandle )
		{
			$mysql_error = mysql_error();
			if( empty($mysql_error) )
			{ // there was a PHP error, like with version below 4.3 which do not support new_link and client_flags; let PHP throw an error:
				$this->print_error( 'Error establishing a database connection!', '
					<p>If you are running a PHP version below 4.3, please upgrade.</p>', false );	// fp> Other causes include: simple tests passing wrong params!

				// Let PHP throw an error:
				mysql_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags );
			}
			else
			{
				$this->print_error( 'Error establishing a database connection!', '
					<p>('.$mysql_error.')</p>
					<ol>
						<li>Are you sure you have typed the correct user/password?</li>
						<li>Are you sure that you have typed the correct hostname?</li>
						<li>Are you sure that the database server is running?</li>
					</ol>', false );
			}
		}
		elseif( isset($this->dbname) )
		{
			$this->select($this->dbname);
		}


		if( !empty($params['connection_charset']) )
		{	// Specify which charset we are using on the client:
			$this->set_connection_charset($params['connection_charset']);
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

		if( $debug )
		{ // Force MySQL strict mode
			//As  Austriaco pointed on the forum (http://forums.b2evolution.net/viewtopic.php?p=68443), 
			//TRADITIONAL mode is only available to mysql > 5.0.22 . 
			$mysql_version = $this->get_version();
			if( version_compare( $mysql_version, '5.0.2' ) > 0 )
			{
				$this->query( 'SET sql_mode = "TRADITIONAL"' );
			}
		}
	}


	/**
	 * Select a DB (if another one needs to be selected)
	 */
	function select($db)
	{
		if( !@mysql_select_db($db, $this->dbhandle) )
		{
			$this->print_error( 'Error selecting database ['.$db.']!', '
				<ol>
					<li>Are you sure the database exists?</li>
					<li>Are you sure the DB user is allowed to use that database?</li>
					<li>Are you sure there is a valid database connection?</li>
				</ol>', false );
		}
	}


	/**
	 * Format a string correctly for safe insert under all PHP conditions
	 */
	function escape($str)
	{
		return mysql_real_escape_string($str, $this->dbhandle);
	}


	/**
	 * Quote a value, either in single quotes (and escaped) or if it's NULL as 'NULL'.
	 *
	 * @param string|array|null
	 * @return string Quoted (and escaped) value or 'NULL'.
	 */
	function quote($str)
	{
		if( is_null( $str ) )
		{
			return 'NULL';
		}
		elseif( is_array( $str ) )
		{
			$r = '';
			foreach( $str as $elt )
			{
				$r .= $this->quote($elt).',';
			}
			$r = substr( $r, 0, strlen( $r ) - 1 );
			return $r;
		}
		else
		{
			return "'".mysql_real_escape_string($str, $this->dbhandle)."'";
		}
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
		$this->last_error = empty($title) ? ( mysql_error($this->dbhandle).'(Errno='.mysql_errno($this->dbhandle).')' ) : $title;

		// Log this error to the global array..
		$EZSQL_ERROR[] = array(
			'query' => $this->last_query,
			'error_str'  => $this->last_error
		);

		if( ! ( $this->halt_on_error || $this->show_errors ) )
		{ // no reason to generate a nice message:
			return;
		}

		if( $is_cli )
		{ // Clean error message for command line interface:
			$err_msg = "MySQL error! {$this->last_error}\n";
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
		// Get rid of these
		$this->last_result = NULL;
		$this->last_query = NULL;
	}


  /**
	 * Get MYSQL version
	 */
	function get_version()
	{
		if( isset( $this->version ) )
		{
			return $this->version;
		}

    $save_show_errors = $this->show_errors;
		$save_halt_on_error = $this->halt_on_error;
		// Blatantly ignore any error generated by potentially unknown function...
		$this->show_errors = false;
		$this->halt_on_error = false;
		$last_error = $this->last_error;
		$error = $this->error;
		if( ($this->version_long = $this->get_var( 'SELECT VERSION()' ) ) === NULL )
		{	// Very old version ( < 4.0 )
			$this->version = '';
			$this->version_long = '';
		}
		else
		{
			$this->version = preg_replace( '�-.*�', '', $this->version_long );
		}
		$this->show_errors = $save_show_errors;
		$this->halt_on_error = $save_halt_on_error;
		$this->last_error = $last_error;
		$this->error = $error;
		$this->halt_on_error = false;

		return $this->version;
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

		// Log how the function was called
		$this->func_call = '$db->query("'.$query.'")';
		// echo $this->func_call, '<br />';

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
			$Timer->resume( 'sql_queries' );
			// Start a timer for this particular query:
			$Timer->start( 'sql_query', false );

			// Run query:
			$this->result = @mysql_query( $query, $this->dbhandle );

			if( $this->log_queries )
			{	// We want to log queries:
				// Get duration for last query:
				$this->queries[ $this->num_queries - 1 ]['time'] = $Timer->get_duration( 'sql_query', 10 );
			}

			// Pause global query timer:
			$Timer->pause( 'sql_queries' );
		}
		else
		{
			// Run query:
			$this->result = @mysql_query( $query, $this->dbhandle );
		}

		// If there is an error then take note of it..
		if( mysql_error($this->dbhandle) )
		{
			@mysql_free_result($this->result);
			$this->print_error( '', '', $title );
			return false;
		}

		if( preg_match( '#^\s*(INSERT|DELETE|UPDATE|REPLACE)\s#i', $query, $match ) )
		{ // Query was an insert, delete, update, replace:

			$this->rows_affected = mysql_affected_rows($this->dbhandle);
			if( $this->log_queries )
			{	// We want to log queries:
				$this->queries[ $this->num_queries - 1 ]['rows'] = $this->rows_affected;
			}

			// Take note of the insert_id, for INSERT and REPLACE:
			$match[1] = strtoupper($match[1]);
			if( $match[1] == 'INSERT' || $match[1] == 'REPLACE' )
			{
				$this->insert_id = mysql_insert_id($this->dbhandle);
			}

			// Return number of rows affected
			$return_val = $this->rows_affected;
		}
		else
		{ // Query was a select, alter, etc...:
			$this->num_rows = 0;

			if( is_resource($this->result) )
			{ // It's not a resource for CREATE or DROP for example and can even trigger a fatal error (see http://forums.b2evolution.net//viewtopic.php?t=9529)

				// Store Query Results
				while( $row = mysql_fetch_object($this->result) )
				{
					// Store relults as an objects within main array
					$this->last_result[$this->num_rows] = $row;
					$this->num_rows++;
				}
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
			if( $this->debug_dump_function_trace_for_queries )
			{
				$this->queries[ $this->num_queries - 1 ]['function_trace'] = debug_get_backtrace( $this->debug_dump_function_trace_for_queries, array( array( 'class' => 'DB' ) ), 1 ); // including first stack entry from class DB
			}

			if( $this->debug_dump_rows )
			{
				$this->queries[ $this->num_queries - 1 ]['results'] = $this->debug_get_rows_table( $this->debug_dump_rows );
			}
		}

		// Free original query's result:
		@mysql_free_result($this->result);

		// EXPLAIN JOINS ??
		if( $this->log_queries && $this->debug_explain_joins && preg_match( '#^ \s* SELECT \s #ix', $query) )
		{ // Query was a select, let's try to explain joins...

			// save values:
			$saved_last_result = $this->last_result;
			$saved_num_rows = $this->num_rows;

			$this->last_result = NULL;
			$this->num_rows = 0;

			$this->result = @mysql_query( 'EXPLAIN '.$query, $this->dbhandle );

			// Store Query Results
			$this->num_rows = 0;
			while( $row = @mysql_fetch_object($this->result) )
			{
				// Store results as an objects within main array
				$this->last_result[$this->num_rows] = $row;
				$this->num_rows++;
			}

			$this->queries[ $this->num_queries - 1 ]['explain'] = $this->debug_get_rows_table( 100, true );

			// Free "EXPLAIN" result resource:
			@mysql_free_result($this->result);

			// Restore:
			$this->last_result = $saved_last_result;
			$this->num_rows = $saved_num_rows;
		}

		return $return_val;
	}


	/**
	 * Get one variable from the DB - see docs for more detail
	 *
	 * Note: To be sure that you received NULL from the DB and not "no rows" check
	 *       for {@link $num_rows}.
	 *
	 * @return mixed NULL if not found, the value otherwise (which may also be NULL).
	 */
	function get_var( $query = NULL, $x = 0, $y = 0, $title = '' )
	{
		// Log how the function was called
		$this->func_call = "\$db->get_var(\"$query\",$x,$y)";

		// If there is a query then perform it if not then use cached results..
		if( $query )
		{
			$this->query($query, $title);
		}

		// Extract var out of cached results based x,y vals
		if( $this->last_result[$y] )
		{
			$values = array_values(get_object_vars($this->last_result[$y]));
		}

		if( isset($values[$x]) )
		{
			return $values[$x];
		}

		return NULL;
	}


	/**
	 * Get one row from the DB - see docs for more detail
	 *
	 * @return array|object
	 */
	function get_row( $query = NULL, $output = OBJECT, $y = 0, $title = '' )
	{
		// If there is a query then perform it if not then use cached results..
		if( $query )
		{
			$this->query($query, $title);
		}

		// If the output is an object then return object using the row offset..
		if( $output == OBJECT )
		{
			return $this->last_result[$y]
				? $this->last_result[$y]
				: NULL;
		}
		// If the output is an associative array then return row as such..
		elseif( $output == ARRAY_A )
		{
			return $this->last_result[$y]
				? get_object_vars( $this->last_result[$y] )
				: array();
		}
		// If the output is an numerical array then return row as such..
		elseif( $output == ARRAY_N )
		{
			return $this->last_result[$y]
				? array_values( get_object_vars($this->last_result[$y]) )
				: array();
		}
		// If invalid output type was specified..
		else
		{
			$this->print_error('DB::get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N', '', false);
		}
	}


	/**
	 * Function to get 1 column from the cached result set based in X index
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
		for( $i = 0, $count = count($this->last_result); $i < $count; $i++ )
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
		for( $i = 0, $count = count($this->last_result); $i < $count; $i++ )
		{
			$key = $this->get_var( NULL, 0, $i );

			$new_array[$key] = $this->get_var( NULL, 1, $i );
		}

		return $new_array;
	}


	/**
	 * Return the the query as a result set - see docs for more details
	 *
	 * @return array
	 */
	function get_results( $query = NULL, $output = OBJECT, $title = '' )
	{
		// If there is a query then perform it if not then use cached results..
		if( $query )
		{
			$this->query($query, $title);
		}

		// Send back array of objects. Each row is an object
		if( $output == OBJECT )
		{
			return $this->last_result ? $this->last_result : array();
		}
		elseif( $output == ARRAY_A || $output == ARRAY_N )
		{
			$new_array = array();

			if( $this->last_result )
			{
				$i = 0;

				foreach( $this->last_result as $row )
				{
					$new_array[$i] = get_object_vars($row);

					if( $output == ARRAY_N )
					{
						$new_array[$i] = array_values($new_array[$i]);
					}

					$i++;
				}

				return $new_array;
			}
			else
			{
				return array();
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

		if( ! is_resource($this->result) )
		{
			return '<p>No Results.</p>';
		}

		// Get column info:
		$col_info = array();
		$n = mysql_num_fields($this->result);
		$i = 0;
		while( $i < $n )
		{
			$col_info[$i] = mysql_fetch_field($this->result, $i);
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

		$i=0;

		// ======================================================
		// print main results
		if( $this->last_result )
		{
			foreach( $this->get_results(NULL,ARRAY_N) as $one_row )
			{
				$i++;
				if( $i >= $max_lines )
				{
					break;
				}
				$r .= '<tr>';
				foreach( $one_row as $item )
				{
					if( $i % 2 )
					{
						$r .= '<td class="odd">';
					}
					else
					{
						$r .= '<td>';
					}

					if( $break_at_comma )
					{
						$item = str_replace( ',', '<br />', $item );
						$item = str_replace( ';', '<br />', $item );
						$r .= $item;
					}
					else
					{
						if( strlen( $item ) > 50 )
						{
							$item = substr( $item, 0, 50 ).'...';
						}
						$r .= htmlspecialchars($item);
					}
					$r .= '</td>';
				}

				$r .= '</tr>';
			}

		} // if last result
		else
		{
			$r .= '<tr><td colspan="'.(count($col_info)+1).'">No Results</td></tr>';
		}
		if( $i >= $max_lines )
		{
			$r .= '<tr><td colspan="'.(count($col_info)+1).'">Max number of dumped rows has been reached.</td></tr>';
		}

		$r .= '</table>';

		return $r;
	}


	/**
	 * Format a SQL query
	 * @static
	 * @todo dh> Steal the code from phpMyAdmin :)
	 * @param string SQL
	 * @param boolean Format with/for HTML?
	 */
	function format_query( $sql, $html = true )
	{
		$sql = str_replace("\t", '  ', $sql );
		if( $html )
		{
			$sql = htmlspecialchars( $sql );
			$replace_prefix = "<br />\n";
		}
		else
		{
			$replace_prefix = "\n";
		}

		$search = array(
			'~(FROM|WHERE|GROUP BY|ORDER BY|LIMIT|VALUES)~',
			'~(AND |OR )~',
			);
		$replace = array(
				$replace_prefix.'$1',
				$replace_prefix.'&nbsp; $1',
			);
		$sql = preg_replace( $search, $replace, $sql );

		return $sql;
	}


	/**
	 * Displays all queries that have been executed
	 */
	function dump_queries()
	{
		global $Timer;
		if( is_object( $Timer ) )
		{
			$time_queries = $Timer->get_duration( 'sql_queries' );
		}
		else
		{
			$time_queries = 0;
		}

		$count_queries = 0;
		$count_rows = 0;

		echo '<strong>DB queries:</strong> '.$this->num_queries."<br />\n";

		if( ! $this->log_queries )
		{ // nothing more to do here..
			return;
		}

		foreach( $this->queries as $query )
		{
			$count_queries++;
			echo '<h4>Query #'.$count_queries.': '.$query['title']."</h4>\n";
			echo '<code>';
			echo $this->format_query( $query['sql'] );
			echo "</code>\n";

			// Color-Format duration: long => red, fast => green, normal => black
			if( $query['time'] > $this->query_duration_slow )
			{
				$style_time_text = 'color:red;font-weight:bold;';
				$style_time_graph = 'background-color:red;';
			}
			elseif( $query['time'] < $this->query_duration_fast )
			{
				$style_time_text = 'color:green;';
				$style_time_graph = 'background-color:green;';
			}
			else
			{
				$style_time_text = '';
				$style_time_graph = 'background-color:black;';
			}

			// Number of rows with time (percentage and graph, if total time available)
			echo '<div class="query_info">';
			echo 'Rows: '.$query['rows'];

			echo ' &ndash; Time: ';
			if( $style_time_text )
			{
				echo '<span style="'.$style_time_text.'">';
			}
			echo number_format( $query['time'], 4 ).'s';

			if( $time_queries > 0 )
			{ // We have a total time we can use to calculate percentage:
				echo ' ('.number_format( 100/$time_queries * $query['time'], 2 ).'%)';
			}

			if( $style_time_text )
			{
				echo '</span>';
			}

			if( $time_queries > 0 )
			{ // We have a total time we can use to display a graph/bar:
				echo '<div style="margin:0; padding:0; height:12px; width:'.( round( 100/$time_queries * $query['time'] ) ).'%;'.$style_time_graph.'"></div>';
			}
			echo '</div>';


			// Explain:
			if( isset($query['explain']) )
			{
				echo $query['explain'];
			}

			// Results:
			if( $query['results'] != 'unknown' )
			{
				echo $query['results'];
			}

			// Function trace:
			if( isset($query['function_trace']) )
			{
				echo $query['function_trace'];
			}

			$count_rows += $query['rows'];
		}
		echo "\n<strong>Total rows:</strong> $count_rows<br />\n";
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


	/**
	 * Set the charset of the connection.
	 *
	 * WARNING: this will fail on MySQL 3.23
	 *
	 * @staticvar array "regular charset => mysql charset map"
	 * @param string Charset
	 * @param boolean Use the "regular charset => mysql charset map"?
	 * @return boolean true on success, false on failure
	 */
	function set_connection_charset( $charset, $use_map = false )
	{
		global $Debuglog;

		/**
		 * This is taken from phpMyAdmin (libraries/select_lang.lib.php).
		 */
		static $mysql_charset_map = array(
				'big5'         => 'big5',
				'cp-866'       => 'cp866',
				'euc-jp'       => 'ujis',
				'euc-kr'       => 'euckr',
				'gb2312'       => 'gb2312',
				'gbk'          => 'gbk',
				'iso-8859-1'   => 'latin1',
				'iso-8859-2'   => 'latin2',
				'iso-8859-7'   => 'greek',
				'iso-8859-8'   => 'hebrew',
				'iso-8859-8-i' => 'hebrew',
				'iso-8859-9'   => 'latin5',
				'iso-8859-13'  => 'latin7',
				'iso-8859-15'  => 'latin1',
				'koi8-r'       => 'koi8r',
				'shift_jis'    => 'sjis',
				'tis-620'      => 'tis620',
				'utf-8'        => 'utf8',
				'windows-1250' => 'cp1250',
				'windows-1251' => 'cp1251',
				'windows-1252' => 'latin1',
				'windows-1256' => 'cp1256',
				'windows-1257' => 'cp1257',
			);

		$charset = strtolower($charset);

		if( $use_map )
		{
			if( ! isset($mysql_charset_map[$charset]) )
			{
				return false;
			}

			$charset = $mysql_charset_map[$charset];
		}

		$r = true;
		if( $charset != $this->connection_charset )
		{
			// SET NAMES is not supported by MySQL 3.23 and for a non-supported charset even not in MySQL 5 probably..

			$save_show_errors = $this->show_errors;
			$save_halt_on_error = $this->halt_on_error;
			$this->show_errors = false;
			$this->halt_on_error = false;
			$last_error = $this->last_error;
			$error = $this->error;
			if( $this->query( 'SET NAMES '.$charset ) === false )
			{
				$Debuglog->add( 'Could not "SET NAMES '.$charset.'"! (MySQL error: '.strip_tags($this->last_error).')', 'locale' );

				$r = false;
			}
			else
			{
				$Debuglog->add( 'Set DB connection charset: '.$charset, 'locale' );
			}
			$this->show_errors = $save_show_errors;
			$this->halt_on_error = $save_halt_on_error;
			// Blatantly ignore any error generated by SET NAMES...
			$this->last_error = $last_error;
			$this->error = $error;

			// dh> TODO: this should only get set in case of success, I'd say..
			$this->connection_charset = $charset;
		}

		return $r;
	}

}


/*
 * $Log$
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