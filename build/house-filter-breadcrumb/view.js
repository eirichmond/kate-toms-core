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
  !*** ./blocks/house-filter-breadcrumb/view.js ***!
  \************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/**
 * WordPress dependencies
 */


/**
 * Extend the shared house filter store with derived state
 * for breadcrumb label display.
 */
const {
  state,
  actions
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-house-filter', {
  actions: {
    /**
     * Clear every active filter and re-run the search, returning the
     * results to their default (unfiltered) state. Triggered by the
     * breadcrumb Reset button, which is only visible once a size,
     * location or feature has been selected.
     */
    resetFilters() {
      state.activeFilters.dtype = [];
      state.activeFilters.size = [];
      state.activeFilters.local = [];
      state.activeFilters.feature = [];
      state.date = '';
      state.dtype = '';
      state.size = '';
      state.local = '';
      state.feature = '';

      // The size/location/feature <select> dropdowns aren't bound to
      // state, so reset their displayed value directly.
      document.querySelectorAll('.houses-filter__select').forEach(select => {
        select.value = '';
      });
      if (typeof actions.updateFilters === 'function') {
        actions.updateFilters();
      }
    }
  },
  state: {
    sizeLabelMap: {},
    localLabelMap: {},
    featureLabelMap: {},
    get activeSizeLabel() {
      const size = state.activeFilters?.size?.[0];
      return size ? state.sizeLabelMap[size] || `Sleeps ${size}` : '';
    },
    get activeLocalLabel() {
      const local = state.activeFilters?.local?.[0];
      return local ? state.localLabelMap[local] || '' : '';
    },
    get activeFeatureLabel() {
      const feature = state.activeFilters?.feature?.[0];
      return feature ? state.featureLabelMap[feature] || '' : '';
    },
    get hasBreadcrumbs() {
      return !!(state.activeSizeLabel || state.activeLocalLabel || state.activeFeatureLabel);
    }
  }
});
})();


//# sourceMappingURL=view.js.map