# Mobile Nav Drilldown

Not a real block. This directory exists purely as a build-pipeline discovery hook so `wp-scripts --source-path=blocks` picks up `view.js` and `style.css` and produces built assets in `build/mobile-nav-drilldown/`. Those assets are then enqueued by `Kate_Toms_Core_Mobile_Nav` against the core `core/navigation` block to add an iOS-style drilldown to the mobile overlay below 1100px.

The stub `block.json` sets `supports.inserter: false` as belt-and-braces, but the primary guard is that `Kate_Toms_Core_Admin::register_kateandtoms_core_blocks()` explicitly skips the `mobile-nav-drilldown` folder in its `$skip_folders` list, so `register_block_type()` is never called against it. If you add more build-only stubs in the future, add them to that skip list too.
