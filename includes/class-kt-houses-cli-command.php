<?php
/**
 * Backfill ipro_property_id — WP CLI Command
 *
 * One-off reconciliation command that populates the `ipro_property_id` post
 * meta on parent `houses` posts by matching each house against the iPro CRM
 * `/properties` list (the same source the Blueprint wizard uses) and storing
 * the matched property's `Id` (the iPro PropertyId).
 *
 * This value is what the booking enquiry API's `&propertyids=` parameter needs,
 * so populating it lets the booking flow resolve the CRM property directly
 * instead of relying on the legacy `availability_site_post_id` reflookup.
 *
 * Usage:
 *   # Preview what would change without writing anything (recommended first run)
 *   wp kt-houses backfill-ipro-property-id --dry-run
 *
 *   # Apply the backfill (only fills empty meta)
 *   wp kt-houses backfill-ipro-property-id
 *
 *   # Also overwrite houses that already have an ipro_property_id
 *   wp kt-houses backfill-ipro-property-id --overwrite
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * WP-CLI commands for maintaining `houses` post data.
 */
class KT_Houses_CLI_Command extends WP_CLI_Command {

	/**
	 * Backfill the ipro_property_id meta on parent houses from the iPro CRM.
	 *
	 * Fetches the CRM properties list, matches each parent house to a property
	 * by (normalised) name, and stores the property's iPro PropertyId as the
	 * `ipro_property_id` post meta. Houses that match zero or more than one
	 * property are reported and left untouched for manual resolution.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change without writing any meta.
	 *
	 * [--overwrite]
	 * : Also update houses that already have a non-empty ipro_property_id.
	 *
	 * [--refresh]
	 * : Bypass the CRM properties cache (and the property-mapping cache when combined with --from-mapping) and fetch fresh.
	 *
	 * [--from-mapping]
	 * : For houses name-matching cannot resolve, fall back to the iPro property mapping (post ID = PropertyReference) via House_Calendar_Manager.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kt-houses backfill-ipro-property-id --dry-run
	 *     wp kt-houses backfill-ipro-property-id --from-mapping --dry-run
	 *     wp kt-houses backfill-ipro-property-id --from-mapping
	 *
	 * @subcommand backfill-ipro-property-id
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments: dry-run, overwrite, refresh, from-mapping.
	 */
	public function backfill_ipro_property_id( $args, $assoc_args ) {
		$dry_run      = isset( $assoc_args['dry-run'] );
		$overwrite    = isset( $assoc_args['overwrite'] );
		$refresh      = isset( $assoc_args['refresh'] );
		$from_mapping = isset( $assoc_args['from-mapping'] );

		if ( ! class_exists( 'Kate_Toms_Blueprint_CRM_API' ) ) {
			WP_CLI::error( 'Kate_Toms_Blueprint_CRM_API class not found.' );
		}

		WP_CLI::log( $dry_run ? 'Running in DRY-RUN mode — no meta will be written.' : 'Applying backfill.' );

		// 1. Fetch the CRM properties list and build a name -> PropertyId index.
		$crm        = new Kate_Toms_Blueprint_CRM_API();
		$properties = $crm->get_properties( $refresh );

		if ( empty( $properties ) ) {
			WP_CLI::error( 'CRM returned no properties (check credentials / connectivity).' );
		}

		$name_index = array(); // normalised name => array of PropertyIds.
		foreach ( $properties as $property ) {
			$id   = isset( $property['Id'] ) ? (int) $property['Id'] : 0;
			$name = isset( $property['Name'] ) ? self::normalise_name( (string) $property['Name'] ) : '';
			if ( $id > 0 && '' !== $name ) {
				$name_index[ $name ][] = $id;
			}
		}

		WP_CLI::log( sprintf( 'Fetched %d active CRM properties (%d distinct names).', count( $properties ), count( $name_index ) ) );

		// 2. Load parent houses (exclude trashed).
		$houses = get_posts(
			array(
				'post_type'      => 'houses',
				'post_parent'    => 0,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $houses ) ) {
			WP_CLI::warning( 'No parent houses found.' );
			return;
		}

		// 3. Optionally prepare the post-ID -> PropertyId mapping resolver, used
		// as a fallback for houses that name-matching cannot resolve.
		$mapping_resolver = null;
		if ( $from_mapping ) {
			if ( ! class_exists( 'House_Calendar_Manager' ) ) {
				WP_CLI::error( 'House_Calendar_Manager class not found; cannot use --from-mapping.' );
			}
			if ( $refresh ) {
				delete_transient( 'kt_property_mapping' );
			}
			$manager = new House_Calendar_Manager();
			$method  = new ReflectionMethod( 'House_Calendar_Manager', 'get_property_id_from_wp_house_id' );
			$method->setAccessible( true );
			$mapping_resolver = static function ( int $house_id ) use ( $manager, $method ): int {
				$pid = $method->invoke( $manager, $house_id );
				return ( false === $pid || '' === $pid ) ? 0 : (int) $pid;
			};
			WP_CLI::log( 'Mapping fallback enabled (post ID = PropertyReference lookup).' );
		}

		// 4. Match and (optionally) write.
		$updated   = array();
		$skipped   = 0;
		$unmatched = array();
		$ambiguous = array();
		$mapped    = 0; // Count filled via the post-ID mapping fallback.

		foreach ( $houses as $house_id ) {
			$title    = get_the_title( $house_id );
			$existing = get_post_meta( $house_id, 'ipro_property_id', true );

			if ( '' !== $existing && ! $overwrite ) {
				++$skipped;
				continue;
			}

			$key           = self::normalise_name( $title );
			$candidate_ids = ( '' !== $key && isset( $name_index[ $key ] ) )
				? array_values( array_unique( $name_index[ $key ] ) )
				: array();

			$property_id = 0;
			$source      = '';

			if ( 1 === count( $candidate_ids ) ) {
				$property_id = (int) $candidate_ids[0];
				$source      = 'name';
			} elseif ( null !== $mapping_resolver ) {
				// Name match absent or ambiguous — try the post-ID mapping.
				$mapped_id = $mapping_resolver( (int) $house_id );
				if ( $mapped_id > 0 ) {
					$property_id = $mapped_id;
					$source      = 'mapping';
				}
			}

			if ( $property_id <= 0 ) {
				if ( count( $candidate_ids ) > 1 ) {
					$ambiguous[] = array(
						'ID'           => $house_id,
						'title'        => $title,
						'property_ids' => implode( ', ', $candidate_ids ),
					);
				} else {
					$unmatched[] = array(
						'ID'    => $house_id,
						'title' => $title,
					);
				}
				continue;
			}

			if ( 'mapping' === $source ) {
				++$mapped;
			}

			$updated[] = array(
				'ID'          => $house_id,
				'title'       => $title,
				'was'         => '' === $existing ? '(empty)' : $existing,
				'property_id' => $property_id,
				'source'      => $source,
			);

			if ( ! $dry_run ) {
				update_post_meta( $house_id, 'ipro_property_id', $property_id );
			}
		}

		// 5. Report.
		if ( ! empty( $updated ) ) {
			WP_CLI::log( "\n" . ( $dry_run ? 'Would update:' : 'Updated:' ) );
			WP_CLI\Utils\format_items( 'table', $updated, array( 'ID', 'title', 'was', 'property_id', 'source' ) );
		}

		if ( ! empty( $ambiguous ) ) {
			WP_CLI::log( "\nAmbiguous (multiple CRM properties share this name — left untouched):" );
			WP_CLI\Utils\format_items( 'table', $ambiguous, array( 'ID', 'title', 'property_ids' ) );
		}

		if ( ! empty( $unmatched ) ) {
			WP_CLI::log( "\nUnmatched (no CRM property with this name — left untouched):" );
			WP_CLI\Utils\format_items( 'table', $unmatched, array( 'ID', 'title' ) );
		}

		$summary = sprintf(
			'%s %d (name %d, mapping %d), ambiguous %d, unmatched %d, already-set skipped %d, of %d parent houses.',
			$dry_run ? 'Would update' : 'Updated',
			count( $updated ),
			count( $updated ) - $mapped,
			$mapped,
			count( $ambiguous ),
			count( $unmatched ),
			$skipped,
			count( $houses )
		);

		if ( ! empty( $unmatched ) || ! empty( $ambiguous ) ) {
			WP_CLI::warning( $summary . ' Resolve the ambiguous/unmatched houses manually.' );
		} else {
			WP_CLI::success( $summary );
		}
	}

	/**
	 * Normalise a name for matching: lowercase, strip non-alphanumerics to
	 * single spaces, drop a leading "the ", and trim.
	 *
	 * @param string $name Raw name.
	 * @return string Normalised key ('' if nothing usable remains).
	 */
	private static function normalise_name( string $name ): string {
		$name = strtolower( $name );
		$name = preg_replace( '/[^a-z0-9]+/', ' ', $name );
		$name = trim( (string) $name );
		$name = preg_replace( '/^the\s+/', '', $name );

		return trim( (string) $name );
	}
}

WP_CLI::add_command( 'kt-houses', 'KT_Houses_CLI_Command' );
