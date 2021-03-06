<?php
/**
 * This is an alternative index.php file.
 * This one is optimized for a multiblog setup where each blog can be identified by its URL.
 * This file will ignore any ?blog= parameter.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * {@internal Note: we need at least one file in the main package}}
 *
 * @package main
 */

/**
 * First thing: Do the minimal initializations required for b2evo:
 */
require_once dirname(__FILE__).'/conf/_config.php';

require_once $inc_path.'_main.inc.php';

$BlogCache = & get_BlogCache();

if( preg_match( '#^(.+?)index.php/([^/]+)#', $ReqHost.$ReqPath, $matches ) )
{ // We have an URL blog name:
	$Debuglog->add( 'Found a potential URL blog name: '.$matches[2], 'detectblog' );
	if( (($Blog = & $BlogCache->get_by_urlname( $matches[2], false )) !== false) )
	{ // We found a matching blog:
		$blog = $Blog->ID;
	}
}

if( empty($blog) )
{ // No blog identified by URL name, let's try to match the absolute URL
	if( preg_match( '#^(.+?)index.php#', $ReqHost.$ReqPath, $matches ) )
	{ // Remove what's not part of the absolute URL
		$ReqAbsUrl = $matches[1];
	}
	else
	{
		$ReqAbsUrl = $ReqHost.$ReqPath;
	}
	$Debuglog->add( 'Looking up absolute url : '.$ReqAbsUrl, 'detectblog' );

	if( (($Blog = & $BlogCache->get_by_url( $ReqAbsUrl, false )) !== false) )
	{ // We found a matching blog:
		$blog = $Blog->ID;
		$Debuglog->add( 'Found matching blog: '.$blog, 'detectblog' );
	}
}

if( empty($blog) )
{ // Still no blog requested, use default
	$blog = $Settings->get('default_blog_ID');
	$Debuglog->add( 'Using default blog '.$blog, 'detectblog' );
}

if( empty($blog) )
{ // No specific blog to be displayed:
	echo 'No default blog is set.';
	exit();
}

// Memorize that blog param as DEFAULT so that it doesn't get passed in regenerate_url()
memorize_param( 'blog', 'integer', $blog );

// A blog has been requested... Let's set a few default params:

# You could *force* a specific skin here with this setting:
# $skin = 'basic';

# This setting retricts posts to those published, thus hiding drafts.
# You should not have to change this.
$show_statuses = array();

# Here you can set a limit before which posts will be ignored
# You can use a unix timestamp value or 'now' which will hide all posts in the past
$timestamp_min = '';

# Here you can set a limit after which posts will be ignored
# You can use a unix timestamp value or 'now' which will hide all posts in the future
$timestamp_max = 'now';

# Additionnaly, you can set other values (see URL params in the manual)...
# $order = 'ASC'; // This for example would display the blog in chronological order...

// That's it, now let b2evolution do the rest! :)
require $inc_path.'_blog_main.inc.php';

?>