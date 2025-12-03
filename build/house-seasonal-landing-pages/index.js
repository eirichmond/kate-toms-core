/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./blocks/house-seasonal-landing-pages/edit.js":
/*!*****************************************************!*\
  !*** ./blocks/house-seasonal-landing-pages/edit.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_editor__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/editor */ "@wordpress/editor");
/* harmony import */ var _wordpress_editor__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_editor__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * WordPress dependencies
 */







/**
 * Edit function for House Seasonal Landing Pages block
 */

function Edit({
  attributes,
  setAttributes,
  context
}) {
  const {
    postsPerPage = -1,
    orderBy = 'meta_value_num',
    order = 'desc',
    metaKey = 'sleeps_max',
    patternStyle = 'coast'
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)();

  // Get current post ID (seasonal post)
  const postId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    return select(_wordpress_editor__WEBPACK_IMPORTED_MODULE_5__.store).getCurrentPostId();
  }, []);

  // Get seasonal meta from current post
  const {
    seasonalMeta,
    postType
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    if (!postId) return {
      seasonalMeta: {},
      postType: null
    };
    const currentPostType = select(_wordpress_editor__WEBPACK_IMPORTED_MODULE_5__.store).getCurrentPostType();

    // Only fetch meta if we're on a seasonal post
    if (currentPostType !== 'seasonal') {
      return {
        seasonalMeta: {},
        postType: currentPostType
      };
    }
    const post = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_4__.store).getEditedEntityRecord('postType', 'seasonal', postId);
    return {
      seasonalMeta: {
        beginning: post?.meta?.beginning || '',
        ending: post?.meta?.ending || '',
        periods_to_include: post?.meta?.periods_to_include || []
      },
      postType: currentPostType
    };
  }, [postId]);

  // Map pattern styles to location taxonomy slugs
  const patternConfig = {
    'coast': {
      slug: 'sea',
      name: 'By the Coast'
    },
    'cotswolds': {
      slug: 'cotswold',
      name: 'In the Cotswolds'
    },
    'country': {
      slug: 'country',
      name: 'In the Country'
    },
    'town': {
      slug: 'town',
      name: 'In town'
    }
  };
  const locationConfig = patternConfig[patternStyle] || patternConfig['coast'];

  // Format period names for display
  const periodLabels = {
    'weeks': 'Weeks',
    'Week': 'Weeks',
    'week': 'Weeks',
    '2 night weekend': 'Weekend (2 night)',
    '3 night weekend': 'Weekend (3 night)',
    'Midweek': 'Midweeks',
    '5 night': '5 nights (Christmas)'
  };
  const formatPeriods = periods => {
    if (!Array.isArray(periods) || periods.length === 0) return 'None selected';
    return periods.map(p => periodLabels[p] || p).join(', ');
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Query Settings', 'kate-toms-core'),
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Order by', 'kate-toms-core'),
          value: orderBy,
          onChange: value => setAttributes({
            orderBy: value
          }),
          options: [{
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Sleeps (Meta Value)', 'kate-toms-core'),
            value: 'meta_value_num'
          }, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Date', 'kate-toms-core'),
            value: 'date'
          }, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Title', 'kate-toms-core'),
            value: 'title'
          }]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Order', 'kate-toms-core'),
          value: order,
          onChange: value => setAttributes({
            order: value
          }),
          options: [{
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Descending (High to Low)', 'kate-toms-core'),
            value: 'desc'
          }, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Ascending (Low to High)', 'kate-toms-core'),
            value: 'asc'
          }]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Card Style', 'kate-toms-core'),
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('House Card Pattern', 'kate-toms-core'),
          value: patternStyle,
          onChange: value => setAttributes({
            patternStyle: value
          }),
          options: [{
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Coast Style', 'kate-toms-core'),
            value: 'coast'
          }, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Cotswolds Style', 'kate-toms-core'),
            value: 'cotswolds'
          }, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Country Style', 'kate-toms-core'),
            value: 'country'
          }, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Town Style', 'kate-toms-core'),
            value: 'town'
          }]
        })
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "house-seasonal-landing-pages-preview",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h3", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('House Seasonal Landing Pages Block', 'kate-toms-core')
        }), postType !== 'seasonal' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          style: {
            padding: '20px',
            background: '#fff3cd',
            border: '1px solid #ffc107',
            borderRadius: '4px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('⚠️ This block only works on Seasonal post types', 'kate-toms-core')
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Please add this block to a seasonal post to see houses filtered by availability.', 'kate-toms-core')
          })]
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            style: {
              padding: '15px',
              background: '#f0f0f1',
              borderRadius: '4px',
              marginBottom: '10px'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Seasonal Criteria:', 'kate-toms-core')
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
              children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Date Range:', 'kate-toms-core'), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
                children: seasonalMeta.beginning || 'Not set'
              }), " \u2192 ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
                children: seasonalMeta.ending || 'Not set'
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("br", {}), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Periods:', 'kate-toms-core'), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
                children: formatPeriods(seasonalMeta.periods_to_include)
              })]
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
            children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Location:', 'kate-toms-core'), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
              children: locationConfig.name
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("br", {}), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Ordering by:', 'kate-toms-core'), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
              children: orderBy === 'meta_value_num' ? 'Sleeps (Meta Value)' : orderBy
            }), " -", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("strong", {
              children: [" ", order === 'desc' ? 'High to Low' : 'Low to High']
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            style: {
              padding: '15px',
              background: '#e7f5ff',
              border: '1px solid #339af0',
              borderRadius: '4px'
            },
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("em", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Houses will be filtered based on availability between the dates and matching the selected periods. Preview shows configuration only - actual filtering happens on the frontend.', 'kate-toms-core')
              })
            })
          })]
        })]
      })
    })]
  });
}

/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/core-data":
/*!**********************************!*\
  !*** external ["wp","coreData"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["coreData"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/editor":
/*!********************************!*\
  !*** external ["wp","editor"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["editor"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

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
/*!******************************************************!*\
  !*** ./blocks/house-seasonal-landing-pages/index.js ***!
  \******************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./blocks/house-seasonal-landing-pages/edit.js");
/**
 * WordPress dependencies
 */



/**
 * Internal dependencies
 */


/**
 * Register the House Seasonal Landing Pages block
 */
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)('kate-toms-core/house-seasonal-landing-pages', {
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"]
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map