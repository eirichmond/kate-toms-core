/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "@wordpress/interactivity":
/*!***************************************!*\
  !*** external ["wp","interactivity"] ***!
  \***************************************/
/***/ ((module) => {

module.exports = window["wp"]["interactivity"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
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
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies
 */


// Just subscribe to the store to access state
const {
  state
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-house-filter');
const {
  actions,
  callbacks
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('kate-toms-house-filter', {
  callbacks: {
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

/******/ })()
;
//# sourceMappingURL=view.js.map