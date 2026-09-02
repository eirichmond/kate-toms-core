/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { getWidgetProps, REVIEW_URL } from './widgets';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * Any change to this output must be paired with a new entry in deprecated.js,
 * or every page already carrying the block will fail validation.
 *
 * @param  root0
 * @param  root0.attributes
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @return {Element} Element to render.
 */
export default function Save( { attributes } ) {
	const { widgetType, theme } = attributes;
	const widgetProps = getWidgetProps( widgetType, theme );

	if ( ! widgetProps ) {
		return null;
	}

	return (
		<div { ...useBlockProps.save() }>
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
	);
}
