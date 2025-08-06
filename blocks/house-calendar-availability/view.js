/**
 * House Calendar Availability Block Frontend Script
 */

document.addEventListener('DOMContentLoaded', function() {
	// Initialize all calendar blocks on the page
	const calendarBlocks = document.querySelectorAll('.house-calendar-availability');
	
	calendarBlocks.forEach(initializeCalendar);
});

function initializeCalendar(blockElement) {
	const blockId = blockElement.id;
	const dataVarName = 'houseCalendarData_' + blockId.replace(/-/g, '_');
	
	
	// Get the localized data
	if (!window[dataVarName]) {
		showError(blockElement, 'Configuration error');
		return;
	}
	
	const config = window[dataVarName];
	const calendar = new HouseCalendar(blockElement, config);
	calendar.init();
}

class HouseCalendar {
	constructor(element, config) {
		this.element = element;
		this.config = config;
		this.calendarData = null;
		this.currentMonth = new Date().toISOString().slice(0, 7); // YYYY-MM format
		
		// Get DOM elements
		this.loadingEl = element.querySelector('.calendar-loading');
		this.errorEl = element.querySelector('.calendar-error');
		this.containerEl = element.querySelector('.calendar-container');
		this.retryBtn = element.querySelector('.retry-button');
		
		// Bind retry button
		if (this.retryBtn) {
			this.retryBtn.addEventListener('click', () => this.fetchData());
		}
	}
	
	init() {
		this.fetchData();
		
		// Set up auto-refresh if enabled
		if (this.config.autoRefresh) {
			const intervalMs = this.config.refreshInterval * 60 * 1000;
			setInterval(() => this.fetchData(), intervalMs);
		}
	}
	
	async fetchData() {
		this.showLoading();
		
		try {
			const formData = new FormData();
			formData.append('action', 'fetch_calendar_data');
			formData.append('nonce', this.config.nonce);
			formData.append('house_id', this.config.houseId);
			
			const response = await fetch(this.config.ajaxUrl, {
				method: 'POST',
				body: formData
			});
			
			const data = await response.json();
			
			if (data.success) {
				this.calendarData = data.data;
				this.renderCalendar();
				this.showCalendar();
			} else {
				this.showError('Failed to load calendar data: ' + (data.data || 'Unknown error'));
			}
		} catch (error) {
			this.showError('Network error. Please check your connection.');
		}
	}
	
	renderCalendar() {
		if (!this.calendarData) {
			return;
		}
		
		let html = '<div class="calendar-wrapper">';
		
		// Generate months
		const startMonth = new Date();
		for (let i = 0; i < this.config.monthsToShow; i++) {
			const month = new Date(startMonth.getFullYear(), startMonth.getMonth() + i, 1);
			html += this.generateMonthHTML(month);
		}
		
		html += '</div>';
		this.containerEl.innerHTML = html;
	}
	
	generateMonthHTML(date) {

		const year = date.getFullYear();
		const month = date.getMonth();
		const monthName = date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
		
		// Get month key for rates (YYYY-MM format)
		const monthKey = `${year}-${String(month + 1).padStart(2, '0')}`;
		const monthNotes = this.getMonthNotes(monthKey);
		
		// Determine which rate columns to show for this month
		const visibleRateColumns = this.getVisibleRateColumns(monthKey);
		
		let html = `
			<div class="kt-calendar-month">
				<div class="month-header">
					<h2>${monthName}</h2>
				</div>
				<table class="kt-calendar-table">
					<thead>
						<tr class="day-headers">
							<th>FRI</th>
							<th>SA</th>
							<th>SU</th>
							<th>M</th>
							<th>TU</th>
							<th>W</th>
							<th>TH</th>`;
		
		// Add rate column headers only for visible columns
		if (this.config.showRates) {
			const rateHeaders = {
				'50': '2 NIGHT WEEKEND',
				'60': '3 NIGHT WEEKEND', 
				'70': 'WEEK',
				'80': 'MIDWEEK',
				'85': '2 NIGHT MIDWEEK',
				'90': '5 NIGHTS'
			};
			
			for (const [rateKey, headerText] of Object.entries(rateHeaders)) {
				if (visibleRateColumns.includes(rateKey)) {
					html += `<th>${headerText}</th>`;
				}
			}
		}
		
		html += `
						</tr>
					</thead>
					<tbody>
		`;
		
		// Generate calendar weeks starting from Friday
		const firstDay = new Date(year, month, 1);
		const lastDay = new Date(year, month + 1, 0);
		
		// Find the Friday of the week that contains the first day of the month
		// Use local timezone consistently to avoid date shifts
		let startDate = new Date(year, month, 1);
		
		// Calculate how many days back to go to reach Friday of this week
		const firstDayOfWeek = startDate.getDay();
		const daysToGoBack = (firstDayOfWeek + 2) % 7; // Formula to get to Friday: (day + 2) % 7
		startDate.setDate(startDate.getDate() - daysToGoBack);
		
		
		// Verify this is actually a Friday by checking a known Friday
		const verifyFriday = new Date(2025, 6, 4); // July 4, 2025 is definitely a Friday
		
		let currentDay = new Date(startDate);
		let weekCount = 0;
		
		while (currentDay <= lastDay || weekCount < 5) {
			// Store the actual Friday date for this week (currentDay should be Friday at start of each week)
			const weekFridayDate = new Date(currentDay);
			html += '<tr class="calendar-week">';
			
			// Generate 7 day cells (Fri-Thu)
			for (let i = 0; i < 7; i++) {
				const dayNum = currentDay.getDate();
				const isCurrentMonth = currentDay.getMonth() === month;
				
				if (isCurrentMonth) {
					const dayData = this.getDayData(currentDay);
					html += this.generateDayCell(dayNum, dayData);
				} else {
					html += '<td class="other-month"></td>';
				}
				
				currentDay.setDate(currentDay.getDate() + 1);
			}
			
			// Add rate columns if enabled
			if (this.config.showRates) {
				html += this.generateWeekRates(monthKey, weekFridayDate, visibleRateColumns);
			}
			
			html += '</tr>';
			weekCount++;
			
			if (weekCount >= 5) break;
		}
		
		html += `
					</tbody>
				</table>`;
		
		// Add notes section if notes exist
		if (monthNotes) {
			html += `
				<div class="month-notes">
					<p>${monthNotes}</p>
				</div>`;
		}
		
		html += `
			</div>
		`;
		
		return html;
	}
	
