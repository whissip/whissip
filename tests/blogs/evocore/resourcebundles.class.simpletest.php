<?php
/**
 * Tests for the {@link ResourceBundles} class.
 * @package tests
 */

/**
 * SimpleTest config
 */
require_once( dirname(__FILE__).'/../../config.simpletest.php' );


load_class('files/model/_resourcebundles.class.php', 'ResourceBundles');


/**
 * Wrapper for {@link ResourceBundles}, which makes certain
 * methods public, for testing.
 */
class TestResourceBundles extends ResourceBundles
{
	public function public_get_resolved_filepath()
	{
		$args = func_get_args();
		return call_user_func_array( array($this, 'parent::get_resolved_filepath'), $args );
	}

	public function public_get_bundle_file_data()
	{
		$args = func_get_args();
		return call_user_func_array( array($this, 'parent::get_bundle_file_data'), $args );
	}

	public function public_resolve_bundle_parts()
	{
		$args = func_get_args();
		return call_user_func_array( array($this, 'parent::resolve_bundle_parts'), $args );
	}

	public function my_get_bundle_parts()
	{
		return $this->bundle_parts;
	}

	/**
	 * Helper function to get the contents of all bundle entries.
	 *
	 * @param string Type ("js", "css")
	 * @return array List of resolved bundles (by cache key)
	 */
	public function my_get_bundle_contents($type)
	{
		$bundle_data = $this->public_get_bundle_file_data($type);
		$r = array();
		foreach( $bundle_data as $key => $bundles )
		{
			$r[$key] = array();
			foreach( $bundles as $info )
			{
				$path = str_replace($this->get_cache_url(), $this->get_cache_path(), $info['content']);
				$r[$key][] = file_get_contents($path);
			}
		}
		return $r;
	}


}


/**
 * Extend TestResourceBundles, but make it believe the cache is not writable.
 */
class TestResourceBundlesNotWritable extends TestResourceBundles
{
	protected function save_bundle_contents($path, $contents)
	{
		return false;
	}
}


/**
 * @package tests
 */
class ResourceBundlesTestCase extends EvoUnitTestCase
{
	protected static $printf_css_script = '<link rel="stylesheet" href="%s" type="text/css" />';
	protected static $printf_js_script = '<script type="text/javascript" src="%s"></script>';
	protected static $match_css_bundle_headline = '<link rel="stylesheet" href="((https?:)?//)?[.\w-_/]*/bundle__a\w{5}-([\w_+-]+-)?\w{32}-\d{9,}.css\?t=\d+" type="text/css" />';

	function __construct()
	{
		parent::__construct( 'ResourceBundles test' );
	}


	/**
	 * Cleanup cache dir on setting up tests.
	 */
	function setup()
	{
		parent::setup();
		$rb = new ResourceBundles();
		$rb->cleanup_cache_dir();
	}


	/**
	 * Test if minify_css/css_tidy handles multibyte
	 * correctly.
	 */
	function test_minifiy_css_import()
	{
		$this->assertEqual( ResourceBundles::minify_css('/* ç */ @import url("foo");'),
			'@import \'foo\';' );
	}


	/**
	 * Test {@link ResourceBundles::get_resolved_filepath()}
	 */
	function test_get_resolved_filepath()
	{
		global $rsc_path, $rsc_url;
		global $skins_path;

		$old_rsc_url = $rsc_url;
		$rsc_url = '//example.com/rsc/'; // Test protocol-relative URL (should not get handled as file path)

		$rb_ro = new TestResourceBundlesNotWritable();
		$rb_ro->add_file('js', 'admin.js');

		$headlines = $rb_ro->get_html_headlines();
		$this->assertEqual($headlines[0], sprintf(self::$printf_js_script, $rsc_url.'js/admin.js'));

		$rsc_url = $old_rsc_url;


		$rb = new TestResourceBundles();
		$this->assertIdentical(
			$rb->public_get_resolved_filepath('admin.js'),
			array($rsc_path.'js/admin.js', $rsc_url.'js/admin.js') );

		$this->assertIdentical(
			$rb->public_get_resolved_filepath('not-existing.js'),
			array(false, 'not-existing.js') );

		// Test that basic.css gets imported for custom's style.css.
		$this->assertPattern(
			'/===> bundle import: basic.css ===/',
			$rb->get_css_file_contents($skins_path.'custom/style.css') );
	}


