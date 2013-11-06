<?php
/**
 * This is the main/default page template for the "custom" skin.
 *
 * This skin only uses one single template which includes most of its features.
 * It will also rely on default includes for specific dispays (like the comment form).
 *
 * For a quick explanation of b2evo 2.0 skins, please start here:
 * {@link http://manual.b2evolution.net/Skins_2.0}
 *
 * The main page template is used to display the blog when no specific page template is available
 * to handle the request (based on $disp).
 *
 * @package evoskins
 * @subpackage terrafirma
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

if( version_compare( $app_version, '3.0' ) < 0 )
{ // Older skins (versions 2.x and above) should work on newer b2evo versions, but newer skins may not work on older b2evo versions.
	die( 'This skin is designed for b2evolution 3.0 and above. Please <a href="http://b2evolution.net/downloads/index.html">upgrade your b2evolution</a>.' );
}

// This is the main template; it may be used to display very different things.
// Do inits depending on current $disp:
skin_init( $disp );


// -------------------------- HTML HEADER INCLUDED HERE --------------------------
skin_include( '_html_header.inc.php' );
// Note: You can customize the default HTML header by copying the generic
// /skins/_html_header.inc.php file into the current skin folder.
// -------------------------------- END OF HEADER --------------------------------
?>
<div id="wrapper">
	<div id="upbg"></div>
	<div id="inner">

		<div class="pageHeader">
			<?php
				// ------------------------- "Header" CONTAINER EMBEDDED HERE --------------------------
				// Display container and contents:
				skin_container( NT_('Header'), array(
						// The following params will be used as defaults for widgets included in this container:
						'block_start'       => '<div class="$wi_class$">',
						'block_end'         => '</div>',
						'block_title_start' => '<h1>',
						'block_title_end'   => '</h1>',
					) );
				// ----------------------------- END OF "Header" CONTAINER -----------------------------
			?>
		</div>
		<div id="splash">
			<div class="PageTop">
			<?php
				// ------------------------- "Page Top" CONTAINER EMBEDDED HERE --------------------------
				// Display container and contents:
				skin_container( NT_('Page Top'), array(
						// The following params will be used as defaults for widgets included in this container:
						'block_start'         => '<div class="$wi_class$">',
						'block_end'           => '</div>',
						'block_display_title' => false,
						'list_start'          => '<ul>',
						'list_end'            => '</ul>',
						'item_start'          => '<li>',
						'item_end'            => '</li>',
					) );
				// ----------------------------- END OF "Page Top" CONTAINER -----------------------------
			?>
			</div>
		</div>
			<div class="top_menu">
				<ul>
				<?php
					// ------------------------- "Menu" CONTAINER EMBEDDED HERE --------------------------
					// Display container and contents:
					// Note: this container is designed to be a single <ul> list
					skin_container( NT_('Menu'), array(
							// The following params will be used as defaults for widgets included in this container:
							'block_start'         => '',
							'block_end'           => '',
							'block_display_title' => false,
							'list_start'          => '',
							'list_end'            => '',
							'item_start'          => '<li>',
							'item_end'            => '</li>',
						) );
					// ----------------------------- END OF "Menu" CONTAINER -----------------------------
				?>
				</ul>
				<?php if ( true /* change to false to hide the search box */ ) { ?>
				<div id="search">
				Find<br />
				<form action="<?php $Blog->gen_blogurl() ?>" method="get" class="search">
				<input name="s" size="15" value="" class="form_text_input" type="text" />&nbsp;<input name="submit" class="searchsubmit" value="Go" type="submit" /></form></div>
				<?php } ?>
				<div class="clear"></div>
			</div>


			<!-- =================================== START OF MAIN AREA =================================== -->
			<div class="bPosts">

				<?php
					// ------------------------- MESSAGES GENERATED FROM ACTIONS -------------------------
					messages( array(
							'block_start' => '<div class="action_messages">',
							'block_end'   => '</div>',
						) );
					// --------------------------------- END OF MESSAGES ---------------------------------
				?>

				<?php
					// ------------------------ TITLE FOR THE CURRENT REQUEST ------------------------
					if ($disp != 'page')
					{
						request_title( array(
								'title_before'=> '<h2 class="pagetitle">',
								'title_after' => '</h2>',
								'title_none'  => '',
								'glue'        => ' - ',
								'title_single_disp' => false,
								'format'      => 'htmlbody',
							) );
					}
					// ----------------------------- END OF REQUEST TITLE ----------------------------
				?>

				<?php
				// Go Grab the featured post:
				if( $Item = & get_featured_Item() )
				{	// We have a featured/intro post to display:
					// ---------------------- ITEM BLOCK INCLUDED HERE ------------------------
					skin_include( '_item_block.inc.php', array(
							'feature_block' => true,
							'content_mode' => 'auto',		// 'auto' will auto select depending on $disp-detail
							'intro_mode'   => 'normal',	// Intro posts will be displayed in normal mode
							'item_class'   => 'bPost featured_post',
							'image_size'	 =>	'fit-400x320',
						) );
					// ----------------------------END ITEM BLOCK  ----------------------------
				}
				?>

				<?php
					// --------------------------------- START OF POSTS -------------------------------------
					// Display message if no post:
					display_if_empty();

					echo '<div id="styled_content_block">'; // Beginning of posts display
					while( $Item = & mainlist_get_item() )
					{	// For each blog post:
						// ---------------------- ITEM BLOCK INCLUDED HERE ------------------------
						skin_include( '_item_block.inc.php', array(
								'content_mode' => 'auto',		// 'auto' will auto select depending on $disp-detail
								'image_size'	 =>	'fit-400x320',
							) );
						// ----------------------------END ITEM BLOCK  ----------------------------
					} // ---------------------------------- END OF POSTS ------------------------------------
					echo '</div>'; // End of posts display
				?>

				<?php
					// -------------------- PREV/NEXT PAGE LINKS (POST LIST MODE) --------------------
					mainlist_page_links( array(
							'block_start' => '<p class="center"><strong>',
							'block_end' => '</strong></p>',
						'prev_text' => '&lt;&lt;',
						'next_text' => '&gt;&gt;',
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


			<!-- =================================== START OF SIDEBAR =================================== -->
			<div class="bSideBar">

				<?php
					// ------------------------- "Sidebar" CONTAINER EMBEDDED HERE --------------------------
					// Display container contents:
					skin_container( NT_('Sidebar'), array(
							// The following (optional) params will be used as defaults for widgets included in this container:
							// This will enclose each widget in a block:
							'block_start' => '<div class="bSideItem $wi_class$">',
							'block_end' => '</div>',
							// This will enclose the title of each widget:
							'block_title_start' => '<h2>',
							'block_title_end' => '</h2>',
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


			<!-- =================================== START OF FOOTER =================================== -->
			<div id="pageFooter">
				<?php
					// Display container and contents:
					skin_container( NT_("Footer"), array(
							// The following params will be used as defaults for widgets included in this container:
						) );
					// Note: Double quotes have been used around "Footer" only for test purposes.
				?>
				<p class="baseline">
					<span class="author_credits">
					<?php
						// Display a link to contact the owner of this blog (if owner accepts messages):
						$Blog->contact_link( array(
								'before'      => '',
								'after'       => '',
								'text'   => T_('Contact'),
								'title'  => T_('Send a message to the owner of this blog...'),
							) );
						// Display a link to help page:
						$Blog->help_link( array(
								'before'      => ' &bull; ',
								'after'       => ' ',
								'text'        => T_('Help'),
							) );
					?>
					<?php
						// Display footer text (text can be edited in Blog Settings):
						$Blog->footer_text( array(
								'before'      => ' &bull; ',
								'after'       => '',
							) );
					?>
					</span>

					<br />Design: <a href="http://www.nodethirtythree.com">Node33</a> | <a href="http://wpthemepark.com/themes/terrafirma/">Sadish Bala</a> | <?php display_param_link( $skinfaktory_links ) ?>

					<?php
						// Display additional credits:
						// If you can add your own credits without removing the defaults, you'll be very cool :))
						// Please leave this at the bottom of the page to make sure your blog gets listed on b2evolution.net
						credits( array(
								'list_start'  => '|',
								'list_end'    => ' ',
								'separator'   => '|',
								'item_start'  => ' ',
								'item_end'    => ' ',
							) );
					?>
				</p>
		</div>
	</div>
</div>

<?php
// ------------------------- HTML FOOTER INCLUDED HERE --------------------------
skin_include( '_html_footer.inc.php' );
// Note: You can customize the default HTML footer by copying the
// _html_footer.inc.php file into the current skin folder.
// ------------------------------- END OF FOOTER --------------------------------
?>