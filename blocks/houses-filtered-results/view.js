/**
 * WordPress dependencies
 */
import { store } from "@wordpress/interactivity";

// Just subscribe to the store to access state
const { state } = store("kate-toms-house-filter");

const { actions, callbacks } = store("kate-toms-house-filter", {
    callbacks: {
        async refreshResults(event) {
            const block = event.target.closest('.wp-block-kate-toms-core-houses-filtered-results');
            if (!block) return;

            const context = JSON.parse(block.getAttribute('data-wp-context') || '{}');
            const defaultLocation = context.defaultLocation;
            
            // If this block has a default location and it doesn't match the selected location,
            // skip updating it unless no location is selected
            if (defaultLocation && state.local && defaultLocation !== state.local) {
                return;
            }

            try {
                state.isLoading = true;

                // Build query parameters
                const params = new URLSearchParams();
                if (state.date) params.append("date", state.date);
                if (state.dtype) params.append("dtype", state.dtype);
                if (state.size) params.append("size", state.size);
                if (state.local) params.append("local", state.local);
                if (state.feature) params.append("feature", state.feature);
                if (defaultLocation) params.append("default_location", defaultLocation);

                const apiUrl = `/wp-json/kate-toms/v1/houses?${params.toString()}`;

                const response = await fetch(apiUrl);
                if (!response.ok) {
                    throw new Error(`API Error: ${response.status}`);
                }

                const data = await response.json();
                if (!data.success) {
                    throw new Error("Invalid response from API");
                }

                const housesGrid = block.querySelector('.houses-grid');
                if (housesGrid) {
                    if (data.data && data.data.html) {
                        housesGrid.innerHTML = data.data.html;
                    }
                }
            } catch (error) {
                console.error('Error refreshing results:', error);
                const housesGrid = block.querySelector('.houses-grid');
                if (housesGrid) {
                    housesGrid.innerHTML = `<div class="houses-filter__error"><p>Error loading houses: ${error.message}</p></div>`;
                }
            } finally {
                state.isLoading = false;
            }
        }
    }
});
