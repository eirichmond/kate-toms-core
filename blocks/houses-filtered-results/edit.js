/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @param {Object}   props               Properties passed to the function.
 * @param {Object}   props.attributes    Available block attributes.
 * @param {Function} props.setAttributes Function that updates individual attributes.
 *
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const { defaultLocation } = attributes;

	const { locations } = useSelect((select) => {
		const { getEntityRecords } = select(coreStore);
		return {
			locations: getEntityRecords('taxonomy', 'location', { per_page: -1 }) || [],
		};
	}, []);

	const locationOptions = locations.map((location) => ({
		value: location.id,
		label: location.name,
	}));

	locationOptions.unshift({ value: '', label: __('Select a location', 'kate-toms-core') });

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Location Settings', 'kate-toms-core')}>
					<SelectControl
						label={__('Default Location', 'kate-toms-core')}
						value={defaultLocation}
						options={locationOptions}
						onChange={(value) => setAttributes({ defaultLocation: Number(value) || '' })}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<p>{__('Houses Filtered Results', 'kate-toms-core')}</p>
			</div>
		</>
	);
}
