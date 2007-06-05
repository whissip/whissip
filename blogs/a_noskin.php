<?php
/**
 * This file will display a blog, WITHOUT using skins.
 *
 * This file will set some display parameters and then display the blog in a template.
 *
 * Note: You only need to use this file for advanced use/customization of b2evolution.
 * Most of the time, calling your blog through index.php with a skin will be enough.
 * You should try to customize a skin before thrying to use this fle.
 *
 * Same display without using skins: a_stub.php
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package evoskins
 * @subpackage noskin
 */

# First, select which blog you want to display here!
# You can find these numbers in the back-office under the Blogs section.
# You can also create new blogs over there. If you do, you may duplicate this file for the new blog.
$blog = 1;

# Tell b2evolution you don't want to use evoSkins for this template:
$skin = '';

# This setting retricts posts to those published, thus hiding drafts.
# You should not have to change this.
$show_statuses = array();

# You could *force* a specific link blog here with this setting: (otherwise, default will be used)
# $linkblog = 4;

# This is the list of categories to restrict the linkblog to (cats will be displayed recursively)
# Example: $linkblog_cat = '4,6,7';
$linkblog_cat = '';

# This is the array if categories to restrict the linkblog to (non recursive)
# Example: $linkblog_catsel = array( 4, 6, 7 );
$linkblog_catsel = array( );

# Here you can set a limit before which posts will be ignored
# You can use a unix timestamp value or 'now' which will hide all posts in the past
$timestamp_min = '';

# Here you can set a limit after which posts will be ignored
# You can use a unix timestamp value or 'now' which will hide all posts in the future
$timestamp_max = 'now';

# Additionnaly, you can set other values (see URL params in the manual)...
# $order = 'ASC'; // This for example would display the blog in chronological order...

/**
 * Let b2evolution handle the query string and load the blog data:
 */
require_once dirname(__FILE__).'/conf/_config.php';

require $inc_path.'_blog_main.inc.php';

// Make sure includes will check in the current folder!
$ads_current_skin_path = dirname(__FILE__).'/';


# Now, below you'll find the main template...


// This is the main template; it may be used to display very different things.
// Do inits depending on current $disp:
skin_init( $disp );

header( 'Content-type: text/html; charset='.$io_charset );
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php locale_lang() ?>" lang="<?php locale_lang() ?>"><!-- InstanceBegin template="/Templates/Standard.dwt" codeOutsideHTMLIsLocked="false" -->
<head>
<!-- InstanceBeginEditable name="doctitle" -->
	<title><?php
		$Blog->disp('name', 'htmlhead');
		request_title( ' - ', '', ' - ', 'htmlhead' );
	?></title>
<!-- InstanceEndEditable -->
<!-- InstanceBeginEditable name="head" -->
<?php skin_content_meta(); /* Charset for static pages */ ?>
<?php $Plugins->trigger_event( 'SkinBeginHtmlHead' ); ?>
<?php skin_base_tag(); /* You're not using any skin here but this won't hurt. However it will be very helpfull to have this here when you make the switch to a skin! */ ?>
<meta name="description" content="<?php $Blog->disp( 'shortdesc', 'htmlattr' ); ?>" />
<meta name="keywords" content="<?php $Blog->disp( 'keywords', 'htmlattr' ); ?>" />
<meta name="generator" content="b2evolution <?php echo $app_version ?>" /> <!-- Please leave this for stats -->
<link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="<?php $Blog->disp( 'rss2_url', 'raw' ) ?>" />
<link rel="alternate" type="application/atom+xml" title="Atom" href="<?php $Blog->disp( 'atom_url', 'raw' ) ?>" />
<!-- InstanceEndEditable -->
<link rel="stylesheet" href="rsc/css/fp02.css" type="text/css" />
</head>
<body>
<div class="pageHeader">
<div class="pageHeaderContent">

<!-- InstanceBeginEditable name="NavBar2" -->
<?php
	// ---------------------------- BLOG LIST INSERTED HERE ------------------------------

	load_class( 'MODEL/collections/_componentwidget.class.php' );

	$colls_list_ComponentWidget = & new ComponentWidget( NULL, 'core', 'colls_list_public' );

	$colls_list_ComponentWidget->display( array(
						'block_start' => '<div class="NavBar">',
						'block_end' => '</div>',
						'block_display_title' => false,
						'list_start' => '',
						'list_end' => '',
						'item_start' => '',
						'item_end' => '',
						'item_selected_start' => '',
						'item_selected_end' => '',
						'link_selected_class' => 'NavButton2',
						'link_default_class' => 'NavButton2',
				) );

	// ---------------------------------- END OF BLOG LIST ---------------------------------
?>
<!-- InstanceEndEditable -->

<div class="NavBar">
<div class="pageTitle">
<h1 id="pageTitle"><!-- InstanceBeginEditable name="PageTitle" --><?php $Blog->disp( 'name', 'htmlbody' ) ?><!-- InstanceEndEditable --></h1>
</div>
</div>

<div class="pageHeaderEnd"></div>

</div>
</div>


