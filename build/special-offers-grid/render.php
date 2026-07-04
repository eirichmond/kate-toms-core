<?php
/**
 * Special Offers Grid render template.
 *
 * Collects the special-offer-house child attributes in editor order, then uses
 * the pure ordering helper to drop expired offers and sort the rest by offer
 * date (soonest expiry first). Each house card is rendered from the theme's
 * special-offer pattern with this card's own offer metadata.
 *
 * The rows-of-four grid and placeholder advert markup are added in later steps.
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

$special_offers_pattern_file = get_theme_file_path( 'patterns/house-card-search-special-offer.php' );

global $post;
$special_offers_original_post = $post;

// Cells actually rendered (houses + manual placeholders); drives the row-fill.
$special_offers_cell_count = 0;
?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>>
	<?php
	foreach ( $special_offers as $special_offer_card ) :
		if ( Kate_Toms_Special_Offers_Grid::TYPE_PLACEHOLDER === $special_offer_card['type'] ) :
			$placeholder_location = sanitize_key( $special_offer_card['placeholderLocation'] );
			$advert               = null;

			if ( '' !== $placeholder_location && class_exists( 'Kate_Toms_Core_Admin' ) ) {
				$special_offers_admin   = new Kate_Toms_Core_Admin( 'kate-toms-core', '1.0.0' );
				$special_offers_adverts = $special_offers_admin->get_adverts_for_location( $placeholder_location, 100 );
				if ( ! empty( $special_offers_adverts ) ) {
					// A new random advert each render, hence "Random Placeholder".
					$advert = $special_offers_adverts[ array_rand( $special_offers_adverts ) ];
				}
			}
			?>
			<div class="special-offer-card special-offer-card--placeholder">
				<div class="wp-block-group has-white-background-color has-background special-offer-advert-placeholder" style="min-height:365px;display:flex;overflow:hidden">
					<?php if ( $advert && ! empty( $advert['image_url'] ) ) : ?>
						<img src="<?php echo esc_url( $advert['image_url'] ); ?>" alt="<?php esc_attr_e( 'Advertisement', 'kate-toms-core' ); ?>" style="width:100%;height:100%;min-height:365px;object-fit:cover;display:block;" />
					<?php elseif ( is_user_logged_in() ) : ?>
						<p style="margin:auto;padding:20px;text-align:center;color:#721c24;">
							<?php
							echo esc_html(
								'' === $placeholder_location
									? __( 'Random Placeholder is on — choose a location in the block settings.', 'kate-toms-core' )
									: __( 'No adverts found for the selected location.', 'kate-toms-core' )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>
			<?php
			++$special_offers_cell_count;
			continue;
		endif;

		$special_offer_house = get_post( $special_offer_card['selectedPostId'] );

		$special_offer_is_valid_house = ( $special_offer_house instanceof WP_Post )
			&& ( 'houses' === $special_offer_house->post_type )
			&& ( 'publish' === $special_offer_house->post_status );

		// An unavailable house is skipped; logged-in editors are told why.
		if ( ! $special_offer_is_valid_house ) {
			if ( is_user_logged_in() ) {
				printf(
					'<div class="special-offer-card special-offer-card--notice" style="padding:20px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:4px;">%s</div>',
					esc_html__( 'A selected special-offer house is no longer available and was skipped.', 'kate-toms-core' )
				);
			}
			continue;
		}

		// Only parent houses render as cards; child sub-pages are skipped.
		if ( 0 !== (int) $special_offer_house->post_parent ) {
			if ( is_user_logged_in() ) {
				printf(
					'<div class="special-offer-card special-offer-card--notice" style="padding:20px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:4px;">%s</div>',
					esc_html__( 'A special offer points at a child page — please select a parent house.', 'kate-toms-core' )
				);
			}
			continue;
		}

		/*
		 * Point the global post at this house and expose its offer to the
		 * pattern. We include the pattern file directly (NOT do_blocks of a
		 * wp:pattern reference) so its PHP re-runs for every card: core caches
		 * a file-based pattern's content after the first include and unsets its
		 * filePath, which would otherwise bake the first card's offer into every
		 * subsequent card.
		 */
		$post = $special_offer_house; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporarily set the post context so the pattern's post-meta bindings resolve to this house.
		setup_postdata( $post );

		global $special_offer_attributes;
		$special_offer_attributes = array(
			'offer'     => $special_offer_card['offer'],
			'offerDate' => $special_offer_card['offerDate'],
		);

		echo '<div class="special-offer-card special-offer-card--house">';
		if ( $special_offers_pattern_file && file_exists( $special_offers_pattern_file ) ) {
			ob_start();
			include $special_offers_pattern_file;
			echo do_blocks( ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted rendered block HTML.
		}
		echo '</div>';
		++$special_offers_cell_count;
	endforeach;

	// Restore the original global post context for the rest of the page.
	$post = $special_offers_original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the post context swapped out above.
	if ( $special_offers_original_post instanceof WP_Post ) {
		setup_postdata( $special_offers_original_post );
	} else {
		wp_reset_postdata();
	}

	// Complete the final row with location-agnostic random adverts.
	$special_offers_fill = Kate_Toms_Special_Offers_Grid::advert_fill_count( $special_offers_cell_count );
	if ( $special_offers_fill > 0 && class_exists( 'Kate_Toms_Core_Admin' ) ) {
		$special_offers_fill_admin = new Kate_Toms_Core_Admin( 'kate-toms-core', '1.0.0' );
		$special_offers_pool       = $special_offers_fill_admin->get_all_adverts();

		if ( ! empty( $special_offers_pool ) ) {
			shuffle( $special_offers_pool );

			foreach ( array_slice( $special_offers_pool, 0, $special_offers_fill ) as $special_offers_fill_advert ) :
				if ( empty( $special_offers_fill_advert['image_url'] ) ) {
					continue;
				}
				?>
				<div class="special-offer-card special-offer-card--placeholder special-offer-card--autofill">
					<div class="wp-block-group has-white-background-color has-background special-offer-advert-placeholder" style="min-height:365px;display:flex;overflow:hidden">
						<img src="<?php echo esc_url( $special_offers_fill_advert['image_url'] ); ?>" alt="<?php esc_attr_e( 'Advertisement', 'kate-toms-core' ); ?>" style="width:100%;height:100%;min-height:365px;object-fit:cover;display:block;" />
					</div>
				</div>
				<?php
			endforeach;
		}
	}
	?>
</div>
