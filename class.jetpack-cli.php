<?php

WP_CLI::add_command( 'jetpack', 'Jetpack_CLI' );

/**
 * Control your local Jetpack installation.
 */
class Jetpack_CLI extends WP_CLI_Command {

	/**
	 * Get Jetpack Details
	 *
	 * ## OPTIONS
	 *
	 * empty: Leave it empty for basic stats
	 *
	 * full: View full stats.  It's the data from the heartbeat
	 *
	 * ## EXAMPLES
	 *
	 * wp jetpack status
	 * wp jetpack status full
	 *
	 */
	public function status( $args, $assoc_args ) {
		if ( ! Jetpack::is_active() ) {
			WP_CLI::error( __( 'Jetpack is not currently connected to WordPress.com', 'jetpack' ) );
		}

		if ( isset( $args[0] ) && 'full' !== $args[0] ) {
			WP_CLI::error( sprintf( __( '%s is not a valid command.', 'jetpack' ), $args[0] ) );
		}

		// Aesthetics
		$green_open  = "\033[32m";
		$red_open    = "\033[31m";
		$yellow_open = "\033[33m";
		$color_close = "\033[0m";

		/*
		 * Are they asking for all data?
		 *
		 * Loop through heartbeat data and organize by priority.
		 */
		$all_data = ( isset( $args[0] ) && 'full' == $args[0] ) ? 'full' : false;
		if ( $all_data ) {
			WP_CLI::success( __( 'Jetpack is currently connected to WordPress.com', 'jetpack' ) );
			WP_CLI::line( sprintf( __( "The Jetpack Version is %s", 'jetpack' ), JETPACK__VERSION ) );
			WP_CLI::line( sprintf( __( "The WordPress.com blog_id is %d", 'jetpack' ), Jetpack_Options::get_option( 'id' ) ) );

			// Heartbeat data
			WP_CLI::line( sprintf( __( "\nAdditional data: ", 'jetpack' ) ) );

			// Get the filtered heartbeat data.
			// Filtered so we can color/list by severity
			$stats = Jetpack::jetpack_check_heartbeat_data();

			// Display red flags first
			foreach ( $stats['bad'] as $stat => $value ) {
				printf( "$red_open%-'.16s %s $color_close\n", $stat, $value );
			}

			// Display caution warnings next
			foreach ( $stats['caution'] as $stat => $value ) {
				printf( "$yellow_open%-'.16s %s $color_close\n", $stat, $value );
			}

			// The rest of the results are good!
			foreach ( $stats['good'] as $stat => $value ) {

				// Modules should get special spacing for aestetics
				if ( strpos( $stat, 'odule-' ) ) {
					printf( "%-'.30s %s\n", $stat, $value );
					usleep( 4000 ); // For dramatic effect lolz
					continue;
				}
				printf( "%-'.16s %s\n", $stat, $value );
				usleep( 4000 ); // For dramatic effect lolz
			}
		} else {
			// Just the basics
			WP_CLI::success( __( 'Jetpack is currently connected to WordPress.com', 'jetpack' ) );
			WP_CLI::line( sprintf( __( 'The Jetpack Version is %s', 'jetpack' ), JETPACK__VERSION ) );
			WP_CLI::line( sprintf( __( 'The WordPress.com blog_id is %d', 'jetpack' ), Jetpack_Options::get_option( 'id' ) ) );
			WP_CLI::line( sprintf( __( "\nView full status with 'wp jetpack status full'", 'jetpack' ) ) );
		}
	}

