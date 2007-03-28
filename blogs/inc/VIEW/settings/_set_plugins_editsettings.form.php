<?php
/**
 * Form to edit settings of a plugin.
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
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 * @author blueyed: Daniel HAHLER
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @global Plugin
 */
global $edit_Plugin;

/**
 * @global Plugins_admin
 */
global $admin_Plugins;

global $edited_plugin_name, $edited_plugin_shortdesc, $edited_plugin_priority, $edited_plugin_code, $edited_plugin_apply_rendering;
global $admin_url;

global $inc_path;
require_once $inc_path.'_misc/_plugin.funcs.php';


$Form = & new Form( NULL, 'pluginsettings_checkchanges' );
$Form->hidden_ctrl();


// Help icon, if available:
if( $edit_Plugin->get_help_file() )
{ // README in JS popup:
	$Form->global_icon( T_('Local documentation of the plugin'), 'help',
		url_add_param( $admin_url, 'ctrl=plugins&amp;action=disp_help_plain&amp;plugin_class='.$edit_Plugin->classname.'#'.$edit_Plugin->classname.'_settings' ), '', array('use_js_popup'=>true, 'id'=>'anchor_help_popup_'.$edit_Plugin->ID) );
}

// Info button:
$Form->global_icon( T_('Display info'), 'info', regenerate_url( 'action,plugin_class', 'action=info&amp;plugin_class='.$edit_Plugin->classname ) );

// Close button:
$Form->global_icon( T_('Cancel edit!'), 'close', regenerate_url() );

$Form->begin_form( 'fform', '',
	// enable all form elements on submit (so values get sent):
	array( 'onsubmit'=>'var es=this.elements; for( var i=0; i < es.length; i++ ) { es[i].disabled=false; };' ) );

$Form->hidden( 'plugin_ID', $edit_Plugin->ID );

$Form->begin_fieldset( T_('Plugin info'), array( 'class' => 'clear' ) );
	// Name:
	$Form->text_input( 'edited_plugin_name', $edited_plugin_name, 25, T_('Name'), '', array('maxlength' => 255) );
	// Desc:
	$Form->text_input( 'edited_plugin_shortdesc', $edited_plugin_shortdesc, 50, T_('Short desc'), '', array('maxlength' => 255) );
	// Links to manual and readme:
	if( $edit_Plugin->get_help_link('$help_url') || $edit_Plugin->get_help_link('$readme') )
	{
		$Form->info( T_('Manual'), trim( $edit_Plugin->get_help_link('$help_url').' '.$edit_Plugin->get_help_link('$readme') ) );
	}
$Form->end_fieldset();

// PluginSettings
if( $edit_Plugin->Settings ) // NOTE: this triggers PHP5 autoloading through Plugin::__get() and therefor the 'isset($this->Settings)' hack in Plugin::GetDefaultSettings() still works, which is good.
{
	global $inc_path;
	require_once $inc_path.'_misc/_plugin.funcs.php';

	// We use output buffers here to display the fieldset only, if there's content in there (either from PluginSettings or PluginSettingsEditDisplayAfter).
	ob_start();
	$Form->begin_fieldset( T_('Plugin settings'), array( 'class' => 'clear' ) );

	ob_start();
	foreach( $edit_Plugin->GetDefaultSettings( $tmp_params = array('for_editing'=>true) ) as $l_name => $l_meta )
	{
		display_plugin_settings_fieldset_field( $l_name, $l_meta, $edit_Plugin, $Form, 'Settings' );
	}

	$admin_Plugins->call_method( $edit_Plugin->ID, 'PluginSettingsEditDisplayAfter', $tmp_params = array( 'Form' => & $Form ) );

	$has_contents = strlen( ob_get_contents() );
	$Form->end_fieldset();

	if( $has_contents )
	{
		ob_end_flush();
		ob_end_flush();
	}
	else
	{ // No content, discard output buffers:
		ob_end_clean();
		ob_end_clean();
	}
}

