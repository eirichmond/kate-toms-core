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
import { useBlockProps, RichText, InnerBlocks } from '@wordpress/block-editor';
import { Icon } from '@wordpress/components';

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
 * @param  root0
 * @param  root0.attributes
 * @param  root0.setAttributes
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { question, isOpen } = attributes;
	const blockProps = useBlockProps( {
		className: `wp-block-kate-toms-core-kateandtoms-faqs ${
			isOpen ? 'is-open' : ''
		}`,
	} );

	const ALLOWED_BLOCKS = [
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/image',
		'core/quote',
	];
	const TEMPLATE = [
		[ 'core/paragraph', { placeholder: 'Enter your answer...' } ],
	];

	return (
		<div { ...blockProps }>
			<div
				className="faq-question"
				role="button"
				tabIndex="0"
				onClick={ () => setAttributes( { isOpen: ! isOpen } ) }
				onKeyDown={ ( e ) => {
					if ( e.target === e.currentTarget ) {
						if ( e.key === 'Enter' ) {
							e.preventDefault();
							setAttributes( { isOpen: ! isOpen } );
						}
					}
				} }
				aria-expanded={ isOpen }
			>
				<div className="faq-question-content">
					<RichText
						tagName="h3"
						value={ question }
						onChange={ ( value ) =>
							setAttributes( { question: value } )
						}
						placeholder={ __(
							'Enter your question…',
							'kateandtoms-faqs'
						) }
					/>
					<Icon
						icon={ isOpen ? 'minus' : 'plus' }
						className="faq-icon"
					/>
				</div>
			</div>
			{ isOpen && (
				<div className="faq-answer">
					<InnerBlocks
						allowedBlocks={ ALLOWED_BLOCKS }
						template={ TEMPLATE }
					/>
				</div>
			) }
		</div>
	);
}
