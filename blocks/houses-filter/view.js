/**
 * WordPress dependencies
 */
import { store, getContext } from '@wordpress/interactivity';

/**
 * Store configuration for the houses filter block.
 */
const storeName = 'kate-toms-house-filter';

// Get the region ID from the form's data-wp-context
const form = document.querySelector( '.houses-filter' );
const context = form
	? JSON.parse( form.getAttribute( 'data-wp-context' ) || '{}' )
	: {};
const initialRegionId = context.regionId || '';

const reverseSizeMap = { '2': '2-10', '10': '10-20', '20': '20+' };

const { state, actions } = store( storeName, {
	state: {
		isLoading: false,
		// True when URL params were present on load — keeps the filter UI
		// visible so the pre-applied filters are legible to the user.
		prefiltered: false,
		// Latches true on the first filter interaction; only resets when the
		// store re-initialises (page reload/revisit). Used to permanently hide
		// the archive's "Featured Properties" group once filtering begins.
		hasInteracted: false,
		// True only after a search resolves with zero matches across every
		// section — drives the single "No houses found" message.
		noResults: false,
		date: '',
		dtype: '',
		size: '',
		local: '',
		feature: '',
		results: 0,
		// Track active states for each filter section
		activeFilters: {
			dtype: [],
			size: [],
			local: [],
			feature: [],
		},
		// Map for size values
		sizeMap: {
			'2-10': '2',
			'10-20': '10',
			'20+': '20',
		},
		get hasResults() {
			return state.results > 0;
		},
		get isFilterPressed() {
			const ctx = getContext();
			const filterType = ctx.filterType;
			const filterValue = ctx.filterValue;
			if ( ! filterType || ! filterValue ) {
				return false;
			}
			const active = state.activeFilters[ filterType ];
			return Array.isArray( active ) && active.includes( filterValue );
		},
	},
	actions: {
		updateDate( event ) {
			state.date = event.target.value;

			// Default to Weekend (dtype '1') when a date is set and no duration
			// has been chosen yet. An existing selection (Week/Midweek) is
			// preserved rather than overwritten.
			if ( state.date && state.activeFilters.dtype.length === 0 ) {
				state.activeFilters.dtype = [ '1' ];
				state.dtype = '1';
			}

			actions.updateFilters();
		},

		updateDtype() {
			const ctx = getContext();
			const value = ctx.filterValue;

			// Toggle the value in activeFilters
			if ( state.activeFilters.dtype.includes( value ) ) {
				state.activeFilters.dtype = state.activeFilters.dtype.filter(
					( v ) => v !== value
				);
				state.dtype = '';
			} else {
				state.activeFilters.dtype = [ value ]; // Single selection
				state.dtype = value;
			}
			actions.updateFilters();
		},

		updateSize( event ) {
			const ctx = getContext();
			const value = ctx.filterValue || event.target.value;

			// Toggle the value in activeFilters
			if ( state.activeFilters.size.includes( value ) ) {
				state.activeFilters.size = state.activeFilters.size.filter(
					( v ) => v !== value
				);
				state.size = '';
			} else {
				state.activeFilters.size = [ value ]; // Single selection
				state.size = state.sizeMap[ value ] || value;
			}
			actions.updateFilters();
		},

		updateLocation( event ) {
			state.isLoading = true;
			const ctx = getContext();
			const value = ctx.filterValue || event.target.value;

			// Toggle the value in activeFilters
			if ( state.activeFilters.local.includes( value ) ) {
				state.activeFilters.local = state.activeFilters.local.filter(
					( v ) => v !== value
				);
				state.local = '';
			} else {
				state.activeFilters.local = [ value ]; // Single selection
				state.local = value;
			}
			actions.updateFilters();
		},

		updateFeature( event ) {
			const ctx = getContext();
			const value = ctx.filterValue || event.target.value;

			// Toggle the value in activeFilters
			if ( state.activeFilters.feature.includes( value ) ) {
				state.activeFilters.feature =
					state.activeFilters.feature.filter( ( v ) => v !== value );
				state.feature = '';
			} else {
				state.activeFilters.feature = [ value ]; // Single selection
				state.feature = value;
			}
			actions.updateFilters();
		},

		async updateFilters() {
			try {
				state.isLoading = true;
				// Every interaction handler funnels through here, so this is the
				// single place that records the user has started filtering.
				state.hasInteracted = true;
				// Clear any previous "no results" message while this search runs.
				state.noResults = false;

				// Build query parameters
				const params = new URLSearchParams();
				if ( state.date ) {
					params.append( 'date', state.date );
				}
				if ( state.dtype ) {
					params.append( 'dtype', state.dtype );
				}
				if ( state.size ) {
					params.append( 'size', state.size );
				}
				if ( state.local ) {
					params.append( 'local', state.local );
				}
				if ( state.feature ) {
					params.append( 'feature', state.feature );
				}

				// Get all houses regions and their default locations
				const housesRegions = document.querySelectorAll(
					'.wp-block-kate-toms-core-houses-filtered-results'
				);
				const totalResults = 0;

				// Create an array of promises for all fetch operations
				const fetchPromises = Array.from( housesRegions ).map(
					async ( region ) => {
						const context = JSON.parse(
							region.getAttribute( 'data-wp-context' ) || '{}'
						);
						const defaultLocation = context.defaultLocation
							? context.defaultLocation.toString()
							: '';

						// Create region-specific params
						const regionParams = new URLSearchParams( params );
						if ( defaultLocation ) {
							regionParams.append(
								'default_location',
								defaultLocation
							);
						}

						const apiUrl = `/wp-json/kate-toms/v1/houses?${ regionParams.toString() }`;

						try {
							const response = await fetch( apiUrl );
							const data = await response.json();

							if ( data.success ) {
								const housesGrid =
									region.querySelector( '.houses-grid' );
								if ( housesGrid && data.data ) {
									// Apply the response even when it's empty (0
									// results) so the grid is cleared and the region
									// hidden below — instead of leaving the stale
									// results rendered on initial page load.
									housesGrid.innerHTML = data.data.html || '';
									const total = data.data.total || 0;

									// Re-arm the filtered-results infinite scroll
									// from page 1. The houses-filtered-results
									// block reads these on scroll/observe.
									region.dataset.currentPage = '1';
									region.dataset.hasMore = data.data.hasMore
										? 'true'
										: 'false';

									// When this is already the final page, append
									// the row-filling adverts (returned separately
									// so they never land mid-grid between pages).
									if (
										! data.data.hasMore &&
										data.data.adverts
									) {
										housesGrid.insertAdjacentHTML(
											'beforeend',
											data.data.adverts
										);
									}

									// Find the parent block element and all its .wp-block-group ancestors
									let currentElement = region;
									while ( currentElement ) {
										// If this is a .wp-block-group or our results block, toggle visibility
										if (
											currentElement.classList.contains(
												'wp-block-group'
											) ||
											currentElement.classList.contains(
												'wp-block-kate-toms-core-houses-filtered-results'
											)
										) {
											currentElement.style.display =
												total === 0 ? 'none' : '';
										}
										// Move up to the next parent
										currentElement =
											currentElement.parentElement;
									}

									return total;
								}
							}
							return 0;
						} catch ( error ) {
							console.error( 'Error updating region:', error );
							const housesGrid =
								region.querySelector( '.houses-grid' );
							if ( housesGrid ) {
								housesGrid.innerHTML = `<div class="houses-filter__error"><p>Error loading houses: ${ error.message }</p></div>`;
							}
							return 0;
						}
					}
				);

				// Wait for all fetch operations to complete
				const results = await Promise.all( fetchPromises );
				state.results = results.reduce(
					( sum, count ) => sum + count,
					0
				);
				// Every section came back empty — show the single message.
				state.noResults = state.results === 0;

				// Nudge the filtered-results infinite scroll: after a filter
				// change the sentinel may already sit within the viewport (short
				// result set), in which case no IntersectionObserver entry fires
				// on its own. A synthetic scroll event triggers each region's
				// checkScroll handler.
				window.dispatchEvent( new Event( 'scroll' ) );
			} catch ( error ) {
				console.error( 'Error updating filters:', error );
				// Show user-friendly error message in all regions
				const housesRegions = document.querySelectorAll(
					'.wp-block-kate-toms-core-houses-filtered-results .houses-grid'
				);
				housesRegions.forEach( ( region ) => {
					region.innerHTML = `<div class="houses-filter__error"><p>Error loading houses: ${ error.message }</p></div>`;
				} );
				state.results = 0;
			} finally {
				state.isLoading = false;
			}
		},
	},
} );

