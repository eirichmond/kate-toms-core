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
/*!********************************************!*\
  !*** ./blocks/autocomplete-search/view.js ***!
  \********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/**
 * WordPress dependencies
 */

let searchTimeout = null;
let allResults = [];
let searchCache = new Map(); // Cache for search results

const {
  state
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-core/autocomplete-search', {
  state: {},
  actions: {
    init() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      // Fetch all search items on initialization
      const {
        actions
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-core/autocomplete-search');
      actions.fetchSearchItems();
    },
    async fetchSearchItems() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      try {
        const response = await fetch('/wp-json/kate-toms/v1/autocomplete-search');
        if (response.ok) {
          allResults = await response.json();
        }
      } catch (error) {
        console.error('Failed to fetch search items:', error);
      }
    },
    handleInput(event) {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const searchTerm = event.target.value;
      context.searchTerm = searchTerm;
      context.selectedIndex = -1;

      // Clear previous timeout
      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }
      if (searchTerm.trim().length === 0) {
        context.results = [];
        context.groupedResults = [];
        context.isOpen = false;
        return;
      }

      // Show loading state
      context.isLoading = true;

      // Reduced debounce time for better responsiveness
      const {
        actions
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-core/autocomplete-search');
      searchTimeout = setTimeout((0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.withScope)(() => {
        actions.performSearch(searchTerm);
      }), 150);
    },
    handleFocus() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const searchTerm = context.searchTerm.trim();
      if (searchTerm.length > 0) {
        if (context.results.length > 0) {
          // Show existing results immediately
          context.isOpen = true;
        } else {
          // Perform search immediately for responsive UX
          const {
            actions
          } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-core/autocomplete-search');
          actions.performSearch(searchTerm);
        }
      }
    },
    handleBlur(event) {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      // Delay hiding results to allow clicks on results
      setTimeout((0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.withScope)(() => {
        // Check if the new focus target is within the search results
        const resultsContainer = event.target.closest('.autocomplete-search').querySelector('.autocomplete-search__results');
        if (!resultsContainer.contains(document.activeElement)) {
          const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
          context.isOpen = false;
        }
      }), 150);
    },
    handleKeyDown(event) {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      if (!context.isOpen || context.results.length === 0) {
        return;
      }
      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault();
          context.selectedIndex = Math.min(context.selectedIndex + 1, context.results.length - 1);
          break;
        case 'ArrowUp':
          event.preventDefault();
          context.selectedIndex = Math.max(context.selectedIndex - 1, -1);
          break;
        case 'Enter':
          event.preventDefault();
          if (context.selectedIndex >= 0 && context.results[context.selectedIndex]) {
            const {
              actions
            } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-core/autocomplete-search');
            actions.navigateToResult(context.results[context.selectedIndex]);
          }
          break;
        case 'Escape':
          context.isOpen = false;
          context.selectedIndex = -1;
          event.target.blur();
          break;
      }
    },
    selectResult(event) {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const resultIndex = context.results.findIndex(result => result === context.result);
      if (resultIndex >= 0) {
        const {
          actions
        } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-core/autocomplete-search');
        actions.navigateToResult(context.results[resultIndex]);
      }
    },
    highlightResult(event) {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const resultIndex = context.results.findIndex(result => result === context.result);
      if (resultIndex >= 0) {
        context.selectedIndex = resultIndex;
      }
    },
    navigateToResult(result) {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      if (result && result.url) {
        context.isOpen = false;
        window.location.href = result.url;
      }
    },
    performSearch(searchTerm) {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      // Clear loading state
      context.isLoading = false;
      if (!searchTerm.trim() || allResults.length === 0) {
        context.results = [];
        context.groupedResults = [];
        context.isOpen = false;
        return;
      }
      const term = searchTerm.toLowerCase().trim();
      const cacheKey = `${term}_${context.maxResults}`;

      // Check cache first
      if (searchCache.has(cacheKey)) {
        const cached = searchCache.get(cacheKey);
        context.results = cached.results;
        context.groupedResults = cached.groupedResults;
        context.isOpen = cached.groupedResults.length > 0;
        return;
      }

      // Category display order: Locations first, then Features, then Houses
      const categoryOrder = {
        'Locations': 0,
        'Features': 1,
        'Houses': 2
      };

      // Match on name/label only (not description)
      const scoredResults = [];
      for (const item of allResults) {
        let score = 0;
        const labelLower = item.label.toLowerCase();
        if (labelLower.startsWith(term)) {
          score = 100;
        } else if (labelLower.includes(term)) {
          score = 80;
        }
        if (score > 0) {
          scoredResults.push({
            ...item,
            score
          });
        }
      }

      // Sort by category order first, then by score within each category
      const sortedResults = scoredResults.sort((a, b) => {
        var _categoryOrder$a$cate, _categoryOrder$b$cate;
        const catDiff = ((_categoryOrder$a$cate = categoryOrder[a.category]) !== null && _categoryOrder$a$cate !== void 0 ? _categoryOrder$a$cate : 99) - ((_categoryOrder$b$cate = categoryOrder[b.category]) !== null && _categoryOrder$b$cate !== void 0 ? _categoryOrder$b$cate : 99);
        if (catDiff !== 0) return catDiff;
        return b.score - a.score;
      }).slice(0, context.maxResults);

      // Group results by category in display order
      const grouped = new Map();
      for (const item of sortedResults) {
        if (!grouped.has(item.category)) {
          grouped.set(item.category, []);
        }
        grouped.get(item.category).push(item);
      }

      // Convert to array format for template
      const groupedArray = Array.from(grouped.entries()).map(([category, results]) => ({
        category,
        results
      }));

      // Cache results (limit cache size to prevent memory issues)
      if (searchCache.size > 50) {
        const firstKey = searchCache.keys().next().value;
        searchCache.delete(firstKey);
      }
      searchCache.set(cacheKey, {
        results: sortedResults,
        groupedResults: groupedArray
      });
      context.results = sortedResults; // Keep flat array for keyboard navigation
      context.groupedResults = groupedArray; // Array of category groups for display
      context.isOpen = groupedArray.length > 0;
    }
  },
  callbacks: {
    isSelected() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const resultIndex = context.results.findIndex(result => result === context.result);
      return resultIndex === context.selectedIndex;
    }
  }
});
})();


//# sourceMappingURL=view.js.map