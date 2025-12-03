/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';


import "./style.scss";

/**
 * Internal dependencies
 */
import Edit from './edit';

/**
 * Register the House Landing Pages block
 */
registerBlockType( 'kate-toms-core/house-landing-pages', {
	edit: Edit,
} );
