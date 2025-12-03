<?php
/**
 * Related Houses Block Template.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 */

// Extract house IDs
$house_ids = array(
	$attributes['house1Id'] ?? 0,
	$attributes['house2Id'] ?? 0,
	$attributes['house3Id'] ?? 0,
	$attributes['house4Id'] ?? 0,
);

// Filter out empty selections
$selected_houses = array_filter($house_ids, function($id) {
	return $id > 0;
});

// Don't render if no houses selected
if (empty($selected_houses)) {
	return '';
}

// Build columns content dynamically
$columns_content = '';
foreach ($selected_houses as $house_id) {
	$columns_content .= '<!-- wp:column -->' . "\n";
	$columns_content .= '<div class="wp-block-column"><!-- wp:kate-toms-core/kateandtoms-single-house {"selectedPostId":' . absint($house_id) . '} /--></div>' . "\n";
	$columns_content .= '<!-- /wp:column -->' . "\n\n";
}

// Create the complete block markup as a string
$pattern_content = '<!-- wp:group {"metadata":{"categories":["house-card-search"],"patternName":"katomswold/houses-you-may-also-like","name":"Houses You May Also Like"},"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"var:preset|spacing|60","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:0;padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40)"><!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"300"},"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);font-style:normal;font-weight:300">Houses you may also like...</h2>
<!-- /wp:heading -->

<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns alignwide">' . $columns_content . '</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->';

// Use do_blocks to process the complete pattern and get proper WordPress block classes
echo do_blocks($pattern_content);