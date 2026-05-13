/**
 * Playwright config for the kate-toms-core plugin's e2e tests.
 *
 * These tests run against the live Laravel Valet site at
 * https://kateandtomsblocks.test, not against a wp-env install. That
 * keeps them developer-local — they will not run unchanged in CI.
 *
 * Run with: `npm run test:e2e:local`
 * Browsers need to be installed once: `npx playwright install chromium`.
 */
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 30 * 1000,
	fullyParallel: false,
	retries: 0,
	workers: 1,
	reporter: 'list',
	use: {
		baseURL: 'https://kateandtomsblocks.test',
		ignoreHTTPSErrors: true,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'mobile-chromium',
			use: {
				// Pixel 5 is a Chromium-based mobile device descriptor,
				// unlike iPhone 13 which defaults to WebKit. Sticking to
				// one engine keeps the browser install light (~150 MB).
				...devices[ 'Pixel 5' ],
				// Force an explicit viewport narrower than the 1100px
				// drilldown breakpoint for clarity.
				viewport: { width: 375, height: 812 },
			},
		},
	],
} );
