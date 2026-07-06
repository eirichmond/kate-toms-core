import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import metadata from './block.json';

import './style.scss';
import './index.scss';

registerBlockType( metadata.name, {
	edit: Edit,
} );
