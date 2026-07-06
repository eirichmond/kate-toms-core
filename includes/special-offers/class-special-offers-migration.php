<?php
/**
 * One-off migration of legacy flat special-offer-house blocks into the grid.
 *
 * Legacy content placed the special-offer-house blocks as flat siblings inside
 * a core/columns wrapper (the old Houses Special Offers pattern). This routine
 * re-parents those children into a single Special Offers Grid block and drops
 * the columns wrapper, preserving each child's attributes.
 *
 * Run once via: wp eval "do_action( 'migrate_special_offers_to_grid' );"
 *
 * @package kate-toms-core
 */

declare(strict_types=1);

/**
 * Converts legacy flat special-offer layouts to the parent/child grid.
 */
class Kate_Toms_Special_Offers_Migration {

	/**
	 * Child block name.
	 *
	 * @var string
	 */
	private const CHILD_BLOCK = 'kate-toms-core/kateandtoms-special-offer-house';

	/**
	 * Parent grid block name.
	 *
	 * @var string
	 */
	private const GRID_BLOCK = 'kate-toms-core/special-offers-grid';

	/**
	 * Registers the migration action hook.
	 */
	public function __construct() {
		add_action( 'migrate_special_offers_to_grid', array( $this, 'run' ) );
	}

	/**
	 * Migrates every candidate post and reports how many were updated.
	 *
	 * @return void
	 */
	public function run(): void {
		$post_ids = $this->get_candidate_post_ids();
		$updated  = 0;

		foreach ( $post_ids as $post_id ) {
			if ( $this->migrate_post( $post_id ) ) {
				++$updated;
			}
		}

		$message = sprintf(
			'Special Offers migration: %1$d of %2$d candidate post(s) updated.',
			$updated,
			count( $post_ids )
		);

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $message );
		}

		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- One-off migration progress output.
	}

	/**
	 * Finds posts that still use flat special-offer-house blocks (not yet gridded).
	 *
	 * @return int[] Candidate post IDs.
	 */
	private function get_candidate_post_ids(): array {
		global $wpdb;

		$like_child = '%' . $wpdb->esc_like( self::CHILD_BLOCK ) . '%';
		$like_grid  = '%' . $wpdb->esc_like( self::GRID_BLOCK ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off content scan, not cacheable.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status NOT IN ( 'trash', 'auto-draft' )
				AND post_content LIKE %s
				AND post_content NOT LIKE %s",
				$like_child,
				$like_grid
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Migrates a single post's content if it contains a convertible layout.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True when the post content was changed and saved.
	 */
	private function migrate_post( int $post_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$new_content = serialize_blocks( $this->transform_blocks( parse_blocks( $post->post_content ) ) );

		if ( $new_content === $post->post_content ) {
			return false;
		}

		global $wpdb;

		// Direct update avoids KSES stripping/altering the block delimiter comments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration; cache cleared below.
		$wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $new_content ),
			array( 'ID' => $post_id )
		);

		clean_post_cache( $post_id );

		return true;
	}

	/**
	 * Recursively replaces columns-wrapped offer layouts with grid blocks.
	 *
	 * Each block is mapped one-to-one so the parent's innerContent placeholders
	 * stay aligned when the tree is re-serialised.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 *
	 * @return array<int, array<string, mixed>> Transformed blocks.
	 */
	private function transform_blocks( array $blocks ): array {
		$out = array();

		foreach ( $blocks as $block ) {
			if ( 'core/columns' === $block['blockName'] ) {
				$offers = $this->collect_offer_children( $block );

				if ( ! empty( $offers ) ) {
					$out[] = $this->build_grid_block( $offers );
					continue;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->transform_blocks( $block['innerBlocks'] );
			}

			$out[] = $block;
		}

		return $out;
	}

	/**
	 * Collects all special-offer-house descendant blocks in document order.
	 *
	 * @param array<string, mixed> $block Parent block.
	 *
	 * @return array<int, array<string, mixed>> Special-offer-house blocks.
	 */
	private function collect_offer_children( array $block ): array {
		$found = array();

		foreach ( $block['innerBlocks'] as $inner ) {
			if ( self::CHILD_BLOCK === $inner['blockName'] ) {
				$found[] = $inner;
				continue;
			}

			if ( ! empty( $inner['innerBlocks'] ) ) {
				$found = array_merge( $found, $this->collect_offer_children( $inner ) );
			}
		}

		return $found;
	}

	/**
	 * Builds a Special Offers Grid block wrapping the given offer children.
	 *
	 * @param array<int, array<string, mixed>> $offer_children Special-offer-house blocks.
	 *
	 * @return array<string, mixed> Parsed grid block ready for serialisation.
	 */
	private function build_grid_block( array $offer_children ): array {
		$grid_markup = '<!-- wp:' . self::GRID_BLOCK . ' {"align":"wide"} -->' . "\n"
			. '<div class="wp-block-kate-toms-core-special-offers-grid alignwide">'
			. serialize_blocks( $offer_children )
			. '</div>' . "\n"
			. '<!-- /wp:' . self::GRID_BLOCK . ' -->';

		foreach ( parse_blocks( $grid_markup ) as $parsed ) {
			if ( self::GRID_BLOCK === $parsed['blockName'] ) {
				return $parsed;
			}
		}

		return array();
	}
}
