<?php
/**
 * Blueprint page templates.
 *
 * Resolves the MASTER source content for each Blueprint page, personalises it
 * for a specific house, and applies the standing layout defaults required by
 * the Blueprint brief.
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/includes
 */

declare(strict_types=1);

/**
 * Blueprint page templates.
 *
 * Fern's MASTER templates are synced patterns stored as `wp_block` posts, not
 * theme pattern files, so they are looked up by post title. The Book page has
 * no MASTER, so it ships with the plugin as a tokenised HTML template derived
 * from the live Marsden Manor book page.
 *
 * MASTER content carries two placeholder tokens, used consistently throughout:
 *
 *   `house-name` — inside `/houses/house-name/…` URLs and `h-house-name-…` anchors.
 *   `HOUSE NAME` — inside the sub-page H1 text.
 */
class Kate_Toms_Blueprint_Templates {

	/**
	 * Placeholder slug token used in MASTER URLs and heading anchors.
	 *
	 * @var string
	 */
	private const SLUG_TOKEN = 'house-name';

	/**
	 * Placeholder display-title token used in MASTER heading text.
	 *
	 * @var string
	 */
	private const TITLE_TOKEN = 'HOUSE NAME';

	/**
	 * Block style applied to every core/buttons block (stacked + centred on mobile).
	 *
	 * Registered by the katomswold theme.
	 *
	 * @var string
	 */
	private const BUTTONS_MOBILE_CLASS = 'is-style-stack-buttons-mobile';

	/**
	 * Utility class applied to the Key Facts bedroom table (25/15/30/30 widths).
	 *
	 * Deliberately not an `is-style-*` class: the bedroom table already carries
	 * `is-style-stripes`, and WordPress replaces the whole `is-style-*` class
	 * when an editor picks a different block style.
	 *
	 * @var string
	 */
	private const BEDROOM_TABLE_CLASS = 'kt-bedroom-table';

	/**
	 * Image fader block name.
	 *
	 * @var string
	 */
	private const FADER_BLOCK = 'create-block/kateandtoms-image-fader';

	/**
	 * Blueprint page configuration, in creation order.
	 *
	 * `label` is the middle segment of the page title and is empty for the
	 * parent. `source` describes where the page's starting content comes from:
	 * a `wp_block` MASTER looked up by title, or a template file shipped with
	 * the plugin. `insert` optionally lists katomswold theme patterns to add to
	 * that content — see insert_pattern() for how each rule is placed.
	 *
	 * @var array<string, array{label: string, source: array{type: string, title?: string, file?: string}, insert?: array<array{pattern: string, replace?: string, before?: string}>}>
	 */
	private static array $pages = array(
		'parent'       => array(
			'label'  => '',
			'source' => array(
				'type'  => 'wp_block',
				'title' => 'House Build Main Page MASTER',
			),
		),
		'availability' => array(
			'label'  => 'Availability',
			'source' => array(
				'type'  => 'wp_block',
				'title' => 'Availability page MASTER',
			),
		),
		'book'         => array(
			'label'  => 'Book',
			'source' => array(
				'type' => 'file',
				'file' => 'book.html',
			),
		),
		'gallery'      => array(
			'label'  => 'Gallery',
			'source' => array(
				'type'  => 'wp_block',
				'title' => 'Gallery MASTER',
			),
			'insert' => array(
				array(
					'pattern' => 'katomswold/standard-widget-virtual-tour',
					'replace' => 'h-virtual-tour',
					'before'  => 'kate-toms-core/related-houses',
				),
			),
		),
		'facts'        => array(
			'label'  => 'Key Facts',
			'source' => array(
				'type'  => 'wp_block',
				'title' => 'Key Facts MASTER',
			),
		),
		'more'         => array(
			'label'  => 'Things To Do',
			'source' => array(
				'type'  => 'wp_block',
				'title' => 'Things to Do MASTER',
			),
		),
	);

	/**
	 * Returns the full page configuration, keyed by page key.
	 *
	 * @return array<string, array{label: string, source: array}> Page config.
	 */
	public static function get_pages(): array {
		return self::$pages;
	}

