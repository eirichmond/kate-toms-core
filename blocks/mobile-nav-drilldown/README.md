# Mobile Nav Drilldown

Not a real block. This directory exists purely as a build-pipeline discovery hook so `wp-scripts --source-path=blocks` picks up `view.js` and `style.css` and produces built assets in `build/mobile-nav-drilldown/`. Those assets are then enqueued by `Kate_Toms_Core_Mobile_Nav` against the core `core/navigation` block to add an iOS-style drilldown to the mobile overlay below 1100px.

The stub `block.json` sets `supports.inserter: false` and is never registered with `register_block_type()`, so it will not appear in the block inserter.
