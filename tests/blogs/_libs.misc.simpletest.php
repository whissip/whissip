<?php
/**
 * Tests for misc. external functions / libraries.
 */

/**
 * SimpleTest config
 */
require_once( dirname(__FILE__).'/../config.simpletest.php' );


global $inc_path;

/**
 * Includes
 */
require_once $inc_path.'_misc/ext/_idna_convert.class.php';
require_once $inc_path.'_misc/_htmlchecker.class.php';


/**
 * Testcase for external libraries, shipped with b2evo.
 *
 * @package tests
 */
class ExtLibsTestCase extends EvoUnitTestCase
{
	function ExtLibsTestCase()
	{
		$this->UnitTestCase( 'ExtLibs functions test' );
	}


	function setUp()
	{
		parent::setup();
	}


	function tearDown()
	{
		parent::tearDown();
	}


	/**
	 * Tests {@link idna_convert::encode()}
	 */
	function test_IDNA_decode()
	{
		if( ! function_exists('utf8_encode') )
		{
			$this->fail( 'utf8_encode() not available, cannot test.' );
			return;
		}
		$IDNA = new Net_IDNA_php4();
		$this->assertEqual( $IDNA->encode( utf8_encode('l�u.de') ), 'xn--lu-via.de' );
	}


	/**
	 * Test {@link SafeHtmlChecker::check()} for encoding issues.
	 */
	function test_htmlchecker_check_encoding()
	{
		global $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme;

		if( ! function_exists('utf8_encode') )
		{
			$this->fail( 'utf8_encode() not available, cannot test.' );
			return;
		}

		$SHC = new SafeHtmlChecker( $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme
			/* default encoding */ );
		$SHC->check( 'foo bar' );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new SafeHtmlChecker( $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme
			/* default encoding */ );
		$SHC->check( 'foo �� bar' );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new SafeHtmlChecker( $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme
			/* default encoding */ );
		$SHC->check( utf8_encode('foo �� bar') );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new SafeHtmlChecker( $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme,
			'UTF-8' );
		$SHC->check( 'foo bar' );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new SafeHtmlChecker( $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme,
			'UTF-8' );
		$SHC->check( utf8_encode('foo �� bar' ) );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new SafeHtmlChecker( $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme,
			'UTF-8' );
		$SHC->check( 'foo �� bar' );
		$this->assertFalse( $SHC->isOK() );

		$SHC = new SafeHtmlChecker( $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme,
			'ISO-8859-1' );
		$SHC->check( 'foo �� bar' );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new SafeHtmlChecker( $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme,
			'ISO-8859-15' );
		$SHC->check( utf8_encode('foo � bar') );
		$this->assertTrue( $SHC->isOK() );
		$this->assertEqual( $SHC->encoding, 'UTF-8' ); // should have been converted to UTF-8
	}

}


if( !isset( $this ) )
{ // Called directly, run the TestCase alone
	$test = new ExtLibsTestCase();
	$test->run( new HtmlReporter() );
	unset( $test );
}
?>
