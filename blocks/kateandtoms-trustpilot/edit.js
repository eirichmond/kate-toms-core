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
import { useEffect } from '@wordpress/element';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const { widgetType } = attributes;
	useEffect(() => {
		if (!window.trustpilotScriptLoaded) {
			const script = document.createElement('script');
			script.src = '//widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js';
			script.async = true;
			document.head.appendChild(script);
			window.trustpilotScriptLoaded = true;
		}
	}, []);

	return (
		<>
			<InspectorControls>
				<PanelBody title='Trustpilot Widget Settings'>
					<SelectControl
						label='Widget Type'
						value={widgetType}
						options={[
							{ label: "Micro Combo", value: "micro-combo" },
							{ label: "Micro Star", value: "micro-star" },
						]}
						onChange={value => setAttributes({ widgetType: value })}
					/>
				</PanelBody>
			</InspectorControls>
			{widgetType === "micro-combo" && (
				<div {...useBlockProps()}>
					{/* TrustBox widget - Micro Combo */}
					<div
						className='trustpilot-widget'
						data-locale='en-GB'
						data-template-id='5419b6ffb0d04a076446a9af'
						data-businessunit-id='5cd41de1c4dd7a0001be3a14'
						data-style-height='20px'
						data-style-width='100%'>
						<a
							href='https://uk.trustpilot.com/review/www.kateandtoms.com'
							target='_blank'
							rel='noopener'>
							Trustpilot
						</a>
					</div>
					{/* End TrustBox widget */}
				</div>
			)}
			{widgetType === "micro-star" && (
				<div {...useBlockProps()}>
					{/* TrustBox widget - Micro Star */}
					<div
						className='trustpilot-widget'
						data-locale='en-GB'
						data-template-id='5419b732fbfb950b10de65e5'
						data-businessunit-id='5cd41de1c4dd7a0001be3a14'
						data-style-height='24px'
						data-style-width='100%'
						data-theme='dark'>
						<a
							href='https://uk.trustpilot.com/review/www.kateandtoms.com'
							target='_blank'
							rel='noopener'>
							Trustpilot
						</a>
					</div>
					{/* End TrustBox widget */}
				</div>
			)}
		</>
	);
}
