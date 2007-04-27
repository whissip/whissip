<?php
/**
 * This is b2evolution's localization & language config file
 *
 * This file sets the default configuration for locales.
 * IMPORTANT: Most of these settings can be overriden in the admin (regional settings) and will then
 * be saved to the database. The database settings superseede settings in this file.
 * Last significant changes to this file: version 1.6
 *
 * @package conf
 */
if( !defined('EVO_CONFIG_LOADED') ) die( 'Please, do not access this page directly.' );


/**
 * Enable localization?
 *
 * Set to 0 to disable localization.
 * Set to 1 to enable gettext localization if supported (not recommended).
 *    Note: you will have to compile the .po files with msgfmt before this will work.
 *          Also, the locale information has to be available on the system ("locale -a" with Unix)
 * Set to 2 to enable b2evo advanced localization (recommended).
 *
 * @global integer
 */
$use_l10n = 2;


/**
 * The internal charset. It's used to convert user INPUT/OUTPUT and database data into for
 * internal use.
 *
 * Setting it to an empty string means "follow the user's charset", which gets
 * taken off his locale (INPUT/OUTPUT charset; {@link $io_charset}).
 *
 * If you don't know, don't change this setting.
 *
 * This should be supported by {@link mb_list_encodings()}.
 */
$evo_charset = '';


/**
 * Set this to a specific charset, to force this as {@link $io_charset I/O charset},
 * if the browser accepts it.
 *
 * Setting this to "utf-8" allows you to deliver all pages in this encoding.
 *
 * NOTE: make sure, that your PHP/MySQL setup supports this. You most probably need
 *       the mbstring PHP extension and MySQL 4.1 for this to work.
 *
 * @global string
 */
$force_io_charset_if_accepted = '';


/**
 * Request a specific charset for the client connection.
 *
 * This will issue a MySQL SET NAMES command. This must be a MySQL charset. Example: 'latin1' or 'utf8'
 *
 * If left empty, the default charset will be used. The default here is
 * the default set your MySQL Server.
 *
 * This should match the charset you are using internally in b2evolution.
 * This allows b2evo to work internally in a different charset from the database charset.
 * Example: b2evo will use latin1 whereas the database uses utf8.
 *
 * TODO: This gets overridden anyway with "SET NAMES $evo_charset" in init_charsets() and gets only used until that!
 *       So, does it make sense to configure it here? Or shouldn't it get overridden, if set explicitly here?
 */
$db_config['connection_charset'] = '';


/**
 * Default locale used for backoffice (when we cannot autodetect) and fallback.
 * This will be overwritten from database settings, if configured there.
 * These use an ISO 639 language code, a '-' and an ISO 3166 country code.
 *
 * This MUST BE in the list below.
 *
 * @global string
 */
$default_locale = 'en-US';


/**
 * Load locale related functions: (we need NT_() here)
 */
require_once $model_path.'settings/_locale.funcs.php';


/**
 * Defining the locales:
 * These are the default settings.
 * This array will be overwritten from DB if locales are set there,
 * that is when they get updated from the Backoffice.
 * They are also used as fallback, if we have no access to the DB yet.
 * Flag source: http://www.crwflags.com/fotw/flags/iso3166.html
 * IMPORTANT: Try to keep the locale names short, they take away valuable space on the screen!
 *
 * Documentation of the keys:
 *  - 'messages':
 *    The directory where the locale's files are.
 *  - 'charset':
 *    Character set of the locale's files.
 *  - 'set_locales':
 *    This gets used for {@link setlocale()} (currently only when using gettext support [$use_l10n=1]).
 *    This is a list of locales that get tried. One of them must be available on the system ("locale -a").
 *    If not given, the value of 'messages' gets used.
 *
 * @todo Locale message dirs should be named LOCALE.CHARSET and not LOCALE_CHARSET, e.g. "zh_CN.utf8" instead of "zh_CN_utf-8" (according to gettext)
 * @todo fp>Actually, the default locale setting should move to install and we should always use the database after that. What were we smoking when we did that? :P
 */
