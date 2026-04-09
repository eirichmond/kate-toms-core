/**
 * WordPress dependencies
 */
import { store } from '@wordpress/interactivity';

/**
 * Extend the shared house filter store with derived state
 * for breadcrumb label display.
 */
const { state } = store( 'kate-toms-house-filter', {
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
