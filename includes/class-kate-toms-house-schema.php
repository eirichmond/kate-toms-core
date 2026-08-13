<?php
/**
 * Product / LodgingBusiness / VideoObject structured data for house pages.
 *
 * Describes each of the 408 top-level house pages as both a Product (the
 * listing) and a LodgingBusiness (the property itself), with the guest
 * testimonials shown on the page and, where one exists, the page's video.
 *
 * Reviews are text only: no reviewRating, star rating or aggregateRating is
 * emitted, since the testimonials carry no score and inventing one would
 * misrepresent them.
 *
 * The nodes are appended to Yoast's existing schema graph rather than printed
 * as a second script: Yoast already emits exactly one JSON-LD graph per page
 * and its Organization node already carries the fixed sitewide `#organization`
 * ID, so appending keeps one script, one Organization entity, and lets the
 * `brand` / `isRelatedTo` / `video` references resolve within the same graph.
 * Where Yoast is unavailable the same nodes are printed as their own script
 * with a minimal Organization inlined.
 *
 * Sub-pages (/more/, /keyfacts/ ...) are deliberately excluded - they are
 * supporting content, and duplicating the entities across them would create
 * competing descriptions of the same house.
 *
 * @package Kate_Toms_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the house page structured data.
 */
class Kate_Toms_House_Schema {

	/**
	 * The post type carrying house listings.
	 *
	 * @var string
	 */
	const POST_TYPE = 'houses';

	/**
	 * Fragment identifying the sitewide Organization entity.
	 *
	 * Hardcoded rather than read from Yoast's Schema_IDs class so a rename
	 * inside Yoast cannot fatal the site.
	 *
	 * @var string
	 */
	const ORGANIZATION_HASH = '#organization';

	/**
	 * Shape of the assembled node set, mixed into the cache key.
	 *
	 * Bumping this retires every cached node set at once. The rest of the key
	 * tracks the content, not the code, so a change to what we publish would
	 * otherwise be masked by caches for up to CACHE_TTL after deployment.
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '4';

	/**
	 * The block holding the guest testimonials shown on house pages.
	 *
	 * @var string
	 */
	const REVIEWS_BLOCK = 'create-block/kateandtoms-reviews';

	/**
	 * Shortest paragraph, in characters, that can serve as the description.
	 *
	 * House pages open with short interface labels ("Sleeps", "GALLERY",
	 * "KEY FACTS") before the marketing copy, so the first paragraph on the
	 * page is not the one we want - the first substantial one is.
	 *
	 * @var int
	 */
	const MIN_DESCRIPTION_LENGTH = 100;

	/**
	 * Maximum number of image URLs to publish per house.
	 *
	 * Houses carry up to 52 gallery images. Publishing all of them bloats the
	 * graph for no benefit, so this is the featured image plus a sample.
	 *
	 * @var int
	 */
	const MAX_IMAGES = 9;

	/**
	 * How long an assembled node set is cached for.
	 *
	 * The key carries the post's modified timestamp, so edits take effect
	 * immediately and this expiry only bounds how long orphans linger.
	 *
	 * @var int
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * How long a resolved Vimeo thumbnail is cached for.
	 *
	 * @var int
	 */
	const VIMEO_CACHE_TTL = WEEK_IN_SECONDS;

	/**
	 * How long a failed Vimeo lookup is remembered before retrying.
	 *
	 * @var int
	 */
	const VIMEO_FAILURE_TTL = HOUR_IN_SECONDS;

	/**
	 * Whether the nodes were merged into Yoast's graph this request.
	 *
	 * @var bool
	 */
	private $added_to_yoast_graph = false;

	/**
	 * Register the hooks.
	 *
	 * Both routes are registered because this plugin loads before Yoast, so
	 * whether Yoast is available cannot be settled here. Yoast prints its graph
	 * early in `wp_head`; the standalone script runs late and stands down if
	 * the filter already did the job.
	 */
	public function __construct() {
		add_filter( 'wpseo_schema_graph', array( $this, 'add_house_nodes_to_graph' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'print_house_schema' ), 99 );
	}

