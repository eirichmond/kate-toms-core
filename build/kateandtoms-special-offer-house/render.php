<?php
/**
 * Special Offer House child render template.
 *
 * This block carries data only. The Special Offers Grid parent renders every
 * front-end card from the child attributes and ignores each child's own
 * rendered output, so this template deliberately produces nothing for visitors.
 *
 * When a child ends up outside a grid — for example legacy content not yet
 * migrated — logged-in editors get an inline hint. Inside a grid the parent
 * discards this output, so the hint never reaches visitors.
 *
 * @package kate-toms-core
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

if ( ! is_user_logged_in() ) {
	return;
}

printf(
	'<p class="kate-toms-single-house-orphan-notice">%s</p>',
	esc_html__( 'Place this Special Offer House inside a Special Offers Grid block.', 'kate-toms-core' )
);