	/**
	 * Disconnect Jetpack Blogs or Users
	 *
	 * ## OPTIONS
	 *
	 * blog: Disconnect the entire blog.
	 *
	 * user <user_identifier>: Disconnect a specific user from WordPress.com.
	 *
	 * Please note, the primary account that the blog is connected
	 * to WordPress.com with cannot be disconnected without
	 * disconnecting the entire blog.
	 *
	 * ## EXAMPLES
	 *
	 * wp jetpack disconnect blog
	 * wp jetpack disconnect user 13
	 * wp jetpack disconnect user username
	 * wp jetpack disconnect user email@domain.com
	 *
	 * @synopsis <blog|user> [<user_identifier>]
	 */
	public function disconnect( $args, $assoc_args ) {
		if ( ! Jetpack::is_active() ) {
			WP_CLI::error( __( 'You cannot disconnect, without having first connected.', 'jetpack' ) );
		}

		$action = isset( $args[0] ) ? $args[0] : 'prompt';
		if ( ! in_array( $action, array( 'blog', 'user', 'prompt' ) ) ) {
			WP_CLI::error( sprintf( __( '%s is not a valid command.', 'jetpack' ), $action ) );
		}

		if ( in_array( $action, array( 'user' ) ) ) {
			if ( isset( $args[1] ) ) {
				$user_id = $args[1];
				if ( ctype_digit( $user_id ) ) {
					$field = 'id';
					$user_id = (int) $user_id;
				} elseif ( is_email( $user_id ) ) {
					$field = 'email';
					$user_id = sanitize_user( $user_id, true );
				} else {
					$field = 'login';
					$user_id = sanitize_user( $user_id, true );
				}
				if ( ! $user = get_user_by( $field, $user_id ) ) {
					WP_CLI::error( __( 'Please specify a valid user.', 'jetpack' ) );
				}
			} else {
				WP_CLI::error( __( 'Please specify a user by either ID, username, or email.', 'jetpack' ) );
			}
		}

		switch ( $action ) {
			case 'blog':
				Jetpack::log( 'disconnect' );
				Jetpack::disconnect();
				WP_CLI::success( __( 'Jetpack has been successfully disconnected.', 'jetpack' ) );
				break;
			case 'user':
				if ( Jetpack::unlink_user( $user->ID ) ) {
					Jetpack::log( 'unlink', $user->ID );
					WP_CLI::success( sprintf( __( '%s has been successfully disconnected.', 'jetpack' ), $action ) );
				} else {
					WP_CLI::error( sprintf( __( '%s could not be disconnected.  Are you sure they\'re connected currently?', 'jetpack' ), "{$user->login} <{$user->email}>" ) );
				}
				break;
			case 'prompt':
				WP_CLI::error( __( 'Please specify if you would like to disconnect a blog or user.', 'jetpack' ) );
				break;
		}
	}

	/**
	 * Reset Jetpack options and settings to default
	 *
	 * ## OPTIONS
	 *
	 * modules: Resets modules to default state ( get_default_modules() )
	 *
	 * options: Resets all Jetpack options except:
	 *  - All private options (Blog token, user token, etc...)
	 *  - id (The Client ID/WP.com Blog ID of this site)
	 *  - master_user
	 *  - version
	 *  - activated
	 *
	 * ## EXAMPLES
	 *
	 * wp jetpack reset options
	 * wp jetpack reset modules
	 *
	 * @synopsis <modules|options>
	 */
	public function reset( $args, $assoc_args ) {
		$action = isset( $args[0] ) ? $args[0] : 'prompt';
		if ( ! in_array( $action, array( 'options', 'modules' ) ) ) {
			WP_CLI::error( sprintf( __( '%s is not a valid command.', 'jetpack' ), $action ) );
		}

		switch ( $action ) {
			case 'options':
				$options_to_reset = Jetpack::get_jetapck_options_for_reset();

				// Reset the Jetpack options
				_e( "Resetting Jetpack Options...\n", "jetpack" );
				sleep(1); // Take a breath
				foreach ( $options_to_reset['jp_options'] as $option_to_reset ) {
					Jetpack_Options::delete_option( $option_to_reset );
					usleep( 100000 );
					WP_CLI::success( sprintf( __( '%s option reset', 'jetpack' ), $option_to_reset ) );
				}

				// Reset the WP options
				_e( "Resetting the jetpack options stored in wp_options...\n", "jetpack" );
				usleep( 500000 ); // Take a breath
				foreach ( $options_to_reset['wp_options'] as $option_to_reset ) {
					delete_option( $option_to_reset );
					usleep( 100000 );
					WP_CLI::success( sprintf( __( '%s option reset', 'jetpack' ), $option_to_reset ) );
				}

				// Reset to default modules
				_e( "Resetting default modules...\n", "jetpack" );
				usleep( 500000 ); // Take a breath
				$default_modules = Jetpack::get_default_modules();
				Jetpack_Options::update_option( 'active_modules', $default_modules );
				WP_CLI::success( __( 'Modules reset to default.', 'jetpack' ) );

				// Jumpstart option is special
				Jetpack_Options::update_option( 'jumpstart', 'new_connection' );
				WP_CLI::success( __( 'jumpstart option reset', 'jetpack' ) );
				break;
			case 'modules':
				$default_modules = Jetpack::get_default_modules();
				Jetpack_Options::update_option( 'active_modules', $default_modules );
				WP_CLI::success( __( 'Modules reset to default.', 'jetpack' ) );
				break;
			case 'prompt':
				WP_CLI::error( __( 'Please specify if you would like to reset your options, or modules', 'jetpack' ) );
				break;
		}
	}

