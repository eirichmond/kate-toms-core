<?php
/**
 * Special Offers Grid render template.
 *
 * Collects the special-offer-house child attributes in editor order, then uses
 * the pure ordering helper to drop expired offers and sort the rest by offer
 * date (soonest expiry first). Cards are rendered in a single ordered flow.
 *
 * Faithful per-card rendering (the theme pattern for houses, advert markup for
 * placeholders) and the rows-of-four grid are added in later steps; for now
 * each card renders a minimal summary so the ordering can be verified.
 *
 * @package kate-toms-core
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks (ignored — the parent builds its own output).
 * @var WP_Block $block      Block instance.
 */

if ( ! class_exists( 'Kate_Toms_Special_Offers_Grid' ) ) {
	return;
}

$child_block_name = 'kate-toms-core/kateandtoms-special-offer-house';
$children         = array();

if ( isset( $block->inner_blocks ) ) {
	foreach ( $block->inner_blocks as $inner_block ) {
		if ( $child_block_name !== $inner_block->name ) {
			continue;
		}

		$children[] = is_array( $inner_block->attributes ) ? $inner_block->attributes : array();
	}
}

if ( empty( $children ) ) {
	return;
}

// "Now" in the site timezone; the helper uses only the date component for expiry.
$special_offers_now = new DateTimeImmutable( current_time( 'Y-m-d' ), wp_timezone() );
$special_offers     = Kate_Toms_Special_Offers_Grid::order_cards( $children, $special_offers_now );

if ( empty( $special_offers ) ) {
	return;
}
?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>>
	<?php foreach ( $special_offers as $special_offer_card ) : ?>
		<?php
		// TODO (step 4): replace this minimal summary with faithful pattern
		// rendering for houses and advert markup for placeholders.
		?>
		<div class="special-offer-card special-offer-card--<?php echo esc_attr( $special_offer_card['type'] ); ?>">
			<?php if ( Kate_Toms_Special_Offers_Grid::TYPE_PLACEHOLDER === $special_offer_card['type'] ) : ?>
				<p>
					<?php
					/* translators: %s: advert location key. */
					echo esc_html( sprintf( __( 'Placeholder advert: %s', 'kate-toms-core' ), $special_offer_card['placeholderLocation'] ) );
					?>
				</p>
			<?php else : ?>
				<p><strong><?php echo esc_html( get_the_title( $special_offer_card['selectedPostId'] ) ); ?></strong></p>
				<?php if ( '' !== $special_offer_card['offer'] ) : ?>
					<p><?php echo esc_html( $special_offer_card['offer'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $special_offer_card['offerDate'] ) : ?>
					<p><?php echo esc_html( $special_offer_card['offerDate'] ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