<div class="pageSubTitle"><!-- InstanceBeginEditable name="SubTitle" --><?php $Blog->disp( 'tagline', 'htmlbody' ) ?><!-- InstanceEndEditable --></div>


<div class="main"><!-- InstanceBeginEditable name="Main" -->
<div class="bPosts">
<?php request_title( '<h2>', '</h2>' ) ?>

<!-- =================================== START OF MAIN AREA =================================== -->

<?php // ------------------------------------ START OF POSTS ----------------------------------------
	if( isset($MainList) ) $MainList->display_if_empty(); // Display message if no post

	if( isset($MainList) ) while( $Item = & $MainList->get_item() )
	{
	$MainList->date_if_changed( '<h2>', '</h2>', '' );
	?>
	<div class="bPost" lang="<?php $Item->lang() ?>">
		<?php $Item->anchor(); ?>
		<div class="bSmallHead">
		<?php $Item->permanent_link( '#icon#' ); ?>
		<?php $Item->issue_time();	echo ', ', T_('Categories'), ': ';	$Item->categories() ?>
		</div>
		<h3 class="bTitle"><?php $Item->title(); ?></h3>
		<div class="bText">
			<?php
				// Increment view count of first post on page:
				$Item->count_view( false );

				// Display CONTENT:
				$Item->content_teaser( array(
						'before'      => '',
						'after'       => '',
					) );
				$Item->more_link();
				$Item->content_extension( array(
						'before'      => '',
						'after'       => '',
					) );

				// Links to post pages (for multipage posts):
				$Item->page_links( '<p class="right">'.T_('Pages:').' ', '</p>', ' &middot; ' );
			?>
		</div>
		<div class="bSmallPrint">
			<?php $Item->feedback_link( 'comments', '', ' &bull; ' ) // Link to comments ?>
			<?php $Item->feedback_link( 'trackbacks', '', ' &bull; ' ) // Link to trackbacks ?>

			<?php $Item->trackback_rdf() // trackback autodiscovery information ?>

			<?php	$Item->permanent_link(); ?>
		</div>
		<?php // ---------------- START OF INCLUDE FOR COMMENTS, TRACKBACK, PINGBACK, ETC. ----------------
		$disp_comments = 1;         // Display the comments if requested
		$disp_comment_form = 1;     // Display the comments form if comments requested
		$disp_trackbacks = 1;       // Display the trackbacks if requested
		$disp_trackback_url = 1;    // Display the trackbal URL if trackbacks requested
		$disp_pingbacks = 0;        // Don't display the pingbacks (deprecated)
		require( $skins_path.'_feedback.php');
		// ------------------- END OF INCLUDE FOR COMMENTS, TRACKBACK, PINGBACK, ETC. ------------------- ?>
	</div>
<?php } // ---------------------------------- END OF POSTS ------------------------------------ ?>

	<?php
		// Links to list pages:
		if( isset($MainList) ) $MainList->page_links( '<p class="center"><strong>', '</strong></p>', '$prev$ :: $next$', array(
   			'prev_text' => '&lt;&lt; '.T_('Previous'),
   			'next_text' => T_('Next').' &gt;&gt;',
			) );
	?>

	<?php
		// -------------- START OF INCLUDES FOR LATEST COMMENTS, MY PROFILE, ETC. --------------
		// Note: you can customize any of the sub templates included here by
		// copying the matching php file into the same directory as this file.
		// Call the dispatcher:
		require $skins_path.'_dispatch.inc.php';
		// --------------- END OF INCLUDES FOR LATEST COMMENTS, MY PROFILE, ETC. ---------------
	?>

</div>

<!-- =================================== START OF SIDEBAR =================================== -->

