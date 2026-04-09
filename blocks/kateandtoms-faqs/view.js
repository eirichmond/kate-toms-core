/**
 * Use this file for JavaScript code that you want to run in the front-end
 * on posts/pages that contain this block.
 *
 * When this file is defined as the value of the `viewScript` property
 * in `block.json` it will be enqueued on the front end of the site.
 *
 * Example:
 *
 * ```js
 * {
 *   "viewScript": "file:./view.js"
 * }
 * ```
 *
 * If you're not making any changes to this file because your project doesn't need any
 * JavaScript running in the front-end, then you should delete this file and remove
 * the `viewScript` property from `block.json`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

/* eslint-disable no-console */
console.log( 'Hello World! (from kate-toms-core-kateandtoms-faqs block)' );
/* eslint-enable no-console */

document.addEventListener( 'DOMContentLoaded', function () {
	const faqBlocks = document.querySelectorAll(
		'.wp-block-kate-toms-core-kateandtoms-faqs'
	);

	faqBlocks.forEach( ( block ) => {
		const question = block.querySelector( '.faq-question' );
		const answer = block.querySelector( '.faq-answer' );
		const icon = block.querySelector( '.faq-icon' );

		if ( question && answer && icon ) {
			question.addEventListener( 'click', () => {
				const isOpen = block.classList.contains( 'is-open' );

				// Toggle the open state
				block.classList.toggle( 'is-open' );

				// Update ARIA attributes
				question.setAttribute( 'aria-expanded', ! isOpen );

				// Update icon
				icon.textContent = isOpen ? '+' : '−';
			} );

			// Handle keyboard navigation
			question.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					question.click();
				}
			} );
		}
	} );
} );
