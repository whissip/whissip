<?php
/**
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2009 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2009 by The Evo Factory - {@link http://www.evofactory.com/}.
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
 * The Evo Factory grants Francois PLANQUE the right to license
 * The Evo Factory's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author efy-maxim: Evo Factory / Maxim.
 * @author fplanque: Francois Planque.
 *
 * @version $Id$
 */

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( '_core/model/dataobjects/_dataobject.class.php', 'DataObject' );

/**
 * Country Class
 */
class Country extends DataObject
{
	var $code = '';
	var $name = '';
	var $curr_ID = '';
	var $enabled = 1;

	/**
	 * Constructor
	 *
	 * @param object database row
	 */
	function Country( stdClass $db_row = NULL )
	{
		// Call parent constructor:
		parent::DataObject( 'T_country', 'ctry_', 'ctry_ID' );

		$this->delete_restrictions = array(
				array( 'table'=>'T_users', 'fk'=>'user_ctry_ID', 'msg'=>T_('%d related users') ),
			);

  		$this->delete_cascades = array();

 		if( $db_row )
		{
			$this->ID            = $db_row->ctry_ID;
			$this->code          = $db_row->ctry_code;
			$this->name          = $db_row->ctry_name;
			$this->curr_ID       = $db_row->ctry_curr_ID;
			$this->enabled       = $db_row->ctry_enabled;
		}
	}


	/**
	 * Load data from Request form fields.
	 *
	 * @return boolean true if loaded data seems valid.
	 */
	function load_from_Request()
	{
		// Name
		$this->set_string_from_param( 'name', true );

		// Code
		param( 'ctry_code', 'string' );
		param_check_regexp( 'ctry_code', '#^[A-Za-z]{2}$#', T_('Country code must be 2 letters parameter.') );
		$this->set_from_Request( 'code', 'ctry_code' );

		// Currency Id
		param( 'ctry_curr_ID', 'integer' );
		param_check_number( 'ctry_curr_ID', 'Please select a currency' );
		$this->set_from_Request( 'curr_ID', 'ctry_curr_ID', true );

		return ! param_errors_detected();
	}


	/**
	 * Set param value
	 *
	 * By default, all values will be considered strings
	 *
	 * @param string parameter name
	 * @param mixed parameter value
	 * @param boolean true to set to NULL if empty value
	 * @return boolean true, if a value has been set; false if it has not changed
	 */
	function set( $parname, $parvalue, $make_null = false )
	{
		switch( $parname )
		{
			case 'code':
				$parvalue = strtolower($parvalue);
			case 'name':
			case 'curr_ID':
			case 'enabled':
			default:
				return $this->set_param( $parname, 'string', $parvalue, $make_null );
		}
	}


	/**
	 * Get country name.
	 *
	 * @return string currency code
	 */
	function get_name()
	{
		return $this->name;
	}


	/**
	 * Check existence of specified country code in ctry_code unique field.
	 *
	 * @return int ID if country code exists otherwise NULL/false
	 */
	function dbexists()
	{
		return parent::dbexists('ctry_code', $this->code);
	}
}


/*
 * $Log$
 * Revision 1.16  2009/09/29 03:14:22  fplanque
 * doc
 *
 * Revision 1.15  2009/09/28 20:55:00  efy-khurram
 * Implemented support for enabling disabling countries.
 *
 * Revision 1.14  2009/09/20 20:07:18  blueyed
 *  - DataObject::dbexists quotes always
 *  - phpdoc fixes
 *  - style fixes
 *
 * Revision 1.13  2009/09/14 13:31:35  efy-arrin
 * Included the ClassName in load_class() call with proper UpperCase
 *
 * Revision 1.12  2009/09/12 12:53:44  tblue246
 * Country class: Call load_class() with $classname parameter
 *
 * Revision 1.11  2009/09/12 10:40:40  tblue246
 * Fixed wrong pathname
 *
 * Revision 1.9  2009/09/10 18:24:07  fplanque
 * doc
 *
 */
?>
