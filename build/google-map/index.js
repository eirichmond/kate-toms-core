/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./blocks/google-map/edit.js":
/*!***********************************!*\
  !*** ./blocks/google-map/edit.js ***!
  \***********************************/
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
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./editor.scss */ "./blocks/google-map/editor.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * WordPress dependencies
 */





/**
 * Internal dependencies
 */


/**
 * Edit component for the Google Map block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {Element} Element to render.
 */

function Edit({
  attributes,
  setAttributes
}) {
  const {
    address,
    lat,
    lng
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: 'google-map-block-editor'
  });
  const mapRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(null);
  const [map, setMap] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const [marker, setMarker] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const [isApiLoaded, setIsApiLoaded] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const isUpdatingProgrammatically = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(false);

  // Stabilize the marker drag handler with useCallback
  const handleMarkerDrag = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useCallback)(() => {
    if (!marker || isUpdatingProgrammatically.current) {
      return;
    }
    const position = marker.getPosition();
    const newLat = position.lat().toString();
    const newLng = position.lng().toString();
    setAttributes({
      lat: newLat,
      lng: newLng
    });

    // Reverse geocode to get address
    const geocoder = new window.google.maps.Geocoder();
    geocoder.geocode({
      location: position
    }, (results, status) => {
      if (status === 'OK' && results[0]) {
        setAttributes({
          address: results[0].formatted_address,
          lat: newLat,
          lng: newLng
        });
      }
    });
  }, [marker, setAttributes]);

  // Load Google Maps API
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (window.google && window.google.maps) {
      setIsApiLoaded(true);
      return;
    }

    // Check if script is already being loaded
    const existingScript = document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]');
    if (existingScript) {
      // Wait for existing script to load
      const checkGoogleMaps = () => {
        if (window.google && window.google.maps) {
          setIsApiLoaded(true);
        } else {
          setTimeout(checkGoogleMaps, 100);
        }
      };
      checkGoogleMaps();
      return;
    }
    const script = document.createElement('script');
    script.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyCWIUdebNRovvJryUDibH8cwjkRsPI2M_8&sensor=false';
    script.async = true;
    script.defer = true;
    script.onload = () => setIsApiLoaded(true);
    script.onerror = () => {
      console.error('Failed to load Google Maps API');
    };
    document.head.appendChild(script);
    return () => {
      // Don't remove script on cleanup as it might be used by other components
    };
  }, []);

  // Initialize map
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (!isApiLoaded || !mapRef.current || map) {
      return;
    }
    const defaultCenter = lat && lng ? {
      lat: parseFloat(lat),
      lng: parseFloat(lng)
    } : {
      lat: 51.5074,
      lng: -0.1278
    }; // London default

    const newMap = new window.google.maps.Map(mapRef.current, {
      zoom: 10,
      center: defaultCenter,
      scrollwheel: false,
      draggable: false,
      // Disable map dragging
      mapTypeId: window.google.maps.MapTypeId.TERRAIN,
      disableDefaultUI: true,
      zoomControl: true,
      zoomControlOptions: {
        style: window.google.maps.ZoomControlStyle.SMALL,
        position: window.google.maps.ControlPosition.LEFT_BOTTOM
      }
    });
    const newMarker = new window.google.maps.Marker({
      position: defaultCenter,
      map: newMap,
      draggable: true,
      title: 'Drag to adjust location'
    });

    // Map panning is disabled, so no drag event handling needed

    setMap(newMap);
    setMarker(newMarker);
  }, [isApiLoaded]);

  // Add marker drag listener when marker is ready
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (!marker) {
      return;
    }
    const dragListener = marker.addListener('dragend', handleMarkerDrag);
    return () => {
      window.google.maps.event.removeListener(dragListener);
    };
  }, [marker, handleMarkerDrag]);

  // Update marker position when attributes change (but don't reinitialize map)
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (!map || !marker || !lat || !lng) {
      return;
    }

    // Only update if this is a programmatic change (e.g., from address search)
    isUpdatingProgrammatically.current = true;
    const newPosition = {
      lat: parseFloat(lat),
      lng: parseFloat(lng)
    };
    marker.setPosition(newPosition);
    map.setCenter(newPosition);

    // Reset flag after a brief delay
    setTimeout(() => {
      isUpdatingProgrammatically.current = false;
    }, 100);
  }, [lat, lng, map, marker]);

  // Handle address search with useCallback for stability
  const handleAddressSearch = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useCallback)(() => {
    if (!address || !window.google) {
      return;
    }
    const geocoder = new window.google.maps.Geocoder();
    geocoder.geocode({
      address
    }, (results, status) => {
      if (status === 'OK' && results[0]) {
        const location = results[0].geometry.location;
        const newLat = location.lat().toString();
        const newLng = location.lng().toString();
        setAttributes({
          address: results[0].formatted_address,
          lat: newLat,
          lng: newLng
        });
        if (map && marker) {
          map.setCenter(location);
          marker.setPosition(location);
        }
      }
    });
  }, [address, map, marker, setAttributes]);
  const handleKeyDown = event => {
    if (event.key === 'Enter') {
      handleAddressSearch();
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Map Settings', 'kate-toms-core'),
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Address', 'kate-toms-core'),
          value: address,
          onChange: value => setAttributes({
            address: value
          }),
          onKeyDown: handleKeyDown,
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Enter an address and press Enter to search', 'kate-toms-core')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "primary",
          onClick: handleAddressSearch,
          disabled: !address,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Search Address', 'kate-toms-core')
        }), lat && lng && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          style: {
            marginTop: '10px',
            fontSize: '12px',
            color: '#666'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Coordinates:', 'kate-toms-core')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("br", {}), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Lat:', 'kate-toms-core'), " ", lat, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("br", {}), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Lng:', 'kate-toms-core'), " ", lng]
        })]
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
        className: "google-map-editor",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          className: "google-map-editor__map-container",
          children: !isApiLoaded ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
            className: "google-map-editor__loading",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Loading Google Maps…', 'kate-toms-core')
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
            ref: mapRef,
            className: "google-map-editor__map",
            style: {
              width: '100%',
              height: '300px'
            }
          }, "google-map-container")
        })
      })
    })]
  });
}

