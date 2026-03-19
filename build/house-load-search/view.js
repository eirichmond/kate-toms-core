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

(0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)("kate-toms-house-load-search", {
  actions: {
    /**
     * Check if we should load more houses based on scroll position.
     * Scoped to the block instance via getElement().
     */
    checkScroll() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        ref
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      if (context.isLoading || !context.hasMore) {
        return;
      }

      // Find the sentinel within this block instance.
      const wrapper = ref.closest('.house-load-search');
      const sentinel = wrapper?.querySelector('.house-load-search-sentinel');
      if (!sentinel) {
        return;
      }
      const rect = sentinel.getBoundingClientRect();
      const inViewport = rect.top <= window.innerHeight + 200;
      if (inViewport) {
        actions.loadMore();
      }
    },
    /**
     * Load more houses via REST API.
     * Each block instance manages its own pagination via context.
     */
    async loadMore() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        ref
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      if (context.isLoading || !context.hasMore) {
        return;
      }
      context.isLoading = true;
      try {
        const nextPage = context.currentPage + 1;
        const params = new URLSearchParams({
          page: nextPage,
          per_page: context.postsPerPage
        });
        if (context.locationTermIds && context.locationTermIds.length > 0) {
          params.set('locations', context.locationTermIds.join(','));
        }
        if (context.featureTermIds && context.featureTermIds.length > 0) {
          params.set('features', context.featureTermIds.join(','));
        }
        if (context.sizeTermIds && context.sizeTermIds.length > 0) {
          params.set('sizes', context.sizeTermIds.join(','));
        }
        if (context.typeTermIds && context.typeTermIds.length > 0) {
          params.set('types', context.typeTermIds.join(','));
        }
        if (context.occasionTermIds && context.occasionTermIds.length > 0) {
          params.set('occasions', context.occasionTermIds.join(','));
        }
        const apiUrl = `/wp-json/kate-toms/v1/houses-load?${params.toString()}`;
        const response = await fetch(apiUrl);
        if (!response.ok) {
          throw new Error(`API Error: ${response.status}`);
        }
        const data = await response.json();
        if (!data.success) {
          throw new Error("Invalid response from API");
        }

        // Find the results container within this block instance.
        const wrapper = ref.closest('.house-load-search');
        const resultsContainer = wrapper?.querySelector('.house-load-search-results');
        if (resultsContainer && data.data && data.data.html) {
          const temp = document.createElement('div');
          temp.innerHTML = data.data.html;
          while (temp.firstChild) {
            resultsContainer.appendChild(temp.firstChild);
          }
        }
        context.currentPage = nextPage;
        if (data.data.hasMore === false || nextPage >= context.totalPages) {
          context.hasMore = false;
          if (data.data.adverts) {
            const advertsContainer = wrapper?.querySelector('.house-load-search-adverts');
            if (advertsContainer) {
              advertsContainer.innerHTML = data.data.adverts;
            }
          }
        }
      } catch (error) {
        console.error('Error loading more houses:', error);
      } finally {
        context.isLoading = false;
      }
    }
  },
  callbacks: {
    /**
     * Initialize the infinite scroll observer for this block instance.
     */
    init() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        ref
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();

      // The sentinel is the element with data-wp-init, so ref IS the sentinel.
      const sentinel = ref;
      if (!sentinel) {
        return;
      }
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting && !context.isLoading && context.hasMore) {
            actions.loadMore();
          }
        });
      }, {
        rootMargin: '200px',
        threshold: 0
      });
      observer.observe(sentinel);
      context.observer = observer;
    }
  }
});

// Store reference so callbacks can call actions.
const {
  actions
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)("kate-toms-house-load-search");
})();


//# sourceMappingURL=view.js.map