/**
 * Mobile Nav Drilldown — view module.
 *
 * Extends the core/navigation Interactivity store to turn the mobile
 * overlay into an iOS-style drilldown below 1100px. This file is loaded
 * as a script module and enqueued by Kate_Toms_Core_Mobile_Nav only on
 * pages that render a core/navigation block.
 */
import { store } from '@wordpress/interactivity';
import './style.css';

// eslint-disable-next-line no-console
console.debug( '[mobile-nav-drilldown] loaded' );

store( 'core/navigation', {} );
