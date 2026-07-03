import { __ } from '@wordpress/i18n';
import {
	InnerBlocks,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';

/**
 * Only special-offer-house children may be placed inside the grid.
 *
 * @type {string[]}
 */
const ALLOWED_BLOCKS = [ 'kate-toms-core/kateandtoms-special-offer-house' ];

/**
 * Starter layout: one empty special-offer-house child ready to configure.
 *
 * @type {Array}
 */
const TEMPLATE = [ [ 'kate-toms-core/kateandtoms-special-offer-house' ] ];

/**
 * Editor UI for the Special Offers Grid container.
 *
 * Renders an InnerBlocks region locked to special-offer-house children, seeded
 * with one child and offering a button appender to add more. Editor order is
 * the authoring order; the front end re-orders by offer date at render time.
 *
 * @return {JSX.Element} Editor element.
 */
export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'kate-toms-special-offers-grid-editor',
	} );

	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		template: TEMPLATE,
		renderAppender: InnerBlocks.ButtonBlockAppender,
	} );

	return (
		<div { ...innerBlocksProps }>
			<span
				className="kate-toms-special-offers-grid-editor__label"
				aria-hidden="true"
			>
				{ __( 'Special Offers Grid', 'kate-toms-core' ) }
			</span>
			{ children }
		</div>
	);
}
