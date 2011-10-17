<?php
/**
 * This file implements the UI view for Post by Email settings.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2011 by Francois Planque - {@link http://fplanque.com/}
 *
 * @package admin
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * @var User
 */
global $current_User;
/**
 * @var GeneralSettings
 */
global $Settings;

global $baseurl, $eblog_test_output, $comment_allowed_tags;


$Form = new Form( NULL, 'remotepublish_checkchanges' );

$Form->begin_form('fform');

$Form->add_crumb( 'globalsettings' );
$Form->hidden( 'ctrl', 'remotepublish' );
$Form->hidden( 'tab', 'eblog' );
$Form->hidden( 'action', 'update' );

$Form->begin_fieldset( T_('Test saved settings') );

	$url = '?ctrl=remotepublish&amp;tab=eblog&amp;'.url_crumb('globalsettings').'&amp;action=';
	$Form->info_field( T_('Perform Server Test'),
				'<a href="'.$url.'test_connection">[ '.T_('connection').' ]</a>
				<a href="'.$url.'test_posting">[ '.T_('messages').' ]</a>' );

	if( !empty($eblog_test_output) )
	{
		echo '<div class="red center" style="margin-top:30px">'.T_('This is just a test run. Nothing will be posted to the database nor will your inbox be altered').'</div>';
		echo '<div style="padding: 6px; margin:5px; border: 1px solid #CCC; overflow:scroll; height: 400px">'.$eblog_test_output.'</div>';
	}
	
$Form->end_fieldset();

$Form->begin_fieldset( T_('General settings').get_manual_link('blog_by_email') );

	$Form->checkbox_input( 'eblog_enabled', $Settings->get('eblog_enabled'), T_('Enable Post by email'),
		array( 'note' => sprintf(T_('Note: This feature needs the php_imap extension (currently %s).' ), extension_loaded('imap') ? T_('loaded') : T_('NOT loaded')) ) );

	$Form->checkbox_input( 'eblog_test_mode', $Settings->get('eblog_test_mode'), T_('Test Mode'),
				array( 'note' => T_('Check to run Post by Email in test mode. Nothing will be posted to the database nor will your inbox be altered.' ) ) );

	// sam2kb> TODO: javascript to preset default eblog_server_port when eblog_method and/or eblog_encrypt change
	$Form->select_input_array( 'eblog_method', $Settings->get('eblog_method'), array( 'pop3' => T_('POP3'), 'imap' => T_('IMAP'), ), // TRANS: E-Mail retrieval method
		T_('Retrieval method'), T_('Choose a method to retrieve the emails.') );

	$Form->text_input( 'eblog_server_host', $Settings->get('eblog_server_host'), 25, T_('Mail Server'), T_('Hostname or IP address of your incoming mail server.'), array( 'maxlength' => 255 ) );

	$Form->text_input( 'eblog_server_port', $Settings->get('eblog_server_port'), 5, T_('Port Number'), T_('Port number of your incoming mail server (Defaults: POP3: 110, IMAP: 143, SSL/TLS: 993).'), array( 'maxlength' => 6 ) );

	$Form->radio( 'eblog_encrypt', $Settings->get('eblog_encrypt'), array(
																		array( 'none', T_('None'), ),
																		array( 'ssl', T_('SSL'), ),
																		array( 'tls', T_('TLS'), ),
																	), T_('Encryption method') );

	$Form->checkbox( 'eblog_novalidatecert', $Settings->get('eblog_novalidatecert'), T_('Do not validate certificate'),
				T_('Do not validate the certificate from the TLS/SSL server. Check this if you are using a self-signed certificate.') );

	$Form->text_input( 'eblog_username', $Settings->get('eblog_username'), 25,
				T_('Account Name'), T_('User name for authenticating on your mail server. Usually it\'s your email address or a part before the @ sign.'), array( 'maxlength' => 255 ) );

	$Form->password_input( 'eblog_password', $Settings->get('eblog_password'), 25,
				T_('Password'), array( 'maxlength' => 255, 'note' => T_('Password for authenticating on your mail server.') ) );

	$Form->checkbox( 'eblog_delete_emails', $Settings->get('eblog_delete_emails'), T_('Delete processed emails'),
				T_('Check this if you want processed messages to be deleted form server after successfull posting.') );

$Form->end_fieldset();

$Form->begin_fieldset( T_('Posting settings') );

	// TODO: provide a list of categories to choose from
	$Form->text_input( 'eblog_default_category', $Settings->get('eblog_default_category'), 5,
				T_('Default Category ID'), sprintf( T_('This is a default category for your posts.').'<br />'.
				T_('You can use the following code in message body to define categories: %s. In this example 2 will be main category and 5, 47 extra categories.'), '<b>&lt;category&gt;2, 5, 47&lt;/category&gt;</b>' ), array( 'maxlength' => 6 ) );

	$Form->text_input( 'eblog_default_title', $Settings->get('eblog_default_title'), 50,
				T_('Default title'), sprintf( T_('This is a default title for your posts.').'<br />'.
				T_('You can use the following code in message body to define post title: %s.'), '<b>&lt;title&gt;Post title here&lt;/title&gt;</b>' ), array( 'maxlength' => 255 ) );

	$Form->checkbox( 'eblog_add_imgtag', $Settings->get('eblog_add_imgtag'), T_('Add &lt;img&gt; tags'),
				T_('Display image attachments using &lt;img&gt; tags (instead of linking them through file manager).') );

	$Form->checkbox( 'eblog_autobr', $Settings->get('eblog_autobr'), T_('Auto-BR'),
				T_('Add &lt;br /&gt; tags at line endings. This setting is for <b>plain text</b> messages only.') );

	$Form->text_input( 'eblog_subject_prefix', $Settings->get('eblog_subject_prefix'), 15,
				T_('Subject Prefix'), T_('Email subject must start with this prefix to be imported, messages that don\'t have this tag will be skipped.'), array( 'maxlength' => 255 ) );

	$Form->text_input( 'eblog_body_terminator', $Settings->get('eblog_body_terminator'), 15,
				T_('Body Terminator'), T_('Starting from this string, everything will be ignored, including this string.').
				'<br />'.T_('You can use this to remove signature from message body.'), array( 'maxlength' => 255 ) );

$Form->end_fieldset();

$Form->begin_fieldset( T_('HTML messages') );

// sam2kb> TODO: display some warning message about potential risk with HTML emails
$Form->checkbox( 'eblog_html_enabled', $Settings->get('eblog_html_enabled'), T_('Enable HTML messages'),
				T_('Check this if you want HTML messages to be processed and posted in your blog.') );

$Form->checkbox( 'eblog_html_tag_limit', $Settings->get('eblog_html_tag_limit'), T_('Limit allowed tags'),
				T_('Check this if you want to limit allowed HTML tags to the following list:').
				'<br /><b>'.htmlspecialchars(str_replace( '>', '> ', $comment_allowed_tags )).'</b>' );

$Form->end_fieldset();

if( $current_User->check_perm( 'options', 'edit' ) )
{
	$Form->end_form( array(
		array( 'submit', '', T_('Update'), 'SaveButton' ),
		array( 'reset', '', T_('Reset'), 'ResetButton' ),
		) );
}


/*
 * $Log$
 * Revision 1.1  2011/10/17 20:16:52  sam2kb
 * Post by Email converted into internal scheduled job
 *
 */
?>