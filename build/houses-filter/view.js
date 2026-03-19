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
/*!**************************************!*\
  !*** ./blocks/houses-filter/view.js ***!
  \**************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/**
 * WordPress dependencies
 */


/**
 * Store configuration for the houses filter block.
 */
const storeName = 'kate-toms-house-filter';

// Get the region ID from the form's data-wp-context
const form = document.querySelector('.houses-filter');
const context = form ? JSON.parse(form.getAttribute('data-wp-context') || '{}') : {};
const initialRegionId = context.regionId || '';
const {
  state,
  actions
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)(storeName, {
  state: {
    isLoading: false,
    date: '',
    dtype: '',
    size: '',
    local: '',
    feature: '',
    results: 0,
    // Track active states for each filter section
    activeFilters: {
      dtype: [],
      size: [],
      local: [],
      feature: []
    },
    // Map for size values
    sizeMap: {
      '2-10': '2',
      '10-20': '10',
      '20+': '20'
    },
    get hasResults() {
      return state.results > 0;
    },
    get isFilterPressed() {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const filterType = ctx.filterType;
      const filterValue = ctx.filterValue;
      if (!filterType || !filterValue) {
        return false;
      }
      const active = state.activeFilters[filterType];
      return Array.isArray(active) && active.includes(filterValue);
    }
  },
  actions: {
    updateDate(event) {
      state.date = event.target.value;

      // Default to Weekend (dtype '1') whenever a new date is set.
      if (state.date) {
        state.activeFilters.dtype = ['1'];
        state.dtype = '1';
      }
      actions.updateFilters();
    },
    updateDtype() {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const value = ctx.filterValue;

      // Toggle the value in activeFilters
      if (state.activeFilters.dtype.includes(value)) {
        state.activeFilters.dtype = state.activeFilters.dtype.filter(v => v !== value);
        state.dtype = '';
      } else {
        state.activeFilters.dtype = [value]; // Single selection
        state.dtype = value;
      }
      actions.updateFilters();
    },
    updateSize(event) {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const value = ctx.filterValue || event.target.value;

      // Toggle the value in activeFilters
      if (state.activeFilters.size.includes(value)) {
        state.activeFilters.size = state.activeFilters.size.filter(v => v !== value);
        state.size = '';
      } else {
        state.activeFilters.size = [value]; // Single selection
        state.size = state.sizeMap[value] || value;
      }
      actions.updateFilters();
    },
    updateLocation(event) {
      state.isLoading = true;
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const value = ctx.filterValue || event.target.value;

      // Toggle the value in activeFilters
      if (state.activeFilters.local.includes(value)) {
        state.activeFilters.local = state.activeFilters.local.filter(v => v !== value);
        state.local = '';
      } else {
        state.activeFilters.local = [value]; // Single selection
        state.local = value;
      }
      actions.updateFilters();
    },
    updateFeature(event) {
      const ctx = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const value = ctx.filterValue || event.target.value;

      // Toggle the value in activeFilters
      if (state.activeFilters.feature.includes(value)) {
        state.activeFilters.feature = state.activeFilters.feature.filter(v => v !== value);
        state.feature = '';
      } else {
        state.activeFilters.feature = [value]; // Single selection
        state.feature = value;
      }
      actions.updateFilters();
    },
    async updateFilters() {
      try {
        state.isLoading = true;

        // Build query parameters
        const params = new URLSearchParams();
        if (state.date) params.append("date", state.date);
        if (state.dtype) params.append("dtype", state.dtype);
        if (state.size) params.append("size", state.size);
        if (state.local) params.append("local", state.local);
        if (state.feature) params.append("feature", state.feature);

        // Get all houses regions and their default locations
        const housesRegions = document.querySelectorAll('.wp-block-kate-toms-core-houses-filtered-results');
        let totalResults = 0;

        // Create an array of promises for all fetch operations
        const fetchPromises = Array.from(housesRegions).map(async region => {
          const context = JSON.parse(region.getAttribute('data-wp-context') || '{}');
          const defaultLocation = context.defaultLocation ? context.defaultLocation.toString() : '';

          // Create region-specific params
          const regionParams = new URLSearchParams(params);
          if (defaultLocation) {
            regionParams.append('default_location', defaultLocation);
          }
          const apiUrl = `/wp-json/kate-toms/v1/houses?${regionParams.toString()}`;
          try {
            const response = await fetch(apiUrl);
            const data = await response.json();
            if (data.success) {
              const housesGrid = region.querySelector('.houses-grid');
              if (housesGrid && data.data && data.data.html) {
                housesGrid.innerHTML = data.data.html;
                const total = data.data.total || 0;

                // Find the parent block element and all its .wp-block-group ancestors
                let currentElement = region;
                while (currentElement) {
                  // If this is a .wp-block-group or our results block, toggle visibility
                  if (currentElement.classList.contains('wp-block-group') || currentElement.classList.contains('wp-block-kate-toms-core-houses-filtered-results')) {
                    currentElement.style.display = total === 0 ? 'none' : '';
                  }
                  // Move up to the next parent
                  currentElement = currentElement.parentElement;
                }
                return total;
              }
            }
            return 0;
          } catch (error) {
            console.error('Error updating region:', error);
            const housesGrid = region.querySelector('.houses-grid');
            if (housesGrid) {
              housesGrid.innerHTML = `<div class="houses-filter__error"><p>Error loading houses: ${error.message}</p></div>`;
            }
            return 0;
          }
        });

        // Wait for all fetch operations to complete
        const results = await Promise.all(fetchPromises);
        state.results = results.reduce((sum, count) => sum + count, 0);
      } catch (error) {
        console.error('Error updating filters:', error);
        // Show user-friendly error message in all regions
        const housesRegions = document.querySelectorAll('.wp-block-kate-toms-core-houses-filtered-results .houses-grid');
        housesRegions.forEach(region => {
          region.innerHTML = `<div class="houses-filter__error"><p>Error loading houses: ${error.message}</p></div>`;
        });
        state.results = 0;
      } finally {
        state.isLoading = false;
      }
    }
  }
});
})();


//# sourceMappingURL=view.js.map