/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @return {Element} Element to render.
 */
export default function Save({ attributes }) {
	const { widgetType } = attributes;

	if (widgetType === 'micro-combo') {
		return (
			<div { ...useBlockProps.save() }>
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
		);
	}

	if (widgetType === 'micro-star') {
		return (
			<div {...useBlockProps.save() }>
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
		);
	}

	return null;
}
