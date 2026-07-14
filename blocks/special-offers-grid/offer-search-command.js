/**
 * Command palette search for special offers.
 *
 * The offers page carries hundreds of offer blocks, and staff need to find one
 * by house name. Browser find-in-page cannot be relied on for that: the block
 * editor renders its canvas in an iframe, so the house names sit behind a
 * frame boundary that Cmd+F does not search consistently.
 *
 * This sidesteps the problem rather than fighting it. The house names are read
 * from the editor's own data store — no DOM, no iframe — and surfaced through
 * the command palette (Cmd+K), which selects and scrolls to the matching offer.
 *
 * @see BugHerd #431
 */
import { useCommandLoader } from '@wordpress/commands';
import { registerPlugin } from '@wordpress/plugins';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as coreStore } from '@wordpress/core-data';
import { home } from '@wordpress/icons';

/**
 * The offer child block whose house names we index.
 *
 * @type {string}
 */
const OFFER_BLOCK = 'kate-toms-core/kateandtoms-special-offer-house';

/**
 * Most matches to offer at once.
 *
 * The palette is a shortlist, not a browser. A house with a dozen offers would
 * otherwise bury every other match.
 *
 * @type {number}
 */
const MAX_RESULTS = 20;

/**
 * Collect every offer block in the post, at any nesting depth.
 *
 * @param {Array} blocks Blocks to walk.
 *
 * @return {Array} The offer child blocks.
 */
function collectOfferBlocks( blocks ) {
	const found = [];

	for ( const block of blocks ) {
		if ( block.name === OFFER_BLOCK ) {
			found.push( block );
		}

		if ( block.innerBlocks?.length ) {
			found.push( ...collectOfferBlocks( block.innerBlocks ) );
		}
	}

	return found;
}

/**
 * Bring a block into view inside the editor canvas.
 *
 * Selecting a block does not always scroll to it when the canvas is iframed, so
 * the block is located in whichever document actually holds the canvas.
 *
 * @param {string} clientId Block client ID.
 */
function scrollToBlock( clientId ) {
	// Defer: the block has to be selected (and rendered) before it can be found.
	window.requestAnimationFrame( () => {
		const canvas = document.querySelector( 'iframe[name="editor-canvas"]' );
		const doc = canvas?.contentDocument || document;
		const node = doc.querySelector( `[data-block="${ clientId }"]` );

		node?.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	} );
}

/**
 * Command loader offering one command per matching special offer.
 *
 * @param {Object} props        Loader props.
 * @param {string} props.search Current palette search term.
 *
 * @return {Object} Commands for the palette.
 */
function useSpecialOfferCommands( { search } ) {
	const { selectBlock } = useDispatch( blockEditorStore );

	const offers = useSelect( ( select ) => {
		const { getBlocks } = select( blockEditorStore );
		const { getEntityRecord } = select( coreStore );

		return collectOfferBlocks( getBlocks() )
			.filter( ( block ) => block.attributes?.selectedPostId )
			.map( ( block ) => {
				const { selectedPostId, offer, offerDate } = block.attributes;

				// Already in the store: every offer block resolves its own house.
				const house = getEntityRecord(
					'postType',
					'houses',
					selectedPostId
				);

				return {
					clientId: block.clientId,
					title: house?.title?.rendered || '',
					offer: offer || '',
					offerDate: offerDate ? offerDate.slice( 0, 10 ) : '',
				};
			} )
			.filter( ( item ) => item.title );
	}, [] );

	const term = ( search || '' ).trim().toLowerCase();

	const matches = (
		term
			? offers.filter( ( item ) =>
					item.title.toLowerCase().includes( term )
			  )
			: offers
	).slice( 0, MAX_RESULTS );

	return {
		isLoading: false,
		commands: matches.map( ( item, index ) => ( {
			// A house can hold several offers, so the client ID is what keeps
			// the command names unique.
			name: `kate-toms/special-offer/${ item.clientId }`,
			label: [ item.title, item.offer, item.offerDate ]
				.filter( Boolean )
				.join( ' · ' ),
			searchLabel: `${ item.title } ${ item.offer }`,
			icon: home,
			callback: ( { close } ) => {
				selectBlock( item.clientId );
				scrollToBlock( item.clientId );
				close();
			},
			// Keep the palette's own ordering stable across renders.
			context: String( index ),
		} ) ),
	};
}

/**
 * Registers the loader with the command palette.
 *
 * @return {null} Renders nothing.
 */
function SpecialOfferCommands() {
	useCommandLoader( {
		name: 'kate-toms-core/special-offers',
		hook: useSpecialOfferCommands,
	} );

	return null;
}

registerPlugin( 'kate-toms-core-special-offer-search', {
	render: SpecialOfferCommands,
} );

export default SpecialOfferCommands;
