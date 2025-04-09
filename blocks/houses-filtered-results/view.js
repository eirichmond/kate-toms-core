/**
 * WordPress dependencies
 */
import { store } from "@wordpress/interactivity";

// We'll use the same store as the filter block, but we won't redefine it
// Just subscribe to it
const { state } = store("kate-toms-house-filter");
