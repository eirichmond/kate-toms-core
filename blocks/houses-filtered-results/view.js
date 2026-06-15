/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

// Just subscribe to the store to access state
const { state } = store( 'kate-toms-house-filter' );

const REGION_SELECTOR = '.wp-block-kate-toms-core-houses-filtered-results';

/**
 * Append a chunk of server-rendered HTML to a region's houses grid.
 *
 * @param {HTMLElement} grid The .houses-grid container.
 * @param {string}      html The HTML string to append.
 */
function appendHtml( grid, html ) {
	if ( ! grid || ! html ) {
		return;
	}
	const temp = document.createElement( 'div' );
	temp.innerHTML = html;
	while ( temp.firstChild ) {
		grid.appendChild( temp.firstChild );
	}
}

/**
 * Fetch and append the next page of houses for a region.
 *
 * Pagination state lives on the region's data-* attributes so it can be shared
 * with the houses-filter block (which resets it on every filter change). The
 * context object is passed in from the calling directive so this helper never
 * relies on Interactivity scope — it is safe to call from an IntersectionObserver
 * callback, which runs outside any directive scope.
 *
 * @param {HTMLElement} region  The filtered-results block element.
 * @param {Object}      context The Interactivity context for this region.
 */
async function loadMoreFor( region, context ) {
	if ( ! region ) {
		return;
	}

	const hasMore = region.dataset.hasMore === 'true';
	if ( context.isLoadingMore || ! hasMore ) {
		return;
	}

	context.isLoadingMore = true;

	try {
		const currentPage = parseInt( region.dataset.currentPage, 10 ) || 1;
		const perPage = parseInt( region.dataset.perPage, 10 ) || 20;
		const nextPage = currentPage + 1;

		// Mirror the param building in houses-filter/view.js updateFilters so
		// the appended pages match the active filters exactly.
		const params = new URLSearchParams();
		if ( state.date ) {
			params.append( 'date', state.date );
		}
		if ( state.dtype ) {
			params.append( 'dtype', state.dtype );
		}
		if ( state.size ) {
			params.append( 'size', state.size );
		}
		if ( state.local ) {
			params.append( 'local', state.local );
		}
		if ( state.feature ) {
			params.append( 'feature', state.feature );
		}

		// This region's default location comes from its server-rendered context.
		const regionContext = JSON.parse(
			region.getAttribute( 'data-wp-context' ) || '{}'
		);
		const defaultLocation = regionContext.defaultLocation
			? regionContext.defaultLocation.toString()
			: '';
		if ( defaultLocation ) {
			params.append( 'default_location', defaultLocation );
		}

		params.append( 'page', nextPage );
		params.append( 'per_page', perPage );

		const apiUrl = `/wp-json/kate-toms/v1/houses?${ params.toString() }`;
		const response = await fetch( apiUrl );
		if ( ! response.ok ) {
			throw new Error( `API Error: ${ response.status }` );
		}

		const data = await response.json();
		if ( ! data.success ) {
			throw new Error( 'Invalid response from API' );
		}

		const housesGrid = region.querySelector( '.houses-grid' );
		appendHtml( housesGrid, data.data && data.data.html );

		region.dataset.currentPage = String( nextPage );

		if ( data.data && data.data.hasMore === false ) {
			region.dataset.hasMore = 'false';
			// Adverts only arrive once the final page is reached.
			appendHtml( housesGrid, data.data.adverts );
		}
	} catch ( error ) {
		console.error( 'Error loading more houses:', error );
	} finally {
		context.isLoadingMore = false;
	}
}

const { actions, callbacks } = store( 'kate-toms-house-filter', {
	actions: {
		/**
		 * Scroll fallback for infinite scroll. Loads the next page when the
		 * sentinel is within ~200px of the viewport. Runs in directive scope.
		 */
		checkScroll() {
			const { ref } = getElement();
			const context = getContext();
			const region = ref.closest( REGION_SELECTOR );
			if ( ! region ) {
				return;
			}

			const sentinel = region.querySelector(
				'.houses-filtered-results-sentinel'
			);
			if ( ! sentinel ) {
				return;
			}

			const rect = sentinel.getBoundingClientRect();
			if ( rect.top <= window.innerHeight + 200 ) {
				loadMoreFor( region, context );
			}
		},
	},

	callbacks: {
		/**
		 * Set up the IntersectionObserver for this region's sentinel. The
		 * element bearing data-wp-init IS the sentinel, so ref is the sentinel.
		 */
		init() {
			const context = getContext();
			const { ref } = getElement();
			const region = ref.closest( REGION_SELECTOR );
			if ( ! region ) {
				return;
			}

			const observer = new IntersectionObserver(
				( entries ) => {
					entries.forEach( ( entry ) => {
						if ( entry.isIntersecting ) {
							loadMoreFor( region, context );
						}
					} );
				},
				{
					rootMargin: '200px',
					threshold: 0,
				}
			);

			observer.observe( ref );
			context.observer = observer;
		},

		async refreshResults( event ) {
			const block = event.target.closest(
				'.wp-block-kate-toms-core-houses-filtered-results'
			);
			if ( ! block ) {
				return;
			}

			const context = JSON.parse(
				block.getAttribute( 'data-wp-context' ) || '{}'
			);
			const defaultLocation = context.defaultLocation;

			// If this block has a default location and it doesn't match the selected location,
			// skip updating it unless no location is selected
			if (
				defaultLocation &&
				state.local &&
				defaultLocation !== state.local
			) {
				return;
			}

			try {
				state.isLoading = true;

				// Build query parameters
				const params = new URLSearchParams();
				if ( state.date ) {
					params.append( 'date', state.date );
				}
				if ( state.dtype ) {
					params.append( 'dtype', state.dtype );
				}
				if ( state.size ) {
					params.append( 'size', state.size );
				}
				if ( state.local ) {
					params.append( 'local', state.local );
				}
				if ( state.feature ) {
					params.append( 'feature', state.feature );
				}
				if ( defaultLocation ) {
					params.append( 'default_location', defaultLocation );
				}

				const apiUrl = `/wp-json/kate-toms/v1/houses?${ params.toString() }`;

				const response = await fetch( apiUrl );
				if ( ! response.ok ) {
					throw new Error( `API Error: ${ response.status }` );
				}

				const data = await response.json();
				if ( ! data.success ) {
					throw new Error( 'Invalid response from API' );
				}

				// Keep the heading and only update the houses grid content
				const housesGrid = block.querySelector( '.houses-grid' );
				if ( housesGrid && data.data && data.data.html ) {
					housesGrid.innerHTML = data.data.html;
				}
			} catch ( error ) {
				console.error( 'Error refreshing results:', error );
				const housesGrid = block.querySelector( '.houses-grid' );
				if ( housesGrid ) {
					housesGrid.innerHTML = `<div class="houses-filter__error"><p>Error loading houses: ${ error.message }</p></div>`;
				}
			} finally {
				state.isLoading = false;
			}
		},
	},
} );
