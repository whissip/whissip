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
 * Set to 1 to enable localization.
 *
 * @global integer
 */
$use_l10n = 1;


/**
 * Default locale used for backoffice (when we cannot autodetect) and fallback.
 * This will be overwritten from database settings, if configured there.
 * These use an ISO 639 language code, a '-' and an ISO 3166 country code.
 *
 * This MUST BE in the list below.
 *
 * @todo this should actually be used by the installer only. After that we should use the value from the DB.
 *
 * @global string
 */
$default_locale = 'en-US';


/**
 * Load locale related functions: (we need NT_() here)
 */
require_once $inc_path.'locales/_locale.funcs.php';


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
 *
 * @todo Locale message dirs should be named LOCALE.CHARSET and not LOCALE_CHARSET, e.g. "zh_CN.utf8" instead of "zh_CN_utf-8" (according to gettext)
 * @todo fp>Actually, the default locale setting should move to install and we should always use the database after that. What were we smoking when we did that? :P
 */
$locales['en-US'] = array(
		'name' => NT_('English (US) utf-8'),
		'charset' => 'utf-8',
		'datefmt' => 'm/d/y',
		'timefmt' => 'h:i:s a',
		'startofweek' => 0,
		'messages' => 'en_US',
		'enabled' => false,	// We need this line to prevent notices in locales conf screen and user profile screen.
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
