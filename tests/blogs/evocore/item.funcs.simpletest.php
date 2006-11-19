<?php
/**
 * Tests for item functions.
 */

/**
 * SimpleTest config
 */
require_once( dirname(__FILE__).'/../../config.simpletest.php' );


global $inc_path;

require_once( $inc_path.'MODEL/items/_item.funcs.php' );


/**
 * @package tests
 */
class ItemFuncsTestCase extends EvoUnitTestCase
{
	function ItemFuncsTestCase()
	{
		$this->EvoUnitTestCase( 'Item functions test' );
	}


	/**
	 * Test {@link urltitle_validate()}
	 */
	function test_urltitle_validate()
	{
		global $evo_charset;

		$old_evo_charset = $evo_charset;
		$evo_charset = 'ISO-8859-1';
		$this->assertEqual( urltitle_validate( '  ', " :: �� c'est \"VRAIMENT\" t�a! " ), 'ca-c-est-vraiment-toa' );
		$this->assertEqual( urltitle_validate( '', "La subtile diff�rence entre acronym et abbr..._452" ), 'la-subtile-difference-entre-acronym-et-a-452' );

		if( ! can_convert_charsets('ISO-8859-1', 'UTF-8') || ! can_convert_charsets('UTF-8', 'ISO-8859-1') )
		{
			echo "Skipping test (cannot convert)...n";
		}

		$this->assertEqual( urltitle_validate('', '�����'), 'aeoeueue' );

		$evo_charset = 'UTF-8';
		$this->assertEqual( urltitle_validate('', convert_charset('�����', 'UTF-8', 'ISO-8859-1')), 'aeoeueue' );

		$evo_charset = $old_evo_charset;
	}

}


if( !isset( $this ) )
{ // Called directly, run the TestCase alone
	$test = new ItemFuncsTestCase();
	$test->run_html_or_cli();
	unset( $test );
}
?>