	/**
	 * Test adding the same file twice to a bundle, verifying that it gets
	 * lazy-resolved and then verify the result.
	 */
	function test_bundle_css()
	{
		global $rsc_path;

		$rb = new TestResourceBundles();
		$rb->add_file('css', 'basic.css');
		$rb->add_file('css', 'basic.css');

		// There should be 2 entries now; unresolved until now.
		$bp = $rb->my_get_bundle_parts();
		$this->assertEqual( 1, count($bp) );
		$this->assertEqual( 1, count($bp['css']) );
		$this->assertEqual( 2, count(array_shift(array_shift($bp))) );

		$headlines = $rb->get_html_headlines();

		// Now the bundle parts should have been resolved.
		$bp = $rb->my_get_bundle_parts();
		$this->assertEqual( 1, count($bp) );
		$this->assertEqual( 1, count($bp['css']) );
		// Get the bundle parts.
		$bp = array_shift(array_shift($bp));
		$this->assertEqual( 1, count($bp) );
		$this->assertEqual( $bp[0]['content_type'], 'file' );
		$this->assertEqual( $bp[0]['content'], $rsc_path.'css/basic.css' );

		$this->assertEqual( 1, count($headlines) );
		$this->assertPattern(self::$match_css_bundle_headline, $headlines[0]);
	}


	/**
	 * Test getting a file below $basedir bundled.
	 */
	function test_bundle_from_basedir()
	{
		$rb = new TestResourceBundles();
		$rb->add_file('css', 'skins_adm/chicago/rsc/css/chicago.css');
		$headlines = $rb->get_html_headlines();
		$this->assertEqual(1, count($headlines));
		$this->assertPattern(self::$match_css_bundle_headline, $headlines[0]);
	}


	/**
	 * Test generating bundle for file relative to basepath, if read-only.
	 */
	function test_bundle_from_basedir_ro()
	{
		global $baseurl;
		$rb = new TestResourceBundlesNotWritable();
		$rb->add_file('css', 'skins_adm/chicago/rsc/css/chicago.css');
		$headlines = $rb->get_html_headlines();
		$this->assertEqual(1, count($headlines));
		$this->assertEqual($headlines[0],
			sprintf(self::$printf_css_script, $baseurl.'skins_adm/chicago/rsc/css/chicago.css'));
	}


	/**
	 * Test clean_href resolving of file relative to basepath,
	 * with '/' as base url.
	 * Using TestResourceBundlesNotWritable, so clean_href makes it to headlines.
	 */
	function test_clean_href_with_slash_baseurl()
	{
		global $basepath;
		$rb = new TestResourceBundlesNotWritable();
		$rb->set_file_base_dirs(array($basepath=>'/'));
		$rb->add_file('css', '/rsc/css/basic.css');
		$headlines = $rb->get_html_headlines();
		$this->assertEqual(1, count($headlines));
		$this->assertEqual($headlines[0],
			sprintf(self::$printf_css_script, '/rsc/css/basic.css'));
	}


	/**
	 * Test maximum bundle filename length to be 255, when using
	 * add_filenames_to_bundle_id=true.
	 */
	function test_max_filename_length()
	{
		global $basepath;

		$rb = new TestResourceBundles();
		$rb->add_filenames_to_bundle_id = true;

		// Add all .css files we have.
		$files = get_filenames( $basepath, true, false );
		$basenameslen = 0;
		foreach($files as $file)
		{
			if( substr($file,-4) == '.css' )
			{
				$rb->add_file('css', $file);
				$basenameslen += strlen(basename($file, '.css'));
			}
		}
		// Assume that the estimated size is at least 200 already, for the filename
		// part. MD5 etc get added to this.
		// Otherwise the test should get probably skipped.
		$this->assertTrue($basenameslen > 200);

		$filenames = array();
		foreach($rb->my_get_bundle_parts() as $type => $bundles)
			foreach( $bundles as $attribs_key => $bundle_parts )
				$filenames[] = $rb->get_bundle_filename($type, $attribs_key, $bundle_parts);

		$this->assertEqual(1, count($filenames));
		$this->assertTrue(255 > strlen($filenames[0]));
	}


	/**
	 * Test that calling order is kept.
	 * This is important, if e.g. the second call (without "rsc_pack") would add
	 * jquery.js and the third call adds code to rely on it.
	 *
	 * Currently, the third line gets bundled together with the first and therefore
	 * before the second line.
	 * TODO: dh> seems broken by design - come up with a solution. Or is this "expected"?!
	 */
	function stub_test_keep_order()
	{
		$rb = new TestResourceBundles();
		$rb->add_inline('js', 'var foo = 1;', array('rsc_pack'=>'inline'));
		$rb->add_inline('js', 'var foo = 2;');
		$rb->add_inline('js', 'var foo = 3;', array('rsc_pack'=>'inline'));
		var_dump($rb->get_html_headlines());

	}


