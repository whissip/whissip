<?php
/**
 * This is the template that displays the user profile page.
 *
 * This file is not meant to be called directly.
 * It is meant to be called by an include in the main.page.php template.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2013 by Francois Planque - {@link http://fplanque.com/}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 *
 * @package evoskins
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
* @var Blog
*/
global $Blog;
/**
 * @var GeneralSettings
 */
global $Settings;
/**
 * @var Current User
 */
global $current_User;

// init is logged in status
$is_logged_in = is_logged_in();

// Default params:
$params = array_merge( array(
		'profile_avatar_before'            => '<span style="position:absolute;right:1em">',
		'profile_avatar_after'             => '</span>',
		'avatar_image_size'                => 'fit-160x160',
		'avatar_image_size_if_anonymous'   => 'fit-160x160-blur-18',
		'avatar_overlay_text_if_anonymous' => '#default#',
		'edit_my_profile_link_text'        => T_('Edit my profile').' &raquo;',
		'edit_user_admin_link_text'        => T_('Edit this user in backoffice'),
		'skin_form_params'                 => array(),
	), $params );


// ------------------- PREV/NEXT USER LINKS (SINGLE USER MODE) -------------------
user_prevnext_links( array(
		'block_start'  => '<table class="prevnext_user"><tr>',
		'prev_start'   => '<td width="33%">',
		'prev_end'     => '</td>',
		'prev_no_user' => '<td width="33%">&nbsp;</td>',
		'back_start'   => '<td width="33%" class="back_users_list">',
		'back_end'     => '</td>',
		'next_start'   => '<td width="33%" class="right">',
		'next_end'     => '</td>',
		'next_no_user' => '<td width="33%">&nbsp;</td>',
		'block_end'    => '</tr></table>',
	) );
// ------------------------- END OF PREV/NEXT USER LINKS -------------------------


$user_ID = param( 'user_ID', 'integer', '' );
if( empty($user_ID) )
{	// Grab the current User
	$user_ID = $current_User->ID;
}

$UserCache = & get_UserCache();
/**
 * @var User
 */
$User = & $UserCache->get_by_ID( $user_ID );

/**
 * form to update the profile
 * @var Form
 */
$ProfileForm = new Form( get_dispctrl_url( 'contacts' ), 'ProfileForm' );

$ProfileForm->switch_template_parts( $params['skin_form_params'] );

if( $is_logged_in )
{ // user is logged in
	if( $current_User->check_perm( 'users', 'edit' ) && $current_User->check_status( 'can_access_admin' ) )
	{ // Current user can edit other user's profiles
		global $admin_url;
		echo '<div class="edit_user_admin_link">';
		echo '<a href="'.url_add_param( $admin_url, 'ctrl=user&amp;user_ID='.$User->ID ).'">'.$params['edit_user_admin_link_text'].'</a>';
		echo '</div>';
	}

	if( $User->ID == $current_User->ID && !empty($params['edit_my_profile_link_text']) )
	{	// Display edit link profile for owner:
		echo '<div class="floatright">';
		echo '<a href="'.url_add_param( $Blog->get('url'), 'disp=profile' ).'">'.$params['edit_my_profile_link_text'].'</a>';
		echo '</div>';
	}
}

echo '<div class="clear"></div>';

$ProfileForm->begin_form( 'bComment' );

$avatar_overlay_text = '';
if( $is_logged_in )
{	// Avatar size for logged in user
	$avatar_image_size = $params['avatar_image_size'];
}
else
{	// Avatar settings for anonymous user
	$avatar_image_size = $params['avatar_image_size_if_anonymous'];
	if( $params['avatar_overlay_text_if_anonymous'] != '#default#' )
	{	// Get overlay text from params
		$avatar_overlay_text = $params['avatar_overlay_text_if_anonymous'];
	}
	else
	{	// Get default overlay text from Back-office settings
		$avatar_overlay_text = $Settings->get('bubbletip_overlay');
	}
}

