<?php
/**
 * Plugin name: WP Deploy Hook
 * Plugin URI: https://github.com/devgeniem/wp-deploy-hooks
 * Description: This plugin registers a hook that can be run via WP CLI during deploy.
 * Version: 0.0.2
 * Author: @Nomafin, @devgeniem
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.html
 */

class WP_Deploy_Hook {
	public function __invoke( $args ) {
		// Check if we have at least one parameter for the command
		if ( isset( $args[0] ) ) {
			// Check if the wanted function exists
			if ( method_exists( __CLASS__, $args[0] ) ) {
				// Call it with all parameters that are left
				self::{$args[0]}( ...array_splice( $args, 1 ) );
			}
			// If not, give an error to the user
			else {
				WP_CLI::error( 'Command "'. $args[0] .'" is not supported.' );
			}
		}
		// If not, ask for more.
		else {
			echo "Usage: wp deploy hook [after|before]\n";
			exit;
		}
	}

	// Run wanted hooks 
	public static function hook( $hook ) {
		// We have a filter to create dynamically more hooks to run here
		$accepted_hooks = apply_filters( "deploy/accepted_hooks", [ "after", "before" ] );

		// If the asked hook is accepted, let's go
		if ( in_array( $hook, $accepted_hooks ) ) {
			try {
				// Run the wanted hook with our modified do_action method
				do_action( 'deploy/'. $hook );
				WP_CLI::success('All hooked functions ran successfully. There probably were some functions in total but we don\'t know that.');
				exit;
			}
			// If there were DeployExceptions, output them here
			catch( DeployException $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		}
		// User didn't define a hook to run
		else if ( empty( $hook ) ) {
			WP_CLI::error('You must declare a hook to run as a parameter.');
			exit;
		}
		// User asked for a hook that doesn't exist at all
		else {
			WP_CLI::error('Hook "'. $hook .'" does not exist.');
			exit;
		}
	}
}

// Our own exception type to catch purely deploy related errors
class DeployException extends Exception {}

// Register the command to the CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'deploy', 'WP_Deploy_Hook' );
}