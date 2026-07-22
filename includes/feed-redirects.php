<?php
/**
 * Feed request handling.
 *
 * The site does not publish RSS/Atom feeds; historically Google crawled
 * thousands of auto-generated feed URLs (site, comment and taxonomy feeds
 * such as /feature/ev-pool/feed/), inflating the crawl budget. The legacy
 * clubsandwich theme redirected these to the homepage, but that fix was
 * lost when the katomswold block theme went live because it was theme-level
 * code. It now lives here so it survives theme switches.
 *
 * Every feed request is 301-redirected to its parent URL (the same URL
 * with the feed suffix removed) rather than the homepage, so crawlers
 * consolidate onto the page the feed belonged to instead of treating the
 * redirect as a soft 404. The <link rel="alternate"> feed tags are also
 * removed from page heads so the feed URLs are no longer advertised.
 *
 * @package Kate_Toms_Core
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Redirect all feed requests to their parent URL with a permanent redirect.
 *
 * Runs on template_redirect at priority 1, before canonical redirects and
 * the template loader, so it catches every feed variant WordPress serves
 * (rss2, atom, rdf, comment feeds and pretty or query-string forms).
 *
 * @return void
 */
function kate_toms_core_redirect_feeds() {
	if ( ! is_feed() ) {
		return;
	}

	/**
	 * Filters whether feed requests are redirected to their parent URL.
	 *
	 * @param bool $redirect Whether to redirect feed requests. Default true.
	 */
	if ( ! apply_filters( 'kate_toms_core_redirect_feeds', true ) ) {
		return;
	}

	$path = '';
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	}

	// Strip the feed suffix, e.g. /feed/, /feed/atom/, /comments/feed/, /rss2/.
	$parent = (string) preg_replace( '#/(comments/)?(feed|rdf|rss2?|atom)(/(rdf|rss2?|atom))?/*$#i', '/', $path );

	if ( '' === $parent || $parent === $path ) {
		$parent = '/';
	}

	wp_safe_redirect( home_url( $parent ), 301 );
	exit;
}
add_action( 'template_redirect', 'kate_toms_core_redirect_feeds', 1 );

/**
 * Remove the RSS/Atom <link rel="alternate"> tags from page heads.
 *
 * Block themes get the automatic-feed-links theme support by default, and
 * the resulting head links are what advertise the feed URLs to crawlers.
 * Hooked to wp_head at priority 1 so the removal runs after the theme has
 * registered the callbacks but before they fire at priorities 2 and 3.
 *
 * @return void
 */
function kate_toms_core_remove_feed_links() {
	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
}
add_action( 'wp_head', 'kate_toms_core_remove_feed_links', 1 );