	/**
	 * Append the house nodes to Yoast's schema graph.
	 *
	 * @param array $graph   The schema graph.
	 * @param mixed $context Yoast's meta tags context.
	 * @return array The filtered graph.
	 */
	public function add_house_nodes_to_graph( $graph, $context = null ) {
		$post = $this->get_house_post();

		if ( ! $post || ! is_array( $graph ) ) {
			return $graph;
		}

		$canonical = '';

		if ( is_object( $context ) && ! empty( $context->canonical ) ) {
			$canonical = $context->canonical;
		}

		$organization_id = $this->locate_organization_id( $graph );

		if ( ! $organization_id ) {
			$organization    = $this->build_organization_node();
			$organization_id = $organization['@id'];
			$graph[]         = $organization;
		}

		$nodes = $this->get_nodes( $post, $canonical, $organization_id );

		if ( empty( $nodes ) ) {
			return $graph;
		}

		$this->added_to_yoast_graph = true;

		return array_merge( $graph, $nodes );
	}

	/**
	 * Print a standalone JSON-LD graph for the house.
	 *
	 * The fallback for when Yoast has not already carried the nodes - it is
	 * inactive, or its schema output is switched off. Runs late in `wp_head`,
	 * by which point Yoast's graph has been filtered.
	 */
	public function print_house_schema() {
		if ( $this->added_to_yoast_graph ) {
			return;
		}

		$post = $this->get_house_post();

		if ( ! $post ) {
			return;
		}

		$organization = $this->build_organization_node();
		$nodes        = $this->get_nodes( $post, '', $organization['@id'] );

		if ( empty( $nodes ) ) {
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_merge( array( $organization ), $nodes ),
		);

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output inside a JSON-LD script.
		);
	}

	/**
	 * Get the house post this request should describe.
	 *
	 * @return WP_Post|false The top-level house post, or false when out of scope.
	 */
	private function get_house_post() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return false;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post || 0 !== (int) $post->post_parent ) {
			return false;
		}

		return $post;
	}

	/**
	 * Find the Organization entity already present in a graph.
	 *
	 * @param array $graph The schema graph.
	 * @return string The Organization's @id, or an empty string when absent.
	 */
	private function locate_organization_id( array $graph ) {
		foreach ( $graph as $node ) {
			if ( ! is_array( $node ) || empty( $node['@id'] ) || empty( $node['@type'] ) ) {
				continue;
			}

			if ( in_array( 'Organization', (array) $node['@type'], true ) ) {
				return $node['@id'];
			}
		}

		return '';
	}

	/**
	 * Build a minimal Organization node.
	 *
	 * Only used when the graph has none - Yoast omits it when the site is not
	 * set to represent a company.
	 *
	 * @return array The Organization node.
	 */
	private function build_organization_node() {
		$organization = array(
			'@type' => 'Organization',
			'@id'   => trailingslashit( home_url() ) . self::ORGANIZATION_HASH,
			'name'  => get_bloginfo( 'name' ),
			'url'   => trailingslashit( home_url() ),
		);

		$logo_id = (int) get_theme_mod( 'custom_logo' );

		if ( $logo_id ) {
			$logo = wp_get_attachment_image_url( $logo_id, 'full' );

			if ( $logo ) {
				$organization['logo'] = $logo;
			}
		}

		return $organization;
	}

	/**
	 * Get the house's nodes, using the cached set where possible.
	 *
	 * @param WP_Post $post            The house post.
	 * @param string  $canonical       Canonical URL, empty to fall back to the permalink.
	 * @param string  $organization_id The Organization entity to reference.
	 * @return array[] Schema nodes.
	 */
	private function get_nodes( WP_Post $post, $canonical, $organization_id ) {
		if ( '' === $canonical ) {
			$canonical = get_permalink( $post );
		}

		if ( ! $canonical ) {
			return array();
		}

		/**
		 * Filters whether assembled house schema nodes are cached.
		 *
		 * @param bool    $use_cache Whether to cache. Default true.
		 * @param WP_Post $post      The house being described.
		 */
		$use_cache = apply_filters( 'kate_toms_core_cache_house_schema', true, $post );
		$cache_key = 'kt_house_schema_' . $post->ID . '_' . md5( self::SCHEMA_VERSION . '|' . $canonical . '|' . $organization_id . '|' . (string) $post->post_modified_gmt );

		if ( $use_cache ) {
			$cached = get_transient( $cache_key );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$nodes = $this->build_nodes( $post, $canonical, $organization_id );

		if ( $use_cache ) {
			set_transient( $cache_key, $nodes, self::CACHE_TTL );
		}

		return $nodes;
	}

	/**
	 * Build the LodgingBusiness, Product and VideoObject nodes for a house.
	 *
	 * @param WP_Post $post            The house post.
	 * @param string  $canonical       Canonical URL.
	 * @param string  $organization_id The Organization entity to reference.
	 * @return array[] Schema nodes.
	 */
	private function build_nodes( WP_Post $post, $canonical, $organization_id ) {
		$name = $this->to_plain_text( get_the_title( $post ) );

		if ( '' === $name ) {
			return array();
		}

		$blocks      = parse_blocks( $post->post_content );
		$description = $this->get_description( $post, $blocks );
		$images      = $this->get_images( $post );

		$product_id = $canonical . '#product';
		$lodging_id = $canonical . '#lodging';

		$lodging = array(
			'@type' => 'LodgingBusiness',
			'@id'   => $lodging_id,
			'name'  => $name,
			'url'   => $canonical,
			'brand' => array( '@id' => $organization_id ),
		);

		if ( '' !== $description ) {
			$lodging['description'] = $description;
		}

		if ( ! empty( $images ) ) {
			$lodging['image'] = $images;
		}

		$address = $this->get_address( $post );

		if ( ! empty( $address ) ) {
			$lodging['address'] = $address;
		}

		$product = array(
			'@type'       => 'Product',
			'@id'         => $product_id,
			'name'        => $name,
			'url'         => $canonical,
			'brand'       => array( '@id' => $organization_id ),
			'isRelatedTo' => array( '@id' => $lodging_id ),
		);

		if ( '' !== $description ) {
			$product['description'] = $description;
		}

		if ( ! empty( $images ) ) {
			$product['image'] = $images;
		}

		$videos = $this->build_video_nodes( $post, $blocks, $canonical, $name );

		if ( ! empty( $videos ) ) {
			$references = array();

			foreach ( $videos as $video ) {
				$references[] = array( '@id' => $video['@id'] );
			}

			// `video` is a CreativeWork property, so the Schema.org validator
			// rejects it on a Product. `subjectOf` carries the same meaning and
			// is defined on Thing, so it is valid here; the VideoObject stays a
			// separate node in the graph either way.
			// A lone video is referenced directly; only multiples become an array.
			$product['subjectOf'] = 1 === count( $references ) ? $references[0] : $references;
		}

		$reviews = $this->build_reviews( $blocks );

		if ( ! empty( $reviews ) ) {
			// A single testimonial goes out as one object, not an array of one.
			$product['review'] = 1 === count( $reviews ) ? $reviews[0] : $reviews;
		}

		return array_merge( array( $lodging, $product ), $videos );
	}

	/**
	 * Get the house's description.
	 *
	 * The first substantial paragraph on the page, which is the marketing
	 * introduction - shorter paragraphs above it are interface labels.
	 *
	 * @param WP_Post $post   The house post.
	 * @param array[] $blocks Parsed blocks.
	 * @return string Plain text description.
	 */
	private function get_description( WP_Post $post, array $blocks ) {
		$description = '';

		$this->walk_blocks(
			$blocks,
			function ( $block ) use ( &$description ) {
				if ( '' !== $description || 'core/paragraph' !== ( $block['blockName'] ?? '' ) ) {
					return;
				}

				$text = $this->to_plain_text( $block['innerHTML'] ?? '' );

				if ( mb_strlen( $text ) >= self::MIN_DESCRIPTION_LENGTH ) {
					$description = $text;
				}
			}
		);

		if ( '' === $description ) {
			$description = $this->to_plain_text( (string) get_post_meta( $post->ID, 'brief_description', true ) );
		}

		return $description;
	}

	/**
	 * Get the house's image URLs.
	 *
	 * The featured image first, then gallery images, capped.
	 *
	 * @param WP_Post $post The house post.
	 * @return string[] Absolute image URLs.
	 */
	private function get_images( WP_Post $post ) {
		$attachment_ids = array();

		$thumbnail_id = get_post_thumbnail_id( $post );

		if ( $thumbnail_id ) {
			$attachment_ids[] = (int) $thumbnail_id;
		}

		$gallery = get_post_meta( $post->ID, 'house_photos', true );

		if ( is_array( $gallery ) ) {
			foreach ( $gallery as $attachment_id ) {
				$attachment_ids[] = (int) $attachment_id;
			}
		}

		$images = array();

		foreach ( array_unique( $attachment_ids ) as $attachment_id ) {
			if ( count( $images ) >= self::MAX_IMAGES ) {
				break;
			}

			$url = wp_get_attachment_image_url( $attachment_id, 'full' );

			if ( $url ) {
				$images[] = $url;
			}
		}

		return array_values( array_unique( $images ) );
	}

	/**
	 * Build the house's postal address.
	 *
	 * Deliberately coarse - region and country only, no street or postcode,
	 * since the properties are private homes and the full address is not
	 * published on the page.
	 *
	 * @param WP_Post $post The house post.
	 * @return array The PostalAddress node, empty when no region is known.
	 */
	private function get_address( WP_Post $post ) {
		$region = $this->to_plain_text( (string) get_post_meta( $post->ID, 'location_text', true ) );

		if ( '' === $region ) {
			return array();
		}

		// Values like "Spetchley, Worcestershire" narrow to the region itself.
		if ( false !== strpos( $region, ',' ) ) {
			$parts  = array_map( 'trim', explode( ',', $region ) );
			$parts  = array_filter( $parts );
			$region = $parts ? (string) end( $parts ) : $region;
		}

		if ( '' === $region ) {
			return array();
		}

		return array(
			'@type'          => 'PostalAddress',
			'addressRegion'  => $region,
			'addressCountry' => 'GB',
		);
	}

	/**
	 * Build the Review nodes from the testimonials shown on the page.
	 *
	 * Text only. The block stores no ratings and none are invented, so no
	 * reviewRating or aggregateRating is emitted. `itemReviewed` is left off
	 * too: these nest inside the Product, which already establishes what was
	 * reviewed.
	 *
	 * Every testimonial held by the block is rendered into the page markup, so
	 * all of them qualify as visible. An item with no quote is skipped; the
	 * author is published where the block carries one.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return array[] Review nodes.
	 */
	private function build_reviews( array $blocks ) {
		$testimonials = array();

		$this->walk_blocks(
			$blocks,
			function ( $block ) use ( &$testimonials ) {
				if ( self::REVIEWS_BLOCK !== ( $block['blockName'] ?? '' ) ) {
					return;
				}

				foreach ( (array) ( $block['attrs']['reviews'] ?? array() ) as $item ) {
					$testimonials[] = $item;
				}
			}
		);

		$nodes = array();

		foreach ( $testimonials as $testimonial ) {
			$body = $this->to_plain_text( $testimonial['review'] ?? '' );

			if ( '' === $body ) {
				continue;
			}

			$node = array(
				'@type'      => 'Review',
				'reviewBody' => $body,
			);

			$author = $this->to_plain_text( $testimonial['reviewer'] ?? '' );

			if ( '' !== $author ) {
				$node['author'] = array(
					'@type' => 'Person',
					'name'  => $author,
				);
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * Build the VideoObject nodes for a house.
	 *
	 * @param WP_Post $post      The house post.
	 * @param array[] $blocks    Parsed blocks.
	 * @param string  $canonical Canonical URL.
	 * @param string  $name      The house name.
	 * @return array[] VideoObject nodes.
	 */
	private function build_video_nodes( WP_Post $post, array $blocks, $canonical, $name ) {
		$videos = array();

		$this->walk_blocks(
			$blocks,
			function ( $block ) use ( &$videos ) {
				if ( 'core/embed' !== ( $block['blockName'] ?? '' ) ) {
					return;
				}

				// Match on the embed's own type and provider: three Vimeo embeds
				// carry a stale "is-provider-youtube" class name, and a Key Facts
				// page lists YouTube among the amenities.
				if ( 'video' !== ( $block['attrs']['type'] ?? '' ) || empty( $block['attrs']['url'] ) ) {
					return;
				}

				$video = $this->parse_video_url( $block['attrs']['url'], $block['attrs']['providerNameSlug'] ?? '' );

				if ( $video ) {
					$videos[] = $video;
				}
			}
		);

		if ( empty( $videos ) ) {
			return array();
		}

		$nodes = array();
		$index = 0;

		foreach ( $videos as $video ) {
			++$index;

			$node = array(
				'@type'       => 'VideoObject',
				'@id'         => $canonical . '#video' . ( $index > 1 ? '-' . $index : '' ),
				'name'        => $name . ' – Luxury Holiday Home Tour',
				'description' => sprintf(
					/* translators: %s: house name. */
					'Video tour of %s, a luxury holiday home available to rent with kate & tom’s',
					$name
				),
				'embedUrl'    => $video['embed_url'],
				'contentUrl'  => $video['content_url'],
			);

			$thumbnail = $this->get_video_thumbnail( $video );

			if ( '' !== $thumbnail ) {
				$node['thumbnailUrl'] = $thumbnail;
			}

			$upload_date = $this->get_video_upload_date( $video );

			if ( '' !== $upload_date ) {
				$node['uploadDate'] = $upload_date;
			}

			$nodes[] = $node;
		}

		unset( $post );

		return $nodes;
	}

	/**
	 * Reduce an embed URL to a provider, an ID and canonical embed/content URLs.
	 *
	 * @param string $url      The embed URL.
	 * @param string $provider The block's provider slug.
	 * @return array|false Video parts, or false when unrecognised.
	 */
	private function parse_video_url( $url, $provider ) {
		$url = trim( (string) $url );

		if ( 'vimeo' === $provider || preg_match( '#vimeo\.com/#i', $url ) ) {
			if ( ! preg_match( '#vimeo\.com/(?:video/)?(\d+)#i', $url, $matches ) ) {
				return false;
			}

			return array(
				'provider'    => 'vimeo',
				'id'          => $matches[1],
				'embed_url'   => 'https://player.vimeo.com/video/' . $matches[1],
				'content_url' => 'https://vimeo.com/' . $matches[1],
				'source_url'  => $url,
			);
		}

		// Covers youtu.be/ID, /watch?v=ID, /embed/ID and /shorts/ID.
		if ( ! preg_match( '#(?:youtu\.be/|/watch\?(?:.*&)?v=|/embed/|/shorts/)([A-Za-z0-9_-]{11})#i', $url, $matches ) ) {
			return false;
		}

		return array(
			'provider'    => 'youtube',
			'id'          => $matches[1],
			'embed_url'   => 'https://www.youtube.com/embed/' . $matches[1],
			'content_url' => 'https://www.youtube.com/watch?v=' . $matches[1],
			'source_url'  => $url,
		);
	}

	/**
	 * Get a video's thumbnail URL.
	 *
	 * YouTube thumbnails are derived from the video ID with no network call -
	 * `hqdefault` rather than `maxresdefault`, because the latter is missing
	 * for several of the estate's videos while the former always exists.
	 * Vimeo publishes no predictable thumbnail URL, so it is resolved through
	 * their public oEmbed endpoint and cached.
	 *
	 * @param array $video Parsed video parts.
	 * @return string Thumbnail URL, empty when unavailable.
	 */
	private function get_video_thumbnail( array $video ) {
		if ( 'youtube' === $video['provider'] ) {
			return 'https://img.youtube.com/vi/' . $video['id'] . '/hqdefault.jpg';
		}

		$remote = $this->get_remote_video_meta( $video );

		return $remote['thumbnail'];
	}

	/**
	 * Get a video's publication date in ISO 8601 form.
	 *
	 * Vimeo publishes the date through oEmbed, so those come for free. YouTube
	 * does not - oEmbed carries the title, author and thumbnail but no date, so
	 * the only source is the YouTube Data API, which needs a server-usable key.
	 * Supply one through the KATE_TOMS_YOUTUBE_API_KEY constant or the
	 * `kate_toms_core_youtube_api_key` filter; without it the date is omitted
	 * rather than guessed from the page's own dates, which would be wrong.
	 *
	 * @param array $video Parsed video parts.
	 * @return string ISO 8601 date, empty when unavailable.
	 */
	private function get_video_upload_date( array $video ) {
		$remote = $this->get_remote_video_meta( $video );

		return $remote['upload_date'];
	}

	/**
	 * Look up whatever the provider will tell us about a video.
	 *
	 * One cached lookup per video serves both the thumbnail and the upload
	 * date - Vimeo's oEmbed response carries both, so splitting them would
	 * double the round trips for no gain.
	 *
	 * @param array $video Parsed video parts.
	 * @return array{thumbnail:string,upload_date:string} Resolved values, empty strings when unavailable.
	 */
	private function get_remote_video_meta( array $video ) {
		$empty     = array(
			'thumbnail'   => '',
			'upload_date' => '',
		);
		$cache_key = 'kt_video_meta_' . md5( $video['provider'] . '|' . $video['id'] );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return array_merge( $empty, $cached );
		}

		$meta = 'vimeo' === $video['provider']
			? $this->fetch_vimeo_meta( $video )
			: $this->fetch_youtube_meta( $video );

		$meta = array_merge( $empty, $meta );

		// A failed lookup is remembered briefly so a provider outage cannot make
		// every render wait on it; the node simply goes out without the field.
		$resolved = ( '' !== $meta['thumbnail'] || '' !== $meta['upload_date'] );

		set_transient( $cache_key, $meta, $resolved ? self::VIMEO_CACHE_TTL : self::VIMEO_FAILURE_TTL );

		return $meta;
	}

	/**
	 * Read a Vimeo video's thumbnail and upload date from their oEmbed endpoint.
	 *
	 * Vimeo publishes no predictable thumbnail URL, so this is the only route.
	 *
	 * @param array $video Parsed video parts.
	 * @return array Resolved values.
	 */
	private function fetch_vimeo_meta( array $video ) {
		$response = wp_remote_get(
			add_query_arg( 'url', rawurlencode( $video['content_url'] ), 'https://vimeo.com/api/oembed.json' ),
			array( 'timeout' => 3 )
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return array();
		}

		$meta = array();

		if ( ! empty( $body['thumbnail_url'] ) ) {
			$meta['thumbnail'] = esc_url_raw( $body['thumbnail_url'] );
		}

		// Vimeo reports "2018-06-27 09:59:56"; schema.org wants ISO 8601.
		if ( ! empty( $body['upload_date'] ) ) {
			$meta['upload_date'] = $this->to_iso_8601( $body['upload_date'] );
		}

		return $meta;
	}

	/**
	 * Read a YouTube video's upload date from the YouTube Data API.
	 *
	 * Thumbnails are derived from the ID without a request, so this is only
	 * reached for the date, and only when a key has been supplied.
	 *
	 * @param array $video Parsed video parts.
	 * @return array Resolved values.
	 */
	private function fetch_youtube_meta( array $video ) {
		$key = $this->get_youtube_api_key();

		if ( '' === $key ) {
			return array();
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'part' => 'snippet',
					'id'   => rawurlencode( $video['id'] ),
					'key'  => rawurlencode( $key ),
				),
				'https://www.googleapis.com/youtube/v3/videos'
			),
			array( 'timeout' => 3 )
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$published = $body['items'][0]['snippet']['publishedAt'] ?? '';

		if ( '' === $published ) {
			return array();
		}

		return array( 'upload_date' => $this->to_iso_8601( $published ) );
	}

	/**
	 * Get the YouTube Data API key, if the site has been given one.
	 *
	 * @return string The key, empty when unset.
	 */
	private function get_youtube_api_key() {
		$key = defined( 'KATE_TOMS_YOUTUBE_API_KEY' ) ? (string) KATE_TOMS_YOUTUBE_API_KEY : '';

		/**
		 * Filters the YouTube Data API key used to resolve video upload dates.
		 *
		 * @param string $key The key, empty when unset.
		 */
		return trim( (string) apply_filters( 'kate_toms_core_youtube_api_key', $key ) );
	}

	/**
	 * Normalise a provider's date string to ISO 8601.
	 *
	 * @param string $date The provider's date.
	 * @return string ISO 8601 date, empty when unparseable.
	 */
	private function to_iso_8601( $date ) {
		$timestamp = strtotime( (string) $date );

		if ( ! $timestamp ) {
			return '';
		}

		return gmdate( 'c', $timestamp );
	}

	/**
	 * Walk a parsed block tree, calling back for every block.
	 *
	 * House content nests several levels deep inside `core/group` blocks, so a
	 * top-level scan finds nothing.
	 *
	 * @param array[]  $blocks   Parsed blocks.
	 * @param callable $callback Receives each block.
	 */
	private function walk_blocks( array $blocks, callable $callback ) {
		foreach ( $blocks as $block ) {
			$callback( $block );

			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->walk_blocks( $block['innerBlocks'], $callback );
			}
		}
	}

	/**
	 * Reduce HTML to the plain text a visitor reads.
	 *
	 * @param string $html Source HTML.
	 * @return string Plain text, collapsed to single spaces.
	 */
	private function to_plain_text( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return '';
		}

		// Separate block-level content before stripping tags, or list items and
		// paragraphs run into each other.
		$html = preg_replace( '#</(li|p|div|h[1-6]|br\s*/?)>#i', '$0 ', $html );

		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, get_bloginfo( 'charset' ) );

		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}
}