	/**
	 * Determine which rate columns should be visible for a month.
	 * Only shows columns for rate keys that exist in the rates data.
	 * Hides columns that have '-2' value for any week in the month.
	 */
	getVisibleRateColumns(monthKey) {
		const allRateKeys = ['50', '60', '70', '80', '85', '90'];
		const monthRates = this.calendarData.rates?.[monthKey];
		
		if (!monthRates || !monthRates.weeks) {
			return []; // Show no columns if no rate data
		}
		
		const visibleColumns = [];
		
		for (const rateKey of allRateKeys) {
			let keyExists = false;
			let shouldHide = false;
			
			// Check all weeks in the month to see if this rate key exists
			for (const weekDate in monthRates.weeks) {
				const weekRates = monthRates.weeks[weekDate];
				if (weekRates && weekRates[rateKey]) {
					keyExists = true;
					
					// If key exists, check if it has '-2' (hidden) value
					if (weekRates[rateKey].type === 'hidden') {
						shouldHide = true;
						break;
					}
				}
			}
			
			// Only add to visible columns if key exists and is not hidden
			if (keyExists && !shouldHide) {
				visibleColumns.push(rateKey);
			}
		}
		
		return visibleColumns;
	}
	
	getMonthNotes(monthKey) {
		// Access notes from rates data structure
		const monthRates = this.calendarData.rates?.[monthKey];
		
		if (monthRates && monthRates.notes && monthRates.notes.trim() !== '') {
			return monthRates.notes;
		}
		
		return null;
	}
	
	getDayData(date) {
		const dateKey = date.toISOString().split('T')[0];
		
		// Get day data directly using the date key (format: "YYYY-MM-DD")
		const dayData = this.calendarData.availability?.[dateKey];
		
		// If no data found, return default
		if (!dayData) {
			return {
				status: 'unknown',
				diagonal_style: 'none',
				is_checkin: false,
				is_checkout: false,
			};
		}
		
		// Calculate diagonal styling for crossover days
		const diagonalStyle = this.calculateDiagonalStyle(date, dayData);
		
		// Return the data with calculated diagonal style
		return {
			status: dayData.status || 'unknown',
			diagonal_style: diagonalStyle,
			is_checkin: dayData.is_checkin || false,
			is_checkout: dayData.is_checkout || false,
		};
	}
	
