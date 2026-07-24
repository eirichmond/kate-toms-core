import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Placeholder, TextControl } from '@wordpress/components';

import './editor.scss';

/**
 * Normalise the stored value into an embeddable Matterport URL.
 *
 * Accepts a full share link (https://my.matterport.com/show/?m=xxxx) or a
 * bare model ID.
 *
 * @param {string} value The raw tourUrl attribute value.
 * @return {string} The embeddable URL, or an empty string.
 */
export function getEmbedUrl( value ) {
	const trimmed = ( value || '' ).trim();
	if ( ! trimmed ) {
		return '';
	}
	if ( /^https?:\/\//i.test( trimmed ) ) {
		return trimmed;
	}
	if ( /^[a-zA-Z0-9]+$/.test( trimmed ) ) {
		return `https://my.matterport.com/show/?m=${ trimmed }`;
	}
	return '';
}

export default function Edit( { attributes, setAttributes } ) {
	const { tourUrl, tourTitle } = attributes;
	const blockProps = useBlockProps();
	const embedUrl = getEmbedUrl( tourUrl );

	const urlControl = (
		<TextControl
			label={ __( 'Matterport tour link', 'kate-toms-core' ) }
			value={ tourUrl }
			onChange={ ( value ) => setAttributes( { tourUrl: value } ) }
			placeholder="https://my.matterport.com/show/?m=..."
			help={ __(
				'Paste the Matterport share link or model ID.',
				'kate-toms-core'
			) }
		/>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Tour Settings', 'kate-toms-core' ) }>
					{ urlControl }
					<TextControl
						label={ __( 'Accessible title', 'kate-toms-core' ) }
						value={ tourTitle }
						onChange={ ( value ) =>
							setAttributes( { tourTitle: value } )
						}
						help={ __(
							'Describes the tour for screen readers.',
							'kate-toms-core'
						) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ embedUrl ? (
					<div className="kt-vr-tour__frame">
						<iframe
							src={ embedUrl }
							title={
								tourTitle || __( 'VR Tour', 'kate-toms-core' )
							}
							allow="fullscreen; web-share; xr-spatial-tracking"
							allowFullScreen
						/>
					</div>
				) : (
					<Placeholder
						icon="embed-generic"
						label={ __( 'VR Tour', 'kate-toms-core' ) }
						instructions={ __(
							'Embed a Matterport VR tour. Paste the share link, e.g. https://my.matterport.com/show/?m=7DzAJrn9axy',
							'kate-toms-core'
						) }
					>
						{ urlControl }
					</Placeholder>
				) }
			</div>
		</>
	);
}
