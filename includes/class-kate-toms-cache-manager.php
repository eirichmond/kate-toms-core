<?php
/**
 * Cache refresh manager (#505).
 *
 * One place that knows about every cache the plugin keeps warm, so the team
 * can refresh them from the admin (Settings → House Caches) instead of WP-CLI.
 *
 * A refresh never runs inside the admin request. Clicking "Refresh" takes a
 * per-cache lock and queues a single WP-Cron event; the work runs in the
 * background. The calendar warm (~400 houses, ~15 minutes, one iPro call pair
 * per house) is split into batches, each its own cron event, so no single
 * request runs long enough to hit a PHP or proxy timeout.
 *
 * The lock is an option row inserted with INSERT IGNORE, so only one caller
 * can win it. While it is held the cache cannot be queued
 * again, so repeated clicks never stack up repeated iPro warms. Locks carry a
 * heartbeat and are treated as abandoned once it goes stale, so a fatal error
 * mid-run can't block a cache for ever.
 *
 * The WP-CLI warmers (`wp kt-cache warm` etc.) take the same lock and record
 * the same "last refreshed" time, so the admin screen is honest about
 * whichever ran last.
 *
 * Booking API failures are handled the way #80 / #81 set out: every iPro call
 * goes through House_Calendar_Manager::api_get() (token refresh + retry on
 * 401), jobs that depend on the API check it is up before touching anything,
 * and a failed fetch never overwrites a good cached value.
 *
 * @package Kate_Toms_Core
 */

