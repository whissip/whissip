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
load_class('_ext/idna/_idna_convert.class.php', 'idna_convert');
load_class( 'xhtml_validator/_xhtml_validator.class.php', 'XHTML_Validator' );


/**
 * Testcase for external libraries, shipped with b2evo.
 *
 * @package tests
 */
class ExtLibsTestCase extends EvoUnitTestCase
{
	function __construct()
	{
		parent::__construct( 'ExtLibs functions test' );
	}


	/**
	 * Test {@link XHTML_Validator::check()} for encoding issues.
	 * NOTE: assignment by "& new" is required for PHP4! See also http://de3.php.net/manual/en/function.xml-set-object.php#46107
	 *       Alternatively, multiple vars for each test may work, or unsetting the last one..
	 */
	function test_htmlchecker_check_encoding()
	{
		global $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme;

		$SHC = new XHTML_Validator();
		$SHC->check( 'foo bar' );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new XHTML_Validator();
		$SHC->check( 'foo äö bar' );

		$this->assertTrue( $SHC->isOK() );

		$SHC = new XHTML_Validator();
		$SHC->check( 'foo äö bar' );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new XHTML_Validator();
		$SHC->encoding = 'utf-8';
		$SHC->check( 'foo bar' );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new XHTML_Validator();
		$SHC->encoding = 'utf-8';
		$SHC->check( 'foo äö bar' );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new XHTML_Validator();
		$SHC->encoding = 'UTF-8';
		$SHC->check( utf8_decode('foo äö bar') );
		$this->assertFalse( $SHC->isOK() );

		$SHC = new XHTML_Validator();
		$SHC->encoding = 'ISO-8859-1';
		$SHC->check( utf8_decode('foo äö bar') );
		$this->assertTrue( $SHC->isOK() );

		$SHC = new XHTML_Validator();
		$SHC->encoding = 'ISO-8859-1';
		$SHC->check( 'foo ä bar' );
		$this->assertTrue( $SHC->isOK() );
		$this->assertEqual( $SHC->encoding, 'ISO-8859-1' );
	}


	/**
	 * Test {@link XHTML_Validator::check()}.
	 * NOTE: assignment by "& new" is required for PHP4! See also http://de3.php.net/manual/en/function.xml-set-object.php#46107
	 *       Alternatively, multiple vars for each test may work, or unsetting the last one..
	 */
	function test_htmlchecker_check()
	{
		global $allowed_tags, $allowed_attributes, $uri_attrs, $allowed_uri_scheme;
		global $Messages;

		$SHC = new XHTML_Validator();
		$SHC->check( '<moo>foo</moo>' );
		$this->assertEqual( $GLOBALS['Messages']->messages['error'][0],
			T_('Illegal tag').': <code>moo</code>' );
		$Messages->clear();

		$SHC = new XHTML_Validator();
		$SHC->check( '<img>foo</img>' );
		$this->assertEqual( $GLOBALS['Messages']->messages['error'][0],
			sprintf( T_('Tag &lt;%s&gt; may not contain raw character data'), '<code>img</code>' ) );

	}

}


if( !isset( $this ) )
{ // Called directly, run the TestCase alone
	$test = new ExtLibsTestCase();
	$test->run_html_or_cli();
	unset( $test );
}
?>
