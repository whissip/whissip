<?php
/**
 * This is the main/default page template.
 *
 * For a quick explanation of b2evo 2.0 skins, please start here:
 * {@link http://manual.b2evolution.net/Skins_2.0}
 *
 * It is used to display the blog when no specific page template is available to handle the request.
 *
 * @package evoskins
 * @subpackage dating_mood
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

// This is the main template; it may be used to display very different things.
// Do inits depending on current $disp:
skin_init( $disp );

// -------------------------- HTML HEADER INCLUDED HERE --------------------------
// Initializations:
require_css( 'rsc/nifty_corners.css', true, 'Nifty Corners' );
require_css( 'rsc/nifty_print.css', true, 'Print', 'print' );
require_js( 'rsc/nifty_corners.js', true );
$custom_js = <<<HEREDOC
	<script type="text/javascript">
		<!--
		window.onload=function()
		{
			if(!NiftyCheck())
					return;
			Rounded("div.outerwrap","all","transparent","#fff","");
			Rounded("div.posts","all","transparent","#fff","");
			Rounded("div.bSideBar","all","transparent","#fff","");
			Rounded("div.bTitle","top","#fff","#D79ADC","smooth");
		}
		// -->
	</script>
HEREDOC;
add_headline( $custom_js );

// Include the HTML HEAD:
skin_include( '_html_header.inc.php' );
// Note: You can customize the default HTML header by copying the
// _html_header.inc.php file into the current skin folder.
// -------------------------------- END OF HEADER --------------------------------
?>



<div class="wrapper">

<div class="outerwrap">
<div class="innerwrap">

	<div class="PageTop">
		<?php
			// ------------------------- "Page Top" CONTAINER EMBEDDED HERE --------------------------
			// Display container and contents:
			skin_container( NT_('Page Top'), array(
					// The following params will be used as defaults for widgets included in this container:
					'block_start' => '<div class="$wi_class$">',
					'block_end' => '</div>',
					'block_display_title' => false,
					'list_start' => '<ul>',
					'list_end' => '</ul>',
					'item_start' => '<li>',
					'item_end' => '</li>',
				) );
			// ----------------------------- END OF "Page Top" CONTAINER -----------------------------
		?>
	</div>

	<div class="pageHeader">
		<?php
			// ------------------------- "Header" CONTAINER EMBEDDED HERE --------------------------
			// Display container and contents:
			skin_container( NT_('Header'), array(
					// The following params will be used as defaults for widgets included in this container:
					'block_start' => '<div class="$wi_class$">',
					'block_end' => '</div>',
					'block_title_start' => '<h1>',
					'block_title_end' => '</h1>',
				) );
			// ----------------------------- END OF "Header" CONTAINER -----------------------------
		?>
	</div>

</div>
</div>

</div>

<div class="wrapper">

<div class="posts">
<div class="innerwrap">

<!-- =================================== START OF MAIN AREA =================================== -->

<div class="top_menu">
	<ul>
	<?php
		// ------------------------- "Menu" CONTAINER EMBEDDED HERE --------------------------
		// Display container and contents:
		// Note: this container is designed to be a single <ul> list
		skin_container( NT_('Menu'), array(
				// The following params will be used as defaults for widgets included in this container:
				'block_start' => '',
				'block_end' => '',
				'block_display_title' => false,
				'list_start' => '',
				'list_end' => '',
				'item_start' => '<li>',
				'item_end' => '</li>',
			) );
		// ----------------------------- END OF "Menu" CONTAINER -----------------------------
	?>
	</ul>
</div>

<?php
	// ------------------------- MESSAGES GENERATED FROM ACTIONS -------------------------
	messages( array(
			'block_start' => '<div class="action_messages">',
			'block_end'   => '</div>',
		) );
	// --------------------------------- END OF MESSAGES ---------------------------------
?>

<?php
	// ------------------- PREV/NEXT POST LINKS (SINGLE POST MODE) -------------------
	item_prevnext_links( array(
			'block_start' => '<table class="prevnext_post"><tr>',
			'prev_start'  => '<td>',
			'prev_end'    => '</td>',
			'next_start'  => '<td class="right">',
			'next_end'    => '</td>',
			'block_end'   => '</tr></table>',
		) );
	// ------------------------- END OF PREV/NEXT POST LINKS -------------------------
?>

<?php
	// ------------------------- TITLE FOR THE CURRENT REQUEST -------------------------
	request_title( array(
			'title_before'=> '<h2>',
			'title_after' => '</h2>',
			'title_none'  => '',
			'glue'        => ' - ',
			'title_single_disp' => true,
			'format'      => 'htmlbody',
		) );
	// ------------------------------ END OF REQUEST TITLE -----------------------------
?>

<?php
    // -------------------- PREV/NEXT PAGE LINKS (POST LIST MODE) --------------------
    mainlist_page_links( array(
            'block_start' => '<p class="center">'.T_('Pages:').' <strong>',
            'block_end' => '</strong></p>',
        ) );
    // ------------------------- END OF PREV/NEXT PAGE LINKS -------------------------
?>

<?php
	// ------------------------------------ START OF POSTS ----------------------------------------
	// Display message if no post:
	display_if_empty();

	while( $Item = & mainlist_get_item() )
	{	// For each blog post, do everything below up to the closing curly brace "}"
	?>

	<div id="<?php $Item->anchor_id() ?>" lang="<?php $Item->lang() ?>">

	<?php
		$Item->locale_temp_switch(); // Temporarily switch to post locale (useful for multilingual blogs)
	?>
	<!-- google_ad_section_start -->
	<div class="bTitle"><h3 class="bTitle"><?php $Item->title(); ?></h3></div>
	<!-- google_ad_section_end -->
	<div class="bPost">
		<div class="bSmallHead">
			<?php
			$Item->permanent_link( array(
					'text' => '#icon#',
				) );
			$Item->author( array(
					'before'       => ' '.T_('by').' <strong>',
					'after'        => '</strong>',
					'link_to'		   => 'userpage',
					'link_text'    => 'preferredname',
				) );
			$Item->msgform_link( array(
					'before'    => ' ',
					'after'     => '',
				) );
			$Item->issue_time( array(
					'before'    => ', ',
					'after'     => '',
					'date_format' => 'l j F Y � H:i',
				) );
			$Item->categories( array(
					'before'          => ', '.T_('Categories').': ',
					'after'           => ' ',
					'include_main'    => true,
					'include_other'   => true,
					'include_external'=> true,
					'link_categories' => true,
				) );
			?>
		</div>

		<!-- google_ad_section_start -->
		<?php
			// ---------------------- POST CONTENT INCLUDED HERE ----------------------
			skin_include( '_item_content.inc.php', array(
					'image_size'	=>	'fit-400x320',
				) );
			// Note: You can customize the default item feedback by copying the generic
			// /skins/_item_feedback.inc.php file into the current skin folder.
			// -------------------------- END OF POST CONTENT -------------------------
		?>
		<?php
			// List all tags attached to this post:
			$Item->tags( array(
					'before' =>         '<div class="bSmallPrint">'.T_('Tags').': ',
					'after' =>          '</div>',
					'separator' =>      ', ',
				) );
		?>
		<!-- google_ad_section_end -->

		<div class="bSmallPrint">
			<?php
				$Item->edit_link( array( // Link to backoffice for editing
						'before'    => '',
						'after'     => '',
						'class'     => 'permalink_right'
					) );
			?>

			<?php
				// Link to comments, trackbacks, etc.:
				$Item->feedback_link( array(
								'type' => 'comments',
								'link_before' => ' <span class="bCommentLink">',
								'link_after' => '</span> ',
								'link_text_zero' => '#',
								'link_text_one' => '#',
								'link_text_more' => '#',
								'link_title' => '#',
								'use_popup' => false,
							) );
			?>
			<?php
				// Link to comments, trackbacks, etc.:
				$Item->feedback_link( array(
								'type' => 'trackbacks',
								'link_before' => ' <span class="bCommentLink">',
								'link_after' => '</span> ',
								'link_text_zero' => '#',
								'link_text_one' => '#',
								'link_text_more' => '#',
								'link_title' => '#',
								'use_popup' => false,
							) );
			?>
		</div>

		<?php
			// ------------------ FEEDBACK (COMMENTS/TRACKBACKS) INCLUDED HERE ------------------
			skin_include( '_item_feedback.inc.php', array(
					'before_section_title' => '<h4>',
					'after_section_title'  => '</h4>',
					'link_to' => 'userpage>userurl',
				) );
			// Note: You can customize the default item feedback by copying the generic
			// /skins/_item_feedback.inc.php file into the current skin folder.
			// ---------------------- END OF FEEDBACK (COMMENTS/TRACKBACKS) ---------------------
		?>
	</div>
	</div>
	<?php
	locale_restore_previous();	// Restore previous locale (Blog locale)
	} // ---------------------------------- END OF POSTS ------------------------------------

?>

	<?php
    // -------------------- PREV/NEXT PAGE LINKS (POST LIST MODE) --------------------
    mainlist_page_links( array(
            'block_start' => '<p class="center">'.T_('Pages:').' <strong>',
            'block_end' => '</strong></p>',
        ) );
    // ------------------------- END OF PREV/NEXT PAGE LINKS -------------------------
	?>


	<?php
		// -------------- MAIN CONTENT TEMPLATE INCLUDED HERE (Based on $disp) --------------
		skin_include( '$disp$', array(
				'disp_posts'  => '',		// We already handled this case above
				'disp_single' => '',		// We already handled this case above
				'disp_page'   => '',		// We already handled this case above
			) );
		// Note: you can customize any of the sub templates included here by
		// copying the matching php file into your skin directory.
		// ------------------------- END OF MAIN CONTENT TEMPLATE ---------------------------
	?>
</div>
</div>


<!-- =================================== START OF SIDEBAR =================================== -->
<div class="bSideBar">
<div class="innerwrap">

	<?php
		// ------------------------- "Sidebar" CONTAINER EMBEDDED HERE --------------------------
		skin_container( NT_('Sidebar'), array(
				// The following (optional) params will be used as defaults for widgets included in this container:
				// This will enclose each widget in a block:
				'block_start' => '<div class="bSideItem $wi_class$">',
				'block_end' => '</div>',
				// This will enclose the title of each widget:
				'block_title_start' => '<h3>',
				'block_title_end' => '</h3>',
				// If a widget displays a list, this will enclose that list:
				'list_start' => '<ul>',
				'list_end' => '</ul>',
				// This will enclose each item in a list:
				'item_start' => '<li>',
				'item_end' => '</li>',
				// This will enclose sub-lists in a list:
				'group_start' => '<ul>',
				'group_end' => '</ul>',
				// This will enclose (foot)notes:
				'notes_start' => '<div class="notes">',
				'notes_end' => '</div>',
			) );
		// ----------------------------- END OF "Sidebar" CONTAINER -----------------------------
	?>

	<?php
		// Please help us promote b2evolution and leave this logo on your blog:
		powered_by( array(
				'block_start' => '<div class="powered_by">',
				'block_end'   => '</div>',
				// Check /rsc/img/ for other possible images -- Don't forget to change or remove width & height too
				'img_url'     => '$rsc$img/powered-by-b2evolution-120t.gif',
				'img_width'   => 120,
				'img_height'  => 32,
			) );
	?>

</div>
</div>

<div class="clear"><img src="<?php echo $rsc_url; ?>img/blank.gif" width="1" height="1" alt="" /></div>

<div id="pageFooter">
	<?php
		// Display container and contents:
		skin_container( NT_("Footer"), array(
				// The following params will be used as defaults for widgets included in this container:
			) );
		// Note: Double quotes have been used around "Footer" only for test purposes.
	?>
	<p class="baseline">
		<?php
			// Display footer text (text can be edited in Blog Settings):
			$Blog->footer_text( array(
					'before'      => '',
					'after'       => ' &bull; ',
				) );
		?>

		<?php
			// Display a link to contact the owner of this blog (if owner accepts messages):
			$Blog->contact_link( array(
					'before'      => '',
					'after'       => ' &bull; ',
					'text'   => T_('Contact'),
					'title'  => T_('Send a message to the owner of this blog...'),
				) );
			// Display a link to help page:
			$Blog->help_link( array(
					'before'      => ' ',
					'after'       => ' &bull; ',
					'text'        => T_('Help'),
				) );
		?>

		<?php $Skin->display_skin_credits(); ?>

		<?php
			// Display additional credits:
 			// If you can add your own credits without removing the defaults, you'll be very cool :))
		 	// Please leave this at the bottom of the page to make sure your blog gets listed on b2evolution.net
			credits( array(
					'list_start'  => '&bull;',
					'list_end'    => ' ',
					'separator'   => '&bull;',
					'item_start'  => ' ',
					'item_end'    => ' ',
				) );
		?>
	</p>
</div>
</div>

<?php
// ------------------------- HTML FOOTER INCLUDED HERE --------------------------
skin_include( '_html_footer.inc.php' );
// Note: You can customize the default HTML footer by copying the
// _html_footer.inc.php file into the current skin folder.
// ------------------------------- END OF FOOTER --------------------------------
?>