	/**
	 * Manage Jetpack Modules
	 *
	 * ## OPTIONS
	 *
	 * list          : View all available modules, and their status.
	 * activate all  : Activate all modules
	 * deactivate all: Deactivate all modules
	 *
	 * activate   <module_slug> : Activate a module.
	 * deactivate <module_slug> : Deactivate a module.
	 * toggle     <module_slug> : Toggle a module on or off.
	 *
	 * ## EXAMPLES
	 *
	 * wp jetpack module list
	 * wp jetpack module activate stats
	 * wp jetpack module deactivate stats
	 * wp jetpack module toggle stats
	 *
	 * wp jetpack module activate all
	 * wp jetpack module deactivate all
	 *
	 * @synopsis <list|activate|deactivate|toggle> [<module_name>]
	 */
	public function module( $args, $assoc_args ) {
		$action = isset( $args[0] ) ? $args[0] : 'list';
		if ( ! in_array( $action, array( 'list', 'activate', 'deactivate', 'toggle' ) ) ) {
			WP_CLI::error( sprintf( __( '%s is not a valid command.', 'jetpack' ), $action ) );
		}
		if ( in_array( $action, array( 'activate', 'deactivate', 'toggle' ) ) ) {
			if ( isset( $args[1] ) ) {
				$module_slug = $args[1];
				if ( 'all' !== $module_slug && ! Jetpack::is_module( $module_slug ) ) {
					WP_CLI::error( sprintf( __( '%s is not a valid module.', 'jetpack' ), $module_slug ) );
				}
				if ( 'toggle' == $action ) {
					$action = Jetpack::is_module_active( $module_slug ) ? 'deactivate' : 'activate';
				}
				// Bulk actions
				if ( 'all' == $args[1] ) {
					$action = ( 'deactivate' == $action ) ? 'deactivate_all' : 'activate_all';
				}
			} else {
				WP_CLI::line( __( 'Please specify a valid module.', 'jetpack' ) );
				$action = 'list';
			}
		}
		switch ( $action ) {
			case 'list':
				WP_CLI::line( __( 'Available Modules:', 'jetpack' ) );
				$modules = Jetpack::get_available_modules();
				sort( $modules );
				foreach( $modules as $module_slug ) {
					$active = Jetpack::is_module_active( $module_slug ) ? __( 'Active', 'jetpack' ) : __( 'Inactive', 'jetpack' );
					WP_CLI::line( "\t" . str_pad( $module_slug, 24 ) . $active );
				}
				break;
			case 'activate':
				$module = Jetpack::get_module( $module_slug );
				Jetpack::log( 'activate', $module_slug );
				Jetpack::activate_module( $module_slug, false, false );
				WP_CLI::success( sprintf( __( '%s has been activated.', 'jetpack' ), $module['name'] ) );
				break;
			case 'activate_all':
				$modules = Jetpack::get_available_modules();
				Jetpack_Options::update_option( 'active_modules', $modules );
				WP_CLI::success( __( 'All modules activated!', 'jetpack' ) );
				break;
			case 'deactivate':
				$module = Jetpack::get_module( $module_slug );
				Jetpack::log( 'deactivate', $module_slug );
				Jetpack::deactivate_module( $module_slug );
				WP_CLI::success( sprintf( __( '%s has been deactivated.', 'jetpack' ), $module['name'] ) );
				break;
			case 'deactivate_all':
				Jetpack_Options::update_option( 'active_modules', '' );
				WP_CLI::success( __( 'All modules deactivated!', 'jetpack' ) );
				break;
			case 'toggle':
				// Will never happen, should have been handled above and changed to activate or deactivate.
				break;
		}
	}

