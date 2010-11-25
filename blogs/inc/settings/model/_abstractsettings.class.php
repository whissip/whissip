<?php
/**
 * This file implements the AbstractSettings class designed to handle any kind of settings.
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
 * @author blueyed: Daniel HAHLER
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


// DEBUG: (Turn switch on or off to log debug info for specified category)
$GLOBALS['debug_settings'] = false;


/**
 * Class to handle settings in an abstract manner (to get used with either 1, 2 or 3 DB column keys).
 *
 * Arrays and Objects automatically get serialized and unserialized
 * (in {@link AbstractSettings::get()} and {@link AbstractSettings::dbupdate()}).
 *
 * Note: I've evaluated splitting this into single classes for performance reasons, but only
 *       get() is relevant performance-wise and we could now only get rid of the switch() therein,
 *       which is not sufficient to split it into *_base + _X classes. (blueyed, 2006-08)
 *
 * @package evocore
 * @abstract
 * @see UserSettings, GeneralSettings, PluginSettings, CollectionSettings, PluginUserSettings
 */
class AbstractSettings
{
	/**
	 * The DB table which stores the settings.
	 *
	 * @var string
	 * @access protected
	 */
	var $db_table_name;

	/**
	 * Array with DB column key names.
	 *
	 * @var array
	 * @access protected
	 */
	var $col_key_names = array();

	/**
	 * DB column name for the value.
	 *
	 * @var string
	 * @access protected
	 */
	var $col_value_name;


	/**
	 * The internal cache.
	 *
	 * false, if settings  could not be loaded or NULL if not initialized.
	 *
 	 * @access protected
	 * @var array
	 */
	var $cache = NULL;


	/**
	 * Default settings.
	 *
	 * Maps last colkeyname to some default setting that will be used by
	 * {@link get()} if no value was defined (and it is set as a default).
	 *
	 * @var array
	 */
	var $_defaults = array();


	/**
	 * Constructor.
	 * @param string The name of the DB table with the settings stored.
	 * @param array List of names for the DB columns keys that reference a value.
	 * @param string The name of the DB column that holds the value.
	 * @param integer The number of column keys to cache by. This are the first x keys of {@link $col_key_names}. 0 means 'load all'.
	 */
	function AbstractSettings( $db_table_name, $col_key_names, $col_value_name, $cache_by_col_keys = 0 )
	{
		$this->db_table_name = $db_table_name;
		$this->col_key_names = $col_key_names;
		$this->col_value_name = $col_value_name;
		$this->cache_by_col_keys = $cache_by_col_keys;

		// TODO: GlobalCache setting.. see mail..
		if( 0 && is_null($this->cache_by_col_keys) )
		{
			global $DB;
			if( $DB->get_var("SELECT COUNT(*) FROM $db_table_name") < 500 )
			{
				$this->cache_by_col_keys = 0;
			}
		}


		/**
		 * internal counter for the number of column keys
		 * @var integer
		 */
		$this->count_col_key_names = count( $this->col_key_names );

		if( $this->cache_by_col_keys >= $this->count_col_key_names )
			debug_die('$cache_by_col_keys >= count($col_key_names) not supported!');

		$this->reset();
	}


	/**
	 * Load all settings, disregarding the derived classes setting of
	 * {@link $cache_by_col_keys} - useful if you know that you want to get
	 * all user settings for example.
	 */
	function load_all()
	{
		return $this->cache->_load();
	}


