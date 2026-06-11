<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

// Generate unique IDs
$filter_id      = wp_unique_id( 'houses-filter-' );
$results_region = wp_unique_id( 'houses-results-' );

// Initialize state for the houses filter
wp_interactivity_state(
	'kate-toms-house-filter',
	array(
		'isLoading'     => false,
		'hasInteracted' => false,
		'noResults'     => false,
		'date'          => '',
		'dtype'         => '',
		'size'          => '',
		'local'         => '',
		'feature'       => '',
		'regionId'      => $results_region,
		'activeFilters' => array(
			'dtype'   => array(),
			'size'    => array(),
			'local'   => array(),
			'feature' => array(),
		),
	)
);

// Get taxonomy terms for locations and features
$locations = get_terms(
	array(
		'taxonomy'   => 'location',
		'hide_empty' => true,
	)
);

$features = get_terms(
	array(
		'taxonomy'   => 'feature',
		'hide_empty' => true,
	)
);

// Size options
$size_options = array(
	array(
		'label' => __( '2-10', 'kate-and-toms-houses-filter-search' ),
		'value' => '2-10',
	),
	array(
		'label' => __( '10-20', 'kate-and-toms-houses-filter-search' ),
		'value' => '10-20',
	),
	array(
		'label' => __( '20+', 'kate-and-toms-houses-filter-search' ),
		'value' => '20+',
	),
);

// Duration options
$duration_options = array(
	array(
		'label' => __( 'Weekend', 'kate-and-toms-houses-filter-search' ),
		'value' => '1',
	),
	array(
		'label' => __( 'Week', 'kate-and-toms-houses-filter-search' ),
		'value' => '2',
	),
	array(
		'label' => __( 'Midweek', 'kate-and-toms-houses-filter-search' ),
		'value' => '3',
	),
);

?>

