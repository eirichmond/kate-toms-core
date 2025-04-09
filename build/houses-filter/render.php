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

<div <?php
	echo wp_kses_post(
		get_block_wrapper_attributes(
			array(
				'class' => 'houses-filter-wrapper',
				'id'    => $filter_id,
			)
		)
	);
	?> data-wp-interactive="kate-toms-house-filter">
    <form class="houses-filter" data-wp-on--submit="actions.handleSubmit"
        data-wp-context='{ "regionId": "<?php echo esc_attr( $results_region ); ?>" }'>
        <div class="houses-filter__grid">
            <!-- Date Picker -->
            <div class="houses-filter__field">
                <label class="houses-filter__label screen-reader-only"
                    for="<?php echo esc_attr( "{$filter_id}-date" ); ?>">
                    <?php esc_html_e( 'Select Date', 'kate-and-toms-houses-filter-search' ); ?>
                </label>
                <input type="date" id="<?php echo esc_attr( "{$filter_id}-date" ); ?>" class="houses-filter__date"
                    data-wp-bind--value="state.date" data-wp-on--change="actions.updateDate" />
                <label class="houses-filter__label screen-reader-only">
                    <?php esc_html_e( 'Duration', 'kate-and-toms-houses-filter-search' ); ?>
                </label>
                <div class="houses-filter__buttons">
                    <?php foreach ( $duration_options as $option ) : ?>
                    <button type="button" class="houses-filter__button"
                        data-value="<?php echo esc_attr( $option['value'] ); ?>" data-wp-on--click="actions.updateDtype"
                        data-wp-bind--aria-pressed="state.activeFilters.dtype.includes('<?php echo esc_js( $option['value'] ); ?>')">
                        <?php echo esc_html( $option['label'] ); ?>
                    </button>
                    <?php endforeach; ?>
                </div>


            </div>

            <!-- Size -->
            <div class="houses-filter__field">
                <label class="houses-filter__label screen-reader-only">
                    <?php esc_html_e( 'Size', 'kate-and-toms-houses-filter-search' ); ?>
                </label>
                <div class="houses-filter__size-controls">
                    <select class="houses-filter__select" data-wp-on--change="actions.updateSize"
                        data-wp-bind--value="state.activeFilters.size[0] || ''"
                        data-wp-bind--aria-pressed="state.activeFilters.size.includes(value)">
                        <option value=""><?php esc_html_e( 'Select a size', 'kate-and-toms-houses-filter-search' ); ?>
                        </option>
                        <?php foreach ( $size_options as $option ) : ?>
                        <option value="<?php echo esc_attr( $option['value'] ); ?>">
                            <?php echo esc_html( $option['label'] ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="houses-filter__buttons">
                        <?php foreach ( $size_options as $option ) : ?>
                        <button type="button" class="houses-filter__button"
                            data-value="<?php echo esc_attr( $option['value'] ); ?>"
                            data-wp-on--click="actions.updateSize"
                            data-wp-bind--aria-pressed="state.activeFilters.size.includes('<?php echo esc_js( $option['value'] ); ?>')">
                            <?php echo esc_html( $option['label'] ); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div class="houses-filter__field">
                <label class="houses-filter__label screen-reader-only">
                    <?php esc_html_e( 'Location', 'kate-and-toms-houses-filter-search' ); ?>
                </label>
                <div class="houses-filter__location-controls">
                    <select class="houses-filter__select" data-wp-on--change="actions.updateLocation"
                        data-wp-bind--value="state.activeFilters.local[0] || ''"
                        data-wp-bind--aria-pressed="state.activeFilters.local.includes(value)">
                        <option value="">
                            <?php esc_html_e( 'Select a location', 'kate-and-toms-houses-filter-search' ); ?></option>
                        <?php foreach ( $locations as $location ) : ?>
                        <option value="<?php echo esc_attr( $location->term_id ); ?>">
                            <?php echo esc_html( $location->name ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="houses-filter__buttons">
                        <?php
						$location_mappings = array(
							'In the Cotswolds' => 'Cotswolds',
							'By the Coast'     => 'Coast',
							'In the Country'   => 'Country',
						);
						foreach ( $locations as $location ) :
							if ( array_key_exists( $location->name, $location_mappings ) ) :
								?>
                        <button type="button" class="houses-filter__button"
                            data-value="<?php echo esc_attr( $location->term_id ); ?>"
                            data-wp-on--click="actions.updateLocation"
                            data-wp-bind--aria-pressed="state.activeFilters.local.includes('<?php echo esc_js( $location->term_id ); ?>')">
                            <?php echo esc_html( $location_mappings[ $location->name ] ); ?>
                        </button>
                        <?php
							endif;
						endforeach;
						?>
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="houses-filter__field">
                <label class="houses-filter__label screen-reader-only">
                    <?php esc_html_e( 'Features', 'kate-and-toms-houses-filter-search' ); ?>
                </label>
                <div class="houses-filter__feature-controls">
                    <select class="houses-filter__select" data-wp-on--change="actions.updateFeature"
                        data-wp-bind--value="state.activeFilters.feature[0] || ''"
                        data-wp-bind--aria-pressed="state.activeFilters.feature.includes(value)">
                        <option value="">
                            <?php esc_html_e( 'Select a feature', 'kate-and-toms-houses-filter-search' ); ?></option>
                        <?php foreach ( $features as $feature ) : ?>
                        <option value="<?php echo esc_attr( $feature->term_id ); ?>">
                            <?php echo esc_html( $feature->name ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="houses-filter__buttons">
                        <?php
						$feature_mappings = array(
							'Pool'           => 'Pool',
							'With a Hot Tub' => 'Hot Tub',
							'Beach'          => 'Beach',
						);
						foreach ( $features as $feature ) :
							if ( array_key_exists( $feature->name, $feature_mappings ) ) :
								?>
                        <button type="button" class="houses-filter__button"
                            data-value="<?php echo esc_attr( $feature->term_id ); ?>"
                            data-wp-on--click="actions.updateFeature"
                            data-wp-bind--aria-pressed="state.activeFilters.feature.includes('<?php echo esc_js( $feature->term_id ); ?>')">
                            <?php echo esc_html( $feature_mappings[ $feature->name ] ); ?>
                        </button>
                        <?php
							endif;
						endforeach;
						?>
                    </div>
                </div>
            </div>
        </div>

        <!-- <div class="houses-filter__actions">
			<button 
				type="submit" 
				class="houses-filter__submit"
				data-wp-bind--disabled="state.isLoading"
			>
				<?php // esc_html_e( 'Search Houses', 'kate-and-toms-houses-filter-search' ); ?>
			</button>
		</div> -->
    </form>

    <!-- Results Region -->
    <div class="houses-filter__results" data-wp-router-region="<?php echo esc_attr( $results_region ); ?>"
        data-wp-bind--aria-busy="state.isLoading">
        <div data-wp-bind--hidden="!state.isLoading" class="houses-filter__loading">
            <?php esc_html_e( 'Loading...', 'kate-and-toms-houses-filter-search' ); ?>
        </div>
    </div>
</div>