// Bootstrap from URL parameters so a landing-page button can link directly
// to pre-filtered results, e.g. /houses/?kt_dtype=2&kt_size=10&kt_local=5
//
// Params are namespaced with `kt_` deliberately: `size` and `feature` are
// registered house taxonomies with `query_var => true`, so an un-prefixed
// `?size=2` is parsed by WordPress as a taxonomy archive request and 404s
// before any JS runs. The prefix keeps these clear of all WP query vars.
( function initFromUrlParams() {
	const params = new URLSearchParams( window.location.search );
	const keys = [ 'kt_date', 'kt_dtype', 'kt_size', 'kt_local', 'kt_feature' ];
	if ( ! keys.some( ( k ) => params.has( k ) ) ) return;

	const date    = params.get( 'kt_date' );
	const dtype   = params.get( 'kt_dtype' );
	const size    = params.get( 'kt_size' );
	const local   = params.get( 'kt_local' );
	const feature = params.get( 'kt_feature' );

	if ( date )    state.date = date;
	if ( dtype ) {
		state.dtype = dtype;
		state.activeFilters.dtype = [ dtype ];
	}
	if ( size ) {
		state.size = size;
		// activeFilters stores the display key ('2-10' etc.) so the UI
		// button highlights; reverse-map from the numeric API value.
		state.activeFilters.size = [ reverseSizeMap[ size ] || size ];
	}
	if ( local ) {
		state.local = local;
		state.activeFilters.local = [ local ];
	}
	if ( feature ) {
		state.feature = feature;
		state.activeFilters.feature = [ feature ];
	}

	state.prefiltered = true;
	actions.updateFilters();
} )();
