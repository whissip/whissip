<?php
/**
 * This file implements the right sidebar for the comment browsing screen.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}.
*
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * {@internal Open Source relicensing agreement:
 * EVO FACTORY grants Francois PLANQUE the right to license
 * EVO FACTORY contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author asimo: Evo Factory / Attila Simo
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var AdminUI
 */
global $AdminUI;
/**
 * @var Blog
 */
global $Blog;

global $current_User;

global $CommentList;

$pp = $CommentList->param_prefix;

global ${$pp.'show_statuses'}, ${$pp.'expiry_statuses'}, ${$pp.'s'}, ${$pp.'sentence'}, ${$pp.'exact'};
global ${$pp.'rating_toshow'}, ${$pp.'rating_turn'}, ${$pp.'rating_limit'}, ${$pp.'url_match'}, ${$pp.'author_url'}, ${$pp.'include_emptyurl'}, ${$pp.'author_IP'};
global $tab3;

$show_statuses = ${$pp.'show_statuses'};
$expiry_statuses = ${$pp.'expiry_statuses'};
$s = ${$pp.'s'};
$sentence = ${$pp.'sentence'};
$exact = ${$pp.'exact'};
$rating_toshow = ${$pp.'rating_toshow'};
$rating_turn = ${$pp.'rating_turn'};
$rating_limit = ${$pp.'rating_limit'};
$url_match = ${$pp.'url_match'};
$author_url = ${$pp.'author_url'};
$include_emptyurl = ${$pp.'include_emptyurl'};
$author_IP = ${$pp.'author_IP'};

load_funcs( 'skins/_skin.funcs.php' );

$Widget = new Widget();
$template = $AdminUI->get_template( 'side_item' );

$Widget->title = T_('Filters');

echo $Widget->replace_vars( $template['block_start'] );

$Form = new Form( NULL, 'comment_filter_form', 'get', 'none' );

