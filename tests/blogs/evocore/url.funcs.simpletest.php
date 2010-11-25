<?php
/**
 * Tests for URL handling functions.
 * @package tests
 */

/**
 * SimpleTest config
 */
require_once( dirname(__FILE__).'/../../config.simpletest.php' );


global $inc_path;

load_funcs( '_core/_url.funcs.php' );


/**
 * @package tests
 */
class UrlFuncsTestCase extends EvoUnitTestCase
{
	function __construct()
	{
		parent::__construct( 'URL functions test' );
	}


	/**
	 * Test {@link url_rel_to_same_host()}
	 */
	function test_url_rel_to_same_host()
	{
		$this->assertEqual(
			url_rel_to_same_host('http://example.com/foobar', 'http://example.com/barfoo'),
			'/foobar' );

		$this->assertEqual(
			url_rel_to_same_host('http://example.com/foobar', 'https://example.com/barfoo'),
			'http://example.com/foobar' );

		$this->assertEqual(
			url_rel_to_same_host('http://example.com/foobar', '/barfoo'),
			'/foobar' );

		$this->assertEqual(
			url_rel_to_same_host('foobar', 'http://example.com/barfoo'),
			'foobar' );

		$this->assertEqual(
			url_rel_to_same_host('http://example.com/barfoo?f=b', 'https://example.com/barfoo'),
			'http://example.com/barfoo?f=b' );

		$this->assertEqual(
			url_rel_to_same_host('https://example.com/barfoo?f=b#a', 'https://user:pass@example.com/barfoo'),
			'https://example.com/barfoo?f=b#a' );

		$this->assertEqual(
			url_rel_to_same_host('foobar', 'http://example.com/barfoo'),
			'foobar' );

		// Tests for URLs without protocol
		// URL has protocol info, keep it.
		$this->assertEqual(
			url_rel_to_same_host('http://host/bar', '//host/baz'),
			'http://host/bar' );

		// Target URL has protocol info, URL is protocol ambivalent.
		$this->assertEqual(
			url_rel_to_same_host('//host/bar', 'https://host/baz'),
			'/bar' );

		$this->assertEqual(
			url_rel_to_same_host('//host/bar', '//host/baz'),
			'/bar' );

		$this->assertEqual(
			url_rel_to_same_host('//hostA/bar', '//hostB/baz'),
			'//hostA/bar' );

		$this->assertEqual(
			url_rel_to_same_host('http://host/?query#fragment', 'http://host/'),
			'/?query#fragment' );

		$this->assertEqual(
			url_rel_to_same_host('http://host/0?0#0', 'http://host/'),
			'/0?0#0' );
	}


	function test_validate_url()
	{
		// valid:
		foreach( array(
			'http://b2evolution.net',
			'https://demo.b2evolution.net',
			'http://user@example.com/path',
			'http://user:pass@example.com/path',
			'mailto:example@example.org',
			'mailto:example@example.org?subject=TEST',
			'http://läu.de/',
			'http://läu.de/foo bar',
			) as $url )
		{
			$r = validate_url( $url, 'commenting' );
			// True means validation ok
			$this->assertFalse( $r, $url.' NOT allowed in comments' );

			$r = validate_url( $url, 'posting' );
			$this->assertFalse( $r, $url.' NOT allowed in posts' );
		}

		// valid in "posting" mode only:
		foreach( array(
			'/foobar',
			'/foobar#anchor',
			'#anchor',
			) as $url )
		{
			$r = validate_url( $url, 'posting' );
			$this->assertFalse( $r, $url.' NOT allowed in posts' );
		}

		// invalid:
		foreach( array(
			'http://',
			'http://&amp;',
			'http://<script>...</script>',
			'mailto:www.example.com',
			'foobar',
			) as $url )
		{
			$r = validate_url( $url, 'commenting' );
			// True means validation rejected
			$this->assertTrue( $r, $url.' allowed in comments' );

			$r = validate_url( $url, 'posting' );
			$this->assertTrue( $r, $url.' allowed in posts' );
		}
	}


	/**
	 * Tests {@link idna_encode()}
	 */
	function test_idna_encode()
	{
		$this->assertEqual( idna_encode( 'läu.de' ), 'xn--lu-via.de' );
	}


	/**
	 * Test {@link url_add_param()}
	 */
	function test_url_add_param()
	{
		$this->assertEqual( url_add_param('foo', 'bar', '&'), 'foo?bar' );
		$this->assertEqual( url_add_param('foo#anchor', 'bar', '&'), 'foo?bar#anchor' );

		$this->assertEqual( url_add_param('foo?', 'bar', '&'), 'foo?bar' );
		$this->assertEqual( url_add_param('foo?#anchor', 'bar', '&'), 'foo?bar#anchor' );
		$this->assertEqual( url_add_param('?', 'bar', '&'), '?bar' );
		$this->assertEqual( url_add_param('?#anchor', 'bar', '&'), '?bar#anchor' );
		$this->assertEqual( url_add_param('#anchor', 'bar', '&'), '?bar#anchor' );

		$this->assertEqual( url_add_param('?', array('foo'=>1)), '?foo=1' );
		$this->assertEqual( url_add_param('?', array('foo'=>array(1=>2))), '?foo%5B1%5D=2' );
		$this->assertEqual( url_add_param('?', array('foo'=>array(1, 2))), '?foo%5B%5D=1&amp;foo%5B%5D=2' );
		$this->assertEqual( url_add_param('?', array('foo'=>'100%')), '?foo=100%25' );
		$this->assertEqual( url_add_param('?', array('foo'=>'1&2')), '?foo=1%262' );

		$this->assertEqual( url_add_param('?',
						array('foo' => array('bar' => 1))), '?foo%5Bbar%5D=1' );
	}
}


if( !isset( $this ) )
{ // Called directly, run the TestCase alone
	$test = new UrlFuncsTestCase();
	$test->run_html_or_cli();
	unset( $test );
}
?>
