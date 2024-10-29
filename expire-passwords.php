<?php
/**
 * Welcome to Expire User Passwords. Start your engines, we're in for a fun ride.
 *
 * @author Paul Gibbs <paul@byotos.com>
 * @package ExpireUserPasswords
 */

/*
Plugin Name: Expire User Passwords
Plugin URI: http://github.com/telegraph/Expire-User-Passwords
Description: Force users to change their passwords every 30 days.
Version: 1.0
Requires at least: 3.2.1
Tested up to: 3.3.1
License: GPLv3
Author: Paul Gibbs, Telegraph Media Group
Author URI: http://www.telegraph.co.uk
Network: true
Domain Path: /languages/
Text Domain: tmg_aep

"Expire User Passwords"
Copyright (C) 2012  Telegraph Media Group Limited

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * If we're in the WordPress Adin, hook into profile update
 *
 * @since 1.0
 */
function tmg_aep_admin() {
	if ( is_admin() )
		add_action( 'user_profile_update_errors', 'tmg_aep_profile_update', 11, 3 );
}
add_action( 'init', 'tmg_aep_admin' );

/**
 * When user successfully changes their password, set the timestamp in user meta.
 *
 * @param WP_Error $errors Errors, by ref.
 * @param bool $update Unknown, by ref.
 * @param object $user User object, by ref.
 * @since 1.0
 */
function tmg_aep_profile_update( $errors, $update, $user ) {
	/**
	 * Bail out if there are errors attached to the change password profile field,
	 * or if the password is not being changed.
	 */
	if ( $errors->get_error_data( 'pass' ) || empty( $_POST['pass1'] ) || empty( $_POST['pass2'] ) )
		return;

	// Store timestamp
	update_user_meta( $user->ID, 'tmg_aep', time() );
}

/**
 * When user successfully resets their password, re-set the timestamp.
 *
 * @param object $user User object
 * @since 1.0
 */
function tmg_aep_password_reset( $user ) {
	update_user_meta( $user->ID, 'tmg_aep', time() );
}
add_action( 'password_reset', 'tmg_aep_password_reset' );

/**
 * When the user logs in, check that their meta timestamp is still in the allowed range.
 * If it isn't, prevent log in.
 *
 * @param WP_Error|WP_User $user WP_User object if login was successful, otherwise WP_Error object.
 * @param string $username
 * @param string $password
 * @return WP_Error|WP_User WP_User object if login was successful and had not expired, otherwise WP_Error object.
 * @since 1.0
 */
function tmg_aep_handle_log_in( $user, $username, $password ) {
	// Check if an error has already been set
	if ( is_wp_error( $user ) )
		return $user;

	// Check we're dealing with a WP_User object
	if ( ! is_a( $user, 'WP_User' ) )
		return $user;

	// This is a log in which would normally be succesful
	$user_id = $user->data->ID;

	// If no timestamp set, it's probably the user's first log in attempt since this plugin was installed, so set the timestamp to now
	$timestamp = (int) get_user_meta( $user_id, 'tmg_aep', true );
	if ( empty( $timestamp ) ) {
		$timestamp = time();
		update_user_meta( $user_id, 'tmg_aep', $timestamp );
	}

	// Compare now to time stored in meta
	$diff         = time() - $timestamp;
	$login_expiry = defined( 'TMG_AEP_EXPIRY' ) ? TMG_AEP_EXPIRY : 60 * 60 * 24 * 30;  // 30 days unless overidden

	// Expired
	if ( $diff >= $login_expiry )
		$user = new WP_Error( 'authentication_failed', sprintf( __( '<strong>ERROR</strong>: You must <a href="%s">reset your password</a>.', 'tmg_aep' ), site_url( 'wp-login.php?action=lostpassword', 'login' ) ) );

	return $user;
}
add_filter( 'authenticate', 'tmg_aep_handle_log_in', 30, 3 );
?>