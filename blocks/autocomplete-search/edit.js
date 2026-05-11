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

/**
 * WordPress components for the inspector controls.
 */
import {
	PanelBody,
	TextControl,
	ToggleControl,
	RangeControl,
} from '@wordpress/components';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @param  root0
 * @param  root0.attributes
 * @param  root0.setAttributes
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { placeholder, showCategories, maxResults } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Search Settings', 'kate-toms-core' ) }>
					<TextControl
						label={ __( 'Placeholder Text', 'kate-toms-core' ) }
						value={ placeholder }
						onChange={ ( value ) =>
							setAttributes( { placeholder: value } )
						}
						help={ __(
							'Text shown in the search input field',
							'kate-toms-core'
						) }
					/>
					<ToggleControl
						label={ __( 'Show Categories', 'kate-toms-core' ) }
						checked={ showCategories }
						onChange={ ( value ) =>
							setAttributes( { showCategories: value } )
						}
						help={ __(
							'Display category labels in search results',
							'kate-toms-core'
						) }
					/>
					<RangeControl
						label={ __( 'Max Results', 'kate-toms-core' ) }
						value={ maxResults }
						onChange={ ( value ) =>
							setAttributes( { maxResults: value } )
						}
						min={ 3 }
						max={ 100 }
						help={ __(
							'Maximum number of search results to display',
							'kate-toms-core'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="autocomplete-search-preview">
					<input
						type="text"
						className="autocomplete-search__input"
						placeholder={ placeholder }
						disabled
					/>
				</div>
			</div>
		</>
	);
}
