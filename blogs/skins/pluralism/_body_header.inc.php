<?php
/**
 * This is the BODY header include template.
 *
 * For a quick explanation of b2evo 2.0 skins, please start here:
 * {@link http://manual.b2evolution.net/Skins_2.0}
 *
 * This is meant to be included in a page template.
 *
 * @package evoskins
 * @subpackage pluralism
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

?>
<div id="wrapper0">
<div id="wrapper">
	<div id="wrapper2">
		<div id="header">
			<div id="logo">
				<?php

					// ------------------------- "Header" CONTAINER EMBEDDED HERE --------------------------
					// Display container and contents:
					skin_container( NT_('Header'), array(
							// The following params will be used as defaults for widgets included in this container:
							'block_start'       => '',
							'block_end'         => '',
							'block_title_start' => '<h1>',
							'block_title_end'   => '</h1>'
						) );
					// ----------------------------- END OF "Header" CONTAINER -----------------------------

				?>
			</div>
			<?php
				// Display container and contents:
				skin_container( NT_('Page Top'), array(
						// The following params will be used as defaults for widgets included in this container:
						'block_start'         => '<div id="blogs">',
						'block_end'           => '</div>',
						'block_display_title' => false,
						'list_start'          => '<ul>',
						'list_end'            => '</ul>',
						'item_start'          => '<li class="first page_item">',
						'item_end'            => '</li>',
					) );
			?>
			<div id="menu"><ul>
			<?php
				// ------------------------- "Menu" CONTAINER EMBEDDED HERE --------------------------
				// Display container and contents:
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
			</ul></div>
		</div>
		<!-- end #header -->
		<hr />
		<div id="page">