	/**
	 * Get a setting from the DB settings table.
	 *
	 * @param string First column key
	 * @param string Second column key
	 * @param string Third column key
	 * @return string|false|NULL value as string on success; NULL if not found; false in case of error
	 */
	function get( $col_key1, $col_key2 = NULL, $col_key3 = NULL )
	{
		global $Debuglog;

		if( $Debuglog instanceof Log )
		{
			global $Timer;
			$this_class = get_class($this);
			$Timer->resume('abstractsettings_'.$this_class.'_get', false );
		}

		// TODO: check if using switch is more performant than only using the generic method.
		switch($this->count_col_key_names)
		{
		case 1:
			$r = $this->cache->$col_key1->value;
			break;
		case 2:
			$r = $this->cache->$col_key1->$col_key2->value;
			break;
		default:
			// Generic purpose
			$args = func_get_args();

			// We require as many args as there are column keys
			assert('count($args) == $this->count_col_key_names');

			$key = $args[count($args)-1]; // last entry

			// TODO: dh> optimize this code path?!
			$get_from = array_pop($this->cache->get_cache_for_args($args));
			$r = $get_from->$key->value;
		}

		if( $Debuglog instanceof Log )
		{
			$args = func_get_args();
			$Debuglog->add( $this_class.'::get( '.implode('/', $args).' ): '.var_export( $r, true ), 'settings' );
			$Timer->pause('abstractsettings_'.$this_class.'_get', false );
		}
		return $r;
	}


	/**
	 * Only set the first variable (passed by reference) if we could retrieve a
	 * setting.
	 *
	 * @param mixed variable to set maybe (by reference)
	 * @param string the values for the column keys (depends on $this->col_key_names
	 *               and must match its count and order)
	 * @return boolean true on success (variable was set), false if not
	 */
	function get_cond( & $toset )
	{
		$args = func_get_args();
		array_shift( $args );

		$result = call_user_func_array( array( & $this, 'get' ), $args );

		if( $result !== NULL && $result !== false )
		{ // No error and value retrieved
			$toset = $result;
			return true;
		}
		else
		{
			return false;
		}
	}


	/**
	 * Temporarily sets a setting ({@link dbupdate()} writes it to DB).
	 *
	 * @param string $args,... the values for the {@link $col_key_names column keys}
	 *                         and {@link $col_value_name column value}. Must match order and count!
	 * @return boolean true, if the value has been set, false if it has not changed (or in case of wrong arg count).
	 */
	function set( $__args__ )
	{
		global $Debuglog;

		$args = func_get_args();

		if( count($args) != $this->count_col_key_names + 1 )
			return false;

		$r = $this->cache->set($args);

		if( $Debuglog instanceof Log )
		{
			if( $r )
				$Debuglog->add( get_class($this).'::set( '.implode(', ', $args ).' ): SET!', 'settings' );
			else
				$Debuglog->add( get_class($this).'::set( '.implode(', ', $args ).' ): Already set to the same value.', 'settings' );
		}

		return $r;
	}


	/**
	 * Set an array of values.
	 *
	 * @param array Array of parameters for {@link set()}
	 */
	function set_array( $array )
	{
		foreach( $array as $lSet )
		{
			call_user_func_array( array( & $this, 'set' ), $lSet );
		}
	}


	/**
	 * Remove a setting.
	 *
	 * @param string $args,... the values for the {@link $col_key_names column keys}.
	 * @return boolean True if value has been delete, false if not.
	 */
	function delete( $__args__ )
	{
		$args = func_get_args();
		return $this->cache->delete($args);
	}


	/**
	 * Delete an array of values.
	 *
	 * @param array Array of parameters for {@link delete()}
	 */
	function delete_array( $array )
	{
		foreach( $array as $lDel )
		{
			call_user_func_array( array( & $this, 'delete' ), array($lDel) );
		}
	}


	/**
	 * Delete values for {@link $_defaults default settings} in DB.
	 *
	 * This will use the default settings on the next {@link get()}
	 * again.
	 *
	 * @return boolean true, if settings have been updated; false otherwise
	 */
	function restore_defaults()
	{
		$this->delete_array( array_keys( $this->_defaults ) );

		return $this->dbupdate();
	}


	/**
	 * Commit changed settings to DB.
	 *
	 * @return boolean true, if settings have been updated; false otherwise
	 */
	function dbupdate()
	{
		return $this->cache->dbupdate();
	}


