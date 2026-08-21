<?php
/**
 * Blueprint parent-to-child inheritance.
 *
 * Keeps the parent house page as the single source of truth for the fader
 * images, the "Houses you may also like" selection, and page visibility,
 * pushing each down to the child pages whenever the parent is saved.
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes
 */

declare(strict_types=1);

/**
 * Blueprint parent-to-child inheritance.
 *
 * Runs on every save of a top-level `houses` post, not only at Blueprint
 * creation, so editors keep the benefit for the life of the house: change the
 * images or the related houses once on the parent and every child follows.
 */
class Kate_Toms_Blueprint_Inheritance {

	/**
	 * Number of parent fader images pushed down to the child pages.
	 *
	 * @var int
	 */
	private const FADER_IMAGE_LIMIT = 5;

	/**
	 * Image fader block name.
	 *
	 * @var string
	 */
	private const FADER_BLOCK = 'create-block/kateandtoms-image-fader';

	/**
	 * Post statuses synced from the parent house page down to its children.
	 *
	 * Draft is included because unpublishing a parent does not take its
	 * children off the web: a published child still resolves at its own URL
	 * while the parent returns a 404, leaving a live page for a house that no
	 * longer has a main page. Trash is deliberately excluded — WordPress has
	 * its own handling for child posts there, and cascading it would be
	 * destructive.
	 *
	 * @var string[]
	 */
	private const VISIBILITY_STATUSES = array( 'publish', 'private', 'draft', 'pending' );

	/**
	 * Guards against re-entrancy while child posts are being updated.
	 *
	 * @var bool
	 */
	private static bool $is_syncing = false;

	/**
	 * Registers WordPress hooks used by this feature.
	 */
	public function __construct() {
		add_action( 'save_post_houses', array( $this, 'sync_content_to_children' ), 20, 3 );
		add_action( 'transition_post_status', array( $this, 'sync_visibility_to_children' ), 10, 3 );
	}

	/**
	 * Pushes the parent's fader images and related houses down to its children.
	 *
	 * @param int     $post_id Saved post ID.
	 * @param WP_Post $post    Saved post object.
	 * @param bool    $update  Whether this is an update rather than an insert.
	 *
	 * @return void
	 */
	public function sync_content_to_children( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );

		if ( self::$is_syncing || ! $this->is_parent_house( $post ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$children = $this->get_children( $post_id );

		if ( empty( $children ) ) {
			return;
		}

		$slides      = $this->extract_fader_slides( $post->post_content );
		$related_ids = $this->extract_related_house_ids( $post->post_content );

		self::$is_syncing = true;

		foreach ( $children as $child ) {
			$content = $child->post_content;
			$content = $this->apply_fader_slides( $content, $slides );
			$content = $this->apply_related_house_ids( $content, $related_ids );

			if ( $content === $child->post_content ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'           => $child->ID,
					'post_content' => $content,
				)
			);
		}

		self::$is_syncing = false;
	}

	/**
	 * Mirrors the parent page's visibility onto its child pages.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post whose status changed.
	 *
	 * @return void
	 */
	public function sync_visibility_to_children( string $new_status, string $old_status, WP_Post $post ): void {
		if ( self::$is_syncing || $new_status === $old_status || ! $this->is_parent_house( $post ) ) {
			return;
		}

		if ( ! in_array( $new_status, self::VISIBILITY_STATUSES, true ) ) {
			return;
		}

		$children = $this->get_children( $post->ID );

		self::$is_syncing = true;

		foreach ( $children as $child ) {
			if ( $child->post_status === $new_status ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $child->ID,
					'post_status' => $new_status,
				)
			);
		}