<div class="bSideBar">

	<div class="bSideItem">
		<h3><?php $Blog->disp( 'name', 'htmlbody' ) ?></h3>
		<p><?php $Blog->disp( 'longdesc', 'htmlbody' ); ?></p>
		<?php
			// Links to list pages:
			if( isset($MainList) ) $MainList->page_links( '<p class="center"><strong>', '</strong></p>', '$prev$ :: $next$', array(
   				'prev_text' => '&lt;&lt; '.T_('Previous'),
   				'next_text' => T_('Next').' &gt;&gt;',
				) );
		?>
		<ul>
			<li><a href="<?php $Blog->disp( 'url', 'raw' ) ?>"><strong><?php echo T_('Recently') ?></strong></a></li>
			<li><a href="<?php $Blog->disp( 'lastcommentsurl', 'raw' ) ?>"><strong><?php echo T_('Latest comments') ?></strong></a></li>
		</ul>
		<?php // -------------------------- CALENDAR INCLUDED HERE -----------------------------
			// Call the Calendar plugin:
			$Plugins->call_by_code( 'evo_Calr', array(	// Params follow:
					'block_start'=>'',
					'block_end'=>'',
					'title'=>'',			// No title.
				) );
			// -------------------------------- END OF CALENDAR ---------------------------------- ?>
	</div>

	<div class="bSideItem">
		<h3 class="sideItemTitle"><?php echo T_('Search') ?></h3>
		<?php form_formstart( $Blog->dget( 'blogurl', 'raw' ), 'search', 'searchform' ) ?>
				<input type="text" name="s" size="30" value="<?php echo htmlspecialchars($s) ?>" class="s1" />
				<input type="radio" name="sentence" value="AND" id="sentAND" <?php if( $sentence=='AND' ) echo 'checked="checked" ' ?>/><label for="sentAND"><?php echo T_('All Words') ?></label>
				<input type="radio" name="sentence" value="OR" id="sentOR" <?php if( $sentence=='OR' ) echo 'checked="checked" ' ?>/><label for="sentOR"><?php echo T_('Some Word') ?></label>
				<input type="radio" name="sentence" value="sentence" id="sentence" <?php if( $sentence=='sentence' ) echo 'checked="checked" ' ?>/><label for="sentence"><?php echo T_('Entire phrase') ?></label>
			<input type="submit" name="submit" value="<?php echo T_('Search') ?>" />
			<input type="reset" value="<?php echo T_('Reset form') ?>" />
		</form>
	</div>

	<div class="bSideItem">
		<h3><?php echo T_('Categories') ?></h3>
		<?php form_formstart( $Blog->dget( 'blogurl', 'raw' ) ) ?>
		<?php // -------------------------- CATEGORIES INCLUDED HERE -----------------------------
			require( $skins_path.'_categories.php');
			// -------------------------------- END OF CATEGORIES ---------------------------------- ?>
		<br />
		<input type="submit" value="<?php echo T_('Get selection') ?>" />
		<input type="reset" value="<?php echo T_('Reset form') ?>" />
		</form>
	</div>

	<div class="bSideItem">
		<h3><?php echo T_('Archives') ?></h3>
		<ul>
			<?php
				// -------------------------- ARCHIVES INSERTED HERE -----------------------------
				// Call the Archives plugin WITH NO MORE LINK AND NO LIST DELIMITERS:
				$Plugins->call_by_code( 'evo_Arch', array(
						'title'=>'',
						'block_start'=>'',
						'block_end'=>'',
						'limit'=>12,
						'more_link'=>'',
						'list_start'=>'',
						'list_end'=>'',
						'line_start'=>'<li>',
						'line_end'=>'</li>',
						'day_date_format'=>'',
					) );
				// ------------------------------ END OF ARCHIVES --------------------------------
			?>
				<li><a href="<?php $Blog->disp( 'arcdirurl', 'raw' ) ?>"><?php echo T_('more...') ?></a></li>
		</ul>
	</div>

	<?php // -------------------------- LINKBLOG INCLUDED HERE -----------------------------
		require( $skins_path.'_linkblog.php' );
		// -------------------------------- END OF LINKBLOG ---------------------------------- ?>


	<div class="bSideItem">
		<h3><?php echo T_('Misc') ?></h3>
		<ul>
			<?php
				// Administrative links:
				user_login_link( '<li>', '</li>' );
				user_register_link( '<li>', '</li>' );
				user_admin_link( '<li>', '</li>' );
				user_profile_link( '<li>', '</li>' );
				user_subs_link( '<li>', '</li>' );
				user_logout_link( '<li>', '</li>' );
			?>
		</ul>
	</div>

	<div class="bSideItem">
		<h3><?php echo T_('Syndicate this blog') ?> <img src="<?php echo $rsc_url ?>img/xml.gif" alt="XML" width="36" height="14" class="middle" /></h3>


			<ul>
				<li><a href="<?php $Blog->disp( 'rss_url', 'raw' ) ?>">RSS 0.92 (Userland)</a></li>
				<li><a href="<?php $Blog->disp( 'rdf_url', 'raw' ) ?>">RSS 1.0 (RDF)</a></li>
				<li><a href="<?php $Blog->disp( 'rss2_url', 'raw' ) ?>">RSS 2.0 (Userland)</a></li>
				<li><a href="<?php $Blog->disp( 'atom_url', 'raw' ) ?>">Atom 0.3</a></li>
			</ul>
			<a href="http://webreference.fr/2006/08/30/rss_atom_xml" title="External - English">What is RSS?</a>

	</div>

	<p class="center">powered by<br />
	<a href="http://b2evolution.net/" title="b2evolution home"><img src="<?php echo $rsc_url ?>img/b2evolution_button.png" alt="b2evolution" width="80" height="15" border="0" class="middle" /></a></p>

</div>
<!-- InstanceEndEditable --></div>
<table cellspacing="3" class="wide">
  <tr>
  <td class="cartouche">Original page design by <a href="http://fplanque.net/">Fran&ccedil;ois PLANQUE</a> </td>

	<td class="cartouche" align="right"> <a href="http://b2evolution.net/" title="b2evolution home"><img src="img/b2evolution_button.png" alt="b2evolution" width="80" height="15" class="middle" /></a></td>
  </tr>
</table>
<p class="baseline"><!-- InstanceBeginEditable name="Baseline" -->
<?php
	$Hit->log();  // log the hit on this page
	debug_info(); // output debug info if requested
?>
<!-- InstanceEndEditable --></p>
</body>
<!-- InstanceEnd --></html>