<?php
/**
 * Settings → House Caches admin screen (#505).
 *
 * Lists every cache Kate_Toms_Cache_Manager knows about with its status,
 * when it was last refreshed and a Refresh button. Refresh posts to
 * admin-post.php, which only queues the work and redirects straight back.
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_Core_Cache_Admin' ) ) {

	/**
	 * Renders the cache screen and handles its Refresh buttons.
	 */
	class Kate_Toms_Core_Cache_Admin {

		/**
		 * Admin page slug.
		 *
		 * @var string
		 */
		public const PAGE = 'house-caches';

		/**
		 * The admin-post.php action.
		 *
		 * @var string
		 */
		public const ACTION = 'kt_refresh_cache';

		/**
		 * Capability required to see the screen and queue refreshes.
		 *
		 * @var string
		 */
		public const CAPABILITY = 'manage_options';

		/**
		 * Register the page under Settings, next to House Settings.
		 *
		 * @return void
		 */
		public static function add_menu() {
			add_options_page(
				__( 'House Caches', 'kate-toms-core' ),
				__( 'House Caches', 'kate-toms-core' ),
				self::CAPABILITY,
				self::PAGE,
				array( __CLASS__, 'render_page' )
			);
		}

		/**
		 * URL of the screen.
		 *
		 * @param array $args Extra query args.
		 * @return string
		 */
		public static function page_url( array $args = array() ) {
			return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'options-general.php' ) );
		}

		/**
		 * Handle a Refresh click: check, queue, redirect back.
		 *
		 * @return void
		 */
		public static function handle_refresh() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You do not have permission to refresh caches.', 'kate-toms-core' ), 403 );
			}

			$cache_id = isset( $_POST['cache'] ) ? sanitize_key( wp_unslash( $_POST['cache'] ) ) : '';

			check_admin_referer( self::ACTION . '_' . $cache_id );

			if ( ! Kate_Toms_Cache_Manager::exists( $cache_id ) ) {
				wp_die( esc_html__( 'Unknown cache.', 'kate-toms-core' ), 400 );
			}

			$result = Kate_Toms_Cache_Manager::queue( $cache_id );

			wp_safe_redirect(
				self::page_url(
					array(
						'kt_cache'        => $cache_id,
						'kt_cache_result' => is_wp_error( $result ) ? 'error' : $result,
					)
				)
			);
			exit;
		}

		/**
		 * Render the screen.
		 *
		 * @return void
		 */
		public static function render_page() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kate-toms-core' ) );
			}

			$caches = Kate_Toms_Cache_Manager::caches();
			?>
			<div class="wrap">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

				<?php self::render_notice( $caches ); ?>

				<p>
					<?php esc_html_e( 'Refreshing runs in the background, so you can leave this page. Each cache can only be queued once at a time; reload the page to see progress.', 'kate-toms-core' ); ?>
					<?php
					if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
						esc_html_e( 'WP-Cron is triggered by the server here, so a queued refresh starts on its next run.', 'kate-toms-core' );
					}
					?>
				</p>

				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Cache', 'kate-toms-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'What "Refresh" does', 'kate-toms-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'kate-toms-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Last refreshed', 'kate-toms-core' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Action', 'kate-toms-core' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $caches as $cache_id => $cache ) : ?>
							<?php $state = Kate_Toms_Cache_Manager::get_status( $cache_id ); ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $cache['label'] ); ?></strong>
									<p class="description"><?php echo esc_html( $cache['holds'] ); ?></p>
								</td>
								<td>
									<?php echo esc_html( $cache['refresh'] ); ?>
									<?php if ( $cache['cli'] ) : ?>
										<p class="description">
											<?php
											/* translators: %s: WP-CLI command. */
											printf( esc_html__( 'Same as %s.', 'kate-toms-core' ), '<code>' . esc_html( $cache['cli'] ) . '</code>' );
											?>
										</p>
									<?php endif; ?>
								</td>
								<td><?php self::render_status( $state ); ?></td>
								<td><?php self::render_last( $state ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
										<input type="hidden" name="cache" value="<?php echo esc_attr( $cache_id ); ?>" />
										<?php wp_nonce_field( self::ACTION . '_' . $cache_id ); ?>
										<?php
										submit_button(
											__( 'Refresh', 'kate-toms-core' ),
											'secondary',
											'submit',
											false,
											'idle' === $state['status'] ? array() : array( 'disabled' => 'disabled' )
										);
										?>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		}

		/**
		 * Notice after a Refresh click.
		 *
		 * @param array $caches Cache registry.
		 * @return void
		 */
		private static function render_notice( array $caches ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only; the action itself was nonce-checked.
			$cache_id = isset( $_GET['kt_cache'] ) ? sanitize_key( wp_unslash( $_GET['kt_cache'] ) ) : '';
			$result   = isset( $_GET['kt_cache_result'] ) ? sanitize_key( wp_unslash( $_GET['kt_cache_result'] ) ) : '';
			// phpcs:enable

			if ( ! isset( $caches[ $cache_id ] ) || '' === $result ) {
				return;
			}

			$label = $caches[ $cache_id ]['label'];

			switch ( $result ) {
				case 'queued':
					$class = 'notice-success';
					/* translators: %s: cache name. */
					$message = sprintf( __( '%s refresh queued. It will run in the background.', 'kate-toms-core' ), $label );
					break;
				case 'busy':
					$class = 'notice-warning';
					/* translators: %s: cache name. */
					$message = sprintf( __( '%s is already queued or refreshing, so it was not queued again.', 'kate-toms-core' ), $label );
					break;
				default:
					$class = 'notice-error';
					/* translators: %s: cache name. */
					$message = sprintf( __( 'Could not queue the %s refresh. Please try again.', 'kate-toms-core' ), $label );
			}

			printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
		}

		/**
		 * Status cell.
		 *
		 * @param array $state From Kate_Toms_Cache_Manager::get_status().
		 * @return void
		 */
		private static function render_status( array $state ) {
			$lock = $state['lock'];

			switch ( $state['status'] ) {
				case 'queued':
					echo '<strong>' . esc_html__( 'Queued', 'kate-toms-core' ) . '</strong>';
					/* translators: %s: human time difference. */
					echo '<br><span class="description">' . esc_html( sprintf( __( '%s ago', 'kate-toms-core' ), human_time_diff( (int) $lock['queued_at'] ) ) ) . '</span>';
					break;
				case 'running':
					echo '<strong>' . esc_html__( 'Running', 'kate-toms-core' ) . '</strong>';
					if ( 'cli' === $lock['source'] ) {
						echo '<br><span class="description">' . esc_html__( 'via WP-CLI / server cron', 'kate-toms-core' ) . '</span>';
					} elseif ( ! empty( $lock['total'] ) ) {
						/* translators: 1: houses done, 2: houses total. */
						echo '<br><span class="description">' . esc_html( sprintf( __( '%1$d of %2$d', 'kate-toms-core' ), (int) $lock['done'], (int) $lock['total'] ) ) . '</span>';
					}
					break;
				default:
					esc_html_e( 'Idle', 'kate-toms-core' );
			}
		}

		/**
		 * Last refreshed cell.
		 *
		 * @param array $state From Kate_Toms_Cache_Manager::get_status().
		 * @return void
		 */
		private static function render_last( array $state ) {
			$last  = $state['last'];
			$error = $state['last_error'];

			if ( $last ) {
				echo esc_html( self::format_time( (int) $last['time'] ) );
				echo '<br><span class="description">' . esc_html( self::source_label( $last['source'] ) . ': ' . $last['summary'] ) . '</span>';
			} else {
				esc_html_e( 'Not recorded yet', 'kate-toms-core' );
			}

			// Only show a failure that happened after the last success.
			if ( $error && ( ! $last || (int) $error['time'] > (int) $last['time'] ) ) {
				echo '<p style="color:#b32d2e;margin:.5em 0 0;">';
				/* translators: 1: time, 2: reason. */
				echo esc_html( sprintf( __( 'Last attempt failed %1$s: %2$s', 'kate-toms-core' ), self::format_time( (int) $error['time'] ), $error['summary'] ) );
				echo '</p>';
			}
		}

		/**
		 * Format a timestamp in the site timezone, with "x ago".
		 *
		 * @param int $timestamp Unix timestamp.
		 * @return string
		 */
		private static function format_time( $timestamp ) {
			/* translators: 1: date and time, 2: human time difference. */
			return sprintf( __( '%1$s (%2$s ago)', 'kate-toms-core' ), wp_date( 'j M Y, H:i', $timestamp ), human_time_diff( $timestamp ) );
		}

		/**
		 * Label for who ran a refresh.
		 *
		 * @param string $source 'admin' or 'cli'.
		 * @return string
		 */
		private static function source_label( $source ) {
			return 'cli' === $source ? __( 'WP-CLI', 'kate-toms-core' ) : __( 'Admin', 'kate-toms-core' );
		}
	}
}