$Form->begin_form( '' );

	$Form->hidden_ctrl();
	$Form->hidden( 'tab3', $tab3 );
	$Form->submit( array( 'submit', T_('Search'), 'search', '', 'float:right' ) );

	echo '<fieldset>';
	echo '<legend>'.T_('Comments to show').'</legend>';

	$exclude_statuses = array_merge( get_restricted_statuses( $Blog->ID, 'blog_comment!' ), array( 'redirected' ) );
	$statuses = get_visibility_statuses( 'notes-array', $exclude_statuses );
	foreach( $statuses as $status_key => $status_name )
	{ // show statuses
		?>
		<input type="checkbox" name="<?php echo $pp ?>show_statuses[]" value="<?php echo $status_key; ?>" id="sh_<?php echo $status_key; ?>" class="checkbox" <?php if( in_array( $status_key, $show_statuses ) ) echo 'checked="checked" '?> />
		<label for="sh_<?php echo $status_key; ?>" title="<?php echo substr( $status_name[1], 1, strlen( $status_name[1] ) - 2 ); ?>"><?php echo $status_name[0] ?></label><br />
		<?php
	}
	?>

	<br />
	<input type="checkbox" name="<?php echo $pp ?>expiry_statuses[]" value="active" id="show_active" class="checkbox" <?php if( in_array( "active", $expiry_statuses ) ) echo 'checked="checked" '?> />
	<label for="show_active"><?php echo T_('Show active') ?> </label><br />
	<input type="checkbox" name="<?php echo $pp ?>expiry_statuses[]" value="expired" id="show_expired" class="checkbox" <?php if( in_array( "expired", $expiry_statuses ) ) echo 'checked="checked" '?> />
	<label for="show_expired"><?php echo T_('Show expired') ?> </label><br />

	<?php
	echo '</fieldset>';
	
	echo '<fieldset>';
	echo '<legend>'.T_('Title / Text contains').'</legend>';

	echo $Form->inputstart;
	?>
	<div><input type="text" name="<?php echo $pp ?>s" size="20" value="<?php echo htmlspecialchars($s) ?>" class="SearchField" /></div>
	<?php
	echo $Form->inputend;
	?>
	<div class="tile">
		<input type="radio" name="<?php echo $pp ?>sentence" value="AND" id="sentAND" class="radio" <?php if( $sentence=='AND' ) echo 'checked="checked" '?> />
		<label for="sentAND"><?php echo T_('AND') ?></label>
	</div>
	<div class="tile">
		<input type="radio" name="<?php echo $pp ?>sentence" value="OR" id="sentOR" class="radio" <?php if( $sentence=='OR' ) echo 'checked="checked" '?> />
		<label for="sentOR"><?php echo T_('OR') ?></label>
	</div>
	<div class="tile">
		<input type="radio" name="<?php echo $pp ?>sentence" value="sentence" id="sentence" class="radio" <?php if( $sentence=='sentence' ) echo 'checked="checked" '?> />
		<label for="sentence"><?php echo T_('Entire phrase') ?></label>
	</div>
	<div class="tile">
		<input type="checkbox" name="<?php echo $pp ?>exact" value="1" id="exact" class="checkbox" <?php if( $exact ) echo 'checked="checked" '?> />
		<label for="exact"><?php echo T_('Exact match') ?></label>
	</div>

	<?php
	echo '</fieldset>';

	echo '<fieldset>';
	echo '<legend>'.T_('Rating').'</legend>';

	?>
	<div class="rating">
		<input type="checkbox" name="<?php echo $pp ?>rating_toshow[]" value="norating" id="rating_ts_norating" class="checkbox" <?php if( isset( $rating_toshow ) && in_array( "norating", $rating_toshow ) ) echo 'checked="checked" '?> />
		<label for="rating_ts_norating"><?php echo T_('No rating') ?> </label><br />
		
		<input type="checkbox" name="<?php echo $pp ?>rating_toshow[]" value="haverating" id="rating_ts_haverating" class="checkbox" <?php if( isset( $rating_toshow ) && in_array( "haverating", $rating_toshow ) ) echo 'checked="checked" '?> />
		<label for="rating_ts_haverating"><?php echo T_('Have rating') ?> </label><br />
	</div>
	<div class="rating">
		<input type="radio" name="<?php echo $pp ?>rating_turn" value="above" id="rating_above" class="radio" <?php if( $rating_turn=='above' ) echo 'checked="checked" '?> />
		<label for="rating_above"><?php echo T_('Above') ?></label>

		<input type="radio" name="<?php echo $pp ?>rating_turn" value="below" id="rating_below" class="radio" <?php if( $rating_turn=='below' ) echo 'checked="checked" '?> />
		<label for="rating_below"><?php echo T_('Below') ?></label><br />

		<input type="radio" name="<?php echo $pp ?>rating_turn" value="exact" id="rating_exact" class="radio" <?php if( $rating_turn=='exact' ) echo 'checked="checked" '?> />
		<label for="rating_norating"><?php echo T_('Exact') ?></label>
	</div>
	<div class="rating">
		<?php
		echo T_('Poor');

		for( $i=1; $i<=5; $i++ )
		{
			echo '<input type="radio" name="'.$pp.'rating_limit" value="'.$i.'" class="radio"';
			if( $rating_limit == $i )
			{
				echo ' checked="checked"';
			}
			echo ' />';
		}

		echo T_('Excellent');
		?>
	</div>

	<?php
	echo '</fieldset>';

	echo '<fieldset>';
	echo '<legend>'.T_('Author URL').'</legend>';

	echo $Form->inputstart;
	?>
	<div><input type="text" name="<?php echo $pp ?>author_url" size="20" value="<?php echo htmlspecialchars($author_url) ?>" class="SearchField" /></div>
	<?php
	echo $Form->inputend;
	?>
	<div>
		<input type="radio" name="<?php echo $pp ?>url_match" value="=" id="with_url" class="radio" <?php if( $url_match=='=' ) echo 'checked="checked" '?> />
		<label for="with_url"><?php echo T_('With this') ?></label>

		<input type="radio" name="<?php echo $pp ?>url_match" value="!=" id="without_url" class="radio" <?php if( $url_match=='!=' ) echo 'checked="checked" '?> />
		<label for="without_url"><?php echo T_('Without this') ?></label>
	</div>
	<div>
		<input type="checkbox" name="<?php echo $pp ?>include_emptyurl" value="true" id="without_any_url" class="checkbox" <?php if( $include_emptyurl ) echo 'checked="checked" '?> />
		<label for="without_any_url"><?php echo T_('Include comments with no url') ?> <span class="notes">(<?php echo T_('Works only when url filter is set') ?>)</span></label><br />
	</div>

	<?php
	echo '</fieldset>';

	echo '<fieldset>';
	echo '<legend>'.T_('IP').'</legend>';
	echo $Form->inputstart;
	?>
	<div><?php echo T_('IP') ?> <input type="text" name="<?php echo $pp ?>author_IP" size="20" value="<?php echo htmlspecialchars($author_IP) ?>" class="SearchField" style="width:85%" /></div>
	<div class="note"><?php echo T_('use % for partial matches') ?></div>
	<?php
	echo $Form->inputend;

	echo '</fieldset>';

	$Form->submit( array( 'submit', T_('Search'), 'search' ) );

$Form->end_form();

echo $template['block_end'];


/*
 * $Log$
 * Revision 1.13  2013/11/06 08:04:07  efy-asimo
 * Update to version 5.0.1-alpha-5
 *
 */
?>