<?php
/**
 * This file implements the Image Smilies Renderer plugin for b2evolution
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @author fplanque: Francois PLANQUE.
 * @author gorgeb: Bertrand GORGE / EPISTEMA
 *
 * @package plugins
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * @package plugins
 */
class smilies_plugin extends Plugin
{
	var $code = 'b2evSmil';
	var $name = 'Smilies';
	/**
	 * @todo dh> Should get a low priority (e.g. 80) so it does not create icon image
	 *           tags which then get processed by another plugin.
	 *           Is there any benefit from a high prio like now? So that we do not
	 *           match "generated" simlies later?
	 * fp> There is... I can't remember the exact problem thouh. Probably some interaction with the code highlight or the video plugins.
	 */
	var $priority = 15;
	var $version = '1.10';
	var $apply_rendering = 'opt-out';
	var $group = 'rendering';
	var $number_of_installs = 3; // QUESTION: dh> why 3?

	/**
	 * Text similes search array
	 *
	 * @access private
	 */
	var $search;

	/**
	 * IMG replace array
	 *
	 * @access private
	 */
	var $replace;

	/**
	 * Smiley definitions
	 *
	 * @access private
	 */
	var $smilies;

	/**
	 * Init
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = T_('Graphical smileys');
		$this->long_desc = T_('This renderer will convert text smilies like :) to graphical icons.<br />
			Optionally, it will also display a toolbar for quick insertion of smilies into a post.');
	}


	/**
	* Defaults for user specific settings: "Display toolbar"
	 *
	 * @return array
	 */
	function GetDefaultSettings()
	{
		global $rsc_subdir;
		return array(
				'use_toolbar_default' => array(
					'label' => T_( 'Use smilies toolbar' ),
					'defaultvalue' => '1',
					'type' => 'checkbox',
					'note' => T_( 'This is the default setting. Users can override it in their profile.' ),
				),
				'render_comments' => array(	// fp> Note: this is not a default in this version, it's an 'always' :]
					'label' => $this->T_('Render comments' ),
					'note' => $this->T_('Check to also render smilies in comments.'),
					'defaultvalue' => '0',
					'type' => 'checkbox',
				),
				// TODO (yabs) : Display these as images and individual inputs
				'smiley_list' => array(
					'label' => $this->T_( 'Smiley list'),
					'note' => sprintf( $this->T_( 'This is the list of smileys [one per line], in the format : char_sequence image_file // optional comment<br />
							To disable a smiley, just add one or more spaces to the start of its setting<br />
							You can add new smiley images by uploading the images to the %s folder.' ), '<span style="font-weight:bold">'.$rsc_subdir.'smilies/</span>' ),
					'type' => 'html_textarea', // allows smilies with "<" in them
					'rows' => 10,
					'cols' => 60,
					'defaultvalue' => '
 =>       icon_arrow.gif
:!:      icon_exclaim.gif
:?:      icon_question.gif
:idea:   icon_idea.gif
:)       icon_smile.gif
:D       icon_biggrin.gif
:p       icon_razz.gif
B)       icon_cool.gif
;)       icon_wink.gif
:>       icon_twisted.gif
:roll:   icon_rolleyes.gif
:oops:   icon_redface.gif
:|       icon_neutral.gif
:-/      icon_confused.gif
:(       icon_sad.gif
 >:(      icon_mad.gif
:\'(      icon_cry.gif
|-|      icon_wth.gif
:>>      icon_mrgreen.gif
:yes:    grayyes.gif
;D       graysmilewinkgrin.gif
:P       graybigrazz.gif
:))      graylaugh.gif
88|      graybigeek.gif
:.       grayshy.gif
:no:     grayno.gif
XX(      graydead.gif
:lalala: icon_lalala.gif
:crazy:  icon_crazy.gif
>:XX     icon_censored.gif
 :DD     icon_lol.gif
 :o      icon_surprised.gif
 8|      icon_eek.gif
 >:-[    icon_evil.gif
 :)      graysmile.gif
 :b      grayrazz.gif
 )-o     grayembarrassed.gif
 U-(     grayuhoh.gif
 :(      graysad.gif
 :**:    graysigh.gif     // alternative: graysighw.gif
 :??:    grayconfused.gif // alternative: grayconfusedw.gif
 :`(     graycry.gif
 >:-(    graymad.gif
 :##      grayupset.gif   // alternative: grayupsetw.gif
 :zz:    graysleep.gif    // alternative: graysleepw.gif
 :wave:  icon_wave.gif',
				),
			);
}


	/**
	 * Allowing the user to override the display of the toolbar.
	 *
	 * @return array
	 */
	function GetDefaultUserSettings()
	{
		return array(
				'use_toolbar' => array(
					'label' => T_( 'Use smilies toolbar' ),
					'defaultvalue' => $this->Settings->get('use_toolbar_default'),
					'type' => 'checkbox',
				),
			);
	}


	/**
	 * Display a toolbar in admin
	 *
	 * @param array Associative array of parameters
	 * @return boolean did we display a toolbar?
	 */
	function AdminDisplayToolbar( & $params )
	{
		if( $this->UserSettings->get('use_toolbar') )
		{
			return $this->display_smiley_bar();
		}
		return false;
	}


	/**
	 * Event handler: Called when displaying editor toolbars.
	 *
	 * @param array Associative array of parameters
	 * @return boolean did we display a toolbar?
	 */
	function DisplayCommentToolbar( & $params )
	{
		if( $this->Settings->get( 'render_comments' )
		&& ( ( is_logged_in() && $this->UserSettings->get( 'use_toolbar' ) )
			|| ( !is_logged_in() && $this->Settings->get( 'use_toolbar_default' ) ) ) )
		{	
			return $this->display_smiley_bar();
		}
		return false;
	}


	/**
	 * Display the smiley toolbar
	 *
	 * @return boolean did we display a toolbar?
	 */
	function display_smiley_bar()
	{
		$this->InitSmilies();	// check smilies cached

		$grins = '';
		$smiled = array();
		foreach( $this->smilies as $smiley )
		{
			if (!in_array($smiley[ 'image' ], $smiled))
			{
				$smiled[] = $smiley[ 'image'];
				$smiley[ 'code' ] = str_replace(' ', '', $smiley[ 'code' ]);
				$grins .= '<img src="'.$smiley[ 'image' ].'" title="'.$smiley[ 'code' ].'" alt="'.$smiley[ 'code' ]
									.'" class="top" onclick="textarea_wrap_selection( b2evoCanvas, \''. str_replace("'","\'",$smiley[ 'code' ]). '\', \'\', 1 );" /> ';
			}
		}

		echo '<div class="edit_toolbar">'.$grins.'</div>' ;

		return true;
	}


	/**
	 * Perform rendering
	 *
	 * @see Plugin::FilterCommentContent()
	 */
	function FilterCommentContent( & $params )
	{
		if( $this->Settings->get( 'render_comments' ) )
		{
			$this->RenderItemAsHtml( $params );
		}
	}	
	


	/**
	 * Perform rendering
	 *
	 * @see Plugin::RenderItemAsHtml()
	 */
	function RenderItemAsHtml( & $params )
	{
		$this->InitSmilies();	// check smilies are already cached


		if( ! isset( $this->search ) )
		{	// We haven't prepared the smilies yet
			$this->search = array();

			$tmpsmilies = $this->smilies;
			usort($tmpsmilies, array(&$this, 'smiliescmp'));

			foreach( $tmpsmilies as $smiley )
			{
				$this->search[] = $smiley[ 'code' ];
				$smiley_masked = '';
				for ($i = 0; $i < strlen($smiley[ 'code' ] ); $i++ )
				{
					$smiley_masked .=  '&#'.ord(substr($smiley[ 'code' ], $i, 1)).';';
				}

				// We don't use getimagesize() here until we have a mean
				// to preprocess smilies. It takes up to much time when
				// processing them at display time.
				$this->replace[] = '<img src="'.$smiley[ 'image' ].'" alt="'.$smiley_masked.'" class="middle" />';
			}
		}


		// REPLACE:  But only in non-HTML blocks, totally excluding <CODE>..</CODE> and <PRE>..</PRE>

		$content = & $params['data'];

		// Lazy-check first, using stristr() (stripos() is only available since PHP5):
		if( stristr( $content, '<code' ) !== false || stristr( $content, '<pre' ) !== false )
		{ // Call ReplaceTagSafe() on everything outside <pre></pre> and <code></code>:
			$content = callback_on_non_matching_blocks( $content,
					'~<(code|pre)[^>]*>.*?</\1>~is',
					array( & $this, 'ReplaceTagSafe' ) );
		}
		else
		{ // No CODE or PRE blocks, replace on the whole thing
			$content = $this->ReplaceTagSafe($content);
		}

		return true;
	}


	/**
	 * This callback gets called once after every tags+text chunk
	 * @return string Text with replaced smilies
	 */
	function preg_insert_smilies_callback( $text )
	{
		return str_replace( $this->search, $this->replace, $text );
	}


	/**
	 * Replace smilies in non-HTML-tag portions of the text.
	 * @uses callback_on_non_matching_blocks()
	 */
	function ReplaceTagSafe($text)
	{
		return callback_on_non_matching_blocks( $text, '~<[^>]*>~', array(&$this, 'preg_insert_smilies_callback') );
	}


	/**
	 * sorts the smilies' array by length
	 * this is important if you want :)) to superseede :) for example
	 */
	function smiliescmp($a, $b)
	{
		if( ($diff = strlen( $b[ 'code' ] ) - strlen( $a[ 'code' ] ) ) == 0)
		{
			return strcmp( $a[ 'code' ], $b[ 'code' ] );
		}
		return $diff;
	}


	/**
	 * Initiates the smiley array if not already initiated
	 *
	 * Attempts to use skin specific smileys where available
	 *	- skins_adm/skin/rsc/smilies/
	 *	- skins/skin/smilies/
	 *
	 * Attempts to fallback to default smilies
	 *	- rsc/smilies/
	 *
	 * If no image file found the smiley is not added
	 *
	 * @return array of available smilies( code, image url )
	 */
	function InitSmilies()
	{
		if( isset( $this->smilies ) )
		{ // smilies are already cached
			return;
		}

		global $admin_skin, $adminskins_path, $adminskins_url, $rsc_path, $rsc_url, $skin, $skins_path, $skins_url;

		// set the skin path/url and the default (rsc) path/url
		$currentskin_path = ( is_admin_page() ? $adminskins_path.$admin_skin.'/rsc' : $skins_path.$skin ).'/smilies/';
		$currentskin_url = ( is_admin_page() ? $adminskins_url.$admin_skin.'/rsc' : $skins_url.$skin ).'/smilies/';
		$default_path = $rsc_path.'smilies/';
		$default_url = $rsc_url.'smilies/';

		$skin_has_smilies = is_dir( $currentskin_path );	// check if skin has a /smilies/ folder

		$this->smilies = array();
		$temp_list = explode( "\n", str_replace( array( "\r", "\t" ), '', $this->Settings->get( 'smiley_list' ) ) );

		foreach( $temp_list as $temp_smiley )
		{
			$a_smiley = explode( '<->',	preg_replace_callback( '#^(\S.+?\s)(.+?)(\/\/.*?)*$#', array( $this, 'get_smiley' ),$temp_smiley ) );
			if( isset( $a_smiley[0] ) and isset( $a_smiley[1] ) )
			{
				// lets see if the file exists
				$temp_img = trim( $a_smiley[1] );
				if( $skin_has_smilies && is_file( $currentskin_path.$temp_img ) )
				{
					$temp_url = $currentskin_url.$temp_img;	// skin has it's own smiley, use it
				}
				elseif ( is_file( $default_path.$temp_img ) )
				{
					$temp_url = $default_url.$temp_img; // no skin image, but default smiley found so use it
				}
				else
				{
					$temp_url = ''; // no smiley image found, so don't add the smiley
				}

				if( $temp_url )
					$this->smilies[] = array( 'code' => trim( $a_smiley[0] ),'image' => $temp_url );
			}
		}
	}

	// returns the relevant smiley parts (char_code, image_file)
	function get_smiley( $smiley_parts )
	{
		return ( ( isset( $smiley_parts[1] ) && isset( $smiley_parts[2] ) ) ? $smiley_parts[1].'<->'.$smiley_parts[2] : '' );
	}
}


/*
 * $Log$
 * Revision 1.43  2007/08/12 19:44:41  blueyed
 * Fixed :yes: smilie
 *
 * Revision 1.42  2007/06/18 21:21:57  fplanque
 * doc
 *
 * Revision 1.41  2007/06/16 20:26:44  blueyed
 * doc
 *
 * Revision 1.40  2007/04/26 00:11:04  fplanque
 * (c) 2007
 *
 * Revision 1.39  2007/04/20 01:42:32  fplanque
 * removed excess javascript
 *
 * Revision 1.38  2007/01/15 03:55:22  fplanque
 * lowered priority so there is no conflict with other replacements generating smileys.
 *
 * Revision 1.37  2006/12/28 23:20:40  fplanque
 * added plugin event for displaying comment form toolbars
 * used by smilies plugin
 *
 * Revision 1.31.2.10  2006/12/27 09:41:12  yabs
 * Removed b2evocanvas, minor other changes, clarified doc
 *
 * Revision 1.31.2.9  2006/12/26 03:18:51  fplanque
 * assigned a few significant plugin groups
 *
 * Revision 1.31.2.8  2006/12/16 04:30:59  fplanque
 * doc
 *
 * Revision 1.31.2.7  2006/12/03 08:27:35  yabs
 * Removed user setting - render_comments
 * Changed behaviour - render_comments_default used instead
 * Added skintag to display toolbar ifsmilies in comments enabled - usersetting overrides toolbar default
 * Other minor changes to code
 *
 * Revision 1.31.2.6  2006/11/27 19:12:50  fplanque
 * 1.9 POT has gone public. No more unnecessary changes.
 * 1.9.x language packs should work for ALL 1.9.x versions.
 * Adding strings is okay. Removing strings/changing strings for fun is not OK.
 * Move to 1.10 or 2.0
 *
 * Revision 1.34  2006/11/27 00:28:36  blueyed
 * trans fix
 *
 * Revision 1.33  2006/08/10 09:07:12  yabs
 * minor mods + added note re smilies folder
 *
 * Revision 1.32  2006/08/09 07:33:46  yabs
 * Redid the format of smiley settings, added ability to comment out a smiley and add optional comments to the end of a definition
 *
 * Revision 1.31  2006/08/07 18:26:21  fplanque
 * nuked obsolete smilies conf file
 *
 * Revision 1.30  2006/08/01 23:55:14  blueyed
 * minor: doc; removed level of indentation
 *
 * Revision 1.29  2006/08/01 08:44:31  yabs
 * Minor change - checks if skin has /smilies/ folder before start of checking for images
 *
 * Revision 1.28  2006/08/01 08:20:47  yabs
 * Added ability for admin skins to override default smiley images
 * Tidied up my previous code
 *
 * Revision 1.27  2006/07/31 16:19:04  yabs
 * Moved settings to admin
 * Added smilies in comments ( as user setting )
 * Added ability for blog skins to override default smiley images
 *
 * *note*
 * These changes are a tad quick 'n' dirty, I'm working on a cleaner version and will commit soon
 *
 * Revision 1.26  2006/07/12 21:13:17  blueyed
 * Javascript callback handler (e.g., for interaction of WYSIWYG editors with toolbar plugins)
 *
 * Revision 1.25  2006/07/10 20:19:30  blueyed
 * Fixed PluginInit behaviour. It now gets called on both installed and non-installed Plugins, but with the "is_installed" param appropriately set.
 *
 * Revision 1.24  2006/07/07 21:26:49  blueyed
 * Bumped to 1.9-dev
 *
 * Revision 1.23  2006/07/06 19:56:29  fplanque
 * no message
 *
 * Revision 1.22  2006/06/16 21:30:57  fplanque
 * Started clean numbering of plugin versions (feel free do add dots...)
 *
 * Revision 1.21  2006/05/30 20:26:59  blueyed
 * typo
 *
 * Revision 1.20  2006/05/30 19:39:55  fplanque
 * plugin cleanup
 *
 * Revision 1.19  2006/04/24 20:16:08  blueyed
 * Use callback_on_non_matching_blocks(); excluding PRE and CODE blocks
 *
 * Revision 1.18  2006/04/11 21:22:26  fplanque
 * partial cleanup
 *
 */
?>