	/**
	 * Reset cache (includes settings to be written to DB).
	 *
	 * This is useful, to rollback settings that have been made, e.g. when a Plugin
	 * decides that his settings should not get updated.
	 */
	function reset()
	{
		// Create the (recursive) cache.
		$this->cache = new AbstractSettings_dynamic_cache;

		// Shared settings between all cache instances.
		$this->cache->__settings = new stdClass;
		$this->cache->__settings->db_table_name = $this->db_table_name;
		$this->cache->__settings->col_key_names = $this->col_key_names;
		$this->cache->__settings->col_value_name = $this->col_value_name;
		$this->cache->__settings->cache_by_col_keys = $this->cache_by_col_keys;
		$this->cache->__settings->count_col_key_names = $this->count_col_key_names;
		$this->cache->__settings->defaults = & $this->_defaults; // only required on the deepest level.

		$this->cache->__levels_loaded_from_DB = array(); // shared list to set state of "all entries on that level loaded"
		$this->cache->__level = 0;
		$this->cache->__parent_keys = array(); // list of parent key names, if level > 0
		$this->cache->__serialized_cache = array(); // global cache
		$this->cache->__this_serialized_cache = & $this->cache->__serialized_cache; // reference
	}


	/**
	 * Get default value for $last_key.
	 * @return string
	 */
	function get_default($last_key)
	{
		return $this->cache->get_default($last_key);
	}
}


/**
 * This is the cache used by {@link AbstractSettings} (and recursively, per column key being used).
 */
class AbstractSettings_dynamic_cache
{
	/**
	 * Level loaded?
	 */
	var $__loaded_DB = false;

	var $__settings;

	var $__dirty_cache = false;

	/**
	 * @var array List of parent keys, shared across all cache instances.
	 */
	var $__parent_keys;
	/**
	 * @var array Cache of (possibly serialized) values.
	 */
	var $__serialized_cache;
	/**
	 * @var array Reference to the current levels cache of (possibly) serialized values.
	 */
	var $__this_serialized_cache;


	/**
	 * Magic method, which gets called by PHP if a object property is not set.
	 * This is used to fill the cache on demand.
	 * @param string
	 */
	function __get($name)
	{
		if( substr($name, 0, 2) == '__' )
			debug_die('Requested invalid name: '.$name); // only used internally

		if( $this->__level < $this->__settings->count_col_key_names - 1 )
		{ // Create a new AbstractSettings_dynamic_cache object, if current level says that there are more keys
			$r = new AbstractSettings_dynamic_cache;
			$r->__settings = & $this->__settings;

			$r->__levels_loaded_from_DB = NULL;
			$r->__levels_loaded_from_DB = & $this->__levels_loaded_from_DB; // shared between all caches

			// Remember the current name (col_key_name):
			$r->__parent_keys = & $this->__parent_keys;
			$r->__parent_keys[$this->__level] = $name;

			$r->__serialized_cache = & $this->__serialized_cache;
			$r->__this_serialized_cache = & $this->__this_serialized_cache[$name];

			// Bump level
			$r->__level = $this->__level + 1;

			$this->$name = $r;
			return $r;
		}

		// Init
		$this->$name = new stdClass;
		$this->$name->value = NULL;

		// Set this->$name
		$this->_load($name);

		return $this->$name;
	}


	/**
	 *
	 *
	 * @return boolean True if value has been updated, false if not.
	 */
	function set( $args )
	{
		return $this->call_and_invalidate_cache_on_change('_set', $args);
	}


	/**
	 * Internal method to set a value.
	 *
	 * @return boolean True if value has been updated, false if not.
	 */
	private function _set( $args )
	{
		global $Debuglog;
		assert('count($args) == 2');
		list( $set_into, $value ) = $args;
		$set_into = $this->$set_into;

		if( isset($set_into->value) && $set_into->value == $value )
		{ // already set to the same value
			return false;
		}

		// Remember original (DB) value on first change. This gets used to skip unnecessary DELETE and UPDATE queries.
		if( isset($set_into->value) && ! isset($set_into->dbValue) )
			$set_into->dbValue = $set_into->value;

		// Update value, set properties so it gets saved later.
		$set_into->value = $this->try_to_unserialize($value); // We haven't tried to unserialize the value yet. This is required when setting unserialized values!
		$set_into->dbUpdate = true;
		unset($set_into->dbRemove);

		return true;
	}


