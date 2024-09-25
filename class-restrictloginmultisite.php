<?php
/**
 * Plugin Name: Restrict Login Multisite
 * Description: Prevent users from logging into subsites if they are not registered on the subsite or not allowed access in a WordPress Multisite network.
 * Version: 1
 * Author:Sandeep jain
 */

defined( 'ABSPATH' ) || exit;

class RestrictLoginMultisite {

	public function __construct() {
		// Hook to save the registered site ID when a user registers
		add_action( 'wpmu_new_user', array( $this, 'save_registered_site_id' ), 10, 1 );
		
		// Hook into authenticate to control login restriction
		add_filter( 'authenticate', array( $this, 'check_user_login_site_restriction' ), 30, 3 );
	}

	/**
	 * Save the registered site ID to user meta when a new user is created.
	 */
	public function save_registered_site_id( $user_id ) {
		$current_blog_id = get_current_blog_id();
		
		// Get the current list of registered sites for the user
		$registered_sites = get_user_meta( $user_id, 'registered_site_ids', true );
		if ( ! is_array( $registered_sites ) ) {
			$registered_sites = array();
		}

		// Add the current site to the array of registered sites
		if ( ! in_array( $current_blog_id, $registered_sites ) ) {
			$registered_sites[] = $current_blog_id;
		}

		// Update user meta with the allowed site IDs
		update_user_meta( $user_id, 'registered_site_ids', $registered_sites );

		// For debugging purposes
		error_log( 'Registered site IDs for user ' . $user_id . ': ' . print_r( $registered_sites, true ) );
	}

	/**
	 * Check if the user is allowed to log in to the current site.
	 * This function is called during the authentication process.
	 */
	public function check_user_login_site_restriction( $user, $username, $password ) {
		// If the user is not a valid WP_User object, return it (could be a login failure or guest)
		if ( ! is_a( $user, 'WP_User' ) ) {
			return $user;
		}

		$user_id = $user->ID;

		// Get the list of sites the user is allowed to access
		$registered_site_ids = get_user_meta( $user_id, 'registered_site_ids', true );
		if(empty($registered_site_ids)){
			return $user;
		}
		if ( ! is_array( $registered_site_ids ) ) {
			$registered_site_ids = array();
		}

		// Get the current site ID
		$current_site_id = get_current_blog_id();

		// For debugging purposes
		error_log( 'Registered site IDs for user ID ' . $user_id . ': ' . print_r( $registered_site_ids, true ) );
		error_log( 'Current site ID is ' . $current_site_id );

		// If the user is not registered for the current site, prevent login
		if ( ! in_array( $current_site_id, $registered_site_ids ) ) {
			// Log the error
			error_log( 'User is not allowed to log in to this site.' );

			// Return an error message to prevent login
			return new WP_Error( 'site_restriction_error', __( 'You cannot log in to this site because you are not registered for it.', 'restrict-login-multisite' ) );
		}

		// Allow login if the user is registered for the site
		return $user;
	}
}

// Initialize the plugin
new RestrictLoginMultisite();
