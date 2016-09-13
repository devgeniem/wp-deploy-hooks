<?php
/**
 * Plugin name: WP Deploy Hook
 * Plugin URI: https://github.com/devgeniem/wp-deploy-hooks
 * Description: This plugin registers a hook that can be run via WP CLI during deploy.
 * Version: 0.0.1
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
				$count = self::do_action( 'deploy/'. $hook );
				WP_CLI::success('All hooked functions ran successfully. There were '. $count .' functions in total.');
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

	// WordPress native do_action with a few tweaks for better output
	private static function do_action( $tag, $arg = '' ) {
	    global $wp_filter, $wp_actions, $merged_filters, $wp_current_filter;

	    $count = 0;
	 
	    if ( ! isset( $wp_actions[ $tag ] ) )
	        $wp_actions[ $tag ] = 1;
	    else
	        ++$wp_actions[ $tag ];
	 
	    // Do 'all' actions first
	    if ( isset( $wp_filter['all'] ) ) {
	        $wp_current_filter[] = $tag;
	        $all_args = func_get_args();
	        _wp_call_all_hook( $all_args );
	    }
	 
	    if ( !isset( $wp_filter[ $tag ] ) ) {
	        if ( isset( $wp_filter['all'] ) )
	            array_pop( $wp_current_filter );
	        return;
	    }
	 
	    if ( !isset( $wp_filter['all'] ) )
	        $wp_current_filter[] = $tag;
	 
	    $args = array();
	    if ( is_array( $arg ) && 1 == count( $arg ) && isset( $arg[0] ) && is_object( $arg[0] ) )
	        $args[] =& $arg[0];
	    else
	        $args[] = $arg;
	    for ( $a = 2, $num = func_num_args(); $a < $num; $a++ )
	        $args[] = func_get_arg( $a );
	 
	    // Sort
	    if ( !isset( $merged_filters[ $tag ] ) ) {
	        ksort( $wp_filter[ $tag ] );
	        $merged_filters[ $tag ] = true;
	    }
	 
	    reset( $wp_filter[ $tag ] );
	 
	    do {
	        foreach ( (array) current( $wp_filter[$tag] ) as $key => $the_ ) {
	        	if ( ctype_xdigit( $key ) && strlen( $key ) == 32 ) {
	        		echo "\033[36mExecuting a closure...\033[0m\n";
	        	}
	        	else {
	        		echo "\033[33mExecuting ". $key ."...\033[0m\n";
	        	}

	            if ( ! is_null( $the_['function'] ) ) {
	                call_user_func_array( $the_['function'], array_slice( $args, 0, (int) $the_['accepted_args'] ) );
	                $count++;
	            }
	        }
	 
	    } while ( next( $wp_filter[ $tag ] ) !== false );
	 
	    array_pop( $wp_current_filter );

	    return $count;
	}
}

// Our own exception type to catch purely deploy related errors
class DeployException extends Exception {}

// Register the command to the CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'deploy', 'WP_Deploy_Hook' );
}