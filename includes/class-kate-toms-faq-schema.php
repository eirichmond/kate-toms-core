<?php
/**
 * FAQPage structured data for the kate & tom's FAQ accordion block.
 *
 * Every use of the `kate-toms-core/kateandtoms-faqs` block lives in
 * `post_content` (122 pages and 2 seasonal posts at the time of writing, none
 * in templates, template parts or synced patterns), so the questions and
 * answers can be read straight out of the parsed block tree - no template
 * scanning and no render-time detection is needed.
 *
 * Question text comes from the block's `question` attribute; answer text comes
 * from its inner blocks (paragraphs and lists today). Both are reduced to plain
 * text so the markup matches what the visitor reads. Answers collapsed behind
 * the accordion still count as visible content: Google explicitly permits FAQ
 * answers inside expandable UI.
 *
 * Output is attached to Yoast's existing WebPage node rather than printed as a
 * second standalone script, so the page keeps one entity in the graph instead
 * of two describing the same URL. Where Yoast is unavailable the same data is
 * printed as its own JSON-LD block.
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds FAQPage structured data from the FAQ accordion block.
 */
class Kate_Toms_FAQ_Schema {

	/**
	 * The FAQ accordion block name.
	 *
	 * @var string
	 */
	const BLOCK_NAME = 'kate-toms-core/kateandtoms-faqs';

	/**
	 * How long a parsed FAQ set is cached for.
	 *
	 * The cache key carries the post's modified timestamp, so edits take effect
	 * immediately and this expiry only bounds how long orphaned entries linger.
	 *
	 * @var int
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Whether the questions were merged into Yoast's graph this request.
	 *
	 * @var bool
	 */
	private $added_to_yoast_graph = false;

	/**
	 * Register the hooks.
	 *
	 * Both routes are registered because this plugin loads before Yoast, so
	 * whether Yoast is available cannot be settled here. Yoast prints its graph
	 * early in `wp_head`; the standalone script runs late and stands down if the
	 * filter already did the job.
	 */
	public function __construct() {
		add_filter( 'wpseo_schema_webpage', array( $this, 'add_faqs_to_webpage_node' ) );
		add_action( 'wp_head', array( $this, 'print_faq_page_schema' ), 99 );
	}

	/**
	 * Add the FAQPage type and its questions to Yoast's WebPage node.
	 *
	 * @param array $data The WebPage graph piece.
	 * @return array The filtered graph piece.
	 */
	public function add_faqs_to_webpage_node( $data ) {
		$faqs = $this->get_faqs_for_current_page();

		if ( empty( $faqs ) ) {
			return $data;
		}

		$types   = isset( $data['@type'] ) ? (array) $data['@type'] : array( 'WebPage' );
		$types[] = 'FAQPage';

		$data['@type'] = array_values( array_unique( $types ) );

		$this->added_to_yoast_graph = true;

		$questions = $this->build_question_nodes( $faqs );

		// Anything already on mainEntity (a Yoast FAQ block elsewhere on the
		// page, for instance) is kept - these questions are appended to it.
		if ( ! empty( $data['mainEntity'] ) && is_array( $data['mainEntity'] ) ) {
			$existing           = wp_is_numeric_array( $data['mainEntity'] ) ? $data['mainEntity'] : array( $data['mainEntity'] );
			$data['mainEntity'] = array_merge( $existing, $questions );

			return $data;
		}

		$data['mainEntity'] = $questions;

		return $data;
	}

