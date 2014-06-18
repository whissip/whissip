<?php
/**
 * This is the BODY header include template.
 *
 * For a quick explanation of b2evo 2.0 skins, please start here:
 * {@link http://b2evolution.net/man/skin-structure}
 *
 * This is meant to be included in a page template.
 *
 * @package evoskins
 * @subpackage evopress
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $dummy_fields;

?>

<!-- New noscript check, we need js on now folks -->
<noscript>
<div id="noscript-wrap">
	<div id="noscript">
		<h2><?php echo T_( 'Notice' ); ?></h2>
		<p><?php echo T_( 'JavaScript for Mobile Safari is currently turned off.' ); ?></p>
		<p><?php echo T_( 'Turn it on in <em> Settings &rsaquo; Safari </em><br /> to view this website.' ); ?></p>
	</div>
</div>
</noscript>

<div style="display: none;" id="wptouch-menu" class="dropper">
	<div id="wptouch-menu-inner">
		<div id="menu-head">
			<div id="tabnav">
				<a class="selected" href="#head-nav"><?php echo T_('Navigation'); ?></a>
				<a class="selected" href="#head-tools"><?php echo T_('Tools'); ?></a>
				<a href="#head-blogs"><?php echo T_('Other Blogs'); ?></a>
			</div>

			<?php
				// ------------------------- "Navigation Menu" CONTAINER EMBEDDED HERE --------------------------
				// Display container and contents:
				skin_container( NT_('Mobile: Navigation Menu'), array(
						// The following params will be used as defaults for widgets included in this container:
						'block_start' => '<ul id="head-nav">',
						'block_end' => '</ul>',
						'block_display_title' => false,
						'list_start' => '',
						'list_end' => '',
						'item_start' => '<li>',
						'item_end' => '</li>',
					) );
				// ----------------------------- END OF "Navigation Menu" CONTAINER -----------------------------
			?>

			<?php
				// ------------------------- "Tools Menu" CONTAINER EMBEDDED HERE --------------------------
				// Display container and contents:
				skin_container( NT_('Mobile: Tools Menu'), array(
						// The following params will be used as defaults for widgets included in this container:
						'block_start' => '<ul id="head-tools">',
						'block_end' => '</ul>',
						'block_display_title' => false,
						'list_start' => '',
						'list_end' => '',
						'item_start' => '<li>',
						'item_end' => '</li>',
					) );
				// ----------------------------- END OF "Tools Menu" CONTAINER -----------------------------
			?>
	
			<?php
				// --------------------------------- START OF BLOG LIST --------------------------------
				// Call widget directly (without container):
				skin_widget( array(
							// CODE for the widget:
							'widget' => 'colls_list_public',
							// Optional display params
						'block_start' => '<ul id="head-blogs">',
						'block_end' => '</ul>',
						'block_display_title' => false,
						'list_start' => '',
						'list_end' => '',
						'item_start' => '<li>',
						'item_end' => '</li>',
					) );
				// ---------------------------------- END OF BLOG LIST ---------------------------------
			?>
		</div>
	</div>
</div>

<div id="headerbar">
	<div id="headerbar-title">
		<a href="<?php echo $Blog->get( 'url', 'raw' ); ?>"><img id="logo-icon" src="<?php echo $Skin->get_url(); ?>img/icon-pool/Default.png" alt="<?php echo $Blog->dget( 'name', 'text' ); ?>"></a>
		<a href="<?php echo $Blog->get( 'url', 'raw' ); ?>"><?php echo $Blog->dget( 'name', 'htmlbody' ); ?></a>
	</div>
	<div id="headerbar-menu">
		<a href="javascript:return false;"><?php echo T_( 'Menu' ); ?></a>
	</div>
</div>

<!-- #start The Search Overlay -->
<div id="wptouch-search"> 
	<div id="wptouch-search-inner">
		<form method="get" id="searchform" action="<?php echo $Blog->gen_blogurl(); ?>">
			<input type="hidden" name="disp" value="search" />
			<input type="text" placeholder="<?php echo T_( 'Search...' ); ?>" name="s" id="search-input" /> 
			<input name="submit" type="submit" tabindex="1" id="search-submit" placeholder="<?php echo T_( 'Search...' ); ?>"  />
			<a href="javascript:return false;"><img class="head-close" src="<?php echo $Skin->get_url(); ?>img/head-close.png" alt="close" /></a>
		</form>
	</div>
</div>

<div id="drop-fade">
	<a id="searchopen" class="top" href="javascript:return%20false;">Search</a>
</div>

<div class="content">