	/**
	 * Returns the child page keys, in creation order (everything but 'parent').
	 *
	 * @return string[] Child page keys.
	 */
	public static function get_child_keys(): array {
		return array_values( array_diff( array_keys( self::$pages ), array( 'parent' ) ) );
	}

	/**
	 * Builds the post title for a Blueprint page.
	 *
	 * Matches the live site convention, e.g. "Marsden Manor | Gallery | kate & tom's".
	 * The parent page uses the display title alone.
	 *
	 * @param string $display_title House display title.
	 * @param string $key           Page key.
	 *
	 * @return string Post title.
	 */
	public static function build_title( string $display_title, string $key ): string {
		$label = self::$pages[ $key ]['label'] ?? '';

		if ( '' === $label ) {
			return $display_title;
		}

		return sprintf( "%s | %s | kate & tom's", $display_title, $label );
	}

	/**
	 * Returns the content sources that could not be found, for preflight warnings.
	 *
	 * Covers both the MASTER synced patterns and the theme patterns appended to
	 * a page, so the wizard can warn before anything is created.
	 *
	 * @return string[] Missing MASTER titles and theme pattern slugs.
	 */
	public function get_missing_sources(): array {
		$missing = array();

		foreach ( self::$pages as $config ) {
			if ( 'wp_block' === $config['source']['type'] && null === $this->find_master( $config['source']['title'] ) ) {
				$missing[] = $config['source']['title'];
			}

			foreach ( $config['insert'] ?? array() as $rule ) {
				if ( null === $this->find_theme_pattern( $rule['pattern'] ) ) {
					$missing[] = $rule['pattern'];
				}
			}
		}

		return $missing;
	}

	/**
	 * Returns finished post_content for a Blueprint page.
	 *
	 * @param string $key               Page key.
	 * @param string $display_title     House display title.
	 * @param string $house_slug        Parent house post slug.
	 * @param int    $ipro_property_id  iPro CRM PropertyId, matching the
	 *                                  `ipro_property_id` meta written on the
	 *                                  parent house post. The availability
	 *                                  calendar block calls the iPro API with
	 *                                  this ID directly (see
	 *                                  House_Calendar_Manager::get_calendar_data()),
	 *                                  so its `houseId` attribute must carry it too.
	 *
	 * @return string Block markup, or an empty string if the source is missing.
	 */
	public function get_content( string $key, string $display_title, string $house_slug, int $ipro_property_id ): string {
		$source = $this->load_source( $key );

		if ( '' === $source ) {
			return '';
		}

		$content = $this->personalise( $source, $display_title, $house_slug, $key );
		$content = $this->ensure_tour_nav_link( $content, $house_slug );

		return $this->apply_defaults( $content, $ipro_property_id );
	}

	/**
	 * Loads the raw starting content for a page key.
	 *
	 * @param string $key Page key.
	 *
	 * @return string Raw block markup, or an empty string when unavailable.
	 */
	private function load_source( string $key ): string {
		$config = self::$pages[ $key ] ?? null;

		if ( null === $config ) {
			$this->log_warning( "Unknown blueprint page key: {$key}" );
			return '';
		}

		$source = $config['source'];

		if ( 'file' === $source['type'] ) {
			$content = $this->load_template_file( $source['file'] );
		} else {
			$content = $this->find_master( $source['title'] );

			if ( null === $content ) {
				$this->log_warning( "MASTER pattern not found: {$source['title']}" );
				$content = '';
			}
		}

		if ( '' === $content ) {
			return '';
		}

		foreach ( $config['insert'] ?? array() as $rule ) {
			$content = $this->insert_pattern( $content, $rule );
		}

		return $content;
	}