$avatar_imgtag = $params['profile_avatar_before'].$User->get_avatar_imgtag( $avatar_image_size, '', '', true, $avatar_overlay_text, 'user' ).$params['profile_avatar_after'];

if( $is_logged_in )
{
	if( $User->ID == $current_User->ID && ! $User->has_avatar() )
	{	// If user hasn't an avatar, add a link to go for uploading of avatar
		$avatar_imgtag = '<a href="'.get_user_avatar_url().'">'.$avatar_imgtag.'</a>';
	}
}

echo $avatar_imgtag;

$ProfileForm->begin_fieldset( T_('Identity') );

echo '<div class="profile_pictured_fieldsets">';

	$login_note = '';
	if( $is_logged_in && ( $User->ID == $current_User->ID ) && ( $User->check_status( 'can_be_validated' ) ) )
	{ // Remind the current user that he has not activated his account yet:
		$login_note = '<span style="color:red; font-weight:bold">[<a style="color:red" href="'.get_activate_info_url().'">'.T_('Not activated').'</a>]</span>';
	}

	$ProfileForm->info( T_('Login'), $User->get_colored_login(), $login_note );

	if( $Settings->get( 'firstname_editing' ) != 'hidden' && $User->get( 'firstname' ) != '' )
	{
		$ProfileForm->info( T_('First Name'), $User->get( 'firstname' ) );
	}

	if( $Settings->get( 'lastname_editing' ) != 'hidden' && $User->get( 'lastname' ) != '' )
	{
		$ProfileForm->info( T_('Last Name'), $User->get( 'lastname' ) );
	}

	$ProfileForm->info( T_( 'I am' ), $User->get_gender() );

	if( ! empty( $User->ctry_ID ) && user_country_visible() )
	{	// Display country
		load_class( 'regional/model/_country.class.php', 'Country' );
		$ProfileForm->info( T_( 'Country' ), $User->get_country_name() );
	}

	if( ! empty( $User->rgn_ID ) && user_region_visible() )
	{	// Display region
		load_class( 'regional/model/_region.class.php', 'Region' );
		$ProfileForm->info( T_( 'Region' ), $User->get_region_name() );
	}

	if( ! empty( $User->subrg_ID ) && user_subregion_visible() )
	{	// Display sub-region
		load_class( 'regional/model/_subregion.class.php', 'Subregion' );
		$ProfileForm->info( T_( 'Sub-region' ), $User->get_subregion_name() );
	}

	if( ! empty( $User->city_ID ) && user_city_visible() )
	{	// Display city
		load_class( 'regional/model/_city.class.php', 'City' );
		$ProfileForm->info( T_( 'City' ), $User->get_city_name() );
	}

	if( ! empty( $User->age_min ) || ! empty( $User->age_max ) )
	{
		$ProfileForm->info( T_( 'My age group' ), sprintf( T_('from %d to %d years old'), $User->get('age_min'), $User->get('age_max') ) );
	}

	$contacts = array();
	if( $is_logged_in && ( $current_User->ID != $User->ID ) && ( $current_User->check_perm( 'perm_messaging', 'reply' ) ) )
	{ // User is logged in, has messaging access permission and is not the same user as displayed user
		$is_contact = check_contact( $User->ID );
		if( $is_contact )
		{ // displayed user is on current User contact list
			$contacts[] = action_icon( T_('On my contacts list'), 'allowback', url_add_param( $Blog->gen_blogurl(), 'disp=contacts' ), T_('On my contacts list'), 3, 4, array(), array( 'style' => 'margin: 0 2px' ) );
			$contacts_groups = get_contacts_groups_list( $User->ID );
		}
		elseif( $is_contact !== NULL )
		{ // displayed user is on current User contact list but it's blocked by current User
			$contacts[] = get_icon( 'file_not_allowed', 'imgtag', array( 'style' => 'margin-left: 4px' ) ).T_('You have blocked this user').' - <a href="'.url_add_param( $Blog->gen_blogurl(), 'disp=contacts&amp;action=unblock&amp;user_ID='.$User->ID.'&amp;'.url_crumb( 'messaging_contacts' ) ).'">'.T_('Unblock').'</a>';
		}
		elseif( $current_User->check_status( 'can_edit_contacts' ) )
		{ // user is not in current User contact list, so allow "Add to my contacts" action, but only if current User is activated
			$contacts[] = action_icon( T_('Add to my contacts'), 'add', url_add_param( $Blog->gen_blogurl(), 'disp=contacts&amp;action=add_user&amp;user_ID='.$User->ID.'&amp;'.url_crumb( 'messaging_contacts' ) ), T_('Add to my contacts'), 3, 4, array(), array( 'style' => 'margin: 0 2px 0 0' ) );
		}
	}

	$msgform_url = $User->get_msgform_url( $Blog->get('msgformurl') );
	if( !empty($msgform_url) )
	{
		$msgform_url = url_add_param( $msgform_url, 'msg_type=PM' );
		$contacts[] = action_icon( T_('Send a message'), 'email', $msgform_url, T_('Send a message'), 3, 4, array(), array( 'style' => 'margin: 2px' ) );
	}
	else
	{ // No message form possibility to contact with User, get the reason why
		$contacts[] = $User->get_no_msgform_reason();
	}

	$ProfileForm->info( T_('Contact'), implode( '<br />', $contacts ) );