	/**
	 * Test that "css" gets output before "js".
	 */
	function test_css_before_js()
	{
		$rb = new TestResourceBundles();
		$rb->add_inline('js', 'var foo = 1;', array('rsc_pack'=>'inline'));
		$rb->add_file('js', '/foo.js');
		$rb->add_inline('css', '.foo {color:black}');
		$rb->add_file('js', '/bar.js');
		$rb->add_file('css', '/bar.css');
		$headlines = $rb->get_html_headlines();
		$this->assertEqual(5, count($headlines));
		$this->assertPattern('/^<link rel="stylesheet"/', $headlines[0]);
		$this->assertPattern('/^<link rel="stylesheet"/', $headlines[1]);
		$this->assertPattern('~^<script type="text/javascript"~', $headlines[2]);
		$this->assertPattern('~^<script type="text/javascript"~', $headlines[3]);
		$this->assertPattern('~^<script type="text/javascript"~', $headlines[4]);
	}


	/**
	 * Test if get_file_base_dirs() returns basepath=>baseurl.
	 */
	function test_file_base_dirs()
	{
		global $basepath, $baseurl;
		$rb = new TestResourceBundles();
		$basedirs = $rb->get_file_base_dirs();
		$this->assertEqual( $basedirs, array($basepath=>$baseurl) );
	}


	/**
	 * Test if an absolute (existing) path gets resolved when the cache dir is not writable.
	 */
	function test_resolve_filepath_ro()
	{
		global $baseurl, $rsc_path;
		$rb = new TestResourceBundlesNotWritable();
		$rb->add_file( 'js', $rsc_path.'js/functions.js' );

		$headlines = $rb->get_html_headlines();
		$this->assertEqual(1, count($headlines));
		$this->assertPattern('~^<script type="text/javascript" src="'.preg_quote($baseurl).'~', $headlines[0]);
	}


	/**
	 * Test if an absolute (non-existing) path gets resolved when the cache dir is not writable.
	 */
	function test_resolve_filepath_nonexisting()
	{
		global $baseurl, $basepath;
		$rb = new TestResourceBundlesNotWritable();
		$rb->add_file( 'js', $basepath.'notexisting' );

		$headlines = $rb->get_html_headlines();
		$this->assertEqual(1, count($headlines));
		$this->assertPattern('~^<script type="text/javascript" src="'.preg_quote($baseurl).'~', $headlines[0]);
	}


	/**
	 * Test that get_cache_dir_files() returns an array always.
	 */
	function test_get_cache_dir_files()
	{
		$rb = new TestResourceBundles();
		$this->assertIdentical( array(), $rb->get_cache_dir_files() );
	}


	/**
	 * Test that duplicate files get resolved properly, when using attribs.
	 */
	function test_unique_entries()
	{
		$rb = new TestResourceBundles();
		$rb->add_file('js', 'jquery.js', array('rsc_order'=>10));
		$rb->add_file('js', 'jquery.js', array('rsc_order'=>10));
		$rb->public_resolve_bundle_parts('js');
		$bundle_parts = $rb->my_get_bundle_parts();
		$this->assertEqual( count(array_shift(array_shift($bundle_parts))), 1);
	}


	/**
	 * Test that multiple @imports (from different files) get added only once to the bundle.
	 */
	function test_recursive_only_once()
	{
		$rb = new TestResourceBundles();
		$rb->add_file('css', 'basic.css');
		$rb->add_file('css', 'item_base.css');

		$rb->add_file('css', 'item_base.css', array('rsc_pack'=>'foo'));

		$contents = $rb->my_get_bundle_contents('css');

		$this->assertEqual( 2, count($contents) );
		$this->assertEqual( array_keys($contents), array('a:1:{s:12:"rsc_compress";b:1;}', 'a:2:{s:12:"rsc_compress";b:1;s:8:"rsc_pack";s:3:"foo";}') );

		// "basic_styles.css" should be once in the "global" bundle..
		$content = array_shift($contents);
		$this->assertEqual( 1, count($content) );
		$this->assertEqual(1, substr_count($content[0], '/* ===> bundle import: basic_styles.css === */'));

		// ..and once in the "rsc_pack=foo" bundle
		$content = array_shift($contents);
		$this->assertEqual( 1, count($content) );
		$this->assertEqual(1, substr_count($content[0], '/* ===> bundle import: basic_styles.css === */'));
	}


