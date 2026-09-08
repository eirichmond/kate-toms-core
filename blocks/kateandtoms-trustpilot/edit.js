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
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * Internal dependencies
 */
import { getWidgetProps, REVIEW_URL, WIDGETS } from './widgets';

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
	const { widgetType, theme, alignment, height } = attributes;
	const blockProps = useBlockProps();
	const widgetProps = getWidgetProps( widgetType, theme, alignment, height );
	const defaultHeight = WIDGETS[ widgetType ]?.height ?? '';

	useEffect( () => {
		if ( ! window.trustpilotScriptLoaded ) {
			const script = document.createElement( 'script' );
			script.src =
				'//widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js';
			script.async = true;
			document.head.appendChild( script );
			window.trustpilotScriptLoaded = true;
		}
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Trustpilot Widget Settings',
						'kateandtoms-trustpilot'
					) }
				>
					<SelectControl
						label={ __( 'Widget Type', 'kateandtoms-trustpilot' ) }
						value={ widgetType }
						options={ [
							{
								label: __(
									'Micro Combo',
									'kateandtoms-trustpilot'
								),
								value: 'micro-combo',
							},
							{
								label: __(
									'Micro Star',
									'kateandtoms-trustpilot'
								),
								value: 'micro-star',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { widgetType: value } )
						}
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Theme', 'kateandtoms-trustpilot' ) }
						value={ theme }
						options={ [
							{
								label: __( 'Light', 'kateandtoms-trustpilot' ),
								value: 'light',
							},
							{
								label: __( 'Dark', 'kateandtoms-trustpilot' ),
								value: 'dark',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { theme: value } )
						}
						help={ __(
							'Use Dark on dark backgrounds, such as the footer.',
							'kateandtoms-trustpilot'
						) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Alignment', 'kateandtoms-trustpilot' ) }
						value={ alignment }
						options={ [
							{
								label: __( 'Centre', 'kateandtoms-trustpilot' ),
								value: 'center',
							},
							{
								label: __( 'Left', 'kateandtoms-trustpilot' ),
								value: 'left',
							},
							{
								label: __( 'Right', 'kateandtoms-trustpilot' ),
								value: 'right',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { alignment: value } )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Height', 'kateandtoms-trustpilot' ) }
						value={ height }
						onChange={ ( value ) =>
							setAttributes( { height: value } )
						}
						placeholder={ defaultHeight }
						help={ __(
							'The widget is clipped to this height. Raise it if the review count wraps onto a second line in a narrow column.',
							'kateandtoms-trustpilot'
						) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			{ widgetProps && (
				<div { ...blockProps }>
					{ /* TrustBox widget */ }
					<div { ...widgetProps }>
						<a
							href={ REVIEW_URL }
							target="_blank"
							rel="noopener noreferrer"
						>
							Trustpilot
						</a>
					</div>
					{ /* End TrustBox widget */ }
				</div>
			) }
		</>
	);
}