// Plugin variables
$Form->begin_fieldset( T_('Plugin variables').' ('.T_('Advanced').')', array( 'class' => 'clear' ) );
$Form->text_input( 'edited_plugin_code', $edited_plugin_code, 15, T_('Code'), T_('The code to call the plugin by code. This is also used to link renderer plugins to items.'), array('maxlength'=>32) );
$Form->text_input( 'edited_plugin_priority', $edited_plugin_priority, 4, T_('Priority'), '', array( 'maxlength' => 4 ) );
$render_note = get_web_help_link('Plugin/apply_rendering');
if( empty( $edited_plugin_code ) )
{
	$render_note .= ' '.T_('Note: The plugin code is empty, so this plugin will not work as an "opt-out", "opt-in" or "lazy" renderer.');
}
$Form->select_input_array( 'edited_plugin_apply_rendering', $edited_plugin_apply_rendering,
		$admin_Plugins->get_apply_rendering_values(), T_('Apply rendering'), $render_note );
$Form->end_fieldset();


// (De-)Activate Events (Advanced)
$Form->begin_fieldset( T_('Plugin events').' ('.T_('Advanced')
	.') <img src="'.get_icon('expand', 'url').'" id="clickimg_pluginevents" />', array('legend_params' => array( 'onclick' => 'toggle_clickopen(\'pluginevents\')') ) );
	?>

	<div id="clickdiv_pluginevents">

	<?php

	if( $edit_Plugin->status != 'enabled' )
	{
		echo '<p class="notes">'.T_('Note: the plugin is not enabled.').'</p>';
	}

	echo '<p>'.T_('Warning: by disabling plugin events you change the behaviour of the plugin! Only change this, if you know what you are doing.').'</p>';

	$enabled_events = $admin_Plugins->get_enabled_events( $edit_Plugin->ID );
	$supported_events = $admin_Plugins->get_supported_events();
	$registered_events = $admin_Plugins->get_registered_events( $edit_Plugin );
	$count = 0;
	foreach( array_keys($supported_events) as $l_event )
	{
		if( ! in_array( $l_event, $registered_events ) )
		{
			continue;
		}
		$Form->hidden( 'edited_plugin_displayed_events[]', $l_event ); // to consider only displayed ones on update
		$Form->checkbox_input( 'edited_plugin_events['.$l_event.']', in_array( $l_event, $enabled_events ), $l_event, array( 'note' => $supported_events[$l_event] ) );
		$count++;
	}
	if( ! $count )
	{
		echo T_( 'This plugin has no registered events.' );
	}
	?>

	</div>

	<?php
$Form->end_fieldset();
?>


<script type="text/javascript">
	<!--
	toggle_clickopen('pluginevents');
	// -->
</script>

<?php
if( $current_User->check_perm( 'options', 'edit', false ) )
{
	$Form->buttons_input( array(
		array( 'type' => 'submit', 'name' => 'actionArray[update_settings]', 'value' => T_('Save !'), 'class' => 'SaveButton' ),
		array( 'type' => 'submit', 'name' => 'actionArray[update_settings][review]', 'value' => T_('Save (and review)'), 'class' => 'SaveButton' ),
		array( 'type' => 'reset', 'value' => T_('Reset'), 'class' => 'ResetButton' ),
		array( 'type' => 'submit', 'name' => 'actionArray[default_settings]', 'value' => T_('Restore defaults'), 'class' => 'SaveButton' ),
		) );
}
$Form->end_form();