	/**
	 * Try to unserialize $val.
	 * @param mixed
	 * @return mixed Either the unserialized value, or the original.
	 */
	function try_to_unserialize( $val )
	{
		if( ($test = @unserialize($val)) !== false )
		{
			return $test;
		}
		return $val;
	}


	/**
	 * Loads the settings. Not meant to be called directly, but gets called
	 * when needed.
	 *
	 * @access protected
	 * @return boolean
	 */
	function _load( $name = NULL )
	{
		if( empty($this->__levels_loaded_from_DB[$this->__level]) && ! $this->__loaded_DB )
		{
			global $DB;

			// Build SQL
			$sql = '
				SELECT '.implode( ', ', $this->__settings->col_key_names ).', '.$this->__settings->col_value_name.'
				FROM '.$this->__settings->db_table_name;
			// If we want to cache by column keys (and do not want to load all ($name=NULL)),
			// we need to assemble a WHERE clause.
			if( $this->__settings->cache_by_col_keys && isset($name) )
			{
				for( $i = 0; $i < $this->__settings->cache_by_col_keys; $i++ )
				{
					$whereList[] = $this->__settings->col_key_names[$i].' = '.$DB->quote($this->__parent_keys[$i]);
				}

				assert('$whereList');
				$sql .= ' WHERE '.implode( ' AND ', $whereList );
			}
			$this->__loaded_DB = true;
			$result = $DB->get_results($sql, ARRAY_A, 'Settings::load');
			if( ! $result && isset($name) )
			{ // Remember that we've tried it
				$this->$name = new stdClass;
				$this->$name->value = $this->get_default($name);
			}
			elseif( $result ) // may be null in tests, when mocked
			{
				// Store results into cache
				foreach($result as $loop_row)
				{
					$value = array_pop($loop_row);
					$set_name = array_pop($loop_row);

					$set_into = & $this->__serialized_cache;
					while( $next = array_shift($loop_row) )
					{
						$set_into = & $set_into[$next];
					}

					$set_into[$set_name] = $value;
				}
			}
			if( empty($whereList) )
			{
				$this->__levels_loaded_from_DB[$this->__level] = true;
			}
		}

		if( isset($this->__this_serialized_cache[$name]) )
		{ // this value has been retrieved but not yet tried to get unserialized
			$this->$name = new stdClass;
			$this->$name->value = $this->try_to_unserialize($this->__this_serialized_cache[$name]);
			unset($this->__this_serialized_cache[$name]);
			return true;
		}

		// Get from defaults:
		if( $this->__loaded_DB && isset($this->__settings->defaults[$name]) )
		{
			$this->$name = new stdClass;
			$this->$name->value = $this->get_default($name);
			return true;
		}

		return true;
	}


	/**
	 * Get the default for the last key of {@link $col_key_names}
	 *
	 * @param string The last column key
	 * @return NULL|mixed NULL if no default is set, otherwise the value (should be string).
	 */
	function get_default( $last_key )
	{
		if( isset($this->__settings->defaults[ $last_key ]) )
		{
			return $this->__settings->defaults[ $last_key ];
		}

		return NULL;
	}


