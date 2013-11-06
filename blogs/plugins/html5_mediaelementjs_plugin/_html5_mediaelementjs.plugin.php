<?php
/**
 * This file implements the HTML 5 MediaElement.js Video Player plugin for b2evolution
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}
 *
 * @author fplanque: Francois PLANQUE.
 *
 * @package plugins
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * @package plugins
 */
class html5_mediaelementjs_plugin extends Plugin
{
	var $code = 'b2evH5M';
	var $name = 'HTML 5 MediaElement.js Video Player';
	var $priority = 80;
	var $version = '5.0.0';
	var $group = 'files';
	var $number_of_installs = 1;
	var $allow_ext = array( 'flv', 'm4v', 'f4v', 'mp4', 'ogv', 'webm' );


	function PluginInit( & $params )
	{
		$this->short_desc = sprintf( T_('Media player for the these file formats: %s.'), implode( ', ', $this->allow_ext ) );
		$this->long_desc = $this->short_desc;
	}


	/**
	 * @see Plugin::SkinBeginHtmlHead()
	 */
	function SkinBeginHtmlHead( & $params )
	{
		require_css( $this->get_plugin_url().'rsc/mediaelementplayer.min.css', 'relative' );
		require_js( '#jquery#', 'blog' );
		require_js( $this->get_plugin_url().'rsc/mediaelement-and-player.min.js', 'relative' );
		$this->require_skin();

		// Set a video size in css style, because option setting cannot sets correct size
		$width = (int) $this->Settings->get( 'width' );
		$height = (int) $this->Settings->get( 'height' );
		add_css_headline( 'video.html5_mediaelementjs_video{ width: '.$width.'px !important; height: '.$height.'px !important; }' );

		// Initialize a player
		add_js_headline( 'jQuery( document ).ready( function() {
				jQuery( "video.html5_mediaelementjs_video" ).mediaelementplayer( {
					defaultVideoWidth: "'.$width.'",
					defaultVideoHeight: "'.$height.'",
					videoWidth: "'.$width.'",
					videoHeight: "'.$height.'",
				} );
			} );' );
		/**
		 * Plugin options:

			// if the <video width> is not specified, this is the default
			defaultVideoWidth: 480,
			// if the <video height> is not specified, this is the default
			defaultVideoHeight: 270,
			// if set, overrides <video width>
			videoWidth: -1,
			// if set, overrides <video height>
			videoHeight: -1,
			// width of audio player
			audioWidth: 400,
			// height of audio player
			audioHeight: 30,
			// initial volume when the player starts
			startVolume: 0.8,
			// useful for <audio> player loops
			loop: false,
			// enables Flash and Silverlight to resize to content size
			enableAutosize: true,
			// the order of controls you want on the control bar (and other plugins below)
			features: ['playpause','progress','current','duration','tracks','volume','fullscreen'],
			// Hide controls when playing and mouse is not over the video
			alwaysShowControls: false,
			// force iPad's native controls
			iPadUseNativeControls: false,
			// force iPhone's native controls
			iPhoneUseNativeControls: false,
			// force Android's native controls
			AndroidUseNativeControls: false,
			// forces the hour marker (##:00:00)
			alwaysShowHours: false,
			// show framecount in timecode (##:00:00:00)
			showTimecodeFrameCount: false,
			// used when showTimecodeFrameCount is set to true
			framesPerSecond: 25,
			// turns keyboard support on and off for this instance
			enableKeyboard: true,
			// when this player starts, it will pause other players
			pauseOtherPlayers: true,
			// array of keyboard commands
			keyActions: []

		 */
	}


	/**
	 * @see Plugin::AdminEndHtmlHead()
	 */
	function AdminEndHtmlHead( & $params )
	{
		$this->SkinBeginHtmlHead( $params );
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
	function GetDefaultSettings( & $params )
	{
		return array(
			'skin' => array(
				'label' => T_('Skin'),
				'type' => 'select',
				'options' => $this->get_skins_list(),
				'defaultvalue' => 'default',
				),
			'width' => array(
				'label' => T_('Video width (px)'),
				'type' => 'integer',
				'defaultvalue' => 425,
				'note' => '',
				'valid_range' => array( 'min' => 1 ),
				),
			'height' => array(
				'label' => T_('Video height (px)'),
				'type' => 'integer',
				'defaultvalue' => 300,
				'note' => '',
				'valid_range' => array( 'min' => 1 ),
				),
			'allow_download' => array(
				'label' => T_('Allow downloading of the video file'),
				'type' => 'checkbox',
				'defaultvalue' => 0,
				),
			);
	}


	/**
	 * Check a file for correct extension
	 *
	 * @param File
	 * @return boolean true if extension of file supported by plugin
	 */
	function is_flp_video( $File )
	{
		return in_array( strtolower( $File->get_ext() ), $this->allow_ext );
	}


	/**
	 * Event handler: Called when displaying item attachment.
	 *
	 * @param array Associative array of parameters. $params['File'] - attachment, $params['data'] - output
	 * @param boolean TRUE - when render in comments
	 * @return boolean true if plugin rendered this attachment
	 */
	function RenderItemAttachment( & $params, $in_comments = false )
	{
		$File = $params['File'];

		if( ! $this->is_flp_video( $File ) )
		{
			return false;
		}

		if( $File->exists() )
		{
			/**
			 * @var integer A number to assign each video player new id attribute
			 */
			global $html5_mediaelementjs_number;
			$html5_mediaelementjs_number++;

			if( $in_comments )
			{
				$params['data'] .= '<div style="clear: both; height: 0px; font-size: 0px"></div>';
			}

			$params['data'] .= '<video class="html5_mediaelementjs_video '.$this->get_skin_class().'" id="html5_mediaelementjs_'.$html5_mediaelementjs_number.'">'.
				'<source src="'.$File->get_url().'" type="'.$this->get_video_mimetype( $File ).'" />'.
			'</video>';

			if( $this->Settings->get( 'allow_download' ) )
			{	// Allow to download the video files
				$params['data'] .= '<div class="small center"><a href="'.$File->get_url().'">'.T_('Download this video').'</a></div>';
			}

			return true;
		}

		return false;
	}


	/**
	 * Event handler: Called when displaying comment attachment.
	 *
	 * @param array Associative array of parameters. $params['File'] - attachment, $params['data'] - output
	 * @return boolean true if plugin rendered this attachment
	 */
	function RenderCommentAttachment( & $params )
	{
		return $this->RenderItemAttachment( $params, true );
	}

	/**
	 * Get a list of the skins
	 *
	 * @return array Skins
	 */
	function get_skins_list()
	{
		$skins_path = dirname( $this->classfile_path ).'/skins';

		$skins = array();
		// Set this skin permanently, because it is a default skin
		$skins['default'] = 'default';

		$files = scandir( $skins_path );
		foreach( $files as $file )
		{
			if( $file != '.' && $file != '..' && is_dir( $skins_path.'/'.$file ) )
			{	// Use folder name as skin name
				$skins[ $file ] = $file;
			}
		}

		return $skins;
	}

	/**
	 * Get skin class name
	 *
	 * @return string Skin class name
	 */
	function get_skin_class()
	{
		$skin = $this->Settings->get( 'skin' );

		if( !empty( $skin ) && $skin != 'default')
		{
			return 'mejs-'.$this->Settings->get( 'skin' );
		}

		return ''; // Default skin
	}

	/**
	 * Require css file of current skin
	 */
	function require_skin()
	{
		$skin = $this->Settings->get( 'skin' );
		if( !empty( $skin ) && $skin != 'default')
		{
			$skins_path = dirname( $this->classfile_path ).'/skins';
			if( file_exists( $skins_path.'/'.$skin.'/style.css' ) )
			{	// Require css file only if it exists
				require_css( $this->get_plugin_url().'skins/'.$skin.'/style.css', 'relative' );
			}
		}
	}

	/**
	 * Get video mimetype
	 *
	 * @param object File
	 * @return string Mimetype
	 */
	function get_video_mimetype( $File )
	{
		switch( $File->get_ext() )
		{
			case 'flv':
				$mimetype = 'video/flv';
				break;

			case 'm4v':
				$mimetype = 'video/m4v';
				break;

			case 'ogv':
				$mimetype = 'video/ogg';
				break;

			case 'webm':
				$mimetype = 'video/webm';
				break;

			case 'f4v':
			case 'mp4':
			default:
				$mimetype = 'video/mp4';
				break;
		}

		return $mimetype;
	}
}
?>