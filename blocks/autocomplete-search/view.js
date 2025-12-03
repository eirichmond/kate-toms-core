/**
 * WordPress dependencies
 */
import { store, getContext, withScope } from '@wordpress/interactivity';

let searchTimeout = null;
let allResults = [];
let searchCache = new Map(); // Cache for search results

const { state } = store('kate-toms-core/autocomplete-search', {
	state: {},
	actions: {
		init() {
			const context = getContext();
			// Fetch all search items on initialization
			const { actions } = store('kate-toms-core/autocomplete-search');
			actions.fetchSearchItems();
		},

		async fetchSearchItems() {
			const context = getContext();
			
			try {
				const response = await fetch('/wp-json/kate-toms/v1/autocomplete-search');
				if (response.ok) {
					allResults = await response.json();
				}
			} catch (error) {
				console.error('Failed to fetch search items:', error);
			}
		},

		handleInput(event) {
			const context = getContext();
			const searchTerm = event.target.value;
			
			context.searchTerm = searchTerm;
			context.selectedIndex = -1;

			// Clear previous timeout
			if (searchTimeout) {
				clearTimeout(searchTimeout);
			}

			if (searchTerm.trim().length === 0) {
				context.results = [];
				context.groupedResults = [];
				context.isOpen = false;
				return;
			}

			// Show loading state
			context.isLoading = true;

			// Reduced debounce time for better responsiveness
			const { actions } = store('kate-toms-core/autocomplete-search');
			searchTimeout = setTimeout(
				withScope(() => {
					actions.performSearch(searchTerm);
				}),
				150
			);
		},

		handleFocus() {
			const context = getContext();
			const searchTerm = context.searchTerm.trim();
			
			if (searchTerm.length > 0) {
				if (context.results.length > 0) {
					// Show existing results immediately
					context.isOpen = true;
				} else {
					// Perform search immediately for responsive UX
					const { actions } = store('kate-toms-core/autocomplete-search');
					actions.performSearch(searchTerm);
				}
			}
		},

		handleBlur(event) {
			const context = getContext();
			
			// Delay hiding results to allow clicks on results
			setTimeout(
				withScope(() => {
					// Check if the new focus target is within the search results
					const resultsContainer = event.target.closest('.autocomplete-search').querySelector('.autocomplete-search__results');
					if (!resultsContainer.contains(document.activeElement)) {
						const context = getContext();
						context.isOpen = false;
					}
				}),
				150
			);
		},

		handleKeyDown(event) {
			const context = getContext();
			
			if (!context.isOpen || context.results.length === 0) {
				return;
			}

			switch (event.key) {
				case 'ArrowDown':
					event.preventDefault();
					context.selectedIndex = Math.min(context.selectedIndex + 1, context.results.length - 1);
					break;

				case 'ArrowUp':
					event.preventDefault();
					context.selectedIndex = Math.max(context.selectedIndex - 1, -1);
					break;

				case 'Enter':
					event.preventDefault();
					if (context.selectedIndex >= 0 && context.results[context.selectedIndex]) {
						const { actions } = store('kate-toms-core/autocomplete-search');
						actions.navigateToResult(context.results[context.selectedIndex]);
					}
					break;

				case 'Escape':
					context.isOpen = false;
					context.selectedIndex = -1;
					event.target.blur();
					break;
			}
		},

		selectResult(event) {
			const context = getContext();
			const resultIndex = context.results.findIndex(result => result === context.result);
			
			if (resultIndex >= 0) {
				const { actions } = store('kate-toms-core/autocomplete-search');
				actions.navigateToResult(context.results[resultIndex]);
			}
		},

		highlightResult(event) {
			const context = getContext();
			const resultIndex = context.results.findIndex(result => result === context.result);
			
			if (resultIndex >= 0) {
				context.selectedIndex = resultIndex;
			}
		},

		navigateToResult(result) {
			const context = getContext();
			
			if (result && result.url) {
				context.isOpen = false;
				window.location.href = result.url;
			}
		},

		performSearch(searchTerm) {
			const context = getContext();
			
			// Clear loading state
			context.isLoading = false;
			
			if (!searchTerm.trim() || allResults.length === 0) {
				context.results = [];
				context.groupedResults = [];
				context.isOpen = false;
				return;
			}

			const term = searchTerm.toLowerCase().trim();
			const cacheKey = `${term}_${context.maxResults}`;
			
			// Check cache first
			if (searchCache.has(cacheKey)) {
				const cached = searchCache.get(cacheKey);
				context.results = cached.results;
				context.groupedResults = cached.groupedResults;
				context.isOpen = cached.groupedResults.length > 0;
				return;
			}
			
			// Optimized search with early exit and scoring
			const scoredResults = [];
			let resultCount = 0;
			
			for (const item of allResults) {
				if (resultCount >= context.maxResults) break;
				
				let score = 0;
				const labelLower = item.label.toLowerCase();
				const categoryLower = item.category.toLowerCase();
				const descLower = item.desc ? item.desc.toLowerCase() : '';
				
				// Prioritized scoring system
				if (labelLower.startsWith(term)) {
					score = 100; // Exact start match gets highest priority
				} else if (labelLower.includes(term)) {
					score = 80; // Label contains term
				} else if (categoryLower.includes(term)) {
					score = 60; // Category match
				} else if (descLower.includes(term)) {
					score = 40; // Description match
				}
				
				if (score > 0) {
					scoredResults.push({ ...item, score });
					resultCount++;
				}
			}
			
			// Sort by score (highest first) and limit results
			const sortedResults = scoredResults
				.sort((a, b) => b.score - a.score)
				.slice(0, context.maxResults);
			
			// Group results by category (optimized)
			const grouped = new Map();
			for (const item of sortedResults) {
				if (!grouped.has(item.category)) {
					grouped.set(item.category, []);
				}
				grouped.get(item.category).push(item);
			}

			// Convert to array format for template
			const groupedArray = Array.from(grouped.entries()).map(([category, results]) => ({
				category,
				results
			}));

			// Cache results (limit cache size to prevent memory issues)
			if (searchCache.size > 50) {
				const firstKey = searchCache.keys().next().value;
				searchCache.delete(firstKey);
			}
			searchCache.set(cacheKey, {
				results: sortedResults,
				groupedResults: groupedArray
			});

			context.results = sortedResults; // Keep flat array for keyboard navigation
			context.groupedResults = groupedArray; // Array of category groups for display
			context.isOpen = groupedArray.length > 0;
		}
	},
	callbacks: {
		isSelected() {
			const context = getContext();
			const resultIndex = context.results.findIndex(result => result === context.result);
			return resultIndex === context.selectedIndex;
		}
	}
});