echo '</div>';

	if( $is_logged_in && $current_User->check_status( 'can_view_user', $user_ID ) )
	{ // Display other pictures, but only for logged in and activated users:
		$user_avatars = $User->get_avatar_Files();
		if( count( $user_avatars ) > 0 )
		{
			$info_content = '';
			foreach( $user_avatars as $uFile )
			{
				$info_content .= $uFile->get_tag( '<div class="avatartag">', '', '', '</div>', 'crop-top-80x80', 'original', $User->login, 'lightbox[user]' );
			}
			$info_content .= '<div class="clear"></div>';
			$ProfileForm->info( T_('Other pictures'), $info_content );
		}
	}

	if( $is_logged_in && $current_User->check_status( 'can_edit_contacts' ) && $current_User->check_perm( 'perm_messaging', 'reply' ) )
	{ // user is logged in, the account was activated and has the minimal messaging permission
		$ProfileForm->add_crumb( 'messaging_contacts' );
		$ProfileForm->hidden( 'user_ID', $User->ID );

		$ProfileForm->output = false;
		$button_add_group = $ProfileForm->submit_input( array(
				'name' => 'actionArray[add_user]',
				'value' => T_('Add'),
				'class' => 'SaveButton'
			) );
		$ProfileForm->output = true;

		if( !empty( $contacts_groups ) )
		{	// Display contacts groups for current User
			$ProfileForm->info( T_('This user is'), $contacts_groups );

			// Form to create a new group
			$ProfileForm->hidden( 'group_ID', 'new' );
			$ProfileForm->text_input( 'group_ID_combo', param( 'group_ID_combo', 'string', '' ), 18, T_('Add to a NEW group'), '', array( 'field_suffix' => $button_add_group, 'maxlength' => 50 ) );
		}
		else if( $User->ID != $current_User->ID )
		{	// Form to add this user into the group
			$ProfileForm->combo_box( 'group_ID', param( 'group_ID_combo', 'string', '' ), get_contacts_groups_options( param( 'group', 'string', '-1' ), false ), T_('Add this user to a group'), array( 'new_field_size' => '8', 'field_suffix' => $button_add_group ) );
		}
	}

	if( $is_logged_in && ( $current_User->ID != $User->ID ) && $current_User->check_status( 'can_report_user' ) )
	{ // Display Report User part
		$ProfileForm->add_crumb( 'messaging_contacts' );
		$ProfileForm->hidden( 'user_ID', $User->ID );

		$report_options = array_merge( array( 'none' => '' ), get_report_statuses() );

		// get current User report from edited User
		$current_report = get_report_from( $User->ID );
		$report_label = T_('Report this user');
		if( $current_report == NULL )
		{ // currentUser didn't add any report from this user yet
			$report_content = '<select id="report_user_status" name="report_user_status">';
			foreach( $report_options as $option => $option_label )
			{ // add select option, none must be selected
				$report_content .= '<option '.( ( $option == 'none' ) ? 'selected="selected" ' : '' ).'value="'.$option.'">'.$option_label.'</option>';
			}
			$report_content .= '</select><div id="report_info" style="width:100%;"></div>';

			$info_content = '<div><span>'.T_('You can provide additional information below').':</span></div>';
			$info_content .= '<table style="width:100%;"><td style="width:99%;background-color:inherit;"><textarea id="report_info_content" name="report_info_content" class="form_textarea_input" style="width:100%;" rows="2" maxlength="240"></textarea></td>';
			$info_content .= '<td style="vertical-align:top;background-color:inherit;"><input type="submit" class="SaveButton" style="color:red;margin-left:2px;" value="Report this user now!" name="actionArray[report_user]" /></td></table>';
			$report_content .= '<script type="text/javascript">
				var info_content = \''.$info_content.'\';
				jQuery("#report_user_status").change( function() {
					var report_info = jQuery("#report_info");
					var value = jQuery(this).val();
					if( value == "none" )
					{
						report_info.html("");
					}
					else if( report_info.is(":empty") )
					{
						report_info.html( info_content );
					}
				});
				</script>';
		}
		else
		{
			$report_content = T_('You have reported this user on %s as "%s" with the additional info "%s" - <a %s>Cancel report</a>');
			$cancel_link = 'href="'.url_add_param( $Blog->get('url'), 'disp=contacts&amp;user_ID='.$User->ID.'&amp;action=remove_report&amp;'.url_crumb( 'messaging_contacts' ) ).'"';
			$report_content = sprintf( $report_content, mysql2localedatetime( $current_report[ 'date' ] ), $report_options[ $current_report[ 'status' ] ], $current_report[ 'info' ], $cancel_link );
		}
		$ProfileForm->info( $report_label, $report_content );
	}

