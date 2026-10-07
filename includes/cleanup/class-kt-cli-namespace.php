<?php
/**
 * Parent `wp kt` WP-CLI namespace, so subcommands such as `wp kt cleanup`
 * can be registered beneath it.
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Kate & Tom's maintenance commands.
 */
class KT_CLI_Namespace extends \WP_CLI\Dispatcher\CommandNamespace {}

WP_CLI::add_command( 'kt', 'KT_CLI_Namespace' );