	calculateDiagonalStyle(date, dayData) {
		// If diagonal style is already set in data, use it
		if (dayData.diagonal_style && dayData.diagonal_style !== 'none') {
			return dayData.diagonal_style;
		}
		
		// Check for crossover days (checkout + checkin on same day)
		if (dayData.is_checkin && dayData.is_checkout) {
			return 'halfafter halfbefore'; // Both triangles for same-day changeover
		}
		
		const prevDay = this.getAdjacentDayData(date, -1);
		const nextDay = this.getAdjacentDayData(date, 1);
		
		// For booked days, check if this is a checkout day
		if (dayData.status === 'booked') {
			// Check if this is a checkout day (this booked, next available)
			if (nextDay?.status === 'available') {
				return 'halfafter'; // Green triangle (checkout day)
			}
		}
		
		// For available days, check for changeover patterns
		if (dayData.status === 'available') {
			// Check if this is a changeover day (available between two booked periods)
			if (prevDay?.status === 'booked' && nextDay?.status === 'booked') {
				return 'halfafter halfbefore'; // Both triangles for changeover day
			}
			
			// Check if this is day before checkin (this available, next booked)
			if (nextDay?.status === 'booked') {
				return 'halfbefore'; // Red triangle (day before checkin)
			}
		}
		
		return 'none';
	}
	
	getAdjacentDayData(date, dayOffset) {
		const adjacentDate = new Date(date);
		adjacentDate.setDate(adjacentDate.getDate() + dayOffset);
		const adjacentDateKey = adjacentDate.toISOString().split('T')[0];
		
		return this.calendarData.availability?.[adjacentDateKey];
	}
	
	generateDayCell(dayNum, dayData) {
		const classes = ['calendar-day'];
		
		// Add status class
		switch (dayData.status) {
			case 'available':
				classes.push('bk_avail');
				break;
			case 'booked':
				classes.push('bk_unav');
				break;
			case 'owner-blocked':
				classes.push('bk_blocked');
				break;
			default:
				classes.push('bk_unknown');
		}
		
		// Add diagonal styling classes if needed
		if (dayData.diagonal_style && dayData.diagonal_style !== 'none') {
			// Split multiple classes and add them individually
			const diagonalClasses = dayData.diagonal_style.split(' ');
			classes.push(...diagonalClasses);
		}
		
		// Add checkin/checkout indicator classes
		if (dayData.is_checkin) {
			classes.push('checkin-day');
		}
		if (dayData.is_checkout) {
			classes.push('checkout-day');
		}
		
		return `<td class="${classes.join(' ')}">${dayNum}</td>`;
	}
	
	generateWeekRates(monthKey, weekFridayDate, visibleRateColumns) {

		//debugger;

		let html = '';
		
		
		for (const rateKey of visibleRateColumns) {
			// Get the correct checkin day for this rate type
			const checkinDate = this.getCheckinDateForRate(
				weekFridayDate,
				rateKey,
				monthKey
			);
			//const weekDateKey = checkinDate.toISOString().split("T")[0]; // YYYY-MM-DD format

			const weekDateKey =
				checkinDate.getFullYear() +
				"-" +
				String(checkinDate.getMonth() + 1).padStart(2, "0") +
				"-" +
				String(checkinDate.getDate()).padStart(2, "0");



			const rateData = this.getWeekRate(monthKey, weekDateKey, rateKey);
			html += `<td class="rate-cell ${rateData.type}">${rateData.display}</td>`;
		}
		
		return html;
	}
	
	/**
	 * Get the correct checkin date for a rate type based on the week.
	 * @param {Date} weekFridayDate - The Friday date of the current week
	 * @param {string} rateKey - The rate key (50, 60, 70, 80, 85, 90)
	 * @param {string} monthKey - The month key (YYYY-MM format)
	 * @returns {Date} The correct checkin date for this rate type
	 */
	getCheckinDateForRate(weekFridayDate, rateKey, monthKey) {
		const friday = new Date(weekFridayDate);
		
		switch (rateKey) {
			case '50': // 2 night weekend - Friday checkin
			case '60': // 3 night weekend - Friday checkin
				return friday;
				
			case '70': // Week - Friday OR Monday checkin
				// Check both Friday and Monday, return the first one with data
				const monday70 = new Date(friday);
				monday70.setDate(friday.getDate() + 3); // Friday + 3 days = Monday
				return this.findBestCheckinDate([friday, monday70], rateKey, monthKey);
				
			case '80': // Midweek - Monday checkin
				const monday80 = new Date(friday);
				monday80.setDate(friday.getDate() + 3); // Friday + 3 days = Monday
				return monday80;
				
			case '85': // 2 night midweek - Monday, Tuesday, or Wednesday checkin
				const monday85 = new Date(friday);
				monday85.setDate(friday.getDate() + 3); // Friday + 3 days = Monday
				
				const tuesday85 = new Date(friday);
				tuesday85.setDate(friday.getDate() + 4); // Friday + 4 days = Tuesday
				
				const wednesday85 = new Date(friday);
				wednesday85.setDate(friday.getDate() + 5); // Friday + 5 days = Wednesday
				
				return this.findBestCheckinDate([monday85, tuesday85, wednesday85], rateKey, monthKey);
				
			case '90': // 5 nights - assume Friday for now
				return friday;
				
			default:
				return friday;
		}
	}
	
