# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Common Development Commands

### Build & Development
- `npm run build` - Build all blocks for production
- `npm run start` - Start development mode with watch for blocks
- `npm run format` - Format JavaScript and CSS files
- `npm run lint:js` - Lint JavaScript files
- `npm run lint:css` - Lint CSS/SCSS files
- `npm run phpcs` - Run PHP CodeSniffer for PHP files
- `npm run phpcbf` - Fix PHP CodeSniffer issues automatically

### Testing
- `npm run test:unit` - Run JavaScript unit tests
- `npm run test:e2e` - Run end-to-end tests (wp-env — not configured)
- `npm run test:e2e:local` - Run Playwright e2e tests against the Valet site (requires `npx playwright install chromium` once)

## Architecture Overview

This is a WordPress plugin for Kate & Toms that provides custom Gutenberg blocks and core functionality. The plugin follows the WordPress Plugin Boilerplate pattern with a modular architecture.

### Core Structure
- **Main Plugin Class**: `Kate_Toms_Core` in `includes/class-kate-toms-core.php` - orchestrates the entire plugin
- **Loader Pattern**: Uses `Kate_Toms_Core_Loader` to manage WordPress hooks
- **Admin/Public Split**: Separate classes for admin (`admin/`) and public-facing (`public/`) functionality

### Block Development
- **Source**: All blocks are in `blocks/` directory, each with its own folder
- **Build Process**: Uses `@wordpress/scripts` with `--source-path=blocks` for building
- **Block Registration**: Handled via `register_kateandtoms_core_blocks()` in admin class (skips `$skip_folders` entries like `mobile-nav-drilldown`)
- **Interactive Blocks**: Uses `@wordpress/interactivity` API for frontend interactions
- **Build-only stubs**: `blocks/mobile-nav-drilldown/` is NOT a real block — it exists solely so wp-scripts discovers and bundles its `view.js` + `style.css`. Its built assets are enqueued by `Kate_Toms_Core_Mobile_Nav` via a `render_block_core/navigation` filter, not via `register_block_type()`

### Key Custom Blocks
- `houses-filter` & `houses-filtered-results` - Property filtering system
- `house-calendar-availability` - Calendar booking functionality
- `button-form-extension` - Extends core button block with form capabilities
- `kateandtoms-reviews`, `kateandtoms-faqs`, `kateandtoms-trustpilot` - Content blocks
- `kateandtoms-image-fader` - Image gallery functionality

### API Integration
- **Houses Filter API**: `includes/houses-filter/class-houses-filter-api.php`
- **Calendar Availability API**: `includes/class-houses-calendar-availability-api.php`
- **AJAX Handlers**: Form loading and submission in main plugin file

### Environment-Specific Features
- **Image URL Replacement**: Automatically replaces image URLs with `kateandtoms.com` domain in local/staging environments
- **Development Helpers**: BugHerd integration for staging environments

### Development Notes
- Uses WordPress coding standards with PHP CodeSniffer
- Follows WordPress Plugin Boilerplate structure
- Custom post types and taxonomies defined in admin class
- Block patterns and pattern categories managed through admin hooks
- All blocks built with modern WordPress block development practices using `block.json`