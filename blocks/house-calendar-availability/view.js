/**
 * House Calendar Availability Block Frontend Script
 */

document.addEventListener( 'DOMContentLoaded', function () {
	// Initialize all calendar blocks on the page
	const calendarBlocks = document.querySelectorAll(
		'.house-calendar-availability'
	);

	calendarBlocks.forEach( initializeCalendar );
} );

function initializeCalendar( blockElement ) {
	const blockId = blockElement.id;
	const dataVarName = 'houseCalendarData_' + blockId.replace( /-/g, '_' );

	// Get the localized data
	if ( ! window[ dataVarName ] ) {
		showError( blockElement, 'Configuration error' );
		return;
	}

	const config = window[ dataVarName ];
	const calendar = new HouseCalendar( blockElement, config );
	calendar.init();
}

class HouseCalendar {
	constructor( element, config ) {
		this.element = element;
		this.config = config;
		this.calendarData = null;
		this.currentMonth = new Date().toISOString().slice( 0, 7 ); // YYYY-MM format

		// Get DOM elements
		this.loadingEl = element.querySelector( '.calendar-loading' );
		this.errorEl = element.querySelector( '.calendar-error' );
		this.containerEl = element.querySelector( '.calendar-container' );
		this.retryBtn = element.querySelector( '.retry-button' );

		// Bind retry button
		if ( this.retryBtn ) {
			this.retryBtn.addEventListener( 'click', () => this.fetchData() );
		}
	}

	init() {
		this.fetchData();

		// Set up auto-refresh if enabled
		if ( this.config.autoRefresh ) {
			const intervalMs = this.config.refreshInterval * 60 * 1000;
			setInterval( () => this.fetchData(), intervalMs );
		}
	}

	async fetchData() {
		this.showLoading();

		try {
			const formData = new FormData();
			formData.append( 'action', 'fetch_calendar_data' );
			formData.append( 'nonce', this.config.nonce );
			formData.append( 'house_id', this.config.houseId );

			const response = await fetch( this.config.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );

			const data = await response.json();
			if ( data.success ) {
				this.calendarData = data.data;
				this.renderCalendar();
				this.showCalendar();
			} else {
				this.showError(
					'Failed to load calendar data: ' +
						( data.data || 'Unknown error' )
				);
			}
		} catch ( error ) {
			this.showError( 'Network error. Please check your connection.' );
		}
	}

	renderCalendar() {
		if ( ! this.calendarData ) {
			return;
		}

		let html = '<div class="calendar-wrapper">';

		// Generate months from now through the last month that has rate data
		const now = new Date();
		const currentYear = now.getFullYear();
		const currentMonth = now.getMonth(); // 0-indexed (0 = January, 11 = December)

		// Determine end date from the last month in rates data
		const rateMonths = Object.keys( this.calendarData.rates || {} ).sort();
		let endYear, endMonth;
		if ( rateMonths.length > 0 ) {
			const lastRateMonth = rateMonths[ rateMonths.length - 1 ]; // e.g. "2026-12"
			const parts = lastRateMonth.split( '-' );
			endYear = parseInt( parts[ 0 ], 10 );
			endMonth = parseInt( parts[ 1 ], 10 ) - 1; // Convert to 0-indexed
		} else {
			// Fallback: show 6 months from now
			endYear = currentYear;
			endMonth = currentMonth + 5;
			if ( endMonth > 11 ) {
				endYear += Math.floor( endMonth / 12 );
				endMonth = endMonth % 12;
			}
		}

		// Calculate number of months to show
		const monthsToShow =
			( endYear - currentYear ) * 12 + ( endMonth - currentMonth ) + 1;

		// Generate each month
		for ( let i = 0; i < monthsToShow; i++ ) {
			const month = new Date( currentYear, currentMonth + i, 1 );
			html += this.generateMonthHTML( month );
		}

		html += '</div>';
		this.containerEl.innerHTML = html;

		// Add click handlers for bookable dates
		this.addBookingEventHandlers();
	}

