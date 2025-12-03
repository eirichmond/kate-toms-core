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
 * Register the House Availability Landing Pages block
 */
registerBlockType( 'kate-toms-core/house-availability-landing-pages', {
	edit: Edit,
} );
