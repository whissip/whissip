<?php
/**
 * This is the HTML header include template.
 *
 * For a quick explanation of b2evo 2.0 skins, please start here:
 * {@link http://manual.b2evolution.net/Skins_2.0}
 *
 * This is meant to be included in a page template.
 * Note: This is also included in the popup: do not include site navigation!
 *
 * @package evoskins
 * @subpackage photoblog
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $app_name, $app_version;

add_js_for_toolbar();		// Registers all the javascripts needed by the toolbar menu

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php locale_lang() ?>" lang="<?php locale_lang() ?>">
<head>
	<?php skin_content_meta(); /* Charset for static pages */ ?>
	<?php skin_base_tag(); /* Base URL for this skin. You need this to fix relative links! */ ?>
	<?php $Plugins->trigger_event( 'SkinBeginHtmlHead' ); ?>
	<title><?php
		// ------------------------- TITLE FOR THE CURRENT REQUEST -------------------------
		request_title( array(
			'auto_pilot'      => 'seo_title',
			'arcdir_text'     => T_('Index'),
			'catdir_text'     => T_('Albums'),
			'category_text'   => T_('Album').': ',
			'categories_text' => T_('Albums').': ',
		) );
		// ------------------------------ END OF REQUEST TITLE -----------------------------
	?></title>
	<?php skin_description_tag(); ?>
	<?php skin_keywords_tag(); ?>
	<?php robots_tag(); ?>
	<meta name="generator" content="<?php echo $app_name.' '.$app_version; ?>" /> <!-- Please leave this for stats -->
	<?php
	if( $Blog->get_setting( 'feed_content' ) != 'none' )
	{ // auto-discovery urls
		?>
	<link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="<?php $Blog->disp( 'rss2_url', 'raw' ) ?>" />
	<link rel="alternate" type="application/atom+xml" title="Atom" href="<?php $Blog->disp( 'atom_url', 'raw' ) ?>" />
		<?php
	}
	require_css(dirname(__FILE__).'/style.css');
	?>
	<?php $Blog->add_custom_css(); ?>
	<?php include_headlines() /* Add javascript and css files included by plugins and skin */ ?>
</head>

<body>

<?php
// ---------------------------- TOOLBAR INCLUDED HERE ----------------------------
require $skins_path.'_toolbar.inc.php';
// ------------------------------- END OF TOOLBAR --------------------------------

echo "\n";
if( is_logged_in() )
{
	echo '<div id="skin_wrapper" class="skin_wrapper_loggedin">';
}
else
{
	echo '<div id="skin_wrapper" class="skin_wrapper_anonymous">';
}
echo "\n";
?>
<!-- Start of skin_wrapper -->