		self::$is_syncing = false;
	}

	/**
	 * Determines whether a post is a top-level house page.
	 *
	 * @param WP_Post $post Post to test.
	 *
	 * @return bool True for a top-level `houses` post.
	 */
	private function is_parent_house( WP_Post $post ): bool {
		return 'houses' === $post->post_type && 0 === (int) $post->post_parent;
	}

	/**
	 * Returns the child pages of a house, in menu order.
	 *
	 * @param int $parent_id Parent house post ID.
	 *
	 * @return WP_Post[] Child posts.
	 */
	private function get_children( int $parent_id ): array {
		return get_posts(
			array(
				'post_type'        => 'houses',
				'post_parent'      => $parent_id,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Extracts the first N slide elements from a page's image fader.
	 *
	 * @param string $content Post content.
	 *
	 * @return string Concatenated slide markup, or an empty string when none.
	 */
	private function extract_fader_slides( string $content ): string {
		$fader = $this->find_first_block( parse_blocks( $content ), self::FADER_BLOCK );

		if ( null === $fader ) {
			return '';
		}

		if ( ! preg_match_all( '#<div class="slide">.*?</div>#s', (string) $fader['innerHTML'], $matches ) ) {
			return '';
		}

		return implode( '', array_slice( $matches[0], 0, self::FADER_IMAGE_LIMIT ) );
	}

	/**
	 * Writes a set of slides into the first image fader in some content.
	 *
	 * The fader's own wrapper element is kept, so each page's alignment and
	 * minimum height survive. Nothing is changed when the source has no images.
	 *
	 * @param string $content Post content to update.
	 * @param string $slides  Slide markup from the parent page.
	 *
	 * @return string Updated content.
	 */
	private function apply_fader_slides( string $content, string $slides ): string {
		if ( '' === $slides ) {
			return $content;
		}

		$blocks  = parse_blocks( $content );
		$changed = false;

		$blocks = $this->map_blocks(
			$blocks,
			function ( array $block ) use ( $slides, &$changed ): array {
				if ( self::FADER_BLOCK !== ( $block['blockName'] ?? '' ) || $changed ) {
					return $block;
				}

				foreach ( $block['innerContent'] as $index => $chunk ) {
					if ( ! is_string( $chunk ) ) {
						continue;
					}

					$updated = preg_replace(
						'#(<div\b[^>]*>).*(</div>)#s',
						'$1' . str_replace( '$', '\$', $slides ) . '$2',
						$chunk,
						1
					);

					if ( null !== $updated && $updated !== $chunk ) {
						$block['innerContent'][ $index ] = $updated;
						$block['innerHTML']              = $updated;
						$changed                         = true;
						break;
					}
				}

				return $block;
			}
		);

		return $changed ? serialize_blocks( $blocks ) : $content;
	}

	/**
	 * Reads the parent page's "Houses you may also like" selection.
	 *
	 * Fern's MASTER templates express the selection two ways: a single
	 * `related-houses` block holding four IDs, or four separate
	 * `kateandtoms-single-house` blocks. Both are read so the parent page works
	 * whichever pattern an editor used.
	 *
	 * @param string $content Parent post content.
	 *
	 * @return int[] Up to four house post IDs, in display order.
	 */
	private function extract_related_house_ids( string $content ): array {
		$blocks  = parse_blocks( $content );
		$related = $this->find_first_block( $blocks, 'kate-toms-core/related-houses' );

		if ( null !== $related ) {
			$ids = array();

			for ( $i = 1; $i <= 4; $i++ ) {
				$ids[] = (int) ( $related['attrs'][ 'house' . $i . 'Id' ] ?? 0 );
			}

			if ( array_filter( $ids ) ) {
				return $ids;
			}
		}

		$ids = array();

		$this->map_blocks(
			$blocks,
			static function ( array $block ) use ( &$ids ): array {
				if ( 'kate-toms-core/kateandtoms-single-house' === ( $block['blockName'] ?? '' ) ) {
					$ids[] = (int) ( $block['attrs']['selectedPostId'] ?? 0 );
				}

				return $block;
			}
		);

		$ids = array_slice( $ids, 0, 4 );

		return array_filter( $ids ) ? array_pad( $ids, 4, 0 ) : array();
	}

	/**
	 * Writes a related-house selection into a child page's content.
	 *
	 * @param string $content Child post content.
	 * @param int[]  $ids     Four house post IDs, in display order.
	 *
	 * @return string Updated content.
	 */
	private function apply_related_house_ids( string $content, array $ids ): string {
		if ( empty( $ids ) ) {
			return $content;
		}

		$changed  = false;
		$position = 0;

		$blocks = $this->map_blocks(
			parse_blocks( $content ),
			static function ( array $block ) use ( $ids, &$changed, &$position ): array {
				$name = $block['blockName'] ?? '';

				if ( 'kate-toms-core/related-houses' === $name ) {
					for ( $i = 1; $i <= 4; $i++ ) {
						$key = 'house' . $i . 'Id';

						if ( (int) ( $block['attrs'][ $key ] ?? 0 ) !== $ids[ $i - 1 ] ) {
							$block['attrs'][ $key ] = $ids[ $i - 1 ];
							$changed                = true;
						}
					}

					return $block;
				}

				if ( 'kate-toms-core/kateandtoms-single-house' === $name ) {
					$id = $ids[ $position ] ?? 0;
					++$position;

					if ( (int) ( $block['attrs']['selectedPostId'] ?? 0 ) !== $id ) {
						$block['attrs']['selectedPostId'] = $id;
						$changed                          = true;
					}
				}

				return $block;
			}
		);

		return $changed ? serialize_blocks( $blocks ) : $content;
	}

	/**
	 * Returns the first block of a given type in a parsed tree.
	 *
	 * @param array[] $blocks     Parsed blocks.
	 * @param string  $block_name Block name to find.
	 *
	 * @return array|null The matching block, or null when absent.
	 */
	private function find_first_block( array $blocks, string $block_name ): ?array {
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === $block_name ) {
				return $block;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->find_first_block( $block['innerBlocks'], $block_name );

				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Applies a callback to every block in a parsed tree, depth-first.
	 *
	 * @param array[]  $blocks   Parsed blocks.
	 * @param callable $callback Receives a block array, returns the replacement.
	 *
	 * @return array[] Mutated blocks.
	 */
	private function map_blocks( array $blocks, callable $callback ): array {
		foreach ( $blocks as $index => $block ) {
			$block = $callback( $block );

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->map_blocks( $block['innerBlocks'], $callback );
			}

			$blocks[ $index ] = $block;
		}

		return $blocks;
	}
}