	/**
	 * Print a standalone FAQPage JSON-LD script.
	 *
	 * The fallback for when Yoast has not already carried the questions - it is
	 * inactive, or its schema output is switched off. Runs late in `wp_head`, by
	 * which point Yoast's graph has been filtered.
	 */
	public function print_faq_page_schema() {
		if ( $this->added_to_yoast_graph ) {
			return;
		}

		$faqs = $this->get_faqs_for_current_page();

		if ( empty( $faqs ) ) {
			return;
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $this->build_question_nodes( $faqs ),
		);

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output inside a JSON-LD script.
		);
	}

	/**
	 * Turn collected question/answer pairs into schema.org Question nodes.
	 *
	 * @param array[] $faqs Question/answer pairs.
	 * @return array[] Question nodes.
	 */
	private function build_question_nodes( array $faqs ) {
		return array_map(
			static function ( $faq ) {
				return array(
					'@type'          => 'Question',
					'name'           => $faq['question'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $faq['answer'],
					),
				);
			},
			$faqs
		);
	}

	/**
	 * Get the FAQ pairs for the post being viewed.
	 *
	 * @return array[] Question/answer pairs, empty when the page has no FAQs.
	 */
	private function get_faqs_for_current_page() {
		if ( ! is_singular() ) {
			return array();
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post || ! has_block( self::BLOCK_NAME, $post ) ) {
			return array();
		}

		return $this->get_faqs( $post );
	}

	/**
	 * Get the FAQ pairs for a post, using the cached set where possible.
	 *
	 * @param WP_Post $post The post to read.
	 * @return array[] Question/answer pairs.
	 */
	private function get_faqs( WP_Post $post ) {
		/**
		 * Filters whether parsed FAQ sets are cached in a transient.
		 *
		 * @param bool    $use_cache Whether to cache. Default true.
		 * @param WP_Post $post      The post being read.
		 */
		$use_cache = apply_filters( 'kate_toms_core_cache_faq_schema', true, $post );
		$cache_key = 'kt_faq_schema_' . $post->ID . '_' . md5( (string) $post->post_modified_gmt );

		if ( $use_cache ) {
			$cached = get_transient( $cache_key );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$faqs = $this->collect_faqs( $post );

		if ( $use_cache ) {
			set_transient( $cache_key, $faqs, self::CACHE_TTL );
		}

		return $faqs;
	}

	/**
	 * Read every FAQ block in a post into plain-text question/answer pairs.
	 *
	 * Pairs are returned in the order they appear on the page. Blocks with an
	 * empty question or answer are skipped, as are repeats of a question that
	 * has already been collected for this post.
	 *
	 * @param WP_Post $post The post to read.
	 * @return array[] Question/answer pairs.
	 */
	private function collect_faqs( WP_Post $post ) {
		$faqs = array();
		$seen = array();

		foreach ( $this->find_faq_blocks( parse_blocks( $post->post_content ) ) as $block ) {
			$question = $this->to_plain_text( $block['attrs']['question'] ?? '' );
			$answer   = $this->to_plain_text( $this->render_inner_blocks( $block['innerBlocks'] ) );

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$key = strtolower( $question );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;

			$faqs[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		return $faqs;
	}

	/**
	 * Walk a parsed block tree and return every FAQ block found.
	 *
	 * FAQ blocks sit inside the group blocks of the theme's `faq-group-widget`
	 * pattern, so the tree has to be walked rather than scanned at the top level.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return array[] FAQ blocks, in document order.
	 */
	private function find_faq_blocks( array $blocks ) {
		$found = array();

		foreach ( $blocks as $block ) {
			if ( isset( $block['blockName'] ) && self::BLOCK_NAME === $block['blockName'] ) {
				$found[] = $block;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = array_merge( $found, $this->find_faq_blocks( $block['innerBlocks'] ) );
			}
		}

		return $found;
	}

	/**
	 * Render an answer's inner blocks to HTML.
	 *
	 * `render_block()` is used rather than the raw `innerHTML` because list
	 * answers keep their items in nested `core/list-item` blocks, which the
	 * saved `innerHTML` of the list block alone does not contain.
	 *
	 * @param array[] $inner_blocks Parsed inner blocks.
	 * @return string Rendered HTML.
	 */
	private function render_inner_blocks( array $inner_blocks ) {
		$html = '';

		foreach ( $inner_blocks as $inner_block ) {
			$html .= render_block( $inner_block );
		}

		return $html;
	}

	/**
	 * Reduce block HTML to the plain text a visitor reads.
	 *
	 * @param string $html Source HTML.
	 * @return string Plain text, collapsed to single spaces.
	 */
	private function to_plain_text( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return '';
		}

		// Separate block-level content before stripping tags, or list items and
		// paragraphs run into each other ("...first item.Second item...").
		$html = preg_replace( '#</(li|p|div|h[1-6]|br\s*/?)>#i', '$0 ', $html );

		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, get_bloginfo( 'charset' ) );

		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}
}
