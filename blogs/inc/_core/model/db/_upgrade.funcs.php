<?php
/**
 * This file implements functions useful for upgrading DB schema.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}.
 * Parts of this file are copyright (c)2004-2005 by Daniel HAHLER - {@link https://thequod.de/}.
 *
 * {@link db_delta()} is based on dbDelta() from {@link http://wordpress.com Wordpress}, see
 * {@link http://trac.wordpress.org/file/trunk/wp-admin/upgrade-functions.php}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
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
 * @author fplanque: Francois PLANQUE
 * @author blueyed: Daniel HAHLER
 * @author Wordpress team
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Get the delta query to adjust the current database according to a given (list of)
 * "CREATE TABLE"-, "CREATE DATABASE"-, "INSERT"- or "UPDATE"-statement(s).
 *
 * It's not recommend to use INSERT or UPDATE statements with this function, as they
 * are just handled "as-is".
 *
 * NOTE:
 *   - You should use single quotes (') to give string type values (this is in fact
 *     required for ENUM and SET fields).
 *   - KEYs for AUTO_INCREMENT fields should be defined in column_definition, otherwise
 *     we had to detect the key type from the INDEX query and add it to the ALTER/ADD query.
 *   - If a column changes from "NULL" to "NOT NULL" we generate an extra UPDATE query
 *     to prevent "Data truncated for column 'X' at row Y" errors.
 *
 * The following query types are generated/marked and can be excluded:
 *  - 'create_table'
 *  - 'create_database'
 *  - 'insert'
 *  - 'update'
 *  - 'drop_column'
 *  - 'change_column'
 *  - 'change_default'
 *  - 'add_column'
 *  - 'add_index'
 *  - 'drop_index'
 *  - 'alter_engine'
 * NOTE: it may be needed to merge an 'add_index' or 'drop_index' type query into an
 *       'add_column'/'change_column' query (adding "AUTO_INCREMENT" for example)!
 *
 * NOTE: collations and charset changes are ignored. It seems quite difficult to support this,
 *       and it seems to be best to handle this "manually".
 *
 * @author Originally taken from Wordpress, heavily enhanced and modified by blueyed
 *
 * @todo Handle COMMENT for tables?!
 *
 * @see http://dev.mysql.com/doc/refman/4.1/en/create-table.html
 *
 * @param array The list of queries for which the DB should be adjusted
 * @param array Exclude query types (see list above).
 * @param boolean Execute generated queries?  TODO: get this outta here!!!! (sooooo bloated!)
 * @return array The generated queries.
 *        table_name => array of arrays (queries with keys 'queries' (array), 'note' (string) and 'type' (string))
 *        There's usually just a single query in "queries", but in some cases additional queries
 *        are needed (e.g., 'UPDATE' before we can change "NULL" setting).
 */
