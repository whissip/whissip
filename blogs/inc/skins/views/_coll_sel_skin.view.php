<?php
/**
 * This file implements the UI view for the skin selection when creating a blog.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * @package admin
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $kind;

echo '<h2>'.sprintf( T_('New %s'), get_collection_kinds($kind) ).':</h2>';

echo '<h3>'.T_('Pick a skin:').'</h3>';

$SkinCache = & get_SkinCache();
$SkinCache->load_all();

// TODO: this is like touching private parts :>
foreach( $SkinCache->cache as $Skin )
{
	if( $Skin->type != 'normal' )
	{	// This skin cannot be used here...
		continue;
	}

	// Display skinshot:
	Skin::disp_skinshot( $Skin->folder, $Skin->name, 'pick', false, '?ctrl=collections&amp;action=new-name&amp;kind='.$kind.'&amp;skin_ID='.$Skin->ID );
}

echo '<div class="clear"></div>';

/*
 * $Log$
 * Revision 1.8  2010/06/01 02:44:44  sam2kb
 * New hooks added: GetCollectionKinds and InitCollectionKinds.
 * Use them to define new and override existing presets for new blogs.
 * See http://forums.b2evolution.net/viewtopic.php?t=21015
 *
 * Revision 1.7  2010/02/08 17:54:42  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.6  2009/09/26 12:00:43  tblue246
 * Minor/coding style
 *
 * Revision 1.5  2009/09/25 07:33:14  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.4  2009/05/23 20:20:18  fplanque
 * Skins can now have a _skin.class.php file to override default Skin behaviour. Currently only the default name but can/will be extended.
 *
 * Revision 1.3  2009/03/08 23:57:45  fplanque
 * 2009
 *
 * Revision 1.2  2008/01/21 09:35:35  fplanque
 * (c) 2008
 *
 * Revision 1.1  2007/06/25 11:01:35  fplanque
 * MODULES (refactored MVC)
 *
 */
?>