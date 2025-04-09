/**
 * WordPress dependencies
 */
import { store } from "@wordpress/interactivity";

// We'll use the same store as the filter block
const { state } = store("kate-toms-house-filter");

// The store is already configured in the filter block's view.js
// This block just needs to register with the same store to receive updates
