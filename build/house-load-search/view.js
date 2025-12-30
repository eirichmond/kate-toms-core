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
/*!******************************************!*\
  !*** ./blocks/house-load-search/view.js ***!
  \******************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/**
 * WordPress dependencies
 */

const {
  state,
  actions,
  callbacks
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)("kate-toms-house-load-search", {
  state: {
    isLoading: false,
    hasMore: true
  },
  actions: {
    /**
     * Check if we should load more houses based on scroll position
     */
    checkScroll() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      // Don't load if already loading or no more houses
      if (state.isLoading || !state.hasMore) {
        return;
      }

      // Find the sentinel element
      const sentinel = document.querySelector('.house-load-search-sentinel');
      if (!sentinel) {
        return;
      }

      // Check if sentinel is in viewport
      const rect = sentinel.getBoundingClientRect();
      const inViewport = rect.top <= window.innerHeight + 200; // 200px buffer

      if (inViewport) {
        actions.loadMore();
      }
    },
    /**
     * Load more houses via REST API
     */
    async loadMore() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      // Prevent multiple simultaneous loads
      if (state.isLoading || !state.hasMore) {
        return;
      }
      state.isLoading = true;
      try {
        const nextPage = context.currentPage + 1;
        const params = new URLSearchParams({
          page: nextPage,
          per_page: context.postsPerPage
        });
        const apiUrl = `/wp-json/kate-toms/v1/houses-load?${params.toString()}`;
        const response = await fetch(apiUrl);
        if (!response.ok) {
          throw new Error(`API Error: ${response.status}`);
        }
        const data = await response.json();
        if (!data.success) {
          throw new Error("Invalid response from API");
        }

        // Append new houses to the results container
        const resultsContainer = document.querySelector('.house-load-search-results');
        if (resultsContainer && data.data && data.data.html) {
          // Create a temporary container to parse the HTML
          const temp = document.createElement('div');
          temp.innerHTML = data.data.html;

          // Append each house card individually
          while (temp.firstChild) {
            resultsContainer.appendChild(temp.firstChild);
          }
        }

        // Update context state
        context.currentPage = nextPage;

        // Check if there are more houses to load
        if (data.data.hasMore === false || nextPage >= context.totalPages) {
          state.hasMore = false;
          context.hasMore = false;

          // Load adverts for final row if needed
          if (data.data.adverts) {
            const advertsContainer = document.querySelector('.house-load-search-adverts');
            if (advertsContainer) {
              advertsContainer.innerHTML = data.data.adverts;
            }
          }
        }
      } catch (error) {
        console.error('Error loading more houses:', error);
      } finally {
        state.isLoading = false;
      }
    },
    /**
     * Append houses to the results (called from custom event)
     */
    appendHouses(event) {
      const {
        html
      } = event.detail;
      const resultsContainer = event.target;
      if (resultsContainer && html) {
        resultsContainer.insertAdjacentHTML('beforeend', html);
      }
    }
  },
  callbacks: {
    /**
     * Initialize the infinite scroll observer
     */
    init() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      // Set up IntersectionObserver for smoother infinite scroll
      const sentinel = document.querySelector('.house-load-search-sentinel');
      if (!sentinel) {
        return;
      }
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting && !state.isLoading && state.hasMore) {
            actions.loadMore();
          }
        });
      }, {
        rootMargin: '200px',
        // Start loading 200px before sentinel is visible
        threshold: 0
      });
      observer.observe(sentinel);

      // Store observer reference for cleanup if needed
      context.observer = observer;
    }
  }
});
})();


//# sourceMappingURL=view.js.map