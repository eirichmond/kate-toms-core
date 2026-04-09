import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import './style.scss';

registerBlockType( 'kate-toms-core/availability-notes', {
	edit: Edit,
} );
