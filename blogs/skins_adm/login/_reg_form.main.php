<?php
/**
 * This is the registration form
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2010 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * {@internal License choice
 * - If you have received this file as part of a package, please find the license.txt file in
 *   the same folder or the closest folder above for complete license terms.
 * - If you have received this file individually (e-g: from http://evocms.cvs.sourceforge.net/)
 *   then you must choose one of the following licenses before using the file:
 *   - GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *   - Mozilla Public License 1.1 (MPL) - http://www.opensource.org/licenses/mozilla1.1.php
 * }}
 *
 * {@internal Open Source relicensing agreement:
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package htsrv
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author blueyed: Daniel HAHLER
 * @author fplanque: Francois PLANQUE.
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( 'regional/model/_country.class.php', 'Country' );

/**
 * Include page header:
 */
$page_title = T_('Register form');
$page_icon = 'icon_register.gif';
require dirname(__FILE__).'/_html_header.inc.php';


$Form = new Form( $htsrv_url_sensitive.'register.php', '', 'post', 'fieldset' );

$Form->begin_form( 'fform' );

$Form->add_crumb( 'regform' );
$Form->hidden( 'action', 'register' );
$Form->hidden( 'source', $source );
$Form->hidden( 'redirect_to', url_rel_to_same_host($redirect_to, $htsrv_url_sensitive) );

$Form->begin_fieldset();

$Form->text_input( 'login', $login, 16,  T_('Login'), '', array( 'maxlength'=>20, 'class'=>'input_text', 'required'=>true ) );

// TODO: dh> display param errors with pass1 and pass2..
?>

	<fieldset>
		<div class="label"><label for="pass1"><?php echo T_('Password') ?><br /><?php echo T_('(twice)').'<br />' ?></label></div>
		<div class="input">
		<input type="password" name="pass1" id="pass1" size="16" maxlength="50" value="" class="input_text field_required" />
		<input type="password" name="pass2" id="pass2" size="16" maxlength="50" value="" class="input_text field_required" />
		<span class="notes"><?php printf( T_('Minimum %d characters, please.'), $Settings->get('user_minpwdlen') ) ?></span>
		</div>
	</fieldset>

	<?php
	$Form->text_input( 'email', $email, 16, T_('Email'), '', array( 'maxlength'=>255, 'class'=>'input_text', 'required'=>true ) );

	$registration_require_country = (bool)$Settings->get('registration_require_country');

	if( $registration_require_country )
	{
		$CountryCache = & get_CountryCache();
		$Form->select_input_object( 'country', $country, $CountryCache, 'Country', array('allow_none'=>true, 'required'=>true) );
	}

	$registration_require_gender = $Settings->get( 'registration_require_gender' );
	if( $registration_require_gender == 'required' )
	{
		$Form->radio_input( 'gender', false, array(
					array( 'value' => 'M', 'label' => T_('Male') ),
					array( 'value' => 'F', 'label' => T_('Female') ),
				), T_('Gender'), array( 'required' => true ) );
	}

	$Form->select( 'locale', $locale, 'locale_options_return', T_('Locale'), T_('Preferred language') );

	$Plugins->trigger_event( 'DisplayRegisterFormFieldset', array( 'Form' => & $Form ) );

	$Form->buttons_input( array( array('name'=>'submit', 'value'=>T_('Register!'), 'class'=>'ActionInput') ) );

$Form->end_fieldset();
$Form->end_form(); // display hidden fields etc
?>

<div style="text-align:right">
	<a href="<?php echo $htsrv_url_sensitive.'login.php?redirect_to='.rawurlencode(url_rel_to_same_host($redirect_to, $htsrv_url_sensitive)) ?>"><?php echo T_('Log into existing account...') ?></a>
</div>

<?php
require dirname(__FILE__).'/_html_footer.inc.php';

/*
 * $Log$
 * Revision 1.16  2011/02/17 14:56:38  efy-asimo
 * Add user source param
 *
 * Revision 1.15  2010/11/24 16:05:52  efy-asimo
 * User country and gender options modifications
 *
 * Revision 1.14  2010/11/24 14:55:30  efy-asimo
 * Add user gender
 *
 * Revision 1.13  2010/02/08 17:56:56  efy-yury
 * copyright 2009 -> 2010
 *
 * Revision 1.12  2010/01/03 13:45:37  fplanque
 * set some crumbs (needs checking)
 *
 * Revision 1.11  2009/10/10 21:43:09  tblue246
 * cleanup
 *
 * Revision 1.10  2009/09/26 12:00:44  tblue246
 * Minor/coding style
 *
 * Revision 1.9  2009/09/25 07:33:31  efy-cantor
 * replace get_cache to get_*cache
 *
 * Revision 1.8  2009/09/16 06:55:13  efy-bogdan
 * Require country checkbox added
 *
 * Revision 1.7  2009/03/08 23:58:09  fplanque
 * 2009
 *
 * Revision 1.6  2008/01/21 09:35:43  fplanque
 * (c) 2008
 *
 * Revision 1.5  2008/01/14 23:41:48  fplanque
 * cleanup load_funcs( urls ) in main because it is ubiquitously used
 *
 * Revision 1.4  2008/01/06 17:10:58  blueyed
 * Fix call to undefined function when accessing register.php and _url.funcs.php has not been loaded
 *
 * Revision 1.3  2007/12/09 22:59:22  blueyed
 * login and register form: Use Form::buttons_input for buttons
 *
 * Revision 1.2  2007/12/09 03:12:34  blueyed
 * Fix layout of register form
 *
 * Revision 1.1  2007/06/25 11:02:40  fplanque
 * MODULES (refactored MVC)
 *
 * Revision 1.13  2007/04/26 00:11:10  fplanque
 * (c) 2007
 *
 * Revision 1.12  2007/02/12 00:20:41  blueyed
 * Pass redirect_to param to "Login..." link
 *
 * Revision 1.11  2006/12/09 01:55:36  fplanque
 * feel free to fill in some missing notes
 * hint: "login" does not need a note! :P
 *
 * Revision 1.10  2006/11/24 18:27:26  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.9  2006/10/15 21:30:46  blueyed
 * Use url_rel_to_same_host() for redirect_to params.
 *
 * Revision 1.8  2006/06/25 23:34:15  blueyed
 * wording pt2
 *
 * Revision 1.7  2006/06/25 23:23:38  blueyed
 * wording
 *
 * Revision 1.6  2006/06/22 22:30:04  blueyed
 * htsrv url for password related scripts (login, register and profile update)
 *
 * Revision 1.5  2006/04/22 01:57:36  blueyed
 * adjusted maxlength for email
 *
 * Revision 1.4  2006/04/21 16:56:36  blueyed
 * Mark fields as required; small fix (double-encoding)
 *
 * Revision 1.3  2006/04/19 20:13:52  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 */
?>