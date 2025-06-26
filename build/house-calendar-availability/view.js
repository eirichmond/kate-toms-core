/******/ (() => { // webpackBootstrap
/*!****************************************************!*\
  !*** ./blocks/house-calendar-availability/view.js ***!
  \****************************************************/
/**
 * House Calendar Availability Block Frontend Script
 */

console.log('House Calendar view.js script loaded');
document.addEventListener('DOMContentLoaded', function () {
  console.log('DOM loaded, looking for calendar blocks');
  // Initialize all calendar blocks on the page
  const calendarBlocks = document.querySelectorAll('.house-calendar-availability');
  console.log('Found calendar blocks:', calendarBlocks.length);
  calendarBlocks.forEach(initializeCalendar);
});
function initializeCalendar(blockElement) {
  const blockId = blockElement.id;
  const dataVarName = 'houseCalendarData_' + blockId.replace(/-/g, '_');
  console.log('Initializing calendar for block:', blockId);
  console.log('Looking for data variable:', dataVarName);
  console.log('Available window variables:', Object.keys(window).filter(key => key.includes('houseCalendar')));

  // Get the localized data
  if (!window[dataVarName]) {
    console.error('Calendar data not found for block:', blockId);
    console.error('Expected variable name:', dataVarName);
    showError(blockElement, 'Configuration error');
    return;
  }
  const config = window[dataVarName];
  console.log('Found config data:', config);
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
    console.log('Initializing calendar for house:', this.config.houseId);
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
        console.error('API Error:', data.data);
        this.showError('Failed to load calendar data: ' + (data.data || 'Unknown error'));
      }
    } catch (error) {
      console.error('Fetch Error:', error);
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
    const monthName = date.toLocaleDateString('en-US', {
      month: 'long',
      year: 'numeric'
    });

    // Get month key for rates (YYYY-MM format)
    const monthKey = `${year}-${String(month + 1).padStart(2, '0')}`;
    const monthNotes = this.getMonthNotes(monthKey);
    let html = `
			<div class="kt-calendar-month">
				<div class="month-header">
					<h3>${monthName}</h3>
				</div>
				<table class="kt-calendar-table">
					<thead>
						<tr class="day-headers">
							<th>FRI</th><th>SAT</th><th>SUN</th><th>MON</th><th>TUE</th><th>WED</th><th>THU</th>
						</tr>
		`;
    if (this.config.showRates) {
      html += `
						<tr class="period-headers">
							<th colspan="2">2 NIGHT WEEKEND</th>
							<th>3 NIGHT WEEKEND</th>
							<th colspan="4">WEEK</th>
						</tr>
						<tr class="period-headers-2">
							<th colspan="4">MIDWEEK</th>
							<th colspan="3">2 NIGHT MIDWEEK</th>
						</tr>
			`;
    }
    html += `
					</thead>
					<tbody>
		`;

    // Generate calendar weeks starting from Friday
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    // Find the first Friday of the month or the Friday before
    let startDate = new Date(firstDay);
    while (startDate.getDay() !== 5) {
      // 5 = Friday
      startDate.setDate(startDate.getDate() - 1);
    }
    let currentDay = new Date(startDate);
    let weekCount = 0;
    while (currentDay <= lastDay || weekCount < 5) {
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
        html += this.generateWeekRates(year, month, weekCount);
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
        is_checkout: false
      };
    }

    // Calculate diagonal styling for crossover days
    const diagonalStyle = this.calculateDiagonalStyle(date, dayData);

    // Return the data with calculated diagonal style
    return {
      status: dayData.status || 'unknown',
      diagonal_style: diagonalStyle,
      is_checkin: dayData.is_checkin || false,
      is_checkout: dayData.is_checkout || false
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
  generateWeekRates(year, month, weekIndex) {
    // Placeholder for rate columns - would integrate with rates API
    return `
			<td class="rate-cell">£250</td>
			<td class="rate-cell">£350</td>
			<td class="rate-cell">£1200</td>
			<td class="rate-cell">£800</td>
			<td class="rate-cell">£450</td>
		`;
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
/******/ })()
;
//# sourceMappingURL=view.js.map