	/**
	 * Find the best checkin date from multiple options by checking which has rate data.
	 * @param {Date[]} possibleDates - Array of possible checkin dates
	 * @param {string} rateKey - The rate key being looked up
	 * @param {string} monthKey - The month key (YYYY-MM format)
	 * @returns {Date} The best checkin date (first one with data, or first option)
	 */
	findBestCheckinDate(possibleDates, rateKey, monthKey) {
		const monthRates = this.calendarData.rates?.[monthKey];
		
		if (!monthRates || !monthRates.weeks) {
			return possibleDates[0];
		}
		
		// Check each possible date to see which has rate data
		for (const date of possibleDates) {
			const dateKey = date.toISOString().split('T')[0];
			const weekRates = monthRates.weeks[dateKey];
			
			if (weekRates && weekRates[rateKey]) {
				const rateData = weekRates[rateKey];
				// Return this date if it has actual rate data (not just 'unavailable' or 'hidden')
				if (rateData.type && !['unavailable', 'hidden'].includes(rateData.type)) {
					return date;
				}
			}
		}
		
		// If no date has rate data, return the first option
		return possibleDates[0];
	}

	/**
	 * Format a Date object as YYYY-MM-DD in local time (not UTC)
	 * @param {Date} date - The date to format
	 * @returns {string} The formatted date string
	 */
	formatDateLocal(date) {
		const year = date.getFullYear();
		const month = String(date.getMonth() + 1).padStart(2, '0');
		const day = String(date.getDate()).padStart(2, '0');
		return `${year}-${month}-${day}`;
	}
	
	/**
	 * Get rate data for a specific week and rate type.
	 * Handles recursive lookup for '0' values.
	 */
	getWeekRate(monthKey, weekDateKey, rateKey) {

		const monthRates = this.calendarData.rates?.[monthKey];
		
		// debugger;
		if (!monthRates || !monthRates.weeks) {
			return { type: 'unavailable', display: 'n/a' };
		}
		// Try to get the rate for this week
		const weekRates = monthRates.weeks[weekDateKey];
		if (weekRates && weekRates[rateKey]) {
			const rateData = weekRates[rateKey];
			
			// If it's a '0' (previous week pricing), look backwards
			if (rateData.type === 'previous') {
				return this.findPreviousWeekRate(monthKey, weekDateKey, rateKey);
			}
			
			return rateData;
		}
		
		// No rate data found
		return { type: 'unavailable', display: 'n/a' };
	}
	
	/**
	 * Recursively find the previous non-zero rate value.
	 */
	findPreviousWeekRate(monthKey, currentWeekDateKey, rateKey) {
		const monthRates = this.calendarData.rates?.[monthKey];
		
		if (!monthRates || !monthRates.weeks) {
			return { type: 'unavailable', display: 'n/a' };
		}
		
		// Get all week dates for this month and sort them
		const weekDates = Object.keys(monthRates.weeks).sort();
		const currentIndex = weekDates.indexOf(currentWeekDateKey);
		
		// Look backwards through previous weeks
		for (let i = currentIndex - 1; i >= 0; i--) {
			const prevWeekDate = weekDates[i];
			const prevWeekRates = monthRates.weeks[prevWeekDate];
			
			if (prevWeekRates && prevWeekRates[rateKey]) {
				const prevRateData = prevWeekRates[rateKey];
				
				// If this is also a '0', continue looking backwards
				if (prevRateData.type === 'previous') {
					continue;
				}
				
				// Found a non-zero value
				return prevRateData;
			}
		}
		
		// TODO: Could extend this to look at previous months if needed
		return { type: 'unavailable', display: 'n/a' };
	}
	
	showLoading() {
		this.loadingEl.style.display = 'block';
		this.errorEl.style.display = 'none';
		this.containerEl.style.display = 'none';
	}
	
	showError(message) {
		this.loadingEl.style.display = 'none';
		this.errorEl.style.display = 'block';
		this.containerEl.style.display = 'none';
		
		const errorMsg = this.errorEl.querySelector('p');
		if (errorMsg) {
			errorMsg.textContent = message || 'Failed to load calendar data. Please try again later.';
		}
	}
	
	showCalendar() {
		this.loadingEl.style.display = 'none';
		this.errorEl.style.display = 'none';
		this.containerEl.style.display = 'block';
	}
}

// Helper function for error display
function showError(element, message) {
	const loadingEl = element.querySelector('.calendar-loading');
	const errorEl = element.querySelector('.calendar-error');
	
	if (loadingEl) loadingEl.style.display = 'none';
	if (errorEl) {
		errorEl.style.display = 'block';
		const errorMsg = errorEl.querySelector('p');
		if (errorMsg) {
			errorMsg.textContent = message;
		}
	}
}
