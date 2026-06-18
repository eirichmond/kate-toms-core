/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'kate-toms-house-seasonal-landing-pages', {
	actions: {
		/**
		 * Check if we should load more houses based on scroll position.
		 */
		checkScroll() {
			const context = getContext();
			const { ref } = getElement();

			if ( context.isLoading || ! context.hasMore ) {
				return;
			}

			// Find the sentinel within this block instance.
			const wrapper = ref.closest( '.house-seasonal-landing-pages' );
			const sentinel = wrapper?.querySelector(
				'.house-seasonal-sentinel'
			);
			if ( ! sentinel ) {
				return;
			}

			const rect = sentinel.getBoundingClientRect();
			const inViewport = rect.top <= window.innerHeight + 200;

			if ( inViewport ) {
				actions.loadMore();
			}
		},

		/**
		 * Load more houses via REST API using pre-filtered house IDs.
		 */
		async loadMore() {
			const context = getContext();
			const { ref } = getElement();

			if ( context.isLoading || ! context.hasMore ) {
				return;
			}

			context.isLoading = true;

			try {
				const nextPage = context.currentPage + 1;

				// Calculate the slice of house IDs for the next page.
				const start = context.currentPage * context.postsPerPage;
				const end = start + context.postsPerPage;
				const houseIds = context.allHouseIds.slice( start, end );

				if ( houseIds.length === 0 ) {
					context.hasMore = false;
					return;
				}

				const params = new URLSearchParams( {
					house_ids: houseIds.join( ',' ),
					pattern_style: context.patternStyle,
					beginning_date: context.beginningDate,
					ending_date: context.endingDate,
					title_bg_color: context.titleBgColor,
				} );

				const apiUrl = `/wp-json/kate-toms/v1/houses-seasonal-load?${ params.toString() }`;
				const response = await fetch( apiUrl );

				if ( ! response.ok ) {
					throw new Error( `API Error: ${ response.status }` );
				}

				const data = await response.json();

				if ( ! data.success ) {
					throw new Error( 'Invalid response from API' );
				}

				// Find the results container within this block instance.
				const wrapper = ref.closest( '.house-seasonal-landing-pages' );
				const resultsContainer = wrapper?.querySelector(
					'.house-seasonal-results'
				);

				if ( resultsContainer && data.data && data.data.html ) {
					const temp = document.createElement( 'div' );
					temp.innerHTML = data.data.html;

					while ( temp.firstChild ) {
						resultsContainer.appendChild( temp.firstChild );
					}
				}

				context.currentPage = nextPage;

				if (
					data.data.hasMore === false ||
					nextPage >= context.totalPages
				) {
					context.hasMore = false;

					if ( data.data.adverts ) {
						const advertsContainer = wrapper?.querySelector(
							'.house-seasonal-adverts'
						);
						if ( advertsContainer ) {
							// The endpoint returns up to 3 adverts, but only
							// enough to complete the final row of 4 should be
							// shown. The exact count depends on the total
							// (e.g. a remainder of 1 needs 3, a remainder of 3
							// needs 1), so trim to what's actually needed —
							// otherwise extra adverts overhang the grid.
							const remainder = context.totalHouses % 4;
							const advertsNeeded =
								remainder === 0 ? 0 : 4 - remainder;

							const temp = document.createElement( 'div' );
							temp.innerHTML = data.data.adverts;
							const advertCards = Array.from(
								temp.children
							).slice( 0, advertsNeeded );

							advertsContainer.innerHTML = '';
							advertCards.forEach( ( card ) =>
								advertsContainer.appendChild( card )
							);
						}
					}
				}
			} catch ( error ) {
				console.error( 'Error loading more seasonal houses:', error );
			} finally {
				context.isLoading = false;
			}
		},
	},

	callbacks: {
		/**
		 * Initialize the infinite scroll observer for this block instance.
		 */
		init() {
			const context = getContext();
			const { ref } = getElement();

			// The sentinel is the element with data-wp-init, so ref IS the sentinel.
			const sentinel = ref;
			if ( ! sentinel ) {
				return;
			}

			const observer = new IntersectionObserver(
				( entries ) => {
					entries.forEach( ( entry ) => {
						if (
							entry.isIntersecting &&
							! context.isLoading &&
							context.hasMore
						) {
							actions.loadMore();
						}
					} );
				},
				{
					rootMargin: '200px',
					threshold: 0,
				}
			);

			observer.observe( sentinel );

			context.observer = observer;
		},
	},
} );

// Store reference so callbacks can call actions.
const { actions } = store( 'kate-toms-house-seasonal-landing-pages' );
