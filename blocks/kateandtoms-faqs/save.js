/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, RichText, InnerBlocks } from '@wordpress/block-editor';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @param  root0
 * @param  root0.attributes
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @return {Element} Element to render.
 */
export default function save( { attributes } ) {
	const { question, isOpen } = attributes;
	const blockProps = useBlockProps.save( {
		className: `wp-block-kate-toms-core-kateandtoms-faqs ${
			isOpen ? 'is-open' : ''
		}`,
	} );

	return (
		<div { ...blockProps }>
			<div
				className="faq-question"
				role="button"
				tabIndex="0"
				aria-expanded={ isOpen }
			>
				<div className="faq-question-content">
					<RichText.Content tagName="h3" value={ question } />
					<span className="faq-icon">{ isOpen ? '−' : '+' }</span>
				</div>
			</div>
			<div className="faq-answer">
				<InnerBlocks.Content />
			</div>
		</div>
	);
}
