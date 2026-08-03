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
	 * the plugin. `append` optionally lists katomswold theme patterns to add
	 * after that content, in order.
	 *
	 * @var array<string, array{label: string, source: array{type: string, title?: string, file?: string}, append?: string[]}>
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
			'append' => array( 'katomswold/standard-widget-virtual-tour' ),
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

			foreach ( $config['append'] ?? array() as $slug ) {
				if ( null === $this->find_theme_pattern( $slug ) ) {
					$missing[] = $slug;
				}
			}
		}

		return $missing;
	}

	/**
	 * Returns finished post_content for a Blueprint page.
	 *
	 * @param string $key           Page key.
	 * @param string $display_title House display title.
	 * @param string $house_slug    Parent house post slug.
	 *
	 * @return string Block markup, or an empty string if the source is missing.
	 */
	public function get_content( string $key, string $display_title, string $house_slug ): string {
		$source = $this->load_source( $key );

		if ( '' === $source ) {
			return '';
		}

		$content = $this->personalise( $source, $display_title, $house_slug );

		return $this->apply_defaults( $content );
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

		foreach ( $config['append'] ?? array() as $slug ) {
			$pattern = $this->find_theme_pattern( $slug );

			if ( null === $pattern ) {
				$this->log_warning( "Theme pattern not found: {$slug}" );
				continue;
			}

			$content = rtrim( $content ) . "\n\n" . trim( $pattern ) . "\n";
		}

		return $content;
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
	 *
	 * @return string Personalised markup.
	 */
	private function personalise( string $content, string $display_title, string $house_slug ): string {
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
			),
			array(
				'/houses/' . $house_slug . '/',
				'h-' . $house_slug . '-',
				$display_title,
			),
			$content
		);

		// Sample Matterport links appear as plain hrefs too — on the Virtual
		// Tour pattern's "View Tour" button and in the Key Facts MASTER. Point
		// them at the tour anchor until the editor sets this house's URL.
		$content = preg_replace( '#href="https?://(?:[a-z0-9-]+\.)*matterport\.com/[^"]*"#i', 'href="#h-virtual-tour"', $content );

		return $content;
	}

	/**
	 * Applies the Blueprint's standing layout and placeholder defaults.
	 *
	 * Walks the parsed block tree once and mutates matching blocks, then
	 * re-serialises. Working on the parsed tree rather than the raw markup
	 * keeps nested blocks and inner HTML intact.
	 *
	 * @param string $content Personalised markup.
	 *
	 * @return string Markup with defaults applied.
	 */
	private function apply_defaults( string $content ): string {
		$blocks = parse_blocks( $content );
		$blocks = $this->walk_blocks( $blocks );

		return serialize_blocks( $blocks );
	}

	/**
	 * Recursively applies per-block defaults across a parsed block tree.
	 *
	 * @param array[] $blocks Parsed blocks.
	 *
	 * @return array[] Mutated blocks.
	 */
	private function walk_blocks( array $blocks ): array {
		foreach ( $blocks as $index => $block ) {
			$block = $this->apply_block_defaults( $block );

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->walk_blocks( $block['innerBlocks'] );
			}

			$blocks[ $index ] = $block;
		}

		return $blocks;
	}

	/**
	 * Applies the Blueprint defaults relevant to a single block.
	 *
	 * @param array $block Parsed block.
	 *
	 * @return array Mutated block.
	 */
	private function apply_block_defaults( array $block ): array {
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
