<?php
/**
 * This file implements the Bookmarket plugin.
 *
 * This file is part of the b2evolution project - {@link http://b2evolution.net/}
 *
 * @copyright (c)2003-2008 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * @package plugins
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE - {@link http://fplanque.net/}
 * @author cafelog (team) - http://cafelog.com/
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Sidebar plugin
 *
 * Adds a tool allowing blogging from the sidebar
 */
class bookmarklet_plugin extends Plugin
{
	var $name = 'Bookmarklet';
	var $code = 'cafeBkmk';
	var $priority = 94;
	var $version = '1.9-dev';
	var $author = 'Cafelog team';
	var $number_of_installs = 1;


	/**
	 * Init
	 */
	function PluginInit( & $params )
	{
		$this->short_desc = T_('Allow bookmarklet blogging.');
		$this->long_desc = T_('Adds a tool allowing blogging through a bookmarklet.');
	}


	/**
	 * We are displaying the tool menu.
	 *
	 * @todo Do not create links/javascript code based on browser detection! But: test for functionality!
	 *
	 * @param array Associative array of parameters
	 * @return boolean did we display a tool menu block?
	 */
	function AdminToolPayload( $params )
	{
		global $Hit, $admin_url;

		if( $Hit->is_NS4 || $Hit->is_gecko || $Hit->is_firefox )
		{
			?>
			<p><?php echo T_('Add this link to your Favorites/Bookmarks:') ?><br />
			<a href="javascript:Q=document.selection?document.selection.createRange().text:document.getSelection();void(window.open('<?php echo $admin_url ?>?ctrl=items&amp;action=new&amp;mode=bookmarklet&amp;content='+escape(Q)+'&amp;post_url='+escape(location.href)+'&amp;post_title='+escape(document.title),'b2evobookmarklet','scrollbars=yes,resizable=yes,width=750,height=550,left=25,top=15,status=yes'));"><?php echo T_('b2evo bookmarklet') ?></a></p>
			<?php
			return true;
		}
		elseif( $Hit->is_winIE )
		{
			?>
			<p><?php echo T_('Add this link to your Favorites/Bookmarks:') ?><br />
			<a href="javascript:Q='';if(top.frames.length==0)Q=document.selection.createRange().text;void(btw=window.open('<?php echo $admin_url ?>?ctrl=items&amp;action=new&amp;mode=bookmarklet&amp;content='+escape(Q)+'&amp;post_url='+escape(location.href)+'&amp;post_title='+escape(document.title),'b2evobookmarklet','scrollbars=yes,resizable=yes,width=750,height=550,left=25,top=15,status=yes'));btw.focus();"><?php echo T_('b2evo bookmarklet') ?></a>
			</p>
			<?php
			return true;
		}
		elseif( $Hit->is_opera )
		{
			?>
			<p><?php echo T_('Add this link to your Favorites/Bookmarks:') ?><br />
			<a href="javascript:void(window.open('<?php echo $admin_url ?>?ctrl=items&amp;action=new&amp;mode=bookmarklet&amp;post_url='+escape(location.href)+'&amp;post_title='+escape(document.title),'b2evobookmarklet','scrollbars=yes,resizable=yes,width=750,height=550,left=25,top=15,status=yes'));"><?php echo T_('b2evo bookmarklet') ?></a></p>
			<?php
			return true;
		}
		elseif( $Hit->is_macIE )
		{
			?>
			<p><?php echo T_('Add this link to your Favorites/Bookmarks:') ?><br />
			<a href="javascript:Q='';if(top.frames.length==0);void(btw=window.open('<?php echo $admin_url ?>?ctrl=items&amp;action=new&amp;mode=bookmarklet&amp;content='+escape(document.getSelection())+'&amp;post_url='+escape(location.href)+'&amp;post_title='+escape(document.title),'b2evobookmarklet','scrollbars=yes,resizable=yes,width=750,height=550,left=25,top=15,status=yes'));btw.focus();"><?php echo T_('b2evo bookmarklet') ?></a></p>
			<?php
			return true;
		}
		else
		{  // This works in Safari, at least
			?>
			<p><?php echo T_('Add this link to your Favorites/Bookmarks:') ?><br />
			<a href="javascript:Q=window.getSelection();void(window.open('<?php echo $admin_url ?>?ctrl=items&amp;action=new&amp;mode=bookmarklet&amp;content='+escape(Q)+'&amp;post_url='+escape(window.location.href)+'&amp;post_title='+escape(document.title),'b2evobookmarklet','scrollbars=yes,resizable=yes,width=750,height=550,status=yes'));"><?php echo T_('b2evo bookmarklet') ?></a></p>
			<?php
			return true;
		}

		return false;
	}
}


/*
 * $Log$
 * Revision 1.21  2008/01/22 00:21:24  personman2
 * Fixing bookmarklet to work with Safari, Firefox
 *
 * Revision 1.20  2008/01/21 09:35:39  fplanque
 * (c) 2008
 *
 * Revision 1.19  2007/04/26 00:11:04  fplanque
 * (c) 2007
 *
 * Revision 1.18  2007/04/20 02:53:13  fplanque
 * limited number of installs
 *
 * Revision 1.17  2006/12/12 02:53:57  fplanque
 * Activated new item/comments controllers + new editing navigation
 * Some things are unfinished yet. Other things may need more testing.
 *
 * Revision 1.16  2006/12/06 23:25:32  blueyed
 * Fixed bookmarklet plugins (props Danny); removed unneeded bookmarklet handling in core
 *
 * Revision 1.15.2.2  2006/11/04 19:55:11  fplanque
 * Reinjected old Log blocks. Removing them from CVS was a bad idea -- especially since Daniel has decided branch 1.9 was his HEAD...
 *
 * Revision 1.15  2006/07/10 20:19:30  blueyed
 * Fixed PluginInit behaviour. It now gets called on both installed and non-installed Plugins, but with the "is_installed" param appropriately set.
 *
 * Revision 1.14  2006/07/07 21:26:49  blueyed
 * Bumped to 1.9-dev
 *
 * Revision 1.13  2006/07/06 19:56:29  fplanque
 * no message
 *
 * Revision 1.12  2006/06/16 21:30:57  fplanque
 * Started clean numbering of plugin versions (feel free do add dots...)
 *
 * Revision 1.11  2006/05/30 19:39:55  fplanque
 * plugin cleanup
 *
 * Revision 1.10  2006/05/19 15:59:52  blueyed
 * Fixed bookmarklet plugin. Thanks to personman for pointing it out.
 *
 * Revision 1.9  2006/04/11 21:22:26  fplanque
 * partial cleanup
 *
 */
?>