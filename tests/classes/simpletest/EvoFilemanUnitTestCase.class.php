<?php
/**
 * This file implements the class for Filemanager unit tests.
 */


/**
 * The class for Filemanager unit tests.
 */
class EvoFilemanUnitTestCase extends EvoUnitTestCase
{
	/**
	 * Remember created files.
	 */
	var $tempFiles = array();


	/**
	 * Create global mocked $Settings object.
	 */
	function setUp()
	{
		parent::setUp();

		Mock::generate('GeneralSettings');

		$this->old_Settings_EvoFilemanUnitTestCase = & $GLOBALS['Settings'];
		$GLOBALS['Settings'] = new MockGeneralSettings();
		$GLOBALS['Settings']->setReturnValue( 'get', 1, array( 'fm_enable_roots_user' ) );
		$GLOBALS['Settings']->setReturnValue( 'get', '775', array( 'fm_default_chmod_dir' ) );
		$GLOBALS['Settings']->setReturnValue( 'get', '664', array( 'fm_default_chmod_file' ) );
	}


	/**
	 * Restore $Settings global
	 */
	function tearDown()
	{
		$GLOBALS['Settings'] = & $this->old_Settings_EvoFilemanUnitTestCase;
		$this->unlinkCreatedFiles();

		parent::tearDown();
	}


	/**
	 * Create a file for a given user.
	 *
	 * @return string|false the file name of the created file
	 */
	function createUserFile( $content = '', $name = '', $user_ID = 1 )
	{
		global $FileRootCache;

		$FileRoot = & $FileRootCache->get_by_type_and_ID( 'user', $user_ID, true );

		if( ! $FileRoot )
		{
			$this->my_skip_message( 'Cannot get FileRoot for user #'.$user_ID );
			return false;
		}

		if( empty($name) )
		{
			$name = basename( tempnam( $FileRoot->ads_path, 'TMP' ) );
		}

		return $this->createFile( $FileRoot->ads_path.$name, $content );
	}


	/**
	 * Create a temp file in TMPDIR.
	 *
	 * @param string Content to write into the file
	 * @param string Name of the file in TMPDIR
	 * @return false|string The filename
	 */
	function createTempFile( $content = '', $name = NULL )
	{
		if( $name === NULL )
		{
			$filepath = tempnam( TMPDIR, 'TMP' );
		}
		else
		{
			$filepath = TMPDIR.$name;
		}

		return $this->createFile( $filepath, $content, $size );
	}


	/**
	 * Create a file.
	 *
	 * @param string Path of the file to write to
	 * @param string Content to write into the file
	 * @return false|string The filename
	 */
	function createFile( $path, $content = '' )
	{
		if( !($fh = @fopen( $path, 'w' )) )
		{
			$this->my_skip_message( "Cannot create file '$path'!" );
			return false;
		}

		fwrite( $fh, $content );
		fclose( $fh );

		$this->tempFiles[] = $path;

		return $path;
	}


	/**
	 * Unlink created temp files.
	 *
	 * Call it in {@link tearDown()} if you use {@link createTempFile()}.
	 */
	function unlinkCreatedFiles()
	{
		while( $tempPath = array_pop( $this->tempFiles ) )
		{
			@unlink( $tempPath );
		}

		parent::tearDown();
	}
}

?>