	/**
	 * Commit changed settings to DB.
	 *
	 * @return boolean true, if settings have been updated; false otherwise
	 */
	function dbupdate( $args = array() )
	{
		if( ! $this->__dirty_cache )
			return false;
		$r = false;

		global $DB;

		$query_insert = array();
		$query_where_delete = array();

		foreach( get_object_vars($this) as $k => $v )
		{
			if( substr($k, 0, 2) == '__' )
				continue;

			if( $v instanceof AbstractSettings_dynamic_cache )
			{ // recurse:
				$r = $v->dbupdate() || $r;
			}
			else
			{
				if( isset($v->dbValue) )
				{ // check if update is required
					$db_value = $v->dbValue;
					unset($v->dbValue);

					if( $db_value == $v->value )
						continue;
				}
				if( isset($v->dbRemove) )
				{
					$delete_queries = array();
					// Create pairs of column key name and value to access this value.
					$del_path_values = $this->__parent_keys;
					$del_path_values[] = $k;
					foreach( array_combine($this->__settings->col_key_names, $del_path_values) as $del_k => $del_v )
					{
						$delete_queries[] = "`$del_k` = ".$DB->quote($del_v);
					}
					$query_where_delete[] = implode(' AND ', $delete_queries);
					unset( $this->$k );
				}
				elseif( ! empty($v->dbUpdate) )
				{
					$value = $v->value;
					if( is_array( $value ) || is_object( $value ) )
					{
						$value = serialize($value);
					}
					$q = $this->__parent_keys;
					$q[] = $k;
					$q[] = $value;
					$q = array_map(array($DB, 'quote'), $q);
					$query_insert[] = '( '.implode(', ', $q).' )';
					unset($this->$k->dbUpdate);
				}
			}
		}

		if( ! empty($query_where_delete) )
		{
			$query = 'DELETE FROM '.$this->__settings->db_table_name." WHERE (".implode( ")\nOR (", $query_where_delete ).')';
			$r = (boolean)$DB->query( $query ) || $r;
		}


		if( ! empty($query_insert) )
		{
			$query = 'REPLACE INTO '.$this->__settings->db_table_name
				.' ('.implode( ', ', $this->__settings->col_key_names ).', '.$this->__settings->col_value_name
				.') VALUES '.implode(', ', $query_insert);

			$r = $DB->query( $query ) || $r;
		}

		$this->__dirty_cache = false;

		return $r;
	}


	/**
	 * Remove a setting.
	 *
	 * @param array List of {@link $col_key_names}
	 * @return boolean True if value has been deleted, false if not.
	 */
	function delete( $args )
	{
		return $this->call_and_invalidate_cache_on_change('_delete', $args, /* do not create non-existent */ false);
	}

	/**
	 * Internal method to delete a value.
	 *
	 * @return boolean True if value has been deleted, false if not.
	 */
	private function _delete($args)
	{
		if( count($args) != 1 )
		{ // we have not found the required key, so it does not exist and cannot get deleted
			return false;
		}

		$delete_key = $args[0];

		if( isset($this->$delete_key) )
		{
			if( ! isset($this->$delete_key->dbValue) )
				$this->$delete_key->dbValue = $this->$delete_key->value;

			$this->$delete_key->value = $this->get_default($delete_key);

			if( isset($this->$delete_key->dbRemove) )
			{ // this has been deleted before => unchanged
				return false;
			}
			$this->$delete_key->dbRemove = true;
			return true;
		}
		return false;
	}


	/**
	 * Return the appropriate cache object, referenced by $args.
	 *
	 * E.g., $args = array('foo', 'bar') will return $this->$foo->$bar.
	 *
	 * @param array Arguments (keys to get to the requested cache)
	 * @param boolean Create non-existent instances on the way?
	 * @return array Path of AbstractSettings_dynamic_cache instances, matching $args.
	 */
	function get_cache_for_args( array & $args, $create_non_existent = true )
	{
		$r = $this;
		$rpath = array($r);
		while( isset($args[0]) )
		{
			$get_next = $args[0];
			if( $this->__level < $this->__settings->count_col_key_names - 1
				&& ($create_non_existent || isset($r->$get_next))
				&& $r->$get_next instanceof AbstractSettings_dynamic_cache )
			{
				$r = $r->$get_next;
				array_shift($args);
				$rpath[] = $r;
			}
			else
			{
				break;
			}
		}
		return $rpath;
	}