function db_delta( $queries, $exclude_types = array(), $execute = false )
{
	global $Debuglog, $DB, $debug;

	if( ! is_array($queries) )
	{
		$queries = array( $queries );
	}

	if( ! is_array($exclude_types) )
	{
		$exclude_types = empty($exclude_types) ? array() : array($exclude_types);
	}

	/**
	 * Generated query items, indexed by table name.
	 */
	$items = array();


	// Split the queries into $items, by their type:
	foreach( $queries as $qry )
	{
		// Remove any comments from the SQL:
		$n = strlen($qry);
		$in_string = false;
		for( $i = 0; $i < $n; $i++ )
		{
			if( $qry[$i] == '\\' )
			{ // backslash/escape; skip
				continue;
			}
			if( $qry[$i] == '"' || $qry[$i] == "'" )
			{
				if( ! $in_string )
				{ // string begins:
					$in_string = $qry[$i];
				}
				elseif( $qry[$i] === $in_string )
				{
					$in_string = false;
				}
			}
			elseif( $in_string === false )
			{ // not in string, check for comment start:
				if( $qry[$i] == '#' || substr($qry, $i, 3) == '-- ' )
				{ // comment start
					// search for newline
					for( $j = $i+1; $j < $n; $j++ )
					{
						if( $qry[$j] == "\n" || $qry[$j] == "\r" )
						{
							break;
						}
					}
					// remove comment
					$qry = substr($qry, 0, $i).substr($qry, $j);
					$n = strlen($qry);
					continue;
				}
			}
		}

		if( preg_match( '|^(\s*CREATE TABLE\s+)(IF NOT EXISTS\s+)?([^\s(]+)(.*)$|is', $qry, $match) )
		{
			$tablename = db_delta_remove_quotes(preg_replace( $DB->dbaliases, $DB->dbreplaces, $match[3] ));
			$qry = $match[1].( empty($match[2]) ? '' : $match[2] ).$tablename.$match[4];

			$items[strtolower($tablename)][] = array(
				'queries' => array($qry),
				'note' => sprintf( 'Created table &laquo;<strong>%s</strong>&raquo;', $tablename ),
				'type' => 'create_table' );
		}
		elseif( preg_match( '|^\s*CREATE DATABASE\s([\S]+)|i', $qry, $match) )
		{ // add to the beginning
			array_unshift( $items, array(
				'queries' => array($qry),
				'note' => sprintf( 'Created database &laquo;<strong>%s</strong>&raquo;', $match[1] ),
				'type' => 'create_database' ) );
		}
		elseif( preg_match( '|^(\s*INSERT INTO\s+)([\S]+)(.*)$|is', $qry, $match) )
		{
			$tablename = db_delta_remove_quotes(preg_replace( $DB->dbaliases, $DB->dbreplaces, $match[2] ));
			$items[strtolower($tablename)][] = array(
				'queries' => array($match[1].$tablename.$match[3]),
				'note' => '',
				'type' => 'insert' );
		}
		elseif( preg_match( '|^(\s*UPDATE\s+)([\S]+)(.*)$|is', $qry, $match) )
		{
			$tablename = db_delta_remove_quotes(preg_replace( $DB->dbaliases, $DB->dbreplaces, $match[2] ));
			$items[strtolower($tablename)][] = array(
				'queries' => array($match[1].$tablename.$match[3]),
				'note' => '',
				'type' => 'update' );
		}
		else
		{
			$Debuglog->add( 'db_delta: Unrecognized query type: '.$qry, 'note' );
		}
	}

	/**
	 * @global array Available tables in the current database
	 */
	$tables = $DB->get_col('SHOW TABLES');

	// Loop through existing tables and check which tables and fields exist
	foreach($tables as $table)
	{ // For every table in the database
		$table_lowered = strtolower($table);  // table names are treated case insensitive

		if( ! isset( $items[$table_lowered] ) )
		{ // This table exists in the database, but not in the creation queries.
			continue;
		}

		/**
		 * @global array Hold the indices we want to create/have, with meta data keys.
		 */
		$indices = array();

		/**
		 * @global array Initially all existing indices. Any index, that does not get unset here, generates a 'drop_index' type query.
		 */
		$obsolete_indices = array();

		/**
		 * @global array Fields of the existing primary key (if any)
		 */
		$existing_primary_fields = array();

		/**
		 * @global array Fields of existing keys (including PRIMARY), lowercased (if any)
		 */
		$existing_key_fields = array();

		/**
		 * @global array Column field names of PRIMARY KEY, lowercased (if any)
		 */
		$primary_key_fields = array();

		/**
		 * @global array of col_names that have KEYs (including PRIMARY; lowercased). We use this for AUTO_INCREMENT magic.
		 */
		$fields_with_keys = array();

		/**
		 * @global string Holds the fielddef of an obsolete ("drop_column") AUTO_INCREMENT field. We must alter this with a PK "ADD COLUMN" query.
		 */
		$obsolete_autoincrement = NULL;


		/**
		 * @global array List of fields (and definition from query)
		 *   <code>fieldname (lowercase) => array(
		 *         'field' => "column_definition",
		 *         'where' => "[FIRST|AFTER xxx]" )
		 *   </code>
		 */
		$wanted_fields = array();

		/**
		 * @global boolean Do we have any variable-length fields? (see http://dev.mysql.com/doc/refman/4.1/en/silent-column-changes.html)
		 */
		$has_variable_length_field = false;


		// Get all of the field names in the query from between the parens
		preg_match( '|\((.*)\)(.*)$|s', $items[$table_lowered][0]['queries'][0], $match ); // we have only one query here
		$qryline = trim($match[1]);
		$qry_table_options = trim($match[2]);

		// Separate field lines into an array
		#$flds = preg_split( '~,(\r?\n|\r)~', $qryline, -1, PREG_SPLIT_NO_EMPTY );

		$flds = array();
		$in_parens = 0;
		$buffer = '';
		for( $i = 0; $i < strlen($qryline); $i++ )
		{
			$c = $qryline[$i];

			if( $c == ',' && ! $in_parens )
			{ // split here:
				$flds[] = trim($buffer);
				$buffer = '';
				continue;
			}

			if( $c == '(' )
			{
				$in_parens++;
			}
			elseif( $c == ')' )
			{
				$in_parens--;
			}

			$buffer .= $c;
		}
		if( strlen($buffer) )
		{
			$flds[] = trim($buffer);
		}

		//echo "<hr/><pre>\n".print_r(strtolower($table), true).":\n".print_r($items, true)."</pre><hr/>";

		// ALTER ENGINE, if different (and given in query):
		if( preg_match( '~\bENGINE\s*=\s*(\w+)~', $qry_table_options, $match ) )
		{
			$wanted_engine = $match[1];
			$current_engine = $DB->get_row( '
				SHOW TABLE STATUS LIKE '.$DB->quote($table) );
			$current_engine = $current_engine->Engine;

			if( strtolower($current_engine) != strtolower($wanted_engine) )
			{
				$items[$table_lowered][] = array(
							'queries' => array('ALTER TABLE '.$table.' ENGINE='.$wanted_engine),
							'note' => 'Alter engine of <strong>'.$table.'.</strong> to <strong>'.$wanted_engine.'</strong>',
							'type' => 'alter_engine' );
			}
		}

		$prev_fld = '';
		foreach( $flds as $create_definition )
		{ // For every field line specified in the query
			// Extract the field name
			preg_match( '|^([^\s(]+)|', trim($create_definition), $match );
			$fieldname = db_delta_remove_quotes($match[1]);
			$fieldname_lowered = strtolower($fieldname);

			$create_definition = trim($create_definition, ", \r\n\t");

			if( in_array( $fieldname_lowered, array( '', 'primary', 'index', 'fulltext', 'unique', 'key' ) ) )
			{ // INDEX (but not in column_definition - those get handled later)
				$add_index = array(
					'create_definition' => $create_definition,
				);

				if( !  preg_match( '~^(PRIMARY(?:\s+KEY)|(?:FULLTEXT|UNIQUE)(?:\s+(?:INDEX|KEY))?|KEY|INDEX) (?:\s+()     (\w+)      )? (\s+USING \w+)? \s* \((.*)\)$~ix', $create_definition, $match )
					&& ! preg_match( '~^(PRIMARY(?:\s+KEY)|(?:FULLTEXT|UNIQUE)(?:\s+(?:INDEX|KEY))?|KEY|INDEX) (?:\s+([`"])([\w\s]+)\\2)? (\s+USING \w+)? \s* \((.*)\)$~ix', $create_definition, $match ) )
				{ // invalid type, should not happen
					debug_die( 'Invalid type in $indices: '.$create_definition );
					// TODO: add test: Invalid type in $indices: KEY "coord" ("lon","lat")
				}
				$add_index['keyword'] = $match[1];
				$add_index['name'] = strtoupper($match[3]);
				$add_index['type'] = $match[4]; // "USING [type_name]"
				$add_index['col_names'] = explode( ',', $match[5] );
				foreach( $add_index['col_names'] as $k => $v )
				{
					$add_index['col_names'][$k] = strtolower(db_delta_remove_quotes(trim($v)));
				}

				if( $fieldname_lowered == 'primary' )
				{ // Remember PRIMARY KEY fields to be indexed (used for NULL check)
					$primary_key_fields = $add_index['col_names'];
					$add_index['is_PK'] = true;
				}
				else
				{
					$add_index['is_PK'] = false;
				}
				$fields_with_keys = array_unique( array_merge( $fields_with_keys, $add_index['col_names'] ) );

				$indices[] = $add_index;
			}
			else
			{ // "normal" field, add it to the field array
				$wanted_fields[ strtolower($fieldname_lowered) ] = array(
						'field' => $create_definition,
						'where' => ( empty($prev_fld) ? 'FIRST' : 'AFTER '.$prev_fld ),
					);
				$prev_fld = $fieldname;

				if( preg_match( '~^\S+\s+(VARCHAR|TEXT|BLOB)~i', $create_definition ) )
				{
					$has_variable_length_field = true;
				}
			}
		}


		// INDEX STUFF:

		/**
		 * @global array Holds the existing indices (with array's key UPPERcased)
		 */
		$existing_indices = array();

		// Fetch the table index structure from the database
		$tableindices = $DB->get_results( 'SHOW INDEX FROM '.$table );

		if( ! empty($tableindices) )
		{
			// For every index in the table
			foreach( $tableindices as $tableindex )
			{
				// Add the index to the index data array
				$keyname = strtoupper($tableindex->Key_name);

				$existing_indices[$keyname]['name'] = $tableindex->Key_name; // original case
				$existing_indices[$keyname]['columns'][] = array('fieldname' => $tableindex->Column_name, 'subpart' => $tableindex->Sub_part);
				$existing_indices[$keyname]['unique'] = ($tableindex->Non_unique == 0) ? true : false;
			}
			unset($tableindices);

			// Let's see which indices are present already for the table:
			// TODO: dh> use meta data available now in $indices, instead of building a regular expression!?
			$obsolete_indices = $existing_indices; // will get unset as found
		}


		// Pre-run KEYs defined in "column_definition" (e.g. used for AUTO_INCREMENT handling)
		foreach( $wanted_fields as $fieldname_lowered => $field_info )
		{
			$parse = $field_info['field'];

			if( preg_match( '~ \b UNIQUE (?:\s+ KEY)? \b ~ix ', $parse, $match ) )
			{ // This has an "inline" UNIQUE index:
				$indices[] = array(
						'name' => $fieldname_lowered,
						'is_PK' => false,
						'create_definition' => NULL, // "inline"
						'col_names' => array($fieldname_lowered),
						'keyword' => NULL,
						#'type' => $match[3], // "USING [type_name]"
					);

				unset( $obsolete_indices[strtoupper($fieldname_lowered)] );
				$parse = str_replace( $match[0], '', $parse );
				$fields_with_keys[] = $fieldname_lowered;
			}

			if( preg_match( '~ \b (PRIMARY\s+)? KEY \b ~ix', $parse, $match ) )
			{ // inline PK:
				$indices[] = array(
						'name' => 'PRIMARY',
						'is_PK' => true,
						'create_definition' => NULL, // "inline"
						'col_names' => array($fieldname_lowered),
						'keyword' => NULL,
						#'type' => $match[3], // "USING [type_name]"
					);
				$fields_with_keys[] = $fieldname_lowered;
				$primary_key_fields = array($fieldname_lowered);
				unset( $obsolete_indices['PRIMARY'] );
			}
		}
		$fields_with_keys = array_unique($fields_with_keys);


		foreach( $existing_indices as $index_name => $index_data )
		{
			// Build a create string to compare to the query
			$index_pattern = '^';
			if( $index_name == 'PRIMARY' )
			{
				$index_pattern .= 'PRIMARY(\s+KEY)?';
				// optional primary key name:
				$index_pattern .= '(\s+[`"]?\w+[`"]?)?';
			}
			elseif( $index_data['unique'] )
			{
				$index_pattern .= 'UNIQUE(\s+(?:INDEX|KEY))?';
			}
			else
			{
				$index_pattern .= '(INDEX|(?:FULLTEXT\s+)?KEY)';
			}
			if( $index_name != 'PRIMARY' )
			{
				$index_pattern .= '(\s+[`"]?'.$index_name.'[`"]?)?'; // optionally in backticks (and index name is optionally itself)
			}

			$index_columns = '';
			// For each column in the index
			foreach( $index_data['columns'] as $column_data )
			{
				if( $index_columns != '' )
				{
					$index_columns .= '\s*,\s*';
				}
				// Add the field to the column list string
				$index_columns .= '[`"]?'.$column_data['fieldname'].'[`"]?'; // optionally in backticks
				if( ! empty($column_data['subpart']) )
				{
					$index_columns .= '\s*\(\s*'.$column_data['subpart'].'\s*\)\s*';
				}
			}

			// Sort index definitions with names to the beginning:
			/*
			usort( $indices, create_function( '$a, $b', '
				if( preg_match( "~^\w+\s+[^(]~", $a["create_definition"] )
				{

				}' ) );
			*/


			$used_auto_keys = array();
			foreach( $indices as $k => $index )
			{
				$pattern = $index_pattern;
				if( ! preg_match( '~^\w+\s+[^(]~', $index['create_definition'], $match ) )
				{ // no key name given, make the name part optional, if it's the default one:
					// (Default key name seems to be the first column, eventually with "_\d+"-suffix)
					$auto_key = db_delta_remove_quotes(strtoupper($index['col_names'][0]));
					if( isset($used_auto_keys[$auto_key]) )
					{
						$used_auto_keys[$auto_key]++;
						$auto_key .= '_'.$used_auto_keys[$auto_key];
					}
					$used_auto_keys[$auto_key] = 1;

					if( $auto_key == $index_name )
					{ // the auto-generated keyname is the same as the one we have, so make it optional in the pattern:
						$pattern .= '?';
					}
				}
				// Add the column list to the index create string
				$pattern .= '\s*\(\s*'.$index_columns.'\s*\)';

				#pre_dump( '~'.$pattern.'~i', trim($index['create_definition']) );
				if( preg_match( '~'.$pattern.'~i', trim($index['create_definition']) ) )
				{ // This index already exists: remove the index from our indices to create
					unset($indices[$k]);
					unset($obsolete_indices[$index_name]);
					break;
				}
			}
			if( isset($obsolete_indices[$index_name]) )
			{
				#echo "<pre style=\"border:1px solid #ccc;margin-top:5px;\">{$table}:<br/><b>Did not find index:</b>".$index_name.'/'.$index_pattern."<br/>".print_r($indices, true)."</pre>\n";
			}
		}

		// Set $existing_primary_fields and $existing_key_fields
		foreach( $existing_indices as $l_key_name => $l_key_info )
		{
			$l_key_fields = array();
			foreach( $l_key_info['columns'] as $l_col )
			{
				$l_key_fields[] = strtolower($l_col['fieldname']);
			}
			if( $l_key_name == 'PRIMARY' )
			{ // Remember _existing_ PRIMARY KEYs
				$existing_primary_fields = $l_key_fields;
			}

			$existing_key_fields = array_merge( $existing_key_fields, $l_key_fields );
		}
		$existing_key_fields = array_unique($existing_key_fields);
		#pre_dump( 'existing_primary_fields', $existing_primary_fields );
		#pre_dump( 'existing_key_fields', $existing_key_fields );


		// Fetch the table column structure from the database
		$tablefields = $DB->get_results( 'DESCRIBE '.$table );


		// If "drop_column" is not excluded we have to check if all existing cols would get dropped,
		// to prevent "You can't delete all columns with ALTER TABLE; use DROP TABLE instead(Errno=1090)"
		if( ! in_array('drop_column', $exclude_types) )
		{
			$at_least_one_col_stays = false;
			foreach($tablefields as $tablefield)
			{
				$fieldname_lowered = strtolower($tablefield->Field);

				if( isset($wanted_fields[ $fieldname_lowered ]) )
				{
					$at_least_one_col_stays = true;
				}
			}

			if( ! $at_least_one_col_stays )
			{ // all columns get dropped: so we need to DROP TABLE and then use the original CREATE TABLE
				array_unshift($items[$table_lowered], array(
					'queries' => array('DROP TABLE '.$table),
					'note' => 'Dropped <strong>'.$table.'.</strong>',
					'type' => 'drop_column' ));
				continue; // next $table
			}
		}

		// For every field in the existing table
		foreach($tablefields as $tablefield)
		{
			$fieldname_lowered = strtolower($tablefield->Field);

			if( ! isset($wanted_fields[ $fieldname_lowered ]) )
			{ // This field exists in the table, but not in the creation queries

				if( in_array('drop_column', $exclude_types) )
				{
					if( preg_match('~\bAUTO_INCREMENT\b~i', $tablefield->Extra) )
					{ // must be modified with a ADD COLUMN which drops a PK
						$obsolete_autoincrement = $tablefield;
					}
				}
				else
				{
					$items[$table_lowered][] = array(
						'queries' => array('ALTER TABLE '.$table.' DROP COLUMN '.$tablefield->Field),
						'note' => 'Dropped '.$table.'.<strong>'.$tablefield->Field.'</strong>',
						'type' => 'drop_column' );

					// Unset in key indices:
					if( ($k = array_search($fieldname_lowered, $existing_key_fields)) !== false )
					{
						unset($existing_key_fields[$k]);
					}
					if( ($k = array_search($fieldname_lowered, $existing_primary_fields)) !== false )
					{
						unset($existing_primary_fields[$k]);
					}
				}

				continue;
			}

			$column_definition = trim( $wanted_fields[$fieldname_lowered]['field'] );

			unset($type_matches); // have we detected the type as matching (for optional length param)
			$fieldtype = '';

			$pattern_field = '[`"]?'.$tablefield->Field.'[`"]?'; // optionally in backticks

			// Get the field type from the query
			if( preg_match( '~^'.$pattern_field.'\s+ (TINYINT|SMALLINT|MEDIUMINT|INTEGER|INT|BIGINT|REAL|DOUBLE|FLOAT|DECIMAL|DEC|NUMERIC) ( \s* \([\d\s,]+\) )? (\s+ UNSIGNED)? (\s+ ZEROFILL)? (.*)$~ix', $column_definition, $match ) )
			{
				$fieldtype = strtoupper($match[1]);

				if( $fieldtype == 'INTEGER' )
				{ // synonym
					$fieldtype = 'INT';
				}
				elseif( $fieldtype == 'DEC' )
				{ // synonym
					$fieldtype = 'DECIMAL';
				}

				if( isset($match[2]) )
				{ // append optional "length" param (trimmed)
					$fieldtype .= preg_replace( '~\s+~', '', $match[2] );
				}
				if( ! empty($match[3]) )
				{ // "unsigned"
					$fieldtype .= ' '.trim($match[3]);
				}
				if( ! empty($match[4]) )
				{ // "zerofill"
					$fieldtype .= ' '.trim($match[4]);
				}

				$field_to_parse = $match[5];

				// The length param is optional:
				if( substr($fieldtype, 0, 7) == 'DECIMAL' )
					$matches_pattern = '~^'.preg_quote($tablefield->Type, '~').'$~i';
				else
					$matches_pattern = '~^'.preg_replace( '~\((\d+)\)~', '(\(\d+\))?', $tablefield->Type ).'$~i';
				$type_matches = preg_match( $matches_pattern, $fieldtype );
			}
			elseif( preg_match( '~^'.$pattern_field.'\s+(DATETIME|DATE|TIMESTAMP|TIME|YEAR|TINYBLOB|BLOB|MEDIUMBLOB|LONGBLOB|TINYTEXT|TEXT|MEDIUMTEXT|LONGTEXT) ( \s+ BINARY )? (.*)$~ix', $column_definition, $match ) )
			{
				$fieldtype = strtoupper($match[1]);
				if( isset($match[2]) )
				{ // "binary"
					$fieldtype .= trim($match[2]);
				}
				$field_to_parse = $match[3];

				// There's a bug with a "NOT NULL" field reported as "NULL", work around it (http://bugs.mysql.com/bug.php?id=20910):
				if( $fieldtype == 'TIMESTAMP' )
				{
					$ct_sql = $DB->get_var( 'SHOW CREATE TABLE '.$table, 1, 0 );
					if( preg_match( '~^\s*`'.$tablefield->Field.'`\s+TIMESTAMP\s+(NOT )?NULL~im', $ct_sql, $match ) )
					{
						$tablefield->Null = empty($match[1]) ? 'YES' : 'NO';
					}
				}
			}
			elseif( preg_match( '~^'.$pattern_field.'\s+ (CHAR|VARCHAR|BINARY|VARBINARY) \s* \( ([\d\s]+) \) (\s+ (BINARY|ASCII|UNICODE) )? (.*)$~ix', $column_definition, $match ) )
			{
				$len = trim($match[2]);
				$fieldtype = strtoupper($match[1]).'('.$len.')';

				if( ! empty($match[3]) )
				{ // "binary", "ascii", "unicode"
					$fieldtype .= ' '.$match[3];
				}
				$field_to_parse = $match[5];

				if( strtoupper($match[1]) == 'VARCHAR' )
				{
					if( $len < 4 )
					{ // VARCHAR shorter than 4 get converted to CHAR (but reported as VARCHAR in MySQL 5.0)
						$type_matches = preg_match( '~^(VAR)?CHAR\('.$len.'\)'.( $match[3] ? ' '.$match[3] : '' ).'$~i', $tablefield->Type );
					}
				}
				elseif( $has_variable_length_field && strtoupper($match[1]) == 'CHAR' )
				{ // CHARs in a row with variable length fields get silently converted to VARCHAR (but reported as CHAR in MySQL 5.0)
					$type_matches = preg_match( '~^(VAR)?'.preg_quote( $fieldtype, '~' ).'$~i', $tablefield->Type );
				}
			}
			elseif( preg_match( '~^'.$pattern_field.'\s+ (ENUM|SET) \s* \( (.*) \) (.*)$~ix', $column_definition, $match ) )
			{
				$values = preg_split( '~\s*,\s*~', trim($match[2]), -1, PREG_SPLIT_NO_EMPTY ); // TODO: will fail for values containing ","..
				$values = implode( ',', $values );

				$fieldtype = strtoupper($match[1]).'('.$values.')';
				$field_compare = strtolower($match[1]).'('.$values.')';

				// compare case-sensitive
				$type_matches = ( $field_compare == $tablefield->Type );

				$field_to_parse = $match[3];
			}
			else
			{
				if( $debug )
				{
					debug_die( 'db_delta(): Cannot find existing types field in column definition ('.$pattern_field.'/'.$column_definition.')' );
				}
				continue;
			}


			// DEFAULT
			$want_default = false;
			if( preg_match( '~^(.*?) \s DEFAULT \s+ (?: (?: (["\']) (.*?) \2 ) | (\w+) ) (\s .*)?$~ix', $field_to_parse, $match ) )
			{
				if( isset($match[4]) && $match[4] !== '' )
				{
					$want_default = $match[4];
					$want_default_set = $match[4];
				}
				else
				{
					$want_default = $match[3];
					$want_default_set = $match[2].$match[3].$match[2];  // encapsulate in quotes again
				}

				$field_to_parse = $match[1].( isset($match[5]) ? $match[5] : '' );
			}


			// KEY
			$has_inline_primary_key = false;
			if( preg_match( '~^(.*) \b (?: (UNIQUE) (?:\s+ (?:INDEX|KEY))? | (?:PRIMARY \s+)? KEY ) \b (.*)$~ix', $field_to_parse, $match ) )
			{ // fields got added to primary_key_fields and fields_with_keys before
				$field_to_parse = $match[1].$match[3];
				if( empty($match[2]) )
				{
					$has_inline_primary_key = true; // we need to DROP the PK if this column definition does not match
				}
			}


			// AUTO_INCREMENT (with special index handling: AUTO_INCREMENT fields need to be PRIMARY or UNIQUE)
			$is_auto_increment = false;
			if( preg_match( '~(.*?) \b AUTO_INCREMENT \b (.*)$~ix', $field_to_parse, $match ) )
			{
				$is_auto_increment = true;
				$field_to_parse = $match[1].$match[2];

				if( ! preg_match( '~\bAUTO_INCREMENT\b~i', $tablefield->Extra ) )
				{ // not AUTO_INCREMENT yet
					$type_matches = false;
				}

				if( ! in_array( $fieldname_lowered, $fields_with_keys ) )
				{ // no KEY defined (but required for AUTO_INCREMENT fields)
					debug_die('No KEY/INDEX defined for AUTO_INCREMENT column!');
				}

				if( in_array( $fieldname_lowered, $existing_key_fields ) )
				{
					if( ! empty( $primary_key_fields ) )
					{
						$column_definition .= ', DROP PRIMARY KEY';
						unset( $obsolete_indices['PRIMARY'] );
					}
				}
				else
				{ // a key for this AUTO_INCREMENT field does not exist yet, we search it in $indices
					foreach( $indices as $k_index => $l_index )
					{ // go through the indexes we want to have

						if( array_search( $fieldname_lowered, $l_index['col_names'] ) === false )
						{ // this is not an index for our column
							continue;
						}

						// this index definition affects us, we have to add it to our ALTER statement..

						// See if we need to drop it, before adding it:
						if( $l_index['is_PK'] )
						{ // Part of a PRIMARY key..
							if( ! empty( $existing_primary_fields ) )
							{ // and a PRIMARY key exists already
								$column_definition .= ', DROP PRIMARY KEY';
								unset( $obsolete_indices['PRIMARY'] );
							}
							$existing_primary_fields = array(); // we expect no existing primary key anymore
							$primary_key_fields = $l_index['col_names']; // this becomes our primary key
						}
						elseif( isset( $existing_indices[$l_index['name']] ) )
						{ // this index already exists, drop it:
							$column_definition .= ', DROP INDEX '.$existing_indices[$l_index['name']]; // original case
							unset( $existing_indices[$l_index['name']] ); // we expect that it does not exist anymore
							if( ! in_array( $fieldname_lowered, $fields_with_keys ) )
							{ // add te field to the list of keys we want/expect to have:
								$fields_with_keys[] = $fieldname_lowered;
							}
						}

						// Merge the INDEX creation into our ALTER query:
						$column_definition .= ', ADD '.$l_index['create_definition'];
						unset( $indices[$k_index] );
					}
				}
			}


			// "[NOT] NULL" (requires $primary_key_fields to be finalized)
			if( preg_match( '~(.*?) \b (NOT\s+)? NULL \b (.*)$~ix', $field_to_parse, $match ) )
			{ // if "NOT" not matched it's NULL
				$want_null = empty($match[2]);
				$field_to_parse = $match[1].$match[3];
			}
			else
			{ // not specified: "NULL" is default
				$want_null = true;
			}

			if( in_array($fieldname_lowered, $primary_key_fields) || $is_auto_increment )
			{ // If part of PRIMARY KEY or AUTO_INCREMENT field "NULL" is implicit
				$change_null = false; // implicit NULL
				$want_null = 'IMPLICIT';
			}
			elseif( in_array($fieldname_lowered, $existing_primary_fields) && ! in_array($fieldname_lowered, $primary_key_fields) )
			{ // the field was in PRIMARY KEY, but is no longer. It should get altered only if we want "NOT NULL"
				$change_null = ( ! $want_null && $tablefield->Null == 'YES' );
				#pre_dump( $want_null );
				#$want_null = 'IMPLICIT2';
				#pre_dump( $primary_key_fields );
			}
			else
			{
				if( $tablefield->Null == 'YES' )
				{
					$change_null = ! $want_null;
				}
				else
				{ // I've seen '' and 'NO' for no..
					$change_null = $want_null;
				}
			}


			if( ! isset($type_matches) )
			{ // not tried to match before
				$type_matches = ( strtoupper($tablefield->Type) == $fieldtype );
			}

			#pre_dump( 'change_null ($change_null, $tablefield, $want_null)', $change_null, $tablefield, $want_null );
			#pre_dump( 'type_matches', $type_matches, strtolower($tablefield->Type), $fieldtype );


			// See what DEFAULT we would get or want
			$update_default = NULL;
			$update_default_set = NULL;

			if( $want_default !== false )
			{
				$update_default = $want_default;
				$update_default_set = $want_default_set;
			}
			else
			{ // implicit default, see http://dev.mysql.com/doc/refman/4.1/en/data-type-defaults.html
				if( preg_match( '~^(TINYINT|SMALLINT|MEDIUMINT|INTEGER|INT|BIGINT|REAL|DOUBLE|FLOAT|DECIMAL|DEC|NUMERIC)~', $fieldtype ) )
				{ // numeric
					$update_default = '0';
					$update_default_set = '0';
				}
				elseif( $fieldtype == 'TIMESTAMP' )
				{ // TODO: the default should be current date and time for the first field - but AFAICS we won't have NULL fields anyway
				}
				elseif( preg_match( '~^(DATETIME|DATE|TIME|YEAR)$~', $fieldtype ) )
				{
					$update_default = '0'; // short form for various special "zero" values
					$update_default_set = '0';
				}
				elseif( substr($fieldtype, 0, 4) == 'ENUM' )
				{
					preg_match( '~["\']?.*?["\']?\s*[,)]~x', substr($fieldtype,5), $match );
					$update_default_set = trim( $match[0], "\n\r\t\0\x0bB ()," ); // strip default whitespace, braces & comma
					// first value (until "," or end) of $fieldtype_param:
					$update_default = preg_replace( '~^(["\'])(.*)\1$~', '$2', $update_default_set ); // without quotes
				}
				else
				{
					$update_default_set = "''"; // empty string for string types
					$update_default = '';
				}
			}


			// Is actual field type different from the field type in query?
			if( ! $type_matches || $change_null )
			{ // Change the whole column to $column_definition:
				/*
				echo '<h2>No_Match</h2>';
				pre_dump( $type_matches, $change_null, $want_null );
				pre_dump( $tablefield, $column_definition );
				pre_dump( 'flds', $flds );
				pre_dump( 'wanted_fields', $wanted_fields );
				pre_dump( strtolower($tablefield->Type), $fieldtype, $column_definition );
				*/

				$queries = array( 'ALTER TABLE '.$table );

				// Handle inline PRIMARY KEY definition:
				if( $has_inline_primary_key && ! empty($existing_primary_fields) ) // there's a PK that needs to get removed
				{ // the column is part of the PRIMARY KEY, which needs to get dropped before (we already handle that for AUTO_INCREMENT fields)
					$queries[0] .= ' DROP PRIMARY KEY,';
					$existing_primary_fields = array(); // we expect no existing primary key anymore
					unset( $obsolete_indices['PRIMARY'] );
				}

				$queries[0] .= ' CHANGE COLUMN '.$tablefield->Field.' '.$column_definition;

				// Handle changes from "NULL" to "NOT NULL"
				if( $change_null && ! $want_null && isset($update_default_set) )
				{ // Prepend query to update NULL fields to default
					array_unshift( $queries, 'UPDATE '.$table.' SET '.$tablefield->Field.' = '.$update_default_set.' WHERE '.$tablefield->Field.' IS NULL' );

					if( substr( $tablefield->Type, 0, 5 ) == 'enum(' )
					{
						$existing_enum_field_values = preg_split( '~\s*,\s*~', substr( $tablefield->Type, 5, -1 ), -1, PREG_SPLIT_NO_EMPTY );

						foreach( $existing_enum_field_values as $k => $v )
						{
							$existing_enum_field_values[$k] = preg_replace( '~^(["\'])(.*)\1$~', '$2', $v ); // strip quotes
						}

						if( ! in_array( $update_default, $existing_enum_field_values ) )
						{ // we cannot update straight to the new default, because it does not exist yet!

							// Update the column first, without the NULL change
							array_unshift( $queries, 'ALTER TABLE '.$table.' CHANGE COLUMN '.$tablefield->Field.' '.preg_replace( '~\sNOT\s+NULL~i', '', $column_definition ) );
						}
					}
				}

				// Add a query to change the column type
				$items[$table_lowered][] = array(
					'queries' => $queries,
					'note' => 'Changed type of '.$table.'.<strong>'.$tablefield->Field.'</strong> from '.$tablefield->Type.' to '.$column_definition,
					'type' => 'change_column' );
			}
			else
			{ // perhaps alter or drop DEFAULT:
				if( $want_default !== false )
				{ // DEFAULT given
					$existing_default = $tablefield->Default === NULL ? 'NULL' : $tablefield->Default;

					if( $existing_default != $want_default ) // DEFAULT is case-sensitive
					{ // Add a query to change the column's default value
						$items[$table_lowered][] = array(
							'queries' => array('ALTER TABLE '.$table.' ALTER COLUMN '.$tablefield->Field.' SET DEFAULT '.$want_default_set),
							'note' => "Changed default value of {$table}.<strong>{$tablefield->Field}</strong> from $existing_default to $want_default_set",
							'type' => 'change_default' );
					}
				}
				elseif( ! empty($tablefield->Default) && $tablefield->Default != $update_default )
				{ // No DEFAULT given, but it exists one, so drop it (IF not a TIMESTAMP or DATETIME field)
					if( $tablefield->Type != 'timestamp' && $tablefield->Type != 'datetime' )
					{
						$items[$table_lowered][] = array(
							'queries' => array('ALTER TABLE '.$table.' ALTER COLUMN '.$tablefield->Field.' DROP DEFAULT'),
							'note' => "Dropped default value of {$table}.<strong>{$tablefield->Field}</strong>",
							'type' => 'change_default' ); // might be also 'drop_default'
					}
				}
			}

			// Remove the field from the array (so it's not added)
			unset($wanted_fields[$fieldname_lowered]);
		}


		foreach($wanted_fields as $fieldname_lowered => $fielddef)
		{ // For every remaining field specified for the table
			$column_definition = $fielddef['field'].' '.$fielddef['where'];

			$is_auto_increment = false;
			// AUTO_INCREMENT (with special index handling: AUTO_INCREMENT fields need to be PRIMARY or UNIQUE)
			if( preg_match( '~(.*?) \b AUTO_INCREMENT \b (.*)$~ix', $fielddef['field'], $match ) )
			{
				if( ! in_array( $fieldname_lowered, $fields_with_keys ) )
				{ // no KEY defined (but required for AUTO_INCREMENT fields)
					debug_die('No KEY/INDEX defined for AUTO_INCREMENT column!');
				}
				$is_auto_increment = true;


				foreach( $indices as $k_index => $l_index )
				{ // go through the indexes we want to have

					if( array_search( $fieldname_lowered, $l_index['col_names'] ) === false )
					{ // this is not an index for our column
						continue;
					}

					// this index definition affects us, we have to add it to our ALTER statement..

					// See if we need to drop it, before adding it:
					if( $l_index['is_PK'] )
					{ // Part of a PRIMARY key..
						if( ! empty( $existing_primary_fields ) )
						{ // and a PRIMARY key exists already
							$column_definition .= ', DROP PRIMARY KEY';
							unset( $obsolete_indices['PRIMARY'] );
						}
						$existing_primary_fields = array(); // we expect no existing primary key anymore
						$primary_key_fields = $l_index['col_names']; // this becomes our primary key
					}
					elseif( isset( $existing_indices[$l_index['name']] ) )
					{ // this index already exists, drop it:
						$column_definition .= ', DROP INDEX '.$existing_indices[$l_index['name']]; // original case
						unset( $existing_indices[$l_index['name']] ); // we expect that it does not exist anymore
						if( ! in_array( $fieldname_lowered, $fields_with_keys ) )
						{ // add te field to the list of keys we want/expect to have:
							$fields_with_keys[] = $fieldname_lowered;
						}
					}

					// Merge the INDEX creation into our ALTER query:
					$column_definition .= ', ADD '.$l_index['create_definition'];
					unset( $indices[$k_index] );
				}
			}

			// Push a query line into $items that adds the field to that table
			$query = 'ALTER TABLE '.$table.' ADD COLUMN '.$column_definition;

			// Handle inline PRIMARY KEY definition:
			if( preg_match( '~^(.*) \b (?: (UNIQUE) (?:\s+ (?:INDEX|KEY))? | (?:PRIMARY \s+)? KEY ) \b (.*)$~ix', $column_definition, $match ) // "has_inline_primary_key"
					&& count($existing_primary_fields)
					&& ! in_array($fieldname_lowered, $existing_primary_fields) )
			{ // the column is part of the PRIMARY KEY, which needs to get dropped before (we already handle that for AUTO_INCREMENT fields)
				$query .= ', DROP PRIMARY KEY';
				$existing_primary_fields = array(); // we expect no existing primary key anymore
				unset( $obsolete_indices['PRIMARY'] );

				if( isset($obsolete_autoincrement) )
				{
					$query .= ', MODIFY COLUMN '.$obsolete_autoincrement->Field.' '.$obsolete_autoincrement->Type.' '.( $obsolete_autoincrement->Field == 'YES' ? 'NULL' : 'NOT NULL' );
				}
			}

			$items[$table_lowered][] = array(
				'queries' => array($query),
				'note' => 'Added column '.$table.'.<strong>'.$fielddef['field'].'</strong>',
				'type' => 'add_column' );
		}


		// Remove the original table creation query from processing
		array_shift( $items[$table_lowered] );

		// Add the remaining indices (which are not "inline" with a column definition and therefor already handled):
		$add_index_queries = array();
		foreach( $indices as $k => $index )
		{
			if( empty($index['create_definition']) )
			{ // skip "inline"
				continue;
			}
			$query = 'ALTER TABLE '.$table;
			if( $index['is_PK'] && $existing_primary_fields )
			{
				$query .= ' DROP PRIMARY KEY,';
				unset( $obsolete_indices['PRIMARY'] );
			}

			// Create a query that adds the index to the table
			$query = array(
				'queries' => array($query.' ADD '.$index['create_definition']),
				'note' => 'Added index <strong>'.$index['create_definition'].'</strong>',
				'type' => 'add_index' );

			// Check if the index creation has to get appended after any DROPs (required for indices with the same name)
			$append_after_drops = false;
			foreach( $obsolete_indices as $obsolete_index )
			{
				if( strtolower($obsolete_index['name']) == strtolower($index['name']) )
				{
					$append_after_drops = true;
					break;
				}
			}
			if( $append_after_drops )
			{ // do this after any DROPs (i.e. KEY name changes)
				$add_index_queries[] = $query;
			}
			else
			{ // this needs to get done before any other DROPs
				// to prevent e.g. "Incorrect table definition; there can be only one auto column and it must be defined as a key(Errno=1075)"
				$items[$table_lowered][] = $query;
			}
		}

		// Now add queries to drop any (maybe changed!) indices
		foreach( $obsolete_indices as $index_info )
		{
			// Push a query line into $items that drops the index from the table
			$items[$table_lowered][] = array(
				'queries' => array("ALTER TABLE {$table} DROP ".( $index_info['name'] == 'PRIMARY' ? 'PRIMARY KEY' : 'INDEX '.$index_info['name'] )),
				'note' => 'Dropped index <strong>'.$index_info['name'].'</strong>',
				'type' => 'drop_index' );
		}

		// Add queries to (re)create (maybe changed indices) to the end
		$items[$table_lowered] = array_merge($items[$table_lowered], $add_index_queries);
	}


	// Filter types we want to exclude:
	if( ! empty($exclude_types) )
	{
		foreach( $items as $table => $itemlist )
		{
			$removed_one = false;
			foreach( $itemlist as $k => $item )
			{
				if( in_array($item['type'], $exclude_types) )
				{
					unset( $items[$table][$k] );
					$removed_one = true;
				}
			}
			if( $removed_one )
			{ // Re-order (0, 1, 2, ..)
				$items[$table] = array_values($items[$table]);
			}
		}
	}

	// Unset empty table indices:
	foreach( $items as $table => $itemlist )
	{
		if( empty($itemlist) )
		{
			unset( $items[$table] );
			continue;
		}
	}

	if( $execute )
	{
		foreach( $items as $table => $itemlist )
		{
			foreach( $itemlist as $item )
			{
				foreach( $item['queries'] as $query )
				{
					#pre_dump( $query );
					$DB->query( $query );
				}
			}
		}
	}

	return $items;
}


/**
 * Remove quotes/backticks around a field/table name.
 *
 * @param string Field name
 * @param string List of quote chars to remove
 * @return string
 */
function db_delta_remove_quotes($fieldname, $quotes = '`"')
{
	$quotes_len = strlen( $quotes );

	for( $i = 0; $i < $quotes_len; $i++ )
	{
		$char = $quotes[$i];
		if( substr($fieldname, 0, 1) == $char && substr($fieldname, -1) == $char )
		{ // found quotes:
			$fieldname = substr($fieldname, 1, -1);
			return $fieldname;
		}
	}
	return $fieldname;
}


/**
 * Alter the DB schema to match the current expected one ({@link $schema_queries}).
 *
 * @todo if used by install only, then put it into the install folder!!!
 *
 * @param boolean Display what we've done?
 */
function install_make_db_schema_current( $display = true )
{
	global $schema_queries, $DB, $debug;

	// Go through all tables:
	foreach( $schema_queries as $table => $query_info )
	{
		// Look for differences between terrain & map:
		$items_need_update = db_delta( $query_info[1], array('drop_column', 'drop_index'), false );

		if( empty($items_need_update) )
		{
			continue;
		}

		if( ! $display )
		{ // just execute queries
			foreach( $items_need_update as $table => $itemlist )
			{
				foreach( $itemlist as $item )
				{
					foreach( $item['queries'] as $query )
					{
						$DB->query( $query );
					}
				}
			}
		}
		else
		{ // execute & output
			foreach( $items_need_update as $table => $itemlist )
			{
				if( count($itemlist) == 1 && $itemlist[0]['type'] == 'create_table' )
				{
					echo $itemlist[0]['note']."<br />\n";
					foreach( $itemlist[0]['queries'] as $query )
					{ // should be just one, but just in case
						if( $debug >= 2 )
						{
							pre_dump( $query );
						}
						$DB->query( $query );
					}
				}
				else
				{
					echo 'Altering table &laquo;'.$table.'&raquo;...';
					echo '<ul>';
					foreach( $itemlist as $item )
					{
						echo '<li>'.$item['note'];
						if( $debug )
						{
							pre_dump( $item['queries'] );
						}
						echo '</li>';
						foreach( $item['queries'] as $query )
						{
							$DB->query( $query );
						}
					}
					echo "</ul>";
				}
			}
		}
	}
}


/* {{{ Revision log:
 * $Log$
 * Revision 1.16  2010/02/08 17:51:55  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.15  2009/11/26 21:55:46  blueyed
 * db_delta: fix for indices containing whitespace
 *
 * Revision 1.14  2009/11/24 01:13:30  blueyed
 * db_delta: another fix for fulltext indices, and another for renaming of indices, where the old one has to be dropped before the new one can be created.
 *
 * Revision 1.13  2009/11/16 14:53:48  tblue246
 * db_delta_remove_quotes(): Do not call strlen() on every for loop iteration.
 *
 * Revision 1.12  2009/11/15 23:01:16  blueyed
 * Fix db_delta for ANSI style SQL (double quotes). Also fix/add support for fulltext indices.
 *
 * Revision 1.11  2009/10/11 02:34:52  blueyed
 * db_delta: fix implicit default, if length is used for numeric fields, e.g. 'int(11)'
 *
 * Revision 1.10  2009/09/21 03:31:23  fplanque
 * made autoupgrade more verbose in debug mode
 *
 * Revision 1.9  2009/04/11 23:24:49  fplanque
 * blep
 *
 * Revision 1.8  2009/03/08 23:57:40  fplanque
 * 2009
 *
 * Revision 1.7  2009/02/25 21:03:29  blueyed
 * install_make_db_schema_current: Output create table entries in debug mode, too. Not tested.
 *
 * Revision 1.6  2008/10/03 21:56:05  blueyed
 * db_delta: fix index names surrounded in backticks. Add test.
 *
 * Revision 1.5  2008/09/26 19:14:17  tblue246
 * minor
 *
 * Revision 1.4  2008/06/22 13:42:57  blueyed
 * db_delta(): add new query_type 'alter_engine', which adds a query to change the engine used for a table. Includes test.
 *
 * Revision 1.3  2008/01/21 09:35:24  fplanque
 * (c) 2008
 *
 * Revision 1.2  2008/01/09 00:22:17  blueyed
 * db_delta(): remove comments from queries
 *
 * Revision 1.1  2007/06/25 10:59:00  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.34  2007/04/26 00:11:08  fplanque
 * (c) 2007
 *
 * Revision 1.33  2007/04/15 22:16:59  blueyed
 * db_delta() fixes regarding index handlin
 *
 * Revision 1.32  2007/02/11 02:17:27  blueyed
 * Normalized case handling for $fieldtype; fixed $update_default for ENUM
 *
 * Revision 1.29  2007/01/18 21:03:51  blueyed
 * db_delta() fixes: splitting fields by comma; inline UNIQUE and PK handling
 *
 * Revision 1.28  2007/01/14 21:55:07  blueyed
 * doc
 *
 * Revision 1.27  2007/01/14 21:40:17  blueyed
 * db_delta() fix for PK handling/obsoleting
 *
 * Revision 1.26  2007/01/14 03:05:54  blueyed
 * db_delta() fix: handle/remove backticks in auto-generated index names
 *
 * Revision 1.25  2007/01/12 01:34:39  fplanque
 * doc
 *
 * Revision 1.24  2007/01/12 01:21:38  blueyed
 * db_delta() fixes: handle backticks (to be tested more), dropping all existing columns in a table and index/key names (to be tested more)
 *
 * Revision 1.23  2006/11/24 17:41:59  blueyed
 * Fixed NULL handling of TIMESTAMPs and work around the buggy behaviour I was experiencing
 * }}}
 */
?>