	generateMonthHTML( date ) {
		const year = date.getFullYear();
		const month = date.getMonth();
		const monthName = date.toLocaleDateString( 'en-US', {
			month: 'long',
			year: 'numeric',
		} );

		// Get month key for rates (YYYY-MM format)
		const monthKey = `${ year }-${ String( month + 1 ).padStart(
			2,
			'0'
		) }`;
		const monthNotes = this.getMonthNotes( monthKey );

		// Determine which rate columns to show for this month
		const visibleRateColumns = this.getVisibleRateColumns( monthKey );

		let html = `
			<div class="kt-calendar-month">
				<div class="month-header">
					<h2>${ monthName }</h2>
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
		if ( this.config.showRates ) {
			const rateHeaders = {
				50: '2 NIGHT WEEKEND',
				60: '3 NIGHT WEEKEND',
				70: 'WEEK',
				80: 'MIDWEEK',
				85: '2 NIGHT MIDWEEK',
				90: '5 NIGHTS',
			};

			for ( const [ rateKey, headerText ] of Object.entries(
				rateHeaders
			) ) {
				if ( visibleRateColumns.includes( rateKey ) ) {
					html += `<th>${ headerText }</th>`;
				}
			}
		}

		html += `
						</tr>
					</thead>
					<tbody>
		`;

		// Generate calendar weeks starting from Friday
		// Find the Friday of the week that contains the first day of the month
		// Use local timezone consistently to avoid date shifts
		const startDate = new Date( year, month, 1 );

		// Calculate how many days back to go to reach Friday of this week
		const firstDayOfWeek = startDate.getDay();
		const daysToGoBack = ( firstDayOfWeek + 2 ) % 7; // Formula to get to Friday: (day + 2) % 7
		startDate.setDate( startDate.getDate() - daysToGoBack );

		const currentDay = new Date( startDate );
		let weekCount = 0;

		while ( true ) {
			// Check if this week will contain any days from the current month
			const checkDate = new Date( currentDay );
			let hasCurrentMonthDays = false;
			for ( let i = 0; i < 7; i++ ) {
				if ( checkDate.getMonth() === month ) {
					hasCurrentMonthDays = true;
					break;
				}
				checkDate.setDate( checkDate.getDate() + 1 );
			}

			// If no days from current month, stop before rendering this week
			if ( ! hasCurrentMonthDays && weekCount > 0 ) {
				break;
			}

			// Store the actual Friday date for this week (currentDay should be Friday at start of each week)
			const weekFridayDate = new Date( currentDay );

			// Find the first day in this week that belongs to the current month
			// This will be used as the reference date for rate lookups
			let weekReferenceDate = null;
			const tempDate = new Date( currentDay );
			for ( let i = 0; i < 7; i++ ) {
				if ( tempDate.getMonth() === month ) {
					weekReferenceDate = new Date( tempDate );
					break;
				}
				tempDate.setDate( tempDate.getDate() + 1 );
			}

			html += '<tr class="calendar-week">';

			// Generate 7 day cells (Fri-Thu)
			for ( let i = 0; i < 7; i++ ) {
				const dayNum = currentDay.getDate();
				const isCurrentMonth = currentDay.getMonth() === month;

				if ( isCurrentMonth ) {
					// change the currentDay variable to a specific date
					// currentDay = new Date("2026-05-23");

					const dayData = this.getDayData( currentDay );
					html += this.generateDayCell( dayNum, dayData, currentDay );
				} else {
					html += '<td class="other-month"></td>';
				}

				currentDay.setDate( currentDay.getDate() + 1 );
			}

			// Add rate columns if enabled
			if ( this.config.showRates ) {
				// Use the reference date (first day of week in current month) if Friday is in previous month
				const rateReferenceDate =
					weekFridayDate.getMonth() === month
						? weekFridayDate
						: weekReferenceDate;
				html += this.generateWeekRates(
					monthKey,
					rateReferenceDate,
					visibleRateColumns
				);
			}

			html += '</tr>';
			weekCount++;
		}

		html += `
					</tbody>
				</table>`;

		// Add notes section if notes exist
		if ( monthNotes ) {
			html += `
				<div class="month-notes">
					<p>${ monthNotes }</p>
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
	 * @param monthKey
	 */
	getVisibleRateColumns( monthKey ) {
		const allRateKeys = [ '50', '60', '70', '80', '85', '90' ];
		const monthRates = this.calendarData.rates?.[ monthKey ];

		if ( ! monthRates || ! monthRates.weeks ) {
			return []; // Show no columns if no rate data
		}

		const visibleColumns = [];

		for ( const rateKey of allRateKeys ) {
			let keyExists = false;
			let shouldHide = false;

			// Check all weeks in the month to see if this rate key exists
			for ( const weekDate in monthRates.weeks ) {
				const weekRates = monthRates.weeks[ weekDate ];
				if ( weekRates && weekRates[ rateKey ] ) {
					keyExists = true;

					// If key exists, check if it has '-2' (hidden) value
					if ( weekRates[ rateKey ].type === 'hidden' ) {
						shouldHide = true;
						break;
					}
				}
			}

			// Only add to visible columns if key exists and is not hidden
			if ( keyExists && ! shouldHide ) {
				visibleColumns.push( rateKey );
			}
		}

		return visibleColumns;
	}

	getMonthNotes( monthKey ) {
		// Access notes from rates data structure
		const monthRates = this.calendarData.rates?.[ monthKey ];

		if (
			monthRates &&
			monthRates.notes &&
			monthRates.notes.trim() !== ''
		) {
			return monthRates.notes;
		}

		return null;
	}

	getDayData( date ) {
		const dateKey = this.formatDateLocal( date );

		// Get day data directly using the date key (format: "YYYY-MM-DD")
		const dayData = this.calendarData.availability?.[ dateKey ];

		// If no data found, return default
		if ( ! dayData ) {
			return {
				status: 'unknown',
				diagonal_style: 'none',
				is_checkin: false,
				is_checkout: false,
			};
		}

		// Calculate diagonal styling for crossover days
		const diagonalStyle = this.calculateDiagonalStyle( date, dayData );

		// Return the data with calculated diagonal style
		return {
			status: dayData.status || 'unknown',
			diagonal_style: diagonalStyle,
			is_checkin: dayData.is_checkin || false,
			is_checkout: dayData.is_checkout || false,
			bk_avail: dayData.bk_avail || false,
		};
	}

	calculateDiagonalStyle( date, dayData ) {
		// If diagonal style is already explicitly set in data (from PHP), use it.
		if ( dayData.diagonal_style && dayData.diagonal_style !== 'none' ) {
			return dayData.diagonal_style;
		}

		const prevDay = this.getAdjacentDayData( date, -1 );
		const nextDay = this.getAdjacentDayData( date, 1 );

		const isPrevBooked =
			prevDay?.status === 'booked' || prevDay?.status === 'owner_blocked';
		const isNextBooked =
			nextDay?.status === 'booked' || nextDay?.status === 'owner_blocked';

		// Diagonals only on the FIRST day of a new status:
		// First BOOKED day (prev=available): halfbefore (green-to-red) = check-in.
		// First AVAILABLE day (prev=booked): halfafter (red-to-green) = check-out.

		if ( dayData.status === 'booked' ) {
			if ( prevDay?.status === 'available' ) {
				return 'halfbefore'; // Check-in: first booked day.
			}
		}

		if ( dayData.status === 'available' ) {
			if ( isPrevBooked && isNextBooked ) {
				return 'halfafter halfbefore'; // Changeover day.
			}
			if ( isPrevBooked ) {
				return 'halfafter'; // Check-out: first available after booking.
			}
		}

		return 'none';
	}

	getAdjacentDayData( date, dayOffset ) {
		const adjacentDate = new Date( date );
		adjacentDate.setDate( adjacentDate.getDate() + dayOffset );
		const adjacentDateKey = this.formatDateLocal( adjacentDate );

		return this.calendarData.availability?.[ adjacentDateKey ];
	}

	generateDayCell( dayNum, dayData, currentDay ) {
		const classes = [ 'calendar-day' ];
		let attributes = '';

		// Format date as YYYY-MM-DD for data attribute
		const dateString = currentDay
			? `${ currentDay.getFullYear() }-${ String(
					currentDay.getMonth() + 1
			  ).padStart( 2, '0' ) }-${ String( currentDay.getDate() ).padStart(
					2,
					'0'
			  ) }`
			: '';

		// Check if date is in the past (before today)
		const today = new Date();
		today.setHours( 0, 0, 0, 0 ); // Reset to midnight for accurate date comparison
		const checkDay = new Date( currentDay );
		checkDay.setHours( 0, 0, 0, 0 );
		const isPastDate = checkDay < today;

		// If date is in the past, override status to unknown
		const effectiveStatus = isPastDate ? 'unknown' : dayData.status;
		// Add status class
		switch ( effectiveStatus ) {
			case 'available':
				classes.push( 'bk_avail' );
				classes.push( 'bookable-date' );
				// Add data attributes for booking
				attributes = ` data-date="${ dateString }" data-day="${ dayNum }" style="cursor: pointer;"`;
				break;
			case 'booked':
				// Check if this booked day is part of a bookable period (checkout day)
				// Use truthy check instead of strict equality (PHP sends 1 instead of true)
				if ( dayData.bk_avail || dayData.is_checkout ) {
					classes.push( 'bk_avail' );
				} else {
					classes.push( 'bk_unav' );
				}
				break;
			case 'owner-blocked':
				classes.push( 'bk_blocked' );
				break;
			default:
				classes.push( 'bk_unknown' );
		}

		// Add diagonal styling classes if needed
		if ( dayData.diagonal_style && dayData.diagonal_style !== 'none' ) {
			// Split multiple classes and add them individually
			const diagonalClasses = dayData.diagonal_style.split( ' ' );
			classes.push( ...diagonalClasses );
		}

		// Add checkin/checkout indicator classes
		if ( dayData.is_checkin ) {
			classes.push( 'checkin-day' );
		}
		if ( dayData.is_checkout ) {
			classes.push( 'checkout-day' );
		}

		return `<td class="${ classes.join(
			' '
		) }"${ attributes }>${ dayNum }</td>`;
	}

	generateWeekRates( monthKey, weekReferenceDate, visibleRateColumns ) {
		let html = '';
		// Calculate the Friday of this week - API data is keyed by Friday's date
		const refDate = new Date( weekReferenceDate );
		const dayOfWeek = refDate.getDay();
		const daysBackToFriday = ( dayOfWeek + 2 ) % 7;
		const weekFriday = new Date( refDate );
		weekFriday.setDate( refDate.getDate() - daysBackToFriday );

		// Get the month key based on Friday's date (where the API data is stored)
		const fridayMonthKey =
			weekFriday.getFullYear() +
			'-' +
			String( weekFriday.getMonth() + 1 ).padStart( 2, '0' );

		// Get Friday's date as the week key - API data is always keyed by Friday
		const fridayDateKey =
			weekFriday.getFullYear() +
			'-' +
			String( weekFriday.getMonth() + 1 ).padStart( 2, '0' ) +
			'-' +
			String( weekFriday.getDate() ).padStart( 2, '0' );

		for ( const rateKey of visibleRateColumns ) {
			// Get the correct checkin day for this rate type (for display purposes and validation)
			const checkinDate = this.getCheckinDateForRate(
				weekReferenceDate,
				rateKey,
				monthKey
			);

			const checkinDateKey =
				checkinDate.getFullYear() +
				'-' +
				String( checkinDate.getMonth() + 1 ).padStart( 2, '0' ) +
				'-' +
				String( checkinDate.getDate() ).padStart( 2, '0' );

			// Pass both the calendar month (for display decisions) and Friday's month/date (for data lookup)
			const rateData = this.getWeekRate(
				monthKey,
				fridayMonthKey,
				fridayDateKey,
				rateKey,
				checkinDateKey
			);

			let display = rateData.display; // this is the display property of the rateData object
			if ( rateData.offer ) {
				// this is the offer property of the rateData object
				display += `*`.repeat( rateData.offer ); // this is the number of stars to append to the display as an asterisk
			}
			html += `<td class="rate-cell ${ rateData.type }">${ display }</td>`; // this is the display property of the rateData object
		}

		return html;
	}

	/**
	 * Get the correct checkin date for a rate type based on the week.
	 * @param {Date}   weekReferenceDate - A reference date within the current week
	 * @param {string} rateKey           - The rate key (50, 60, 70, 80, 85, 90)
	 * @param {string} monthKey          - The month key (YYYY-MM format)
	 * @return {Date} The correct checkin date for this rate type
	 */
	getCheckinDateForRate( weekReferenceDate, rateKey, monthKey ) {
		// Calculate the Friday that started this week (week runs Fri-Thu)
		// Day of week: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
		const refDate = new Date( weekReferenceDate );
		const dayOfWeek = refDate.getDay();

		// Calculate days back to Friday
		// If Fri(5): 0 days back
		// If Sat(6): 1 day back
		// If Sun(0): 2 days back
		// If Mon(1): 3 days back
		// If Tue(2): 4 days back
		// If Wed(3): 5 days back
		// If Thu(4): 6 days back
		const daysBackToFriday = ( dayOfWeek + 2 ) % 7;
		const friday = new Date( refDate );
		friday.setDate( refDate.getDate() - daysBackToFriday );

		// Get Friday's month key (where rate data is stored)
		const fridayMonthKey =
			friday.getFullYear() +
			'-' +
			String( friday.getMonth() + 1 ).padStart( 2, '0' );

		switch ( rateKey ) {
			case '50': // 2 night weekend - Friday checkin
			case '60': // 3 night weekend - Friday checkin
				return friday;

			case '70': // Week - Friday OR Monday checkin
				// Check both Friday and Monday, return the first one with data
				// Prefer the checkin date that's in the calendar month being viewed
				const monday70 = new Date( friday );
				monday70.setDate( friday.getDate() + 3 ); // Friday + 3 days = Monday
				return this.findBestCheckinDate(
					[ friday, monday70 ],
					rateKey,
					fridayMonthKey,
					monthKey
				);

			case '80': // Midweek - Monday checkin
				const monday80 = new Date( friday );
				monday80.setDate( friday.getDate() + 3 ); // Friday + 3 days = Monday
				return monday80;

			case '85': // 2 night midweek - Monday, Tuesday, or Wednesday checkin
				const monday85 = new Date( friday );
				monday85.setDate( friday.getDate() + 3 ); // Friday + 3 days = Monday

				const tuesday85 = new Date( friday );
				tuesday85.setDate( friday.getDate() + 4 ); // Friday + 4 days = Tuesday

				const wednesday85 = new Date( friday );
				wednesday85.setDate( friday.getDate() + 5 ); // Friday + 5 days = Wednesday

				return this.findBestCheckinDate(
					[ monday85, tuesday85, wednesday85 ],
					rateKey,
					fridayMonthKey,
					monthKey
				);

			case '90': { // 5 nights - no fixed checkin day, any day in the week works
				const candidates90 = [];
				for ( let i = 0; i < 7; i++ ) {
					const candidate = new Date( friday );
					candidate.setDate( friday.getDate() + i );
					candidates90.push( candidate );
				}
				return this.findBestCheckinDate(
					candidates90,
					rateKey,
					fridayMonthKey,
					monthKey
				);
			}

			default:
				return friday;
		}
	}

	/**
	 * Check whether a stay of the given length starting on a date is fully available.
	 * Mirrors the server-side validate_booking_period(): every night of the stay
	 * must have status 'available'.
	 * @param {Date}   checkinDate - The checkin date
	 * @param {number} nights      - Number of nights in the stay
	 * @return {boolean} True if all nights are available
	 */
	isStayAvailable( checkinDate, nights ) {
		for ( let i = 0; i < nights; i++ ) {
			const night = new Date( checkinDate );
			night.setDate( night.getDate() + i );
			const dayData =
				this.calendarData.availability?.[
					this.formatDateLocal( night )
				];
			if ( ! dayData || dayData.status !== 'available' ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get the number of nights for a rate key.
	 * @param {string} rateKey - The rate key (50, 60, 70, 80, 85, 90)
	 * @return {number} Number of nights
	 */
	getRateNights( rateKey ) {
		const nights = {
			50: 2,
			60: 3,
			70: 7,
			80: 4,
			85: 2,
			90: 5,
		};
		return nights[ rateKey ] || 7;
	}

	/**
	 * Find the best checkin date from multiple options by checking which has rate data
	 * and an actually-available stay period.
	 * Prefers dates that fall within the calendar month being viewed, so a price only
	 * shows in a month's table when a valid checkin exists in that month.
	 * @param {Date[]} possibleDates    - Array of possible checkin dates
	 * @param {string} rateKey          - The rate key being looked up
	 * @param {string} storageMonthKey  - The month key where data is stored (YYYY-MM format)
	 * @param {string} calendarMonthKey - The month key being viewed in the calendar (YYYY-MM format)
	 * @return {Date} The best checkin date (prioritizes dates in calendar month, or first option)
	 */
	findBestCheckinDate(
		possibleDates,
		rateKey,
		storageMonthKey,
		calendarMonthKey
	) {
		const monthRates = this.calendarData.rates?.[ storageMonthKey ];

		if ( ! monthRates || ! monthRates.weeks ) {
			return possibleDates[ 0 ];
		}

		const nights = this.getRateNights( rateKey );

		// First, try to find a date in the calendar month being viewed that has rate
		// data AND a fully available stay period
		for ( const date of possibleDates ) {
			const dateKey = this.formatDateLocal( date );
			const dateMonth = dateKey.substring( 0, 7 );

			// Prefer dates in the calendar month
			if ( dateMonth === calendarMonthKey ) {
				// Check if this date has rate data (data is keyed by Friday, but we check all dates)
				// For rate data lookup, we always use the Friday's date as the key
				const weekRates =
					monthRates.weeks[ dateKey ] ||
					this.getWeekRatesForDate( monthRates.weeks, date );

				if ( weekRates && weekRates[ rateKey ] ) {
					const rateData = weekRates[ rateKey ];
					// Return this date if it has actual rate data (not just 'unavailable' or 'hidden')
					// and the stay starting on it is bookable
					if (
						rateData.type &&
						! [ 'unavailable', 'hidden' ].includes(
							rateData.type
						) &&
						this.isStayAvailable( date, nights )
					) {
						return date;
					}
				}
			}
		}

		// If no date in calendar month qualifies, check all dates for any with data
		// and availability. Returning an out-of-month date makes getWeekRate() render
		// an empty cell in this month's table — the price belongs to the other month.
		for ( const date of possibleDates ) {
			const dateKey = this.formatDateLocal( date );
			const weekRates =
				monthRates.weeks[ dateKey ] ||
				this.getWeekRatesForDate( monthRates.weeks, date );

			if ( weekRates && weekRates[ rateKey ] ) {
				const rateData = weekRates[ rateKey ];
				// Return this date if it has actual rate data (not just 'unavailable' or 'hidden')
				// and the stay starting on it is bookable
				if (
					rateData.type &&
					! [ 'unavailable', 'hidden' ].includes( rateData.type ) &&
					this.isStayAvailable( date, nights )
				) {
					return date;
				}
			}
		}

		// If no date has rate data, prefer dates in the calendar month
		for ( const date of possibleDates ) {
			const dateKey = this.formatDateLocal( date );
			const dateMonth = dateKey.substring( 0, 7 );
			if ( dateMonth === calendarMonthKey ) {
				return date;
			}
		}

		// Otherwise return the first option
		return possibleDates[ 0 ];
	}

	/**
	 * Helper to find week rates for any date by looking for the Friday of that week
	 * @param {Object} weeks - The weeks object from rate data
	 * @param {Date}   date  - The date to find rates for
	 * @return {Object|null} The week rates or null
	 */
	getWeekRatesForDate( weeks, date ) {
		// Calculate the Friday for this date's week
		const dayOfWeek = date.getDay();
		const daysBackToFriday = ( dayOfWeek + 2 ) % 7;
		const friday = new Date( date );
		friday.setDate( date.getDate() - daysBackToFriday );

		const fridayKey = this.formatDateLocal( friday );
		return weeks[ fridayKey ] || null;
	}

	/**
	 * Format a Date object as YYYY-MM-DD in local time (not UTC)
	 * @param {Date} date - The date to format
	 * @return {string} The formatted date string
	 */
	formatDateLocal( date ) {
		const year = date.getFullYear();
		const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
		const day = String( date.getDate() ).padStart( 2, '0' );
		return `${ year }-${ month }-${ day }`;
	}

	/**
	 * Get rate data for a specific week and rate type.
	 * Handles recursive lookup for '0' values.
	 * @param {string} calendarMonthKey - The month being displayed in the calendar (YYYY-MM)
	 * @param {string} storageMonthKey  - The month key where data is stored (Friday's month, YYYY-MM)
	 * @param {string} weekDateKey      - The Friday date key (YYYY-MM-DD) - API lookup key
	 * @param {string} rateKey          - The rate key (50, 60, 70, 80, 85, 90)
	 * @param {string} checkinDateKey   - The actual checkin date (YYYY-MM-DD) - for validation
	 */
	getWeekRate(
		calendarMonthKey,
		storageMonthKey,
		weekDateKey,
		rateKey,
		checkinDateKey
	) {
		// Extract month from checkin date
		const checkinMonth = checkinDateKey.substring( 0, 7 ); // Get YYYY-MM portion

		// If checkin date is in a different month than the calendar being viewed, handle based on rate type
		if ( checkinMonth !== calendarMonthKey ) {
			// For Monday checkin rates (Midweek and 2 night midweek), show empty space
			// since there's no Monday at the end of this month (or it's in previous month at start)
			if ( rateKey === '80' || rateKey === '85' ) {
				return { type: 'empty', display: '' };
			}
			// For Friday checkin rates (2 night weekend and 3 night weekend), show empty space
			// since there's no Friday at the beginning of this month (or it's in next month at end)
			if ( rateKey === '50' || rateKey === '60' ) {
				return { type: 'empty', display: '' };
			}
			// For WEEK rate (70) and the flexible 5 night rate (90), it depends on
			// which checkin day has data. If checkin is not in the calendar month,
			// don't show it.
			if ( rateKey === '70' || rateKey === '90' ) {
				return { type: 'empty', display: '' };
			}
			// For other rates, show n/a
			return { type: 'unavailable', display: '' };
		}

		// Use storage month (Friday's month) to look up the actual rate data
		const monthRates = this.calendarData.rates?.[ storageMonthKey ];

		if ( ! monthRates || ! monthRates.weeks ) {
			return { type: 'unavailable', display: '' };
		}
		// Try to get the rate for this week

		// note that the weekDateKey might be a Monday checkin date so it might not be in the monthRates.weeks object,
		// so if that is the case we need to find the previous week date and use that as the weekDateKey and then get
		// the rate data for that week and return it
		if ( ! monthRates.weeks[ weekDateKey ] ) {
			// Early return if weekDateKey is after all available weeks - no need to look for previous week
			const weekDates = Object.keys( monthRates.weeks );
			if (
				weekDates.length === 0 ||
				weekDateKey > Math.max( ...weekDates )
			) {
				return { type: 'unavailable', display: '' };
			}

			const previousWeekDate = this.findPreviousWeekDate(
				storageMonthKey,
				weekDateKey
			);

			// ****** if rateKey === 70 and fridayDateKey === 'not available' then check rates for checkinDateKey
			if ( previousWeekDate ) {
				if ( rateKey === '70' ) {
					const monthKey = previousWeekDate.substring( 0, 7 );
					if ( monthKey !== calendarMonthKey ) {
						const rateData =
							this.calendarData.rates[ calendarMonthKey ].weeks[
								calendarMonthKey + '-01'
							][ rateKey ];
						return rateData;
					}
				} else {
					weekDateKey = previousWeekDate;
				}
			}
		}

		const weekRates = monthRates.weeks[ weekDateKey ];
		if ( weekRates && weekRates[ rateKey ] ) {
			const rateData = weekRates[ rateKey ];

			// If it's a '0' (previous week pricing), look backwards
			if ( rateData.type === 'previous' ) {
				return this.findPreviousWeekRate(
					storageMonthKey,
					weekDateKey,
					rateKey
				);
			}

			return rateData;
		}

		// No rate data found
		return { type: 'unavailable', display: '' };
	}

	/**
	 * Find the previous week date that exists in monthRates.weeks.
	 * Used when a checkin date (e.g., Monday) doesn't exist as a week key,
	 * so we need to find the previous Friday week date that does exist.
	 * @param {string} monthKey    - The month key (YYYY-MM format)
	 * @param {string} weekDateKey - The week date key to find the previous week for (YYYY-MM-DD format)
	 * @return {string|null} The previous week date string, or null if not found
	 */
	findPreviousWeekDate( monthKey, weekDateKey ) {
		const monthRates = this.calendarData.rates?.[ monthKey ];

		if ( ! monthRates || ! monthRates.weeks ) {
			return null;
		}

		// Get all week dates for this month and sort them chronologically
		const weekDates = Object.keys( monthRates.weeks ).sort();

		// If no week dates exist, return null
		if ( weekDates.length === 0 ) {
			return null;
		}

		// Find the week date that is just before the given weekDateKey
		// Since dates are sorted, we can iterate backwards to find the first date that is less than weekDateKey
		for ( let i = weekDates.length - 1; i >= 0; i-- ) {
			if ( weekDates[ i ] < weekDateKey ) {
				// this doesn't work because they are both dates strings in the format of YYYY-MM-DD so we need to convert them to Date objects and then compare them
				const previousWeekDate = new Date( weekDates[ i ] );
				const currentWeekDate = new Date( weekDateKey );
				if ( previousWeekDate < currentWeekDate ) {
					return weekDates[ i ];
				}
			}
		}

		// If no previous week found (weekDateKey is before all existing weeks), return null
		return null;
	}

	/**
	 * Recursively find the previous non-zero rate value.
	 * @param monthKey
	 * @param currentWeekDateKey
	 * @param rateKey
	 */
	findPreviousWeekRate( monthKey, currentWeekDateKey, rateKey ) {
		const monthRates = this.calendarData.rates?.[ monthKey ];

		if ( ! monthRates || ! monthRates.weeks ) {
			return { type: 'unavailable', display: 'n/a' };
		}

		// Get all week dates for this month and sort them
		const weekDates = Object.keys( monthRates.weeks ).sort();
		const currentIndex = weekDates.indexOf( currentWeekDateKey );

		// Look backwards through previous weeks
		for ( let i = currentIndex - 1; i >= 0; i-- ) {
			const prevWeekDate = weekDates[ i ];
			const prevWeekRates = monthRates.weeks[ prevWeekDate ];

			if ( prevWeekRates && prevWeekRates[ rateKey ] ) {
				const prevRateData = prevWeekRates[ rateKey ];

				// If this is also a '0', continue looking backwards
				if ( prevRateData.type === 'previous' ) {
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

	showError( message ) {
		this.loadingEl.style.display = 'none';
		this.errorEl.style.display = 'block';
		this.containerEl.style.display = 'none';

		const errorMsg = this.errorEl.querySelector( 'p' );
		if ( errorMsg ) {
			errorMsg.textContent =
				message ||
				'Failed to load calendar data. Please try again later.';
		}
	}

	showCalendar() {
		this.loadingEl.style.display = 'none';
		this.errorEl.style.display = 'none';
		this.containerEl.style.display = 'block';
	}

	/**
	 * Add click event handlers to bookable dates
	 */
	addBookingEventHandlers() {
		// Find all bookable date cells
		const bookableDates =
			this.containerEl.querySelectorAll( '.bookable-date' );

		bookableDates.forEach( ( dateCell ) => {
			dateCell.addEventListener( 'click', async ( e ) => {
				e.preventDefault();

				const dateString = dateCell.dataset.date;

				if ( ! dateString ) {
					console.error( 'No date data found on clicked element' );
					return;
				}

				// Show loading state on clicked cell
				const originalContent = dateCell.innerHTML;
				dateCell.innerHTML = '<div class="booking-loader">...</div>';
				dateCell.style.pointerEvents = 'none';

				try {
					// Make AJAX request to get booking data
					const response = await this.fetchBookingData( dateString );

					if ( response.success ) {
						// Redirect to booking page with appropriate parameters
						window.location.href = response.data.booking_url;
					} else {
						console.error( 'Booking data error:', response.data );
						alert(
							'Sorry, there was an error processing your booking request. Please try again.'
						);
					}
				} catch ( error ) {
					console.error( 'Booking request failed:', error );
					alert(
						'Sorry, there was a network error. Please check your connection and try again.'
					);
				} finally {
					// Restore original content and enable clicking
					dateCell.innerHTML = originalContent;
					dateCell.style.pointerEvents = 'auto';
				}
			} );
		} );
	}

	/**
	 * Fetch booking data for a selected date
	 * @param {string} dateString Date in YYYY-MM-DD format
	 * @return {Promise} AJAX response promise
	 */
	async fetchBookingData( dateString ) {
		const formData = new FormData();
		formData.append( 'action', 'get_house_booking_data' );
		formData.append( 'house_id', this.config.houseId );
		formData.append( 'date', dateString );
		formData.append( 'nonce', this.config.bookingNonce );

		const response = await fetch( this.config.ajaxUrl, {
			method: 'POST',
			body: formData,
		} );

		if ( ! response.ok ) {
			throw new Error( `HTTP error! status: ${ response.status }` );
		}

		return await response.json();
	}
}

// Helper function for error display
function showError( element, message ) {
	const loadingEl = element.querySelector( '.calendar-loading' );
	const errorEl = element.querySelector( '.calendar-error' );

	if ( loadingEl ) {
		loadingEl.style.display = 'none';
	}
	if ( errorEl ) {
		errorEl.style.display = 'block';
		const errorMsg = errorEl.querySelector( 'p' );
		if ( errorMsg ) {
			errorMsg.textContent = message;
		}
	}
}