	/**
	 * Inserts a theme pattern into a page's content.
	 *
	 * Placement is tried in three steps, so the result stays right whether or
	 * not the MASTER still carries the section the pattern supersedes:
	 *
	 *   `replace` — an HTML anchor identifying a section the MASTER already
	 *               has. The top-level block containing it is swapped for the
	 *               pattern. The Gallery MASTER ends with an older Virtual Tour
	 *               section — same heading and copy, a flat image in place of
	 *               the vr-tour block — which the pattern replaces outright.
	 *   `before`  — otherwise the pattern goes above this block, keeping
	 *               "Houses you may also like" as the closing section.
	 *   neither   — otherwise the pattern is appended.
	 *
	 * @param string $rule_content Page content.
	 * @param array  $rule         Rule: `pattern` slug, optional `replace` anchor and `before` block name.
	 *
	 * @return string Content with the pattern placed.
	 */
	private function insert_pattern( string $rule_content, array $rule ): string {
		$pattern = $this->find_theme_pattern( $rule['pattern'] );

		if ( null === $pattern ) {
			$this->log_warning( "Theme pattern not found: {$rule['pattern']}" );
			return $rule_content;
		}

		$blocks   = parse_blocks( $rule_content );
		$addition = parse_blocks( $pattern );
		$replace  = $rule['replace'] ?? '';

		if ( '' !== $replace ) {
			$index = $this->find_block_with_anchor( $blocks, $replace );

			if ( null !== $index ) {
				array_splice( $blocks, $index, 1, $addition );

				return serialize_blocks( $blocks );
			}
		}

		$before   = $rule['before'] ?? '';
		$position = count( $blocks );

		if ( '' !== $before ) {
			foreach ( $blocks as $index => $block ) {
				if ( ( $block['blockName'] ?? '' ) === $before ) {
					$position = $index;
					break;
				}
			}
		}

		array_splice( $blocks, $position, 0, $addition );

		return serialize_blocks( $blocks );
	}

