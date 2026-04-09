/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Save component for the Google Map block.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @return {Element} Element to render.
 */
export default function Save( { attributes } ) {
	const { address, lat, lng } = attributes;
	const blockProps = useBlockProps.save();

	// Don't render anything if no coordinates are set
	if ( ! lat || ! lng ) {
		return (
			<div { ...blockProps }>
				<div className="google-map-placeholder">
					<p>Please configure the map location in the editor.</p>
				</div>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<div
				className="google-map-container"
				data-lat={ lat }
				data-lng={ lng }
				data-address={ address }
			>
				<div
					className="google-map"
					style={ { width: '100%', height: '400px' } }
				>
					{ /* Map will be initialized by view.js */ }
				</div>
			</div>
		</div>
	);
}