$ProfileForm->end_fieldset();


// Load the user fields:
$User->userfields_load();

// fp> TODO: have some clean iteration support
$group_ID = 0;
foreach( $User->userfields as $userfield )
{
	if( $group_ID != $userfield->ufgp_ID )
	{	// Start new group
		if( $group_ID > 0 )
		{	// End previous group
			$ProfileForm->end_fieldset();
		}
		$ProfileForm->begin_fieldset( T_( $userfield->ufgp_name ) );
	}

	if( $userfield->ufdf_type == 'text' )
	{	// convert textarea values
		$userfield->uf_varchar = nl2br( $userfield->uf_varchar );
	}
	$ProfileForm->info( $userfield->ufdf_name, $userfield->uf_varchar );

	$group_ID = $userfield->ufgp_ID;
}
if( $group_ID > 0 )
{	// End fieldset if userfields are exist
	$ProfileForm->end_fieldset();
}

$ProfileForm->begin_fieldset( T_( 'Reputation' ) );

	$ProfileForm->info( T_('Number of posts'), $User->get_reputation_posts() );

	$ProfileForm->info( T_('Number of comments'), $User->get_reputation_comments() );

	$ProfileForm->info( T_('Number of helpful votes'), $User->get_reputation_helpful() );

	$ProfileForm->info( T_('Spam fighter score'), $User->get_reputation_spam() );

$ProfileForm->end_fieldset();

$Plugins->trigger_event( 'DisplayProfileFormFieldset', array( 'Form' => & $ProfileForm, 'User' => & $User, 'edit_layout' => 'public' ) );

// Make sure we're below the floating user avatar on the right
echo '<div class="clear"></div>';

$ProfileForm->end_form();

if( $is_logged_in && ( $User->ID == $current_User->ID ) && ( !empty( $params['edit_my_profile_link_text'] ) ) )
{ // Display edit link profile for owner:
	echo '<div class="center" style="font-size:150%">';
	echo '<a href="'.url_add_param( $Blog->get('url'), 'disp=profile' ).'">'.$params['edit_my_profile_link_text'].'</a>';
	echo '</div>';
}
?>
