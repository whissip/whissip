<?php
/**
 * This file implements the HTML 5 VideoJS Player plugin for b2evolution
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
class html5_videojs_plugin extends Plugin
{
	var $code = 'b2evH5V';
	var $name = 'HTML 5 VideoJS Player';
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
		require_css( 'http://vjs.zencdn.net/c/video-js.css', 'relative' );
		require_js( 'http://vjs.zencdn.net/c/video.js', 'relative' );
		$this->require_skin();

		// Set a video size in css style, because option setting is ignored by some reason
		$width = (int) $this->Settings->get( 'width' );
		$width = empty( $width ) ? '100%' : $width.'px';
		$height = (int) $this->Settings->get( 'height' );
		add_css_headline( '.video-js{ width: '.$width.' !important; height: '.$height.'px !important; }' );
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
				'defaultvalue' => 'tubecss',
				),
			'width' => array(
				'label' => T_('Video width (px)'),
				'note' => T_('100% width if left empty or 0'),
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
			global $html5_videojs_number;
			$html5_videojs_number++;

			if( $in_comments )
			{
				$params['data'] .= '<div style="clear: both; height: 0px; font-size: 0px"></div>';
			}

			/**
			 * Video options:
			 *
			 * controls:  true // The controls option sets whether or not the player has controls that the user can interact with.
			 * autoplay:  true // If autoplay is true, the video will start playing as soon as page is loaded (without any interaction from the user). NOT SUPPORTED BY APPLE iOS DEVICES
			 * preload:   'auto'|'metadata'|'none' // The preload attribute informs the browser whether or not the video data should begin downloading as soon as the video tag is loaded.
			 * poster:    'myPoster.jpg' // The poster attribute sets the image that displays before the video begins playing.
			 * loop:      true // The loop attribute causes the video to start over as soon as it ends.
			 */
			$video_options = array();
			$video_options['controls'] = true;
			$video_options['preload'] = 'auto';


			$params['data'] .= '<video id="html5_videojs_'.$html5_videojs_number.'" class="video-js '.$this->Settings->get( 'skin' ).'" data-setup=\''.evo_json_encode( $video_options ).'\'>'.
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
		// Set this skin permanently, because it is a default skin name
		$skins['vjs-default-skin'] = 'vjs-default-skin';

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
	 * Require css file of current skin
	 */
	function require_skin()
	{
		$skin = $this->Settings->get( 'skin' );
		if( !empty( $skin ) && $skin != 'vjs-default-skin')
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