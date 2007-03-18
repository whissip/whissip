<?php
/**
 * This template generates an RSS 2.0 feed for the requested blog's latest comments
 *
 * See {@link http://backend.userland.com/rss}
 *
 * This file is not meant to be called directly.
 * It is meant to be called automagically by the main template (_main.php).
 *
 * This file is part of the b2evolution project - {@link http://b2evolution.net/}
 *
 * @copyright (c)2003-2006 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * {@internal Open Source relicensing agreement:
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evoskins
 * @subpackage rss
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER.
 * @author fplanque: Francois PLANQUE - {@link http://fplanque.net/}
 * }}
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

$CommentList = & new CommentList( $Blog, "'comment'", array('published'), '',	'',	'DESC',	'',	$Blog->get_setting('posts_per_feed') );

skin_content_header( 'application/xml' );	// Sets charset!

echo '<?xml version="1.0" encoding="'.$io_charset.'"?'.'>';

?>
<!-- generator="<?php echo $app_name ?>/<?php echo $app_version ?>" -->
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:admin="http://webns.net/mvcb/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:content="http://purl.org/rss/1.0/modules/content/">
	<channel>
		<title><?php
			$Blog->disp( 'name', 'xml' );
			request_title( ' - ', '', ' - ', 'xml' );
		?></title>
		<link><?php $Blog->disp( 'lastcommentsurl', 'xml' ) ?></link>
		<description></description>
		<language><?php $Blog->disp( 'locale', 'xml' ) ?></language>
		<docs>http://backend.userland.com/rss</docs>
		<admin:generatorAgent rdf:resource="http://b2evolution.net/?v=<?php echo $app_version ?>"/>
		<ttl>60</ttl>
		<?php while( $Comment = & $CommentList->get_next() )
		{ // Loop through comments:
			// Load comment's Item:
			$Comment->get_Item();
			?>
		<item>
			<title><?php echo format_to_output( T_('In response to:'), 'xml' ) ?> <?php $Comment->Item->title( '', '', false, 'xml' ) ?></title>
			<pubDate><?php $Comment->time( 'r', true ); ?></pubDate>
			<dc:creator><?php $Comment->author( '', '#', '', '#', 'xml' ); ?></dc:creator>
			<guid isPermaLink="false">c<?php $Comment->ID() ?>@<?php echo $baseurl ?></guid>
			<description><?php echo make_rel_links_abs( $Comment->get_content('entityencoded') ); ?></description>
			<content:encoded><![CDATA[<?php echo make_rel_links_abs( $Comment->get_content() ); ?>]]></content:encoded>
			<link><?php $Comment->permanent_url() ?></link>
		</item>
		<?php
		} /* End of comment loop. */
	?>
	</channel>
</rss>
<?php
	$Hit->log(); // log the hit on this page

	// This is a self contained XML document, make sure there is no additional output:
	exit();
?>