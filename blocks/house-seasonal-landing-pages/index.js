/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Styles
 */
import './style.scss';

/**
 * Internal dependencies
 */
import Edit from './edit';
import metadata from './block.json';

/**
 * Register the House Seasonal Landing Pages block
 */
registerBlockType( metadata.name, {
	edit: Edit,
} );