	/**
	 * Get the appropriate cache object using $args (and $create_non_existent), then
	 * call the method $method on it.
	 *
	 * @param string Method name
	 * @param array  Arguments to refer to the cache (column key names)
	 * @param boolean Create non existent cache objects (typically false when deleting)
	 * @return mixed Return value of $method
	 */
	function call_and_invalidate_cache_on_change($method, array $args, $create_non_existent = true)
	{
		$cache_path = $this->get_cache_for_args($args, $create_non_existent);
		$last = array_pop($cache_path);

		$r = call_user_func(array($last, $method), $args);

		if( $r )
		{
			while( $invalidate_cache = array_pop($cache_path) )
				$invalidate_cache->__dirty_cache = true;
			$last->__dirty_cache = true;
		}
		return $r;
	}


	/**
	 * Get a param from Request and save it to Settings, or default to previously saved user setting.
	 *
	 * If the setting was not set before (and there's no default given that gets returned), $default gets used.
	 *
	 * @param string Request param name
	 * @param string setting name. Make sure this is unique!
	 * @param string Force value type to one of:
	 * - float
	 * - string (strips (HTML-)Tags, trims whitespace)
	 * - array
	 * - object
	 * - null
	 * - html (does nothing)
	 * - '' (does nothing)
	 * - '/^...$/' check regexp pattern match (string)
	 * - boolean (will force type to boolean, but you can't use 'true' as a default since it has special meaning. There is no real reason to pass booleans on a URL though. Passing 0 and 1 as integers seems to be best practice).
	 * Value type will be forced only if resulting value (probably from default then) is !== NULL
	 * @param mixed Default value or TRUE
	 * @param boolean Do we need to memorize this to regenerate the URL for this page?
	 * @param boolean Override if variable already set
	 * @return NULL|mixed NULL, if neither a param was given nor knows about it.
	 */
	function param_Request( $param_name, $set_name, $type = '', $default = '', $memorize = false, $override = false ) // we do not force setting it..
	{
		$value = param( $param_name, $type, NULL, $memorize, $override, false ); // we pass NULL here, to see if it got set at all

		if( $value !== false )
		{ // we got a value
			$this->set( $set_name, $value );
			$this->dbupdate();
		}
		else
		{ // get the value from user settings
			$value = $this->get($set_name);

			if( is_null($value) )
			{ // it's not saved yet and there's not default defined ($_defaults)
				$value = $default;
			}
		}

		set_param( $param_name, $value );
		return get_param($param_name);
	}
}


/*
 * $Log$
 * Revision 1.9  2010/02/08 17:53:55  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.8  2010/01/22 08:39:02  sam2kb
 * Fixied warning: call_user_func_array() expects parameter 2 to be array
 *
 * Revision 1.7  2009/11/30 00:22:05  fplanque
 * clean up debug info
 * show more timers in view of block caching
 *
 * Revision 1.6  2009/10/08 20:05:52  efy-maxim
 * Modular/Pluggable Permissions
 *
 * Revision 1.5  2009/03/08 23:57:45  fplanque
 * 2009
 *
 * Revision 1.4  2008/01/21 09:35:34  fplanque
 * (c) 2008
 *
 * Revision 1.3  2007/11/28 16:57:50  fplanque
 * bugfix when trying to access a serialized value rigth after setting it
 *
 * Revision 1.2  2007/11/28 16:38:20  fplanque
 * minor
 *
 * Revision 1.1  2007/06/25 11:01:20  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.21  2007/04/26 00:10:59  fplanque
 * (c) 2007
 *
 * Revision 1.20  2007/02/06 00:41:52  waltercruz
 * Changing double quotes to single quotes
 *
 * Revision 1.19  2006/12/07 23:13:11  fplanque
 * @var needs to have only one argument: the variable type
 * Otherwise, I can't code!
 *
 * Revision 1.18  2006/11/24 18:27:25  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.17  2006/11/15 21:04:46  blueyed
 * - Fixed removing setting after delete() and get() (from defaults) (When getting value from default do not reset $dbRemove property)
 * - Opt: default settings are already unserialized
 *
 * Revision 1.16  2006/11/15 20:18:50  blueyed
 * Fixed AbstractSettings::delete(): unset properties in cache
 *
 * Revision 1.15  2006/11/04 01:35:02  blueyed
 * Fixed unserializing of array()
 */
?>
