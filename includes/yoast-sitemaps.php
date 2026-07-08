<?php
/**
 * Yoast SEO sitemap tweaks.
 *
 * Yoast ships a transient-based cache for rendered XML sitemap pages
 * (WPSEO_Sitemaps_Cache) but it is disabled by default and has no admin
 * setting - the filter below is the only way to switch it on. Without it,
 * every sitemap request regenerates the page from scratch; the houses
 * sitemap queries 1,000 posts per page and DOM-parses each post_content
 * for images (~26k images), taking ~7s per request.
 *
 * With the cache enabled, each rendered sitemap page is stored as a
 * transient and served from there. Invalidation is handled by Yoast
 * itself: saving a post, editing terms or updating relevant options
 * clears the affected sitemap transients, so the next request
 * regenerates them.
 *
 * @package Kate_Toms_Core
 */

add_filter( 'wpseo_enable_xml_sitemap_transient_caching', '__return_true' );
