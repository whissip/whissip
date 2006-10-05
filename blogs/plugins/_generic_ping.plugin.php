<?php
/**
 * This file implements the generic_ping_plugin.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2006 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * {@internal License choice
 * - If you have received this file as part of a package, please find the license.txt file in
 *   the same folder or the closest folder above for complete license terms.
 * - If you have received this file individually (e-g: from http://cvs.sourceforge.net/viewcvs.py/evocms/)
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
 * @package plugins
 *
 * @author blueyed: Daniel HAHLER
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Generic Ping Plugin
 *
 * @package plugins
 */
class generic_ping_plugin extends Plugin
{
	/**
	 * Variables below MUST be overriden by plugin implementations,
	 * either in the subclass declaration or in the subclass constructor.
	 */
	var $code = '';
	var $priority = 50;
	var $version = '1.9-dev';
	var $author = 'The b2evo Group';
	var $help_url = '';  // empty URL defaults to manual wiki, in this case: http://manual.b2evolution.net/Plugins/test_plugin';

	/*
	 * These variables MAY be overriden.
	 */
	var $apply_rendering = 'never';
	var $group = 'ping';


	/**
	 * Init
	 *
	 * This gets called after a plugin has been registered/instantiated.
	 */
	function PluginInit( & $params )
	{
		$this->name = T_('Ping plugin');
		$this->short_desc = T_('Generic Ping plugin');
		$this->long_desc = T_('Use this plugin to add a ping service to your installation.');

		if( isset($this->Settings) )
		{ // is not set for not-installed Plugins
			$this->ping_service_name = $this->Settings->get('ping_service_name');
			$this->ping_service_note = $this->Settings->get('ping_service_note');
		}
	}


	/**
	 * Get the settings that the plugin can use.
	 *
	 * Those settings are transfered into a Settings member object of the plugin
	 * and can be edited in the backoffice (Settings / Plugins).
	 *
	 * @see Plugin::GetDefaultSettings()
	 * @see PluginSettings
	 * @see Plugin::PluginSettingsValidateSet()
	 * @return array
	 */
	function GetDefaultSettings()
	{
		return array(
			'ping_service_url' => array(
					'label' => T_('Ping service URL'),
					'defaultvalue' => '',
					'type' => 'text',
					'size' => 50,
					'note' => T_('The URL of the ping service.').' '.sprintf('E.g. &laquo;%s&raquo;', 'rpc.weblogs.com/RPC2 or rpc.foobar.com:8080'),
				),
			'ping_service_name' => array(
					'label' => T_('Ping service name'),
					'defaultvalue' => '',
					'type' => 'text',
					'size' => 25,
					'note' => T_('The name of the ping service, used for displaying only.'),
			),
			'ping_service_note' => array(
					'label' => T_('Ping service note'),
					'defaultvalue' => '',
					'type' => 'text',
					'size' => 50,
					'note' => T_('Notes about the ping service, used for displaying only.'),
			),
		);
	}


	/**
	 * Check ping service URL and plugin code.
	 */
	function BeforeEnable()
	{
		$ping_service_url = $this->Settings->get('ping_service_url');
		if( empty($ping_service_url) )
		{
			return T_('You must configure a ping service URL before the plugin can be enabled.');
		}

		if( empty($this->code) )
		{
			return T_('The ping plugin needs a non-empty code.');
		}

		return true;
	}


	/**
	 * Check ping service URL.
	 */
	function PluginSettingsValidateSet( & $params )
	{
		if( $params['name'] == 'ping_service_url' )
		{
			if( ! $this->parse_ping_url($params['value']) )
			{
				return T_('The ping service URL is invalid.');
			}
		}
	}


	/**
	 * Parse a given ping service URL
	 *
	 * @return false|array False in case of error, array with keys 'host', 'port', 'path' otherwise
	 */
	function parse_ping_url( $url )
	{
		if( ! preg_match( '~^([^/:]+)(:\d+)?(/.*)?$~', $url, $match ) )
		{
			return false;
		}

		$r = array(
				'host' => $match[1],
				'port' => empty($match[2]) ? 80 : $match[2],
				'path' => empty($match[3]) ? '/' : $match[3],
			);

		return $r;
	}


	/**
	 * Send a ping to the configured service.
	 */
	function ItemSendPing( & $params )
	{
		global $debug;

		$url = $this->parse_ping_url( $this->Settings->get( 'ping_service_url' ) );

		$item_Blog = $params['Item']->get_Blog();

		$client = new xmlrpc_client( $url['path'], $url['host'], $url['port'] );
		$client->debug = ($debug && $params['display']);

		$message = new xmlrpcmsg("weblogUpdates.ping", array(
				new xmlrpcval( $item_Blog->get('name') ),
				new xmlrpcval( $item_Blog->get('url') ) ));
		$result = $client->send($message);

		$params['xmlrpcresp'] = $result;

		return true;
	}

}


/*
 * $Log$
 * Revision 1.1  2006/10/05 01:19:11  blueyed
 * Initial import of generic ping plugin.
 *
 */
?>