/***/ }),

/***/ "./blocks/google-map/index.js":
/*!************************************!*\
  !*** ./blocks/google-map/index.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./blocks/google-map/style.scss");
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./editor.scss */ "./blocks/google-map/editor.scss");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./edit */ "./blocks/google-map/edit.js");
/* harmony import */ var _save__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./save */ "./blocks/google-map/save.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./block.json */ "./blocks/google-map/block.json");
/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */


/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor. All other files
 * get applied to the editor only.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */



/**
 * Internal dependencies
 */




/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_5__.name, {
  /**
   * @see ./edit.js
   */
  edit: _edit__WEBPACK_IMPORTED_MODULE_3__["default"],
  /**
   * @see ./save.js
   */
  save: _save__WEBPACK_IMPORTED_MODULE_4__["default"]
});

/***/ }),

/***/ "./blocks/google-map/save.js":
/*!***********************************!*\
  !*** ./blocks/google-map/save.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Save)
/* harmony export */ });
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * WordPress dependencies
 */


/**
 * Save component for the Google Map block.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @return {Element} Element to render.
 */

function Save({
  attributes
}) {
  const {
    address,
    lat,
    lng
  } = attributes;
  const blockProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useBlockProps.save();

  // Don't render anything if no coordinates are set
  if (!lat || !lng) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
        className: "google-map-placeholder",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("p", {
          children: "Please configure the map location in the editor."
        })
      })
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
    ...blockProps,
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
      className: "google-map-container",
      "data-lat": lat,
      "data-lng": lng,
      "data-address": address,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
        className: "google-map",
        style: {
          width: '100%',
          height: '400px'
        }
      })
    })
  });
}

/***/ }),

/***/ "./blocks/google-map/editor.scss":
/*!***************************************!*\
  !*** ./blocks/google-map/editor.scss ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./blocks/google-map/style.scss":
/*!**************************************!*\
  !*** ./blocks/google-map/style.scss ***!
  \**************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


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

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "./blocks/google-map/block.json":
/*!**************************************!*\
  !*** ./blocks/google-map/block.json ***!
  \**************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"kate-toms-core/google-map","version":"0.1.0","title":"House location","category":"widgets","icon":"location-alt","description":"to render a google map for house location","keywords":["Google","Map","House Location"],"example":{},"supports":{"html":false},"textdomain":"kate-toms-core","attributes":{"address":{"type":"string","default":""},"lat":{"type":"string","default":""},"lng":{"type":"string","default":""}},"editorScript":"file:./index.js","editorStyle":"file:./index.css","style":"file:./style-index.css","viewScript":"file:./view.js"}');

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
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
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
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"google-map/index": 0,
/******/ 			"google-map/style-index": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkkate_toms_core"] = globalThis["webpackChunkkate_toms_core"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["google-map/style-index"], () => (__webpack_require__("./blocks/google-map/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map