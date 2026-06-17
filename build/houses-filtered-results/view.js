import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

/***/ "@wordpress/interactivity":
/*!*******************************************!*\
  !*** external "@wordpress/interactivity" ***!
  \*******************************************/
/***/ ((module) => {

module.exports = __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__;

/***/ })

/******/ });
/************************************************************************/
/******/ // The module cache
/******/ var __webpack_module_cache__ = {};
/******/ 
/******/ // The require function
/******/ function __webpack_require__(moduleId) {
/******/ 	// Check if module is in cache
/******/ 	var cachedModule = __webpack_module_cache__[moduleId];
/******/ 	if (cachedModule !== undefined) {
/******/ 		return cachedModule.exports;
/******/ 	}
/******/ 	// Create a new module (and put it into the cache)
/******/ 	var module = __webpack_module_cache__[moduleId] = {
/******/ 		// no module.id needed
/******/ 		// no module.loaded needed
/******/ 		exports: {}
/******/ 	};
/******/ 
/******/ 	// Execute the module function
/******/ 	__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 
/******/ 	// Return the exports of the module
/******/ 	return module.exports;
/******/ }
/******/ 
/************************************************************************/
/******/ /* webpack/runtime/make namespace object */
/******/ (() => {
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = (exports) => {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/ })();
/******/ 
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!************************************************!*\
  !*** ./blocks/houses-filtered-results/view.js ***!
  \************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/**
 * WordPress dependencies
 */


// Just subscribe to the store to access state
const {
  state
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-house-filter');
const REGION_SELECTOR = '.wp-block-kate-toms-core-houses-filtered-results';

/**
 * Append a chunk of server-rendered HTML to a region's houses grid.
 *
 * @param {HTMLElement} grid The .houses-grid container.
 * @param {string}      html The HTML string to append.
 */
function appendHtml(grid, html) {
  if (!grid || !html) {
    return;
  }
  const temp = document.createElement('div');
  temp.innerHTML = html;
  while (temp.firstChild) {
    grid.appendChild(temp.firstChild);
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
async function loadMoreFor(region, context) {
  if (!region) {
    return;
  }
  const hasMore = region.dataset.hasMore === 'true';
  if (context.isLoadingMore || !hasMore) {
    return;
  }
  context.isLoadingMore = true;
  try {
    const currentPage = parseInt(region.dataset.currentPage, 10) || 1;
    const perPage = parseInt(region.dataset.perPage, 10) || 20;
    const nextPage = currentPage + 1;

    // Mirror the param building in houses-filter/view.js updateFilters so
    // the appended pages match the active filters exactly.
    const params = new URLSearchParams();
    if (state.date) {
      params.append('date', state.date);
    }
    if (state.dtype) {
      params.append('dtype', state.dtype);
    }
    if (state.size) {
      params.append('size', state.size);
    }
    if (state.local) {
      params.append('local', state.local);
    }
    if (state.feature) {
      params.append('feature', state.feature);
    }

    // This region's default location comes from its server-rendered context.
    const regionContext = JSON.parse(region.getAttribute('data-wp-context') || '{}');
    const defaultLocation = regionContext.defaultLocation ? regionContext.defaultLocation.toString() : '';
    if (defaultLocation) {
      params.append('default_location', defaultLocation);
    }
    params.append('page', nextPage);
    params.append('per_page', perPage);
    const apiUrl = `/wp-json/kate-toms/v1/houses?${params.toString()}`;
    const response = await fetch(apiUrl);
    if (!response.ok) {
      throw new Error(`API Error: ${response.status}`);
    }
    const data = await response.json();
    if (!data.success) {
      throw new Error('Invalid response from API');
    }
    const housesGrid = region.querySelector('.houses-grid');
    appendHtml(housesGrid, data.data && data.data.html);
    region.dataset.currentPage = String(nextPage);
    if (data.data && data.data.hasMore === false) {
      region.dataset.hasMore = 'false';
      // Adverts only arrive once the final page is reached.
      appendHtml(housesGrid, data.data.adverts);
    }
  } catch (error) {
    console.error('Error loading more houses:', error);
  } finally {
    context.isLoadingMore = false;
  }
}
const {
  actions,
  callbacks
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-house-filter', {
  actions: {
    /**
     * Scroll fallback for infinite scroll. Loads the next page when the
     * sentinel is within ~200px of the viewport. Runs in directive scope.
     */
    checkScroll() {
      const {
        ref
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const region = ref.closest(REGION_SELECTOR);
      if (!region) {
        return;
      }
      const sentinel = region.querySelector('.houses-filtered-results-sentinel');
      if (!sentinel) {
        return;
      }
      const rect = sentinel.getBoundingClientRect();
      if (rect.top <= window.innerHeight + 200) {
        loadMoreFor(region, context);
      }
    }
  },
  callbacks: {
    /**
     * Set up the IntersectionObserver for this region's sentinel. The
     * element bearing data-wp-init IS the sentinel, so ref is the sentinel.
     */
    init() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        ref
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const region = ref.closest(REGION_SELECTOR);
      if (!region) {
        return;
      }
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            loadMoreFor(region, context);
          }
        });
      }, {
        rootMargin: '200px',
        threshold: 0
      });
      observer.observe(ref);
      context.observer = observer;
    },
    async refreshResults(event) {
      const block = event.target.closest('.wp-block-kate-toms-core-houses-filtered-results');
      if (!block) {
        return;
      }
      const context = JSON.parse(block.getAttribute('data-wp-context') || '{}');
      const defaultLocation = context.defaultLocation;

      // If this block has a default location and it doesn't match the selected location,
      // skip updating it unless no location is selected
      if (defaultLocation && state.local && defaultLocation !== state.local) {
        return;
      }
      try {
        state.isLoading = true;

        // Build query parameters
        const params = new URLSearchParams();
        if (state.date) {
          params.append('date', state.date);
        }
        if (state.dtype) {
          params.append('dtype', state.dtype);
        }
        if (state.size) {
          params.append('size', state.size);
        }
        if (state.local) {
          params.append('local', state.local);
        }
        if (state.feature) {
          params.append('feature', state.feature);
        }
        if (defaultLocation) {
          params.append('default_location', defaultLocation);
        }
        const apiUrl = `/wp-json/kate-toms/v1/houses?${params.toString()}`;
        const response = await fetch(apiUrl);
        if (!response.ok) {
          throw new Error(`API Error: ${response.status}`);
        }
        const data = await response.json();
        if (!data.success) {
          throw new Error('Invalid response from API');
        }

        // Keep the heading and only update the houses grid content
        const housesGrid = block.querySelector('.houses-grid');
        if (housesGrid && data.data && data.data.html) {
          housesGrid.innerHTML = data.data.html;
        }
      } catch (error) {
        console.error('Error refreshing results:', error);
        const housesGrid = block.querySelector('.houses-grid');
        if (housesGrid) {
          housesGrid.innerHTML = `<div class="houses-filter__error"><p>Error loading houses: ${error.message}</p></div>`;
        }
      } finally {
        state.isLoading = false;
      }
    }
  }
});
})();


//# sourceMappingURL=view.js.map