/**
 * WordPress dependencies
 */
import { store } from '@wordpress/interactivity';

/**
 * Extend the shared house filter store with derived state
 * for breadcrumb label display.
 */
const { state, actions } = store( 'kate-toms-house-filter', {
	actions: {
		/**
		 * Clear every active filter and re-run the search, returning the
		 * results to their default (unfiltered) state. Triggered by the
		 * breadcrumb Reset button, which is only visible once a size,
		 * location or feature has been selected.
		 */
		resetFilters() {
			state.activeFilters.dtype = [];
			state.activeFilters.size = [];
			state.activeFilters.local = [];
			state.activeFilters.feature = [];
			state.date = '';
			state.dtype = '';
			state.size = '';
			state.local = '';
			state.feature = '';

			// The size/location/feature <select> dropdowns aren't bound to
			// state, so reset their displayed value directly.
			document
				.querySelectorAll( '.houses-filter__select' )
				.forEach( ( select ) => {
					select.value = '';
				} );

			if ( typeof actions.updateFilters === 'function' ) {
				actions.updateFilters();
			}
		},
	},
	state: {
		sizeLabelMap: {},
		localLabelMap: {},
		featureLabelMap: {},

		get activeSizeLabel() {
			const size = state.activeFilters?.size?.[ 0 ];
			return size ? state.sizeLabelMap[ size ] || `Sleeps ${ size }` : '';
		},
		get activeLocalLabel() {
			const local = state.activeFilters?.local?.[ 0 ];
			return local ? state.localLabelMap[ local ] || '' : '';
		},
		get activeFeatureLabel() {
			const feature = state.activeFilters?.feature?.[ 0 ];
			return feature ? state.featureLabelMap[ feature ] || '' : '';
		},
		get hasBreadcrumbs() {
			return !! (
				state.activeSizeLabel ||
				state.activeLocalLabel ||
				state.activeFeatureLabel
			);
		},
	},
} );
