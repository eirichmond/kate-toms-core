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
    }
  },
  actions: {
    updateDate(event) {
      state.date = event.target.value;
      actions.updateFilters();
    },
    updateDtype(event) {
      const value = event.target.dataset.value;

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
      const value = event.target.dataset.value || event.target.value;

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
      const value = event.target.dataset.value || event.target.value;

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
      const value = event.target.dataset.value || event.target.value;

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
        const apiUrl = `/wp-json/kate-toms/v1/houses?${params.toString()}`;
        console.log("Fetching:", apiUrl);

        // Fetch filtered results using REST API
        const fetchResponse = await fetch(apiUrl);
        console.log("Raw Response:", fetchResponse);
        if (!fetchResponse.ok) {
          throw new Error(`API Error: ${fetchResponse.status} ${fetchResponse.statusText}`);
        }
        const jsonResponse = await fetchResponse.json();
        console.log("JSON Response:", jsonResponse);
        if (!jsonResponse || !jsonResponse.success) {
          throw new Error("Invalid response from API");
        }

        // Update all houses regions with new results
        const housesRegions = document.querySelectorAll('.wp-block-kate-toms-core-houses-filtered-results');
        let totalResults = 0;
        housesRegions.forEach(region => {
          const context = JSON.parse(region.getAttribute('data-wp-context') || '{}');
          const defaultLocation = context.defaultLocation ? context.defaultLocation.toString() : '';

          // Add default location to the API request
          const regionParams = new URLSearchParams(params);
          if (defaultLocation) {
            regionParams.append('default_location', defaultLocation);
          }

          // Make a separate request for this region with its default location
          fetch(`/wp-json/kate-toms/v1/houses?${regionParams.toString()}`).then(response => response.json()).then(data => {
            if (data.success) {
              const housesGrid = region.querySelector('.houses-grid');
              if (housesGrid) {
                if (data.data && data.data.html) {
                  housesGrid.innerHTML = data.data.html;
                  totalResults += data.data.total || 0;
                  state.results = totalResults;
                }
              }
            }
          }).catch(error => {
            console.error('Error updating region:', error);
            const housesGrid = region.querySelector('.houses-grid');
            if (housesGrid) {
              housesGrid.innerHTML = `<div class="houses-filter__error"><p>Error loading houses: ${error.message}</p></div>`;
            }
          });
        });
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