<div
<?php
	echo wp_kses_post(
		get_block_wrapper_attributes(
			array(
				'class' => 'houses-filter-wrapper',
				'id'    => $filter_id,
			)
		)
	);
	?>
	data-wp-interactive="kate-toms-house-filter">
	<form class="houses-filter" data-wp-on--submit="actions.handleSubmit"
		data-wp-context='{ "regionId": "<?php echo esc_attr( $results_region ); ?>" }'>

		<!-- Main Filter Bar -->
		<div class="houses-filter__row">
			<!-- Date Picker -->
			<div class="houses-filter__field houses-filter__field--date">
				<svg class="houses-filter__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
					<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
				</svg>
				<label class="houses-filter__label" for="<?php echo esc_attr( "{$filter_id}-date" ); ?>">
					<?php esc_html_e( 'WHEN', 'kate-and-toms-houses-filter-search' ); ?>
				</label>
				<input type="date" id="<?php echo esc_attr( "{$filter_id}-date" ); ?>" class="houses-filter__input"
					data-wp-bind--value="state.date" data-wp-on--change="actions.updateDate"
					placeholder="<?php esc_attr_e( 'Select Date...', 'kate-and-toms-houses-filter-search' ); ?>" />
			</div>

			<!-- Only show on small screens -->
			<div class="houses-filter__button-group houses-filter__button-group--date is-style-hide-navigation-desktop">
				<?php foreach ( $duration_options as $option ) : ?>
				<button type="button" class="houses-filter__button"
					data-wp-context='<?php echo wp_json_encode( array( 'filterType' => 'dtype', 'filterValue' => $option['value'] ) ); ?>'
					data-wp-on--click="actions.updateDtype"
					data-wp-bind--aria-pressed="state.isFilterPressed">
					<?php echo esc_html( $option['label'] ); ?>
				</button>
				<?php endforeach; ?>
			</div>


			<!-- Size -->
			<div class="houses-filter__field houses-filter__field--size">
				<svg class="houses-filter__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
					<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
				</svg>
				<label class="houses-filter__label">
					<?php esc_html_e( 'SLEEPS', 'kate-and-toms-houses-filter-search' ); ?>
				</label>
				<select class="houses-filter__select" data-wp-on--change="actions.updateSize">
					<option value=""><?php esc_html_e( 'Any', 'kate-and-toms-houses-filter-search' ); ?></option>
					<?php foreach ( $size_options as $option ) : ?>
					<option value="<?php echo esc_attr( $option['value'] ); ?>">
						<?php echo esc_html( $option['label'] ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Location -->
			<div class="houses-filter__field houses-filter__field--location">
				<svg class="houses-filter__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
					<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
				</svg>
				<label class="houses-filter__label">
					<?php esc_html_e( 'WHERE', 'kate-and-toms-houses-filter-search' ); ?>
				</label>
				<select class="houses-filter__select" data-wp-on--change="actions.updateLocation">
					<option value=""><?php esc_html_e( 'Any', 'kate-and-toms-houses-filter-search' ); ?></option>
					<?php
					// Priority locations shown first, with their dropdown labels.
					$location_priority = array(
						'In the Cotswolds' => 'Cotswolds',
						'By the Coast'     => 'Coast',
						'In the Country'   => 'Country',
					);

					// Build the ordered list: priority terms first, then the rest alphabetically.
					$ordered_locations = array();

					foreach ( $location_priority as $priority_name => $priority_label ) {
						foreach ( $locations as $location ) {
							if ( $location->name === $priority_name ) {
								$ordered_locations[] = array(
									'term'  => $location,
									'label' => $priority_label,
								);
								break;
							}
						}
					}

					$remaining_locations = array();
					foreach ( $locations as $location ) {
						if ( ! isset( $location_priority[ $location->name ] ) ) {
							$remaining_locations[] = $location;
						}
					}

					usort(
						$remaining_locations,
						static function ( $a, $b ) {
							return strcasecmp( $a->name, $b->name );
						}
					);

					foreach ( $remaining_locations as $location ) {
						$ordered_locations[] = array(
							'term'  => $location,
							'label' => $location->name,
						);
					}

					foreach ( $ordered_locations as $ordered_location ) :
						?>
					<option value="<?php echo esc_attr( $ordered_location['term']->term_id ); ?>">
						<?php echo esc_html( $ordered_location['label'] ); ?>
					</option>
						<?php
					endforeach;
					?>
				</select>
			</div>

			<!-- Features -->
			<div class="houses-filter__field houses-filter__field--features">
				<svg class="houses-filter__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
					<path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>
				</svg>
				<label class="houses-filter__label">
					<?php esc_html_e( 'FEATURES', 'kate-and-toms-houses-filter-search' ); ?>
				</label>
				<select class="houses-filter__select" data-wp-on--change="actions.updateFeature">
					<option value=""><?php esc_html_e( 'Any', 'kate-and-toms-houses-filter-search' ); ?></option>
					<?php foreach ( $features as $feature ) : ?>
					<option value="<?php echo esc_attr( $feature->term_id ); ?>">
						<?php echo esc_html( $feature->name ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<!-- Button Groups Row -->
		<div class="houses-filter__buttons-row">
			<!-- Date Type Buttons -->
			<div class="houses-filter__button-group houses-filter__button-group--date is-style-hide-navigation-mobile">
				<?php foreach ( $duration_options as $option ) : ?>
				<button type="button" class="houses-filter__button"
					data-wp-context='<?php echo wp_json_encode( array( 'filterType' => 'dtype', 'filterValue' => $option['value'] ) ); ?>'
					data-wp-on--click="actions.updateDtype"
					data-wp-bind--aria-pressed="state.isFilterPressed">
					<?php echo esc_html( $option['label'] ); ?>
				</button>
				<?php endforeach; ?>
			</div>

			<!-- Size Buttons -->
			<div class="houses-filter__button-group houses-filter__button-group--size is-style-hide-navigation-mobile">
				<?php foreach ( $size_options as $option ) : ?>
				<button type="button" class="houses-filter__button"
					data-wp-context='<?php echo wp_json_encode( array( 'filterType' => 'size', 'filterValue' => $option['value'] ) ); ?>'
					data-wp-on--click="actions.updateSize"
					data-wp-bind--aria-pressed="state.isFilterPressed">
					<?php echo esc_html( $option['label'] ); ?>
				</button>
				<?php endforeach; ?>
			</div>

			<!-- Location Buttons -->
			<div class="houses-filter__button-group houses-filter__button-group--location is-style-hide-navigation-mobile">
				<?php
				$location_mappings = array(
					'In the Cotswolds' => 'Cotswolds',
					'By the Coast'     => 'Coast',
					'In the Country'   => 'Country',
				);

				foreach ( $location_mappings as $location_name => $button_label ) :
					$matching_location = null;
					foreach ( $locations as $location ) {
						if ( $location->name === $location_name ) {
							$matching_location = $location;
							break;
						}
					}

					if ( $matching_location ) :
						?>
				<button type="button" class="houses-filter__button"
					data-wp-context='<?php echo wp_json_encode( array( 'filterType' => 'local', 'filterValue' => (string) $matching_location->term_id ) ); ?>'
					data-wp-on--click="actions.updateLocation"
					data-wp-bind--aria-pressed="state.isFilterPressed">
					<?php echo esc_html( $button_label ); ?>
				</button>
						<?php
					endif;
				endforeach;
				?>
			</div>

			<!-- Feature Buttons -->
			<div class="houses-filter__button-group houses-filter__button-group--features is-style-hide-navigation-mobile">
				<?php
				$feature_mappings = array(
					'Pool'           => 'Pool',
					'With a Hot Tub' => 'Hot Tub',
					'Beach'          => 'Beach',
				);

				foreach ( $feature_mappings as $feature_name => $button_label ) :
					$matching_feature = null;
					foreach ( $features as $feature ) {
						if ( $feature->name === $feature_name ) {
							$matching_feature = $feature;
							break;
						}
					}

					if ( $matching_feature ) :
						?>
				<button type="button" class="houses-filter__button"
					data-wp-context='<?php echo wp_json_encode( array( 'filterType' => 'feature', 'filterValue' => (string) $matching_feature->term_id ) ); ?>'
					data-wp-on--click="actions.updateFeature"
					data-wp-bind--aria-pressed="state.isFilterPressed">
					<?php echo esc_html( $button_label ); ?>
				</button>
						<?php
					endif;
				endforeach;
				?>
			</div>
		</div>

	</form>

	<div
		class="houses-loading-overlay"
		data-wp-bind--hidden="!state.isLoading"
	>
		<div class="houses-loading-spinner">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
				<path fill="currentColor" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z" opacity=".25"/>
				<path fill="currentColor" d="M12,4a8,8,0,0,1,7.89,6.7A1.53,1.53,0,0,0,21.38,12h0a1.5,1.5,0,0,0,1.48-1.75,11,11,0,0,0-21.72,0A1.5,1.5,0,0,0,2.62,12h0a1.53,1.53,0,0,0,1.49-1.3A8,8,0,0,1,12,4Z">
				<animateTransform attributeName="transform" type="rotate" dur="0.75s" values="0 12 12;360 12 12" repeatCount="indefinite"/>
				</path>
			</svg>
		</div>
	</div>

	<div class="houses-filter__no-results" data-wp-bind--hidden="!state.noResults">
		<p><?php esc_html_e( 'No houses found', 'kate-and-toms-houses-filter-search' ); ?></p>
	</div>
</div>
