/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Edit from './edit';

/**
 * Register the House Seasonal Landing Pages block
 */
registerBlockType( 'kate-toms-core/house-seasonal-landing-pages', {
	edit: Edit,
} );
