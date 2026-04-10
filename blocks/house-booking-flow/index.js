/**
 * House Booking Flow Block
 *
 * Handles the multi-step booking process for Kate & Tom's houses.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './editor.scss';
import './style.scss';

/**
 * Block edit function
 */
function Edit() {
	const blockProps = useBlockProps( {
		className: 'house-booking-flow-editor',
	} );

	return (
		<div { ...blockProps }>
			<div className="booking-flow-placeholder">
				<h3>{ __( 'House Booking Flow', 'kate-toms-core' ) }</h3>
				<p>
					{ __(
						'This block will display the booking steps based on URL parameters.',
						'kate-toms-core'
					) }
				</p>
				<div className="progress-preview">
					<div className="step active">1. Select booking</div>
					<div className="step">2. Personal details</div>
					<div className="step">3. All done</div>
				</div>
				<p>
					<small>
						{ __(
							'The actual booking flow will be displayed on the frontend when URL parameters are present.',
							'kate-toms-core'
						) }
					</small>
				</p>
			</div>
		</div>
	);
}

/**
 * Register the block
 */
registerBlockType( 'kate-toms-core/house-booking-flow', {
	edit: Edit,
} );
