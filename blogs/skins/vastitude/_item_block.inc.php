<?php
/**
 * This is the template that displays the item block
 *
 * This file is not meant to be called directly.
 * It is meant to be called by an include in the main.page.php template (or other templates)
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package evoskins
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $Item;

// Default params:
$params = array_merge( array(
		'feature_block'   => false,
		'content_mode'    => 'auto',		// 'auto' will auto select depending on $disp-detail
		'item_class'      => 'bPost',
		'image_size'	    => 'fit-400x320',
	), $params );

?>

<div id="<?php $Item->anchor_id() ?>" class="<?php $Item->div_classes( $params ) ?>" lang="<?php $Item->lang() ?>">

	<?php
		$Item->locale_temp_switch(); // Temporarily switch to post locale (useful for multilingual blogs)
	?>

	<div class="post_date">
		<?php
			$Item->issue_time( array( 'time_format' => 'F jS, Y', ) );
		?>
	</div>

	<div class="head">
		<h2><?php $Item->title(); ?></h2>
		<div class="bSmallHead">
			<div class="bSmallHeadMisc">
				<?php
					$Item->author( array(
							'before'	=> T_('Written by:').'<strong>',
							'after'		=> '</strong>',
						) );
					echo '<br /> ';
					echo ' Published on ';
					$Item->issue_time(array('time_format' => 'F jS, Y',) );
					echo ' @ ';
					$Item->issue_time();
					echo ', using ';
					$Item->wordcount();
					echo ' '.T_('words');
					echo ', ';
					$Item->views();
				?>
			</div>
			<div class="bSmallHeadCats">
				<?php
					$Item->categories( array(
						'before'		=>	T_('Posted in').' ',
						'after'			=>	' ',
						'include_main'		=>	true,
						'include_other'		=>	true,
						'include_external'	=>	true,
						'link_categories'	=>	true,
						) );
				?>
			</div>
		</div><!-- END SMALLHEAD DIV -->
	</div><!-- END HEAD DIV -->
	<?php
		// ---------------------- POST CONTENT INCLUDED HERE ----------------------
		skin_include( '_item_content.inc.php', $params );
	?>
	<div class="bSmallPrint">
		<?php
			// List all tags attached to this post:
			$Item->tags( array(
				'before'	=>	'<div class="posttags">'.T_('Tags').': ',
				'after'		=>	'</div>',
				'separator'	=>	', ',
				) );
		?>
		<?php
			$Item->permanent_link( array(
				'class'		=>	'permalink_right'
				) );
			$Item->feedback_link( array(
				'type'		=>	'comments',
				'link_before'	=>	'',
				'link_after'	=>	'',
				'link_text_zero'=>	'#',
				'link_text_one'	=>	'#',
				'link_text_more'=>	'#',
				'link_title'	=>	'#',
				'use_popup'	=>	false,
				) );
			$Item->feedback_link( array(
				'type'		=>	'trackbacks',
				'link_before'	=>	' &bull; ',
				'link_after'	=>	'',
				'link_text_zero'=>	'#',
				'link_text_one'	=>	'#',
				'link_text_more'=>	'#',
				'link_title'	=>	'#',
				'use_popup'	=>	false,
				) );
			$Item->edit_link( array( // Link to backoffice for editing
				'before'	=>	' &nbsp; ',
				'after'		=>	'',
				) );
		?>
	</div> <!-- END bSmallPrint DIV -->
	<?php
		// ------------------ FEEDBACK (COMMENTS/TRACKBACKS) INCLUDED HERE ------------------
		skin_include( '_item_feedback.inc.php', array(
		'before_section_title'	=>	'<h4>',
		'after_section_title'	=>	'</h4>',
		) );
		// Note: You can customize the default item feedback by copying the generic
		// /skins/_item_feedback.inc.php file into the current skin folder.
		// ---------------------- END OF FEEDBACK (COMMENTS/TRACKBACKS) ---------------------
	?>

	<?php
		locale_restore_previous();	// Restore previous locale (Blog locale)
	?>

</div>
<?php
/*
 * $Log$
 * Revision 1.3  2010/02/08 17:56:45  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.2  2009/06/02 14:16:02  sam2kb
 * minor
 *
 * Revision 1.1  2009/05/23 14:12:42  fplanque
 * All default skins now support featured posts and intro posts.
 *
 */
?>