	/**
	 * Manage Jetpack Protect Settings
	 *
	 * ## OPTIONS
	 *
	 * whitelist: Whitelist an IP address.  You can also read or clear the whitelist.
	 *
	 *
	 * ## EXAMPLES
	 *
	 * wp jetpack protect whitelist <ip address>
	 * wp jetpack protect whitelist list
	 * wp jetpack protect whitelist clear
	 *
	 * @synopsis <whitelist> [<ip|ip_low-ip_high|list|clear>]
	 */
	public function protect( $args, $assoc_args ) {
		$action = isset( $args[0] ) ? $args[0] : 'prompt';
		if ( ! in_array( $action, array( 'whitelist' ) ) ) {
			WP_CLI::error( sprintf( __( '%s is not a valid command.', 'jetpack' ), $action ) );
		}
		// Check if module is active
		if ( ! Jetpack::is_module_active( __FUNCTION__ ) ) {
			WP_CLI::error( sprintf( __( '%s is not active. You can activate it with "wp jetpack module activate %s"', 'jetpack' ), __FUNCTION__, __FUNCTION__ ) );
		}
		if ( in_array( $action, array( 'whitelist' ) ) ) {
			if ( isset( $args[1] ) ) {
				$action = 'whitelist';
			} else {
				$action = 'prompt';
			}
		}
		switch ( $action ) {
			case 'whitelist':
				$whitelist         = array();
				$new_ip            = $args[1];
				$current_whitelist = get_site_option( 'jetpack_protect_whitelist' );

				// Build array of IPs that are already whitelisted.
				// Re-build manually instead of using jetpack_protect_format_whitelist() so we can easily get
				// low & high range params for jetpack_protect_ip_address_is_in_range();
				foreach( $current_whitelist as $whitelisted ) {

					// IP ranges
					if ( $whitelisted->range ) {

						// Is it already whitelisted?
						if ( jetpack_protect_ip_address_is_in_range( $new_ip, $whitelisted->range_low, $whitelisted->range_high ) ) {
							WP_CLI::error( __( "$new_ip has already been whitelisted", 'jetpack' ) );
							break;
						}
						$whitelist[] = $whitelisted->range_low . " - " . $whitelisted->range_high;

					} else { // Individual IPs

						// Check if the IP is already whitelisted (single IP only)
						if ( $new_ip == $whitelisted->ip_address ) {
							WP_CLI::error( __( "$new_ip has already been whitelisted", 'jetpack' ) );
							break;
						}
						$whitelist[] = $whitelisted->ip_address;

					}
				}

				/*
				 * List the whitelist
				 * Done here because it's easier to read the $whitelist array after it's been rebuilt
				 */
				if ( isset( $args[1] ) && 'list' == $args[1] ) {
					if ( ! empty( $whitelist ) ) {
						WP_CLI::success( __( 'Here are your whitelisted IPs:', 'jetpack' ) );
						foreach ( $whitelist as $ip ) {
							WP_CLI::line( "\t" . str_pad( $ip, 24 ) ) ;
						}
					} else {
						WP_CLI::line( __( 'Whitelist is empty.', "jetpack" ) ) ;
					}
					break;
				}

				/*
				 * Clear the whitelist
				 */
				if ( isset( $args[1] ) && 'clear' == $args[1] ) {
					if ( ! empty( $whitelist ) ) {
						$whitelist = array();
						jetpack_protect_save_whitelist( $whitelist );
						WP_CLI::success( __( 'Cleared all whitelisted IPs', 'jetpack' ) );
					} else {
						WP_CLI::line( __( 'Whitelist is empty.', "jetpack" ) ) ;
					}
					break;
				}

				// Append new IP to whitelist array
				array_push( $whitelist, $new_ip );

				// Save whitelist if there are no errors
				$result = jetpack_protect_save_whitelist( $whitelist );
				if ( is_wp_error( $result ) ) {
					WP_CLI::error( __( $result, 'jetpack' ) );
				}

				WP_CLI::success( sprintf( __( '%s has been whitelisted.', 'jetpack' ), $new_ip ) );
				break;
			case 'prompt':
				WP_CLI::error(
					__( "No command found.
						\nPlease enter the IP address you want to whitelist.\nYou can save a range of IPs {low_range}-{high_range}. No spaces allowed.  (example: 1.1.1.1-2.2.2.2)
						\nYou can also 'list' or 'clear' the whitelist.",
						'jetpack'
					)
				);
				break;
		}
	}

}