/* {{{ Revision log:
 * $Log$
 * Revision 1.32  2007/03/28 23:10:17  blueyed
 * Added link to external manual/wiki
 *
 * Revision 1.31  2007/02/19 23:17:00  blueyed
 * Only display Plugin(User)Settings fieldsets if there is content in them.
 *
 * Revision 1.30  2007/01/23 08:57:36  fplanque
 * decrap!
 *
 * Revision 1.29  2007/01/14 08:21:01  blueyed
 * Optimized "info", "disp_help" and "disp_help_plain" actions by refering to them through classname, which makes Plugins::discover() unnecessary
 *
 * Revision 1.28  2006/12/09 01:55:36  fplanque
 * feel free to fill in some missing notes
 * hint: "login" does not need a note! :P
 *
 * Revision 1.27  2006/11/24 18:27:26  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.26  2006/11/14 00:22:13  blueyed
 * doc
 *
 * Revision 1.25  2006/11/09 23:40:57  blueyed
 * Fixed Plugin UserSettings array type editing; Added jquery and use it for AJAHifying Plugin (User)Settings editing of array types
 *
 * Revision 1.24  2006/11/05 18:33:58  fplanque
 * no external links in action icons
 *
 * Revision 1.23  2006/10/30 19:00:36  blueyed
 * Lazy-loading of Plugin (User)Settings for PHP5 through overloading
 *
 * Revision 1.22  2006/10/17 18:36:47  blueyed
 * Priorize NULL for plug_name/plug_shortdesc (localization); minor fixes in this area
 *
 * Revision 1.21  2006/09/30 23:42:06  blueyed
 * Allow editing the plugin name and short desc of installed plugins
 *
 * Revision 1.20  2006/09/10 19:23:28  blueyed
 * Removed Plugin::code(), ::name(), ::short_desc() and ::long_desc(); Fixes for mt-import.php
 *
 * Revision 1.19  2006/08/30 17:45:28  blueyed
 * Fixed "$help_url"/www Plugin help popups (use target _blank instead); Added "$help_url"/www help Plugin to plugin settings (linking to #Settings)
 *
 * Revision 1.18  2006/08/07 18:10:32  fplanque
 * removed bloated action icons (no reason to come to this pace to perform these actions)
 *
 * Revision 1.17  2006/07/27 21:53:45  blueyed
 * Added help link for "apply_rendering" to the manual
 *
 * Revision 1.16  2006/07/08 12:48:33  blueyed
 * Removed "broken" icon, because it's no action icon.
 *
 * Revision 1.15  2006/07/03 22:02:18  blueyed
 * Fixed warning/broken icon (which is just an icon, without action/url)
 *
 * Revision 1.14  2006/06/22 19:50:51  blueyed
 * Plugin action icons (status and uninstall) with the settings form.
 *
 * Revision 1.13  2006/05/30 19:39:55  fplanque
 * plugin cleanup
 *
 * Revision 1.12  2006/05/22 20:35:36  blueyed
 * Passthrough some attribute of plugin settings, allowing to use JS handlers. Also fixed submitting of disabled form elements.
 *
 * Revision 1.11  2006/04/21 16:58:11  blueyed
 * Add warning to "disable plugin events".
 *
 * Revision 1.10  2006/04/19 20:13:52  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.9  2006/04/13 01:23:19  blueyed
 * Moved help related functions back to Plugin class
 *
 * Revision 1.8  2006/04/05 19:16:34  blueyed
 * Refactored/cleaned up help link handling: defaults to online-manual-pages now.
 *
 * Revision 1.7  2006/03/15 23:35:38  blueyed
 * "broken" state support for Plugins: set/unset state, allowing to un-install and display error in "edit_settings" action
 *
 * Revision 1.6  2006/03/15 21:02:07  blueyed
 * Display event status for all plugins with $admin_Plugins
 *
 * Revision 1.5  2006/03/12 23:09:01  fplanque
 * doc cleanup
 *
 * Revision 1.4  2006/03/01 01:07:43  blueyed
 * Plugin(s) polishing
 *
 * Revision 1.3  2006/02/27 16:57:12  blueyed
 * PluginUserSettings - allows a plugin to store user related settings
 *
 * Revision 1.2  2006/02/24 23:38:55  blueyed
 * fixes
 *
 * Revision 1.1  2006/02/24 23:02:16  blueyed
 * Added _set_plugins_editsettings.form VIEW
 *
 * }}}
 */
?>