	/**
	 * Test that no exception (JSMinException) gets thrown for invalid JS
	 * and the original data gets used.
	 */
	function test_jsmin_exception()
	{
		$invalid_js = "var foo = 'a\nb';";
		$rb = new TestResourceBundles();
		$rb->add_refs_to_origin = false;
		$rb->add_inline('js', $invalid_js, array('rsc_pack'=>'inline'));

		$contents = $rb->my_get_bundle_contents('js');
		$this->assertEqual(1, count($contents));
		$contents = array_shift($contents);
		$this->assertEqual(1, count($contents));

		$this->assertEqual( $invalid_js, $contents[0] );
	}


	/**
	 * Test that all url() references are resolved correctly.
	 * @todo dh> This is a slow tests, should get marked as such and not get done by default. Completely disabled for now.
	 */
	function _disabled_test_css_resolved_urls_in_skins()
	{
		global $skins_path;

		$rb = new TestResourceBundles();

		foreach( glob($skins_path.'*/style.css') as $test_filename )
		{
			$css = $rb->get_css_file_contents($test_filename);

			// match any empty "url()"
			preg_match_all('~url\(\)~', $css, $match);

			$skinname = basename(dirname($test_filename));
			$this->assertEqual(count($match[0]), 0, 'All url() instances resolved for '.$skinname);
		}
	}


	function test_css_data_uri()
	{
		$rb = new TestResourceBundles();
		$css = $rb->minify_css('.foo {background-image: url("data:image/gif;base64,AAAA");}');
		$this->assertEqual($css, '.foo{background-image:url(data:image/gif;base64,AAAA)}');

		$css = $rb->get_css_content_processed('.foo {background-image: url("data:image/gif;base64,AAAA");}', __FILE__);
		$this->assertEqual($css, '.foo{background-image:url(data:image/gif;base64,AAAA)}');
	}


	function test_css_imports()
	{
		$rb = new TestResourceBundles();
		$css = $rb->minify_css('@import url(foo)');
		$this->assertEqual($css, "@import 'foo';");
		$css = $rb->minify_css('@import "foo"');
		$this->assertEqual($css, '@import "foo";');
		$css = $rb->minify_css('@import url("foo")');
		$this->assertEqual($css, "@import 'foo';");
		$css = $rb->minify_css('@import url(\'foo\')');
		$this->assertEqual($css, "@import 'foo';");
		$css = $rb->minify_css('@import url(\'foo")');
		$this->assertEqual($css, "@import 'foo';");
		$css = $rb->minify_css('@import url("foo with whitespace")');
		$this->assertEqual($css, "@import 'foo with whitespace';");
		$css = $rb->minify_css('@import url(foo\ with\ whitespace)');
		$this->assertEqual($css, "@import 'foo with whitespace';");
		$css = $rb->minify_css('@import url("foo\ with\ whitespace")');
		$this->assertEqual($css, "@import 'foo with whitespace';");
	}


	function test_css_fix_urls()
	{
		$rb = new TestResourceBundles();
		$rb->set_cache_path(dirname(__FILE__));
		$css = $rb->get_css_content_processed('p { background-url:url(../../foo.gif) }', __FILE__.'.css');
		$this->assertEqual($css, 'p{background-url:url(../../foo.gif)}');

		$rb->set_cache_path(dirname(__FILE__).'/../../');
		$css = $rb->get_css_content_processed('p { background-url:url(../../../../foo.gif) }', __FILE__.'.css');
		$this->assertEqual($css, 'p{background-url:url(../../foo.gif)}');

		$rb->set_cache_path(dirname(__FILE__).'/../../');
		$css = $rb->get_css_content_processed('p { background-url:url(../../foo.gif) }', __FILE__.'.css');
		$this->assertEqual($css, 'p{background-url:url(foo.gif)}');

		$rb->set_cache_path(dirname(__FILE__).'/../../');
		$css = $rb->get_css_content_processed('p { background-url:url(../../media/foo.gif) }', __FILE__.'.css');
		$this->assertEqual($css, 'p{background-url:url(media/foo.gif)}');
	}


	function test_css_unicode_no_infinite_loop()
	{
		$rb = new TestResourceBundles();
		$css = $rb->minify_css('* html b\ody{margin:0}');
		$this->assertEqual($css, "* html b\ody{margin:0}");
	}


}



if( !isset( $this ) )
{ // Called directly, run the TestCase alone
	$test = new ResourceBundlesTestCase();
	$test->run_html_or_cli();
	unset( $test );
}
?>