if ( ! class_exists( 'Kate_Toms_Cache_Manager' ) ) {

	/**
	 * Queues, locks, runs and records cache refreshes.
	 */
	class Kate_Toms_Cache_Manager {

		/**
		 * WP-Cron hook that runs a queued refresh.
		 *
		 * Args: ( string $cache_id, int $offset, string $token ).
		 *
		 * @var string
		 */
		public const HOOK = 'kate_toms_core_cache_refresh';

		/**
		 * Option prefix for the per-cache lock.
		 *
		 * @var string
		 */
		public const LOCK_PREFIX = 'kt_cache_lock_';

		/**
		 * Option prefix for the per-cache "last refreshed" record.
		 *
		 * @var string
		 */
		public const LAST_PREFIX = 'kt_cache_last_';

		/**
		 * Houses fetched from iPro per calendar batch (~2s each).
		 *
		 * @var int
		 */
		public const CALENDAR_BATCH = 20;

		/**
		 * How long a queued refresh may wait for WP-Cron before its lock is
		 * treated as abandoned and the cache can be queued again.
		 *
		 * @var int
		 */
		public const QUEUED_TTL = HOUR_IN_SECONDS;

		/**
		 * Every cache the admin screen can refresh.
		 *
		 * @return array<string, array{label: string, holds: string, refresh: string, cli: string, stale_after: int}>
		 */
		public static function caches() {
			return array(
				'calendar'         => array(
					'label'       => __( 'House calendars & pricing', 'kate-toms-core' ),
					'holds'       => __( 'Each house\'s availability and rates from iPro (kt_house_calendar_*). Used by house calendars, the booking flow, the houses search/filter date results and everything below.', 'kate-toms-core' ),
					'refresh'     => __( 'Re-fetches every published house from iPro in batches of 20 (a full run takes around 15 minutes). A house whose fetch fails keeps its existing data. Refresh the landing pages and special offers afterwards to pick up the changes.', 'kate-toms-core' ),
					'cli'         => 'wp kt-cache warm',
					'stale_after' => 30 * MINUTE_IN_SECONDS,
				),
				'property_mapping' => array(
					'label'       => __( 'iPro property mapping', 'kate-toms-core' ),
					'holds'       => __( 'The house ↔ iPro property lookup (kt_property_mapping) the houses search/filter and the calendar warm use. Normally refreshes itself hourly.', 'kate-toms-core' ),
					'refresh'     => __( 'Fetches a fresh lookup from iPro and replaces the cached one. If iPro doesn\'t answer, the existing lookup is kept.', 'kate-toms-core' ),
					'cli'         => '',
					'stale_after' => 15 * MINUTE_IN_SECONDS,
				),
				'seasonal'         => array(
					'label'       => __( 'Seasonal & availability landing pages', 'kate-toms-core' ),
					'holds'       => __( 'The precomputed house lists on seasonal and late-availability landing pages (kt_seasonal_ids_*), plus the per-house verdicts and prices they are built from.', 'kate-toms-core' ),
					'refresh'     => __( 'Clears the per-house verdicts and prices, then recomputes every landing page section from the calendar cache. Skipped, leaving the current lists in place, if iPro is down.', 'kate-toms-core' ),
					'cli'         => 'wp kt-seasonal-cache warm',
					'stale_after' => HOUR_IN_SECONDS,
				),
				'special_offers'   => array(
					'label'       => __( 'Special offers (booked-out check)', 'kate-toms-core' ),
					'holds'       => __( 'Which special offers have been booked and should be hidden from the offers grid (kt_special_offer_booked_map).', 'kate-toms-core' ),
					'refresh'     => __( 'Rechecks every dated offer against the calendar cache and rebuilds the map. Skipped, leaving the current map in place, if iPro is down.', 'kate-toms-core' ),
					'cli'         => 'wp kt-offers-cache warm',
					'stale_after' => HOUR_IN_SECONDS,
				),
				'sitemap'          => array(
					'label'       => __( 'Houses sitemap', 'kate-toms-core' ),
					'holds'       => __( 'Yoast\'s cached copy of /houses-sitemap.xml.', 'kate-toms-core' ),
					'refresh'     => __( 'Clears Yoast\'s houses sitemap cache and requests each sitemap page so it is rebuilt straight away.', 'kate-toms-core' ),
					'cli'         => 'wp kt-sitemap-cache warm',
					'stale_after' => 15 * MINUTE_IN_SECONDS,
				),
			);
		}

		/**
		 * Hook the background runner.
		 *
		 * @return void
		 */
		public static function register() {
			add_action( self::HOOK, array( __CLASS__, 'run' ), 10, 3 );
		}

		/**
		 * Whether a cache ID is one we know about.
		 *
		 * @param string $cache_id Cache ID.
		 * @return bool
		 */
		public static function exists( $cache_id ) {
			return array_key_exists( (string) $cache_id, self::caches() );
		}

		/**
		 * Current state of a cache.
		 *
		 * @param string $cache_id Cache ID.
		 * @return array{status: string, lock: array|null, last: array|null, last_error: array|null}
		 */
		public static function get_status( $cache_id ) {
			$lock = self::get_live_lock( $cache_id );
			$last = get_option( self::LAST_PREFIX . $cache_id, array() );

			return array(
				'status'     => $lock ? $lock['status'] : 'idle',
				'lock'       => $lock,
				'last'       => ! empty( $last['time'] ) ? $last : null,
				'last_error' => ! empty( $last['error'] ) ? $last['error'] : null,
			);
		}

		/**
		 * Queue a background refresh.
		 *
		 * @param string $cache_id Cache ID.
		 * @return string|WP_Error 'queued', or 'busy' if already queued/running.
		 */
		public static function queue( $cache_id ) {
			if ( ! self::exists( $cache_id ) ) {
				return new WP_Error( 'kt_cache_unknown', __( 'Unknown cache.', 'kate-toms-core' ) );
			}

			$lock = self::acquire( $cache_id, 'queued', 'admin' );

			if ( ! $lock ) {
				return 'busy';
			}

			$scheduled = wp_schedule_single_event( time(), self::HOOK, array( $cache_id, 0, $lock['token'] ), true );

			if ( is_wp_error( $scheduled ) || ! $scheduled ) {
				self::release( $cache_id );
				return is_wp_error( $scheduled ) ? $scheduled : new WP_Error( 'kt_cache_schedule', __( 'Could not schedule the refresh.', 'kate-toms-core' ) );
			}

			// Start it now rather than on the next visitor (no-op if cron is off).
			if ( ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) {
				spawn_cron();
			}

			return 'queued';
		}

		/**
		 * Take the lock for a WP-CLI run.
		 *
		 * A refresh that is only queued is taken over (its cron event is
		 * dropped) so the nightly CLI warm isn't blocked by a click that cron
		 * never picked up; one that is already running is left alone.
		 *
		 * @param string $cache_id Cache ID.
		 * @return bool True if the caller now holds the lock.
		 */
		public static function begin_cli( $cache_id ) {
			$lock = self::get_live_lock( $cache_id );

			if ( $lock && 'queued' === $lock['status'] ) {
				self::release( $cache_id );
			}

			return (bool) self::acquire( $cache_id, 'running', 'cli' );
		}

		/**
		 * Record a finished run and drop the lock.
		 *
		 * The "last refreshed" time only moves on success; a failure is kept
		 * alongside it so the screen can show what went wrong.
		 *
		 * @param string $cache_id Cache ID.
		 * @param bool   $ok       Whether the refresh succeeded.
		 * @param string $summary  One-line result.
		 * @param string $source   'admin' or 'cli'.
		 * @return void
		 */
		public static function finish( $cache_id, $ok, $summary, $source ) {
			$last = get_option( self::LAST_PREFIX . $cache_id, array() );
			$last = is_array( $last ) ? $last : array();

			if ( $ok ) {
				$last = array(
					'time'    => time(),
					'source'  => $source,
					'summary' => $summary,
				);
			} else {
				$last['error'] = array(
					'time'    => time(),
					'source'  => $source,
					'summary' => $summary,
				);
			}

			update_option( self::LAST_PREFIX . $cache_id, $last, false );
			self::release( $cache_id );
		}

		/**
		 * WP-Cron callback: run (one batch of) a queued refresh.
		 *
		 * @param string $cache_id Cache ID.
		 * @param int    $offset   Batch offset (calendar only).
		 * @param string $token    Lock token the event was queued under.
		 * @return void
		 */
		public static function run( $cache_id, $offset = 0, $token = '' ) {
			$lock = self::get_live_lock( $cache_id );

			// Lock gone, superseded, or taken by a CLI run: this event is orphaned.
			if ( ! $lock || 'admin' !== $lock['source'] || $lock['token'] !== $token ) {
				return;
			}

			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Background job; batches are sized to finish well inside this anyway.
			}
			wp_raise_memory_limit( 'cron' );

			$lock['status']    = 'running';
			$lock['heartbeat'] = time();
			self::save_lock( $cache_id, $lock );

			try {
				$result = call_user_func( array( __CLASS__, 'job_' . $cache_id ), (int) $offset, $lock );
			} catch ( Throwable $e ) {
				$result = array(
					'done'    => true,
					'ok'      => false,
					'summary' => 'Error: ' . $e->getMessage(),
				);
			}

			if ( empty( $result['done'] ) ) {
				// More batches to go: keep the lock and queue the next one.
				$lock              = array_merge( $lock, $result['lock'] );
				$lock['heartbeat'] = time();
				self::save_lock( $cache_id, $lock );
				wp_schedule_single_event( time(), self::HOOK, array( $cache_id, (int) $result['next'], $token ) );
				return;
			}

			self::finish( $cache_id, ! empty( $result['ok'] ), (string) $result['summary'], 'admin' );
		}

		/**
		 * Published houses that have an iPro property, as WP ID => PropertyId.
		 *
		 * @param House_Calendar_Manager $manager Calendar manager.
		 * @return array<int, string>|false Map, or false if the mapping could not be loaded.
		 */
		public static function get_calendar_targets( $manager ) {
			$method = new ReflectionMethod( $manager, 'get_cached_property_mapping' );
			$method->setAccessible( true );
			$mapping = $method->invoke( $manager );

			if ( empty( $mapping ) || ! is_array( $mapping ) ) {
				return false;
			}

			$wp_to_property = array();

			foreach ( $mapping as $property ) {
				$wp_id   = isset( $property['PropertyReference'] ) ? (int) $property['PropertyReference'] : 0;
				$ipro_id = isset( $property['PropertyId'] ) ? (string) $property['PropertyId'] : '';

				if ( $wp_id && $ipro_id ) {
					$wp_to_property[ $wp_id ] = $ipro_id;
				}
			}

			if ( empty( $wp_to_property ) ) {
				return array();
			}

			$houses = get_posts(
				array(
					'post_type'      => 'houses',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'post__in'       => array_keys( $wp_to_property ),
					'fields'         => 'ids',
				)
			);

			return array_intersect_key( $wp_to_property, array_flip( array_map( 'intval', $houses ) ) );
		}

		/**
		 * Clear and re-request the houses sitemap so Yoast rebuilds its cache.
		 *
		 * @param int $max_pages Safety cap on paginated sitemap pages.
		 * @return array{warmed: int, failed: int, error: string}
		 */
		public static function warm_houses_sitemap( $max_pages = 20 ) {
			$warmed = 0;
			$failed = 0;
			$error  = '';

			for ( $page = 1; $page <= $max_pages; $page++ ) {
				$url = 1 === $page
					? home_url( '/houses-sitemap.xml' )
					: home_url( '/houses-sitemap' . $page . '.xml' );

				$response = wp_remote_get(
					$url,
					array(
						'timeout'   => 60,
						// A loopback to this site: verify SSL the way core's own loopbacks (spawn_cron) do.
						'sslverify' => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
					)
				);
				$code     = (int) wp_remote_retrieve_response_code( $response );

				if ( is_wp_error( $response ) || 200 !== $code ) {
					// Page 1 must exist; a miss on a later page just means we're done.
					if ( 1 === $page ) {
						++$failed;
						$error = $url . ( is_wp_error( $response ) ? ': ' . $response->get_error_message() : " (HTTP {$code})" );
					}
					break;
				}

				++$warmed;
			}

			return array(
				'warmed' => $warmed,
				'failed' => $failed,
				'error'  => $error,
			);
		}

		/**
		 * Calendar job: one batch of the full warm.
		 *
		 * @param int   $offset Index of the first house in this batch.
		 * @param array $lock   Current lock (carries the target list and counts).
		 * @return array Job result.
		 */
		private static function job_calendar( $offset, array $lock ) {
			$manager = new House_Calendar_Manager();

			if ( 0 === $offset ) {
				$api = $manager->check_api_available();

				if ( is_wp_error( $api ) ) {
					return self::api_down( $api );
				}

				$targets = self::get_calendar_targets( $manager );

				if ( false === $targets ) {
					return array(
						'done'    => true,
						'ok'      => false,
						'summary' => 'Could not load the iPro property mapping; existing calendars left in place.',
					);
				}

				$lock['targets'] = array_values( $targets );
				$lock['warmed']  = 0;
				$lock['failed']  = 0;
			}

			$targets = isset( $lock['targets'] ) ? (array) $lock['targets'] : array();
			$total   = count( $targets );

			foreach ( array_slice( $targets, $offset, self::CALENDAR_BATCH ) as $property_id ) {
				// Forced fetch; on failure the existing transient is left untouched.
				$result = $manager->get_calendar_data( $property_id, '', true );

				if ( is_array( $result ) && ! isset( $result['error'] ) ) {
					++$lock['warmed'];
				} else {
					++$lock['failed'];
				}
			}

			$next = $offset + self::CALENDAR_BATCH;

			if ( $next < $total ) {
				$lock['done']  = $next;
				$lock['total'] = $total;

				return array(
					'done' => false,
					'next' => $next,
					'lock' => $lock,
				);
			}

			return array(
				'done'    => true,
				'ok'      => $lock['warmed'] > 0 || 0 === $total,
				'summary' => sprintf( '%d of %d houses refreshed, %d failed (kept their previous data).', $lock['warmed'], $total, $lock['failed'] ),
			);
		}

		/**
		 * Property mapping job.
		 *
		 * @return array Job result.
		 */
		private static function job_property_mapping() {
			$manager = new House_Calendar_Manager();
			$method  = new ReflectionMethod( $manager, 'fetch_property_mapping' );
			$method->setAccessible( true );
			$mapping = $method->invoke( $manager );

			if ( empty( $mapping ) || ! is_array( $mapping ) ) {
				return array(
					'done'    => true,
					'ok'      => false,
					'summary' => 'iPro did not return a property mapping; existing mapping kept.',
				);
			}

			// Same key and lifetime as House_Calendar_Manager::get_cached_property_mapping().
			set_transient( 'kt_property_mapping', $mapping, HOUR_IN_SECONDS );

			return array(
				'done'    => true,
				'ok'      => true,
				'summary' => sprintf( '%d iPro properties mapped.', count( $mapping ) ),
			);
		}

		/**
		 * Seasonal / availability landing pages job.
		 *
		 * @return array Job result.
		 */
		private static function job_seasonal() {
			$api = ( new House_Calendar_Manager() )->check_api_available();

			if ( is_wp_error( $api ) ) {
				return self::api_down( $api );
			}

			// Verdicts live 12h; without this the lists would be rebuilt from old answers.
			$cleared  = self::delete_transients_by_prefix( 'kt_seasonal_avail_v2_' );
			$cleared += self::delete_transients_by_prefix( 'kt_seasonal_prices_v2_' );

			$result = Kate_Toms_Seasonal_Cache_Warmer::warm();

			return array(
				'done'    => true,
				'ok'      => true,
				'summary' => sprintf( '%d page(s), %d section(s), %d house slots (%d stale verdicts/prices cleared).', $result['posts'], $result['sections'], $result['houses'], $cleared ),
			);
		}

		/**
		 * Special offers booked map job.
		 *
		 * @return array Job result.
		 */
		private static function job_special_offers() {
			$api = ( new House_Calendar_Manager() )->check_api_available();

			if ( is_wp_error( $api ) ) {
				return self::api_down( $api );
			}

			$result = Kate_Toms_Special_Offer_Availability::build_booked_map();

			return array(
				'done'    => true,
				'ok'      => true,
				'summary' => sprintf( '%d booked, %d bookable, %d unknown (shown).', $result['booked'], $result['bookable'], $result['unknown'] ),
			);
		}

		/**
		 * Houses sitemap job.
		 *
		 * @return array Job result.
		 */
		private static function job_sitemap() {
			if ( class_exists( 'WPSEO_Sitemaps_Cache_Validator' ) ) {
				// Immediate, unlike WPSEO_Sitemaps_Cache::invalidate(), which waits for shutdown.
				WPSEO_Sitemaps_Cache_Validator::invalidate_storage( 'houses' );
			}

			$result = self::warm_houses_sitemap();

			return array(
				'done'    => true,
				'ok'      => 0 === $result['failed'],
				'summary' => $result['failed']
					? 'Cache cleared, but rebuilding failed: ' . $result['error'] . '. It will rebuild on the next visit.'
					: sprintf( '%d sitemap page(s) rebuilt.', $result['warmed'] ),
			);
		}

		/**
		 * Result for a job skipped because the booking API is down.
		 *
		 * @param WP_Error $error API error.
		 * @return array Job result.
		 */
		private static function api_down( WP_Error $error ) {
			return array(
				'done'    => true,
				'ok'      => false,
				'summary' => 'Booking API unavailable, existing cache left in place: ' . $error->get_error_message(),
			);
		}

		/**
		 * Delete every DB-stored transient whose name starts with a prefix.
		 *
		 * With a persistent object cache transients aren't in the options
		 * table and can't be listed, so nothing is deleted and they expire on
		 * their own TTL instead.
		 *
		 * @param string $prefix Transient name prefix.
		 * @return int Number of transients deleted.
		 */
		private static function delete_transients_by_prefix( $prefix ) {
			global $wpdb;

			if ( wp_using_ext_object_cache() ) {
				return 0;
			}

			$names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Listing transients by prefix has no API.
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( '_transient_' . $prefix ) . '%'
				)
			);

			foreach ( $names as $name ) {
				delete_transient( substr( $name, strlen( '_transient_' ) ) );
			}

			return count( $names );
		}

		/**
		 * The lock for a cache, or null if there is none or it has gone stale.
		 *
		 * @param string $cache_id Cache ID.
		 * @return array|null
		 */
		private static function get_live_lock( $cache_id ) {
			wp_cache_delete( self::LOCK_PREFIX . $cache_id, 'options' );
			$lock = get_option( self::LOCK_PREFIX . $cache_id );

			if ( ! is_array( $lock ) || empty( $lock['status'] ) ) {
				return null;
			}

			$caches = self::caches();
			$ttl    = 'queued' === $lock['status'] ? self::QUEUED_TTL : ( $caches[ $cache_id ]['stale_after'] ?? HOUR_IN_SECONDS );

			if ( (int) ( $lock['heartbeat'] ?? 0 ) + $ttl < time() ) {
				return null;
			}

			return $lock;
		}

		/**
		 * Atomically take a cache's lock.
		 *
		 * @param string $cache_id Cache ID.
		 * @param string $status   'queued' or 'running'.
		 * @param string $source   'admin' or 'cli'.
		 * @return array|false The new lock, or false if someone else holds it.
		 */
		private static function acquire( $cache_id, $status, $source ) {
			$option = self::LOCK_PREFIX . $cache_id;

			// Clear out an abandoned lock (and any batch it left scheduled).
			if ( false !== get_option( $option ) && ! self::get_live_lock( $cache_id ) ) {
				self::release( $cache_id );
			}

			$lock = array(
				'status'    => $status,
				'source'    => $source,
				'token'     => wp_generate_password( 12, false ),
				'queued_at' => time(),
				'heartbeat' => time(),
			);

			/*
			 * INSERT IGNORE on the unique option_name key, so only one caller
			 * can win. add_option() can't be used: it upserts with ON DUPLICATE
			 * KEY UPDATE, so two racing clicks would both "succeed".
			 */
			global $wpdb;

			$inserted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock; caches are cleared below.
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
					$option,
					maybe_serialize( $lock )
				)
			);

			wp_cache_delete( $option, 'options' );
			wp_cache_delete( 'notoptions', 'options' );

			return 1 === $inserted ? $lock : false;
		}

		/**
		 * Persist an updated lock.
		 *
		 * @param string $cache_id Cache ID.
		 * @param array  $lock     Lock data.
		 * @return void
		 */
		private static function save_lock( $cache_id, array $lock ) {
			update_option( self::LOCK_PREFIX . $cache_id, $lock, false );
		}

		/**
		 * Drop a cache's lock and any of its refresh events still scheduled.
		 *
		 * @param string $cache_id Cache ID.
		 * @return void
		 */
		private static function release( $cache_id ) {
			delete_option( self::LOCK_PREFIX . $cache_id );

			foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
				foreach ( $hooks[ self::HOOK ] ?? array() as $event ) {
					if ( ( $event['args'][0] ?? '' ) === $cache_id ) {
						wp_unschedule_event( $timestamp, self::HOOK, $event['args'] );
					}
				}
			}
		}
	}
}