$locales = array(
	'cs-CZ' => array( 'name' => NT_('Czech (CZ)'),
										'charset' => 'utf-8',
										'datefmt' => 'd. m. y',
										'timefmt' => 'H.i:s',
										'startofweek' => 1,
										'messages' => 'cs_CZ',
										'enabled' => 1,
									),
	'da-DK' => array( 'name' => NT_('Danish (DK)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd/m/y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'da_DK',
										'enabled' => 1,
									),
	'de-DE' => array( 'name' => NT_('German (DE)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd.m.y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'de_DE',
										'enabled' => 1,
									),
	'ee-ET' => array( 'name' => NT_('Estonia (ET)'),
										'charset' => 'utf-8',
										'datefmt' => 'd/m/Y',
										'timefmt' => 'H.i:s',
										'startofweek' => 1,
										'messages' => 'ee_ET',
										'enabled' => 1,
									),
	'en-EU' => array( 'name' => NT_('English (EU)'), // not really a valid ISO-3166 code
										'charset' => 'iso-8859-1',
										'datefmt' => 'Y-m-d',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'en_EU',
										'enabled' => 0,
									),
	'en-GB' => array( 'name' => NT_('English (GB)'), // correct ISO-3166 code
										'charset' => 'iso-8859-1',
										'datefmt' => 'd/m/y',
										'timefmt' => 'h:i:s a',
										'startofweek' => 1,
										'messages' => 'en_GB',
										'enabled' => 1,
									),
	'en-UK' => array( 'name' => NT_('English (UK)'), // incorrect ISO-3166 code
										'charset' => 'iso-8859-1',
										'datefmt' => 'd/m/y',
										'timefmt' => 'h:i:s a',
										'startofweek' => 1,
										'messages' => 'en_UK',
										'enabled' => 0,
									),
	'en-US' => array( 'name' => NT_('English (US)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'm/d/y',
										'timefmt' => 'h:i:s a',
										'startofweek' => 0,
										'messages' => 'en_US',
										'enabled' => 1,
									),
	'en-CA' => array( 'name' => NT_('English (CA)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'm/d/y',
										'timefmt' => 'h:i:s a',
										'startofweek' => 0,
										'messages' => 'en_CA',
										'enabled' => 1,
									),
	'en-AU' => array( 'name' => NT_('English (AU)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd/m/y',
										'timefmt' => 'h:i:s a',
										'startofweek' => 1,
										'messages' => 'en_AU',
										'enabled' => 1,
									),
	'en-IL' => array( 'name' => NT_('English (IL)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'Y-m-d',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'en_IL',
										'enabled' => 1,
									),
	'en-NZ' => array( 'name' => NT_('English (NZ)'), // New Zealand
										'charset' => 'iso-8859-1',
										'datefmt' => 'd/m/y',
										'timefmt' => 'h:i:s a',
										'startofweek' => 1,
										'messages' => 'en_NZ',
										'enabled' => 1,
									),
	'en-SG' => array( 'name' => NT_('English (SG)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd/m/y',
										'timefmt' => 'H:i:s a',
										'startofweek' => 0,
										'messages' => 'en_SG',
										'enabled' => 1,
									),
	'es-ES' => array( 'name' => NT_('Spanish (ES)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd.m.y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'es_ES',
										'enabled' => 1,
									),
	'es-MX' => array( 'name' => NT_('Spanish (MX)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd.m.y',
										'timefmt' => 'H:i:s',
										'startofweek' => 0,
										'messages' => 'es_MX',
										'enabled' => 1,
									),
	'es-VE' => array( 'name' => NT_('Spanish (VE)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd/m/Y',
										'timefmt' => 'h:i:s a',
										'startofweek' => 1,
										'messages' => 'es_VE',
										'enabled' => 1,
									),
	'eu-ES' => array( 'name' => NT_('Basque (ES)'),
					          'charset' => 'iso-8859-1',
					          'datefmt' => 'y.m.d',
					          'timefmt' => 'H:i:s',
										'startofweek' => 1,
					          'messages' => 'eu_ES',
					          'enabled' => 1,
					         ),
	'fi-FI' => array( 'name' => NT_('Finnish (FI)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd.m.Y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'fi_FI',
										'enabled' => 1,
									),
	'fr-FR' => array( 'name' => NT_('French (FR)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd.m.y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'fr_FR',
										'enabled' => 1,
									),
	'fr-CA' => array( 'name' => NT_('French (CA)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'm/d/y',
										'timefmt' => 'h:i:s a',
										'startofweek' => 0,
										'messages' => 'fr_FR',
										'enabled' => 1,
									),
	'fr-BE' => array( 'name' => NT_('French (BE)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd/m/y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'fr_FR',
										'enabled' => 1,
									),
	'gl-ES' => array( 'name' => NT_('Galician (ES)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd.m.y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'gl_ES',
										'enabled' => 1,
									),
	'hu-HU' => array( 'name' => NT_('Hungarian (HU)'),
										'charset' => 'iso-8859-2',
										'datefmt' => 'Y. M. d.',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'hu_HU',
										'enabled' => 1,
									),
	'it-IT' => array( 'name' => NT_('Italian (IT)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd.m.y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'it_IT',
										'enabled' => 1,
									),
	'is-IS' => array( 'name' => NT_('Icelandic (IS)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd.m.y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'is_IS',
										'enabled' => 1,
									),
	'ja-JP' => array( 'name' => NT_('Japanese (JP)'),
										'charset' => 'utf-8',
										'datefmt' => 'Y/m/d',
										'timefmt' => 'H:i:s',
										'startofweek' => 0,
										'messages' => 'ja_JP',
										'enabled' => 1,
									),
	'lt-LT' => array( 'name' => NT_('Lithuanian (LT)'),
										'charset' => 'utf-8',
										'datefmt' => 'Y-m-d',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'lt_LT',
										'enabled' => 1,
									),
	'lv-LV' => array( 'name' => NT_('Latvian (LV)'),
										'charset' => 'utf-8',
										'datefmt' => 'd.m.y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'lv_LV',
										'enabled' => 1,
									),
	'nb-NO' => array( 'name' => NT_('Bokm&aring;l (NO)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd.m.y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'nb_NO',
										'enabled' => 1,
									),
	'nl-NL' => array( 'name' => NT_('Dutch (NL)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd-m-y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'nl_NL',
										'enabled' => 1,
									),
	'nl-BE' => array( 'name' => NT_('Dutch (BE)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd/m/y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'nl_NL',
										'enabled' => 1,
									),
	'pt-BR' => array( 'name' => NT_('Portuguese (BR)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd.m.y',
										'timefmt' => 'H:i:s',
										'startofweek' => 0,
										'messages' => 'pt_BR',
										'enabled' => 1,
									),
	'pl-PL-utf-8' => array( 'name' => NT_('Polish utf-8 (PL)'),
										'charset' => 'utf-8',
										'datefmt' => 'd/m/Y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'pl_PL',
										'enabled' => 1,
									),
	'pt-PT' => array(	'name' => NT_('Portuguese (PT)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'd-m-y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'pt_PT',
										'enabled' => 1,
									),
	'ru-RU' => array( 'name' => NT_('Russian (RU)'),
										'charset' => 'utf-8',
										'datefmt' => 'Y-m-d',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'ru_RU',
										'enabled' => 1,
									),
	'ru-RU-cp1251' => array( 'name' => NT_('Russian cp1251 (RU)'),
										'charset' => 'windows-1251',
										'datefmt' => 'Y-m-d',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'ru_RU_cp1251',
										'enabled' => 1,
									),
	'sk-SK' => array( 'name' => NT_('Slovak (SK)'),
										'charset' => 'utf-8',
										'datefmt' => 'd.m.Y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'sk_SK',
										'enabled' => 1,
									),
	'sv-SE' => array( 'name' => NT_('Swedish (SE)'),
										'charset' => 'iso-8859-1',
										'datefmt' => 'y-m-d',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'sv_SE',
										'enabled' => 1,
									),
	'th-TH' => array( 'name' => NT_('Thai (TH)'),
										'charset' => 'utf-8',
										'datefmt' => 'd/m/Y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'th_TH',
										'enabled' => 1,
									),
	'tr-TR' => array(	'name' => NT_('Turkish (TR)'),
										'charset' => 'iso-8859-9',
										'datefmt' => 'd/m/y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'tr_TR',
										'enabled' => 1,
									),
	'tr-TR-utf-8' => array(	'name' => NT_('Turkish utf-8 (TR)'),
										'charset' => 'utf-8',
										'datefmt' => 'd/m/y',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'tr_TR_utf-8',
										'enabled' => 0,
									),
	'zh-CN' => array( 'name' => NT_('Chinese(S) gb2312 (CN)'),
										'charset' => 'gb2312',
										'datefmt' => 'y-m-d',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'zh_CN',
										'enabled' => 1,
									),
	'zh-CN-utf-8' => array( 'name' => NT_('Chinese(S) utf-8 (CN)'),
										'charset' => 'utf-8',
										'datefmt' => 'y-m-d',
										'timefmt' => 'H:i:s',
										'startofweek' => 1,
										'messages' => 'zh_CN_utf-8',
										'enabled' => 0,
									),
/* No correct flag...
		'zh-HK' => array( 'name' => NT_('Trad. Chinese (HK)'),
										'charset' => 'utf-8',
										'datefmt' => 'd/m/y',
										'timefmt' => 'H:i:s',
										'startofweek' => 0,
										'messages' => 'zh_TW',
										'enabled' => 1,
									),
*/
	'zh-TW' => array( 'name' => NT_('Trad. Chinese (TW)'),
										'charset' => 'utf-8',
										'datefmt' => 'Y-m-d',
										'timefmt' => 'H:i:s',
										'startofweek' => 0,
										'messages' => 'zh_TW',
										'enabled' => 1,
									),
);


/**
 * Set this to 1 if you are a translator and wish to extract strings from your .po file.
 * Warning: do *not* extract .PO files you have not edited yourself.
 * Shipped .PO files contain automatic translations that have *not* been reviewed.
 * 
 * @todo fp>This should be moved to the backoffice.
 * 
 * @global boolean
 */
$allow_po_extraction = 0;

?>