	/**
	 * Finds the top-level block whose markup carries a given HTML anchor.
	 *
	 * @param array[] $blocks Parsed top-level blocks.
	 * @param string  $anchor Anchor id to look for.
	 *
	 * @return int|null Index of the matching block, or null when absent.
	 */
	private function find_block_with_anchor( array $blocks, string $anchor ): ?int {
		$needle = sprintf( 'id="%s"', $anchor );

		foreach ( $blocks as $index => $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( str_contains( serialize_block( $block ), $needle ) ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Returns a registered theme pattern's content by slug.
	 *
	 * @param string $slug Pattern slug, e.g. 'katomswold/standard-widget-virtual-tour'.
	 *
	 * @return string|null Pattern content, or null when it is not registered.
	 */
	private function find_theme_pattern( string $slug ): ?string {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $slug );

		if ( ! is_array( $pattern ) || ! isset( $pattern['content'] ) ) {
			return null;
		}

		return (string) $pattern['content'];
	}

	/**
	 * Reads a template file shipped with the plugin.
	 *
	 * @param string $file File name within templates/blueprint/.
	 *
	 * @return string File contents, or an empty string if unreadable.
	 */
	private function load_template_file( string $file ): string {
		$path = plugin_dir_path( __DIR__ ) . 'templates/blueprint/' . basename( $file );

		if ( ! is_readable( $path ) ) {
			$this->log_warning( "Blueprint template file not readable: {$path}" );
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $path );

		return false === $contents ? '' : $contents;
	}

	/**
	 * Finds a MASTER synced pattern's content by post title.
	 *
	 * @param string $title Exact `wp_block` post title.
	 *
	 * @return string|null Pattern content, or null when no match exists.
	 */
	private function find_master( string $title ): ?string {
		$query = new WP_Query(
			array(
				'post_type'              => 'wp_block',
				'post_status'            => 'publish',
				'title'                  => $title,
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		return (string) $query->posts[0]->post_content;
	}

	/**
	 * Replaces the MASTER placeholder tokens with real house values.
	 *
	 * Also normalises absolute local URLs and any hard-coded reference to the
	 * Marsden Manor page that leaked into the MASTER content.
	 *
	 * @param string $content       Raw MASTER markup.
	 * @param string $display_title House display title.
	 * @param string $house_slug    Parent house post slug.
	 * @param string $key           Page key, used to resolve tour links.
	 *
	 * @return string Personalised markup.
	 */
	private function personalise( string $content, string $display_title, string $house_slug, string $key ): string {
		// Absolute local URLs in MASTER content become root-relative first, so
		// the token replacements below catch them too.
		$content = preg_replace( '#https?://[^/"\']+/houses/#', '/houses/', $content );

		// A couple of MASTERs reference the Marsden Manor page directly rather
		// than the placeholder token.
		$content = str_replace( '/houses/marsden-manor/', '/houses/' . self::SLUG_TOKEN . '/', $content );

		$content = str_replace(
			array(
				'/houses/' . self::SLUG_TOKEN . '/',
				'h-' . self::SLUG_TOKEN . '-',
				self::TITLE_TOKEN,
				// The katomswold banner patterns use their own placeholders,
				// which reach the MASTERs whenever one is re-synced from them.
				'{perma}',
				'{housetitle}',
			),
			array(
				'/houses/' . $house_slug . '/',
				'h-' . $house_slug . '-',
				$display_title,
				$house_slug,
				$display_title,
			),
			$content
		);

		// The banner's VR TOUR link targets the tour block's own anchor, which
		// only exists once an editor sets a tour URL. Point it at the section
		// heading instead, which is always there.
		$content = str_replace( '/gallery/#vr-tour', '/gallery/#h-virtual-tour', $content );

		// Sample Matterport links appear as plain hrefs too — on the Virtual
		// Tour pattern's "View Tour" button and in the Key Facts MASTER. The
		// tour itself lives on the Gallery page, so point them there; only the
		// Gallery page's own button can use a bare fragment.
		$tour_link = 'gallery' === $key
			? '#h-virtual-tour'
			: sprintf( '/houses/%s/gallery/#h-virtual-tour', $house_slug );

		$content = preg_replace(
			'#href="https?://(?:[a-z0-9-]+\.)*matterport\.com/[^"]*"#i',
			sprintf( 'href="%s"', $tour_link ),
			$content
		);

		return $content;
	}

	/**
	 * Ensures the house title banner's VR TOUR item links to the tour.
	 *
	 * The MASTERs carry a VR TOUR item in the banner nav, but as plain text —
	 * it predates the tour having anywhere to point at. It is turned into a
	 * link here. Only if a page has no VR TOUR item at all is one added beside
	 * GALLERY, matching how the katomswold banner patterns order it.
	 *
	 * @param string $content    Personalised page markup.
	 * @param string $house_slug Parent house post slug.
	 *
	 * @return string Markup with a linked VR TOUR nav item.
	 */
	private function ensure_tour_nav_link( string $content, string $house_slug ): string {
		$href    = sprintf( '/houses/%s/gallery/#h-virtual-tour', $house_slug );
		$blocks  = parse_blocks( $content );
		$found   = false;
		$changed = false;

		$blocks = $this->link_tour_nav_item( $blocks, $href, $found, $changed );

		if ( ! $found ) {
			$blocks = $this->add_tour_nav_item( $blocks, $href, $changed );
		}

		return $changed ? serialize_blocks( $blocks ) : $content;
	}

	/**
	 * Turns an existing VR TOUR nav item into a link to the tour.
	 *
	 * `$found` records that the page has a VR TOUR item at all, separately from
	 * `$changed`, which records that this pass rewrote one. An item that is
	 * already linked correctly leaves the markup untouched, and conflating the
	 * two would make the caller add a second item on a re-run.
	 *
	 * @param array[] $blocks  Parsed blocks.
	 * @param string  $href    Tour URL.
	 * @param bool    $found   Set to true when a VR TOUR item exists.
	 * @param bool    $changed Set to true when an item's markup was rewritten.
	 *
	 * @return array[] Mutated blocks.
	 */
	private function link_tour_nav_item( array $blocks, string $href, bool &$found, bool &$changed ): array {
		foreach ( $blocks as $index => $block ) {
			if ( $found ) {
				break;
			}

			if ( 'core/paragraph' === ( $block['blockName'] ?? '' ) ) {
				$html = (string) ( $block['innerHTML'] ?? '' );

				if ( 'VR TOUR' === trim( wp_strip_all_tags( $html ) ) ) {
					$found = true;

					$linked = preg_replace(
						'#(<p\b[^>]*>).*(</p>)#s',
						'${1}' . $this->escape_replacement( sprintf( '<a href="%s">VR TOUR</a>', $href ) ) . '${2}',
						$html,
						1
					);

					if ( null !== $linked && $linked !== $html ) {
						$block['innerHTML']    = $linked;
						$block['innerContent'] = array( $linked );
						$blocks[ $index ]      = $block;
						$changed               = true;
					}

					continue;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->link_tour_nav_item( $block['innerBlocks'], $href, $found, $changed );
				$blocks[ $index ]     = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Adds a VR TOUR nav item after the banner's GALLERY link.
	 *
	 * Used only when a page has no VR TOUR item to link. The new paragraph is
	 * cloned from the GALLERY one so the styling matches, and the parent's
	 * `innerContent` gains a matching null placeholder — serialize_block()
	 * pairs each null with the next inner block, so without one the parent's
	 * last child would be dropped from the output.
	 *
	 * @param array[] $blocks  Parsed blocks.
	 * @param string  $href    Tour URL.
	 * @param bool    $changed Set to true once the item has been added.
	 *
	 * @return array[] Mutated blocks.
	 */
	private function add_tour_nav_item( array $blocks, string $href, bool &$changed ): array {
		foreach ( $blocks as $index => $block ) {
			if ( $changed ) {
				break;
			}

			$children = $block['innerBlocks'] ?? array();

			foreach ( $children as $child_index => $child ) {
				if ( ! $this->is_gallery_nav_link( $child ) ) {
					continue;
				}

				$html = (string) ( $child['innerHTML'] ?? '' );
				$html = preg_replace(
					'#(<p\b[^>]*>).*(</p>)#s',
					'${1}' . $this->escape_replacement( sprintf( '<a href="%s">VR TOUR</a>', $href ) ) . '${2}',
					$html,
					1
				);

				$child['innerHTML']    = (string) $html;
				$child['innerContent'] = array( (string) $html );

				array_splice( $block['innerBlocks'], $child_index + 1, 0, array( $child ) );
				$block['innerContent'] = $this->insert_inner_placeholder( $block['innerContent'], $child_index );

				$blocks[ $index ] = $block;
				$changed          = true;

				return $blocks;
			}

			if ( ! empty( $children ) ) {
				$block['innerBlocks'] = $this->add_tour_nav_item( $children, $href, $changed );
				$blocks[ $index ]     = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Adds an inner-block placeholder to an `innerContent` list.
	 *
	 * @param array $inner_content Parent block's innerContent.
	 * @param int   $after         Index of the inner block to insert after.
	 *
	 * @return array Updated innerContent.
	 */
	private function insert_inner_placeholder( array $inner_content, int $after ): array {
		$seen = -1;

		foreach ( $inner_content as $position => $chunk ) {
			if ( null !== $chunk ) {
				continue;
			}

			++$seen;

			if ( $seen === $after ) {
				array_splice( $inner_content, $position + 1, 0, array( null ) );

				return $inner_content;
			}
		}

		$inner_content[] = null;

		return $inner_content;
	}

	/**
	 * Escapes a preg_replace replacement string.
	 *
	 * @param string $replacement Literal text to substitute in.
	 *
	 * @return string Escaped replacement.
	 */
	private function escape_replacement( string $replacement ): string {
		return str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $replacement );
	}

	/**
	 * Determines whether a block is the banner nav's GALLERY link.
	 *
	 * Matches on the upper-case link text the banner nav uses, so ordinary
	 * "View Gallery" buttons elsewhere on a page are left alone.
	 *
	 * @param array $block Parsed block.
	 *
	 * @return bool True for the banner's GALLERY nav paragraph.
	 */
	private function is_gallery_nav_link( array $block ): bool {
		if ( 'core/paragraph' !== ( $block['blockName'] ?? '' ) ) {
			return false;
		}

		return 1 === preg_match(
			'#<a\s[^>]*href="[^"]*/gallery/?"[^>]*>\s*GALLERY\s*</a>#',
			(string) ( $block['innerHTML'] ?? '' )
		);
	}

	/**
	 * Applies the Blueprint's standing layout and placeholder defaults.
	 *
	 * Walks the parsed block tree once and mutates matching blocks, then
	 * re-serialises. Working on the parsed tree rather than the raw markup
	 * keeps nested blocks and inner HTML intact.
	 *
	 * @param string $content          Personalised markup.
	 * @param int    $ipro_property_id iPro CRM PropertyId, passed through to
	 *                                 apply_block_defaults() for blocks that key off it.
	 *
	 * @return string Markup with defaults applied.
	 */
	private function apply_defaults( string $content, int $ipro_property_id ): string {
		$blocks = parse_blocks( $content );
		$blocks = $this->walk_blocks( $blocks, $ipro_property_id );

		return serialize_blocks( $blocks );
	}

	/**
	 * Recursively applies per-block defaults across a parsed block tree.
	 *
	 * @param array[] $blocks           Parsed blocks.
	 * @param int     $ipro_property_id iPro CRM PropertyId.
	 *
	 * @return array[] Mutated blocks.
	 */
	private function walk_blocks( array $blocks, int $ipro_property_id ): array {
		foreach ( $blocks as $index => $block ) {
			$block = $this->apply_block_defaults( $block, $ipro_property_id );

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->walk_blocks( $block['innerBlocks'], $ipro_property_id );
			}

			$blocks[ $index ] = $block;
		}

		return $blocks;
	}

	/**
	 * Applies the Blueprint defaults relevant to a single block.
	 *
	 * @param array $block            Parsed block.
	 * @param int   $ipro_property_id iPro CRM PropertyId.
	 *
	 * @return array Mutated block.
	 */
	private function apply_block_defaults( array $block, int $ipro_property_id ): array {
		switch ( $block['blockName'] ?? '' ) {
			case 'core/buttons':
				return $this->add_block_class( $block, self::BUTTONS_MOBILE_CLASS );

			case 'core/table':
				return $this->is_bedroom_table( $block )
					? $this->add_block_class( $block, self::BEDROOM_TABLE_CLASS )
					: $block;

			case 'kate-toms-core/related-houses':
				// Clear the MASTER's sample selections; the parent house page is
				// the single source of truth and inheritance fills these in.
				$block['attrs']['house1Id'] = 0;
				$block['attrs']['house2Id'] = 0;
				$block['attrs']['house3Id'] = 0;
				$block['attrs']['house4Id'] = 0;
				return $block;

			case 'kate-toms-core/kateandtoms-single-house':
				$block['attrs']['selectedPostId'] = 0;
				return $block;

			case self::FADER_BLOCK:
				return $this->clear_fader_slides( $block );

			case 'kate-toms-core/vr-tour':
				// The pattern ships with a sample Matterport tour. Left in
				// place it would render as a working tour of a different
				// house; empty, the block renders nothing and the editor is
				// prompted for this house's URL.
				$block['attrs']['tourUrl'] = '';
				return $block;

			case 'kate-toms-core/house-calendar-availability':
				// The MASTER ships with a literal "enter it here" placeholder.
				// Despite the "houseId" name, this block's view.js posts it
				// straight through to House_Calendar_Manager::get_calendar_data(),
				// which puts it directly in the iPro API URL path — it is the
				// iPro PropertyId (the same value written to the parent's
				// ipro_property_id meta), not a WordPress post ID. Confirmed
				// against live houses: e.g. Chestnut Tree House's parent post
				// carries ipro_property_id 57125, matching its Availability
				// page's houseId attribute exactly.
				$block['attrs']['houseId'] = (string) $ipro_property_id;
				return $block;
		}

		return $block;
	}

	/**
	 * Empties an image fader, keeping its wrapper and layout settings.
	 *
	 * The MASTER templates ship with sample Marsden Manor photographs in every
	 * fader. A new house starts with none: the editor uploads images to the
	 * parent page and Kate_Toms_Blueprint_Inheritance pushes the first five
	 * down to the child pages.
	 *
	 * @param array $block Parsed image fader block.
	 *
	 * @return array Mutated block.
	 */
	private function clear_fader_slides( array $block ): array {
		// A stale attribute left by an earlier version of the block, now that
		// the images are parsed back out of the saved markup instead.
		unset( $block['attrs']['images'] );

		foreach ( $block['innerContent'] as $index => $chunk ) {
			if ( ! is_string( $chunk ) ) {
				continue;
			}

			$updated = preg_replace( '#(<div\b[^>]*>).*(</div>)#s', '$1$2', $chunk, 1 );

			if ( null !== $updated && $updated !== $chunk ) {
				$block['innerContent'][ $index ] = $updated;
				$block['innerHTML']              = $updated;
				break;
			}
		}

		return $block;
	}

	/**
	 * Determines whether a core/table block is the Key Facts bedroom table.
	 *
	 * Matches on the header row rather than position, so reordering the Key
	 * Facts MASTER or adding further tables will not misapply the widths.
	 *
	 * @param array $block Parsed core/table block.
	 *
	 * @return bool True when the table's first row is the bedroom header row.
	 */
	private function is_bedroom_table( array $block ): bool {
		$html = (string) ( $block['innerHTML'] ?? '' );

		if ( ! preg_match( '#<tr\b.*?</tr>#is', $html, $matches ) ) {
			return false;
		}

		$first_row = wp_strip_all_tags( $matches[0] );

		foreach ( array( 'Bedrooms', 'Sleeps', 'Beds', 'Features' ) as $heading ) {
			if ( ! str_contains( $first_row, $heading ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Adds a CSS class to a block's `className` attribute and its saved markup.
	 *
	 * Static blocks render straight from `innerContent` on the front end, so the
	 * class has to land in both places — attribute alone would not show up until
	 * an editor re-saved the post.
	 *
	 * @param array  $block Parsed block.
	 * @param string $class_name Class name to add.
	 *
	 * @return array Mutated block.
	 */
	private function add_block_class( array $block, string $class_name ): array {
		$existing = (string) ( $block['attrs']['className'] ?? '' );
		$classes  = preg_split( '/\s+/', trim( $existing ), -1, PREG_SPLIT_NO_EMPTY );

		if ( in_array( $class_name, $classes, true ) ) {
			return $block;
		}

		$classes[]                   = $class_name;
		$block['attrs']['className'] = implode( ' ', $classes );

		foreach ( $block['innerContent'] as $index => $chunk ) {
			if ( ! is_string( $chunk ) || '' === trim( $chunk ) ) {
				continue;
			}

			$updated = $this->add_class_to_first_tag( $chunk, $class_name );

			if ( null !== $updated ) {
				$block['innerContent'][ $index ] = $updated;

				if ( isset( $block['innerHTML'] ) ) {
					$block['innerHTML'] = $this->add_class_to_first_tag( (string) $block['innerHTML'], $class_name ) ?? $block['innerHTML'];
				}

				break;
			}
		}

		return $block;
	}

	/**
	 * Adds a class to the first opening HTML tag in a markup chunk.
	 *
	 * @param string $html  Markup chunk.
	 * @param string $class_name Class name to add.
	 *
	 * @return string|null Updated markup, or null when no opening tag was found.
	 */
	private function add_class_to_first_tag( string $html, string $class_name ): ?string {
		$matched = false;

		$result = preg_replace_callback(
			'#<([a-zA-Z][a-zA-Z0-9-]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)(/?)>#',
			static function ( array $matches ) use ( $class_name, &$matched ): string {
				if ( $matched ) {
					return $matches[0];
				}

				$matched = true;
				$tag     = $matches[1];
				$attrs   = $matches[2];
				$closing = $matches[3];

				if ( preg_match( '#\sclass=(["\'])(.*?)\1#i', $attrs, $class_match ) ) {
					$values = preg_split( '/\s+/', trim( $class_match[2] ), -1, PREG_SPLIT_NO_EMPTY );

					if ( ! in_array( $class_name, $values, true ) ) {
						$values[] = $class_name;
					}

					$replacement = sprintf( ' class=%1$s%2$s%1$s', $class_match[1], implode( ' ', $values ) );
					$attrs       = str_replace( $class_match[0], $replacement, $attrs );
				} else {
					$attrs .= sprintf( ' class="%s"', $class_name );
				}

				return sprintf( '<%s%s%s>', $tag, $attrs, $closing );
			},
			$html,
			1
		);

		return $matched && null !== $result ? $result : null;
	}

	/**
	 * Writes a warning to the PHP error log when WP_DEBUG is active.
	 *
	 * @param string $message Warning message.
	 *
	 * @return void
	 */
	private function log_warning( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Kate & Toms Blueprint] ' . $message );
		}
	}
}
