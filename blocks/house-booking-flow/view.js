/**
 * House Booking Flow Block Frontend Script
 */

document.addEventListener( 'DOMContentLoaded', function () {
	// Initialize all booking flow blocks on the page
	const bookingBlocks = document.querySelectorAll( '.house-booking-flow' );

	bookingBlocks.forEach( initializeBookingFlow );
} );

function initializeBookingFlow( blockElement ) {
	const blockId = blockElement.id;
	const dataVarName = 'bookingFlowData_' + blockId.replace( /-/g, '_' );

	// Get the localized data
	if ( ! window[ dataVarName ] ) {
		showError( blockElement, 'Configuration error: Missing booking data' );
		return;
	}

	const config = window[ dataVarName ];
	const bookingFlow = new HouseBookingFlow( blockElement, config );
	bookingFlow.init();
}

class HouseBookingFlow {
	constructor( element, config ) {
		this.element = element;
		this.config = config;

		// Get DOM elements
		this.loadingEl = element.querySelector( '.booking-loading' );
		this.errorEl = element.querySelector( '.booking-error' );
		this.containerEl = element.querySelector( '.booking-container' );

		console.log( 'Booking flow initialized with config:', config );
	}

	init() {
		// Check if we have the required URL parameters
		if ( ! this.config.dateParam ) {
			this.showError(
				'No date specified. Please select a date from the availability calendar.'
			);
			return;
		}

		// Parse and validate the date
		const selectedDate = this.parseDate( this.config.dateParam );
		if ( ! selectedDate ) {
			this.showError(
				'Invalid date format. Please select a date from the calendar.'
			);
			return;
		}

		// Process the booking flow
		this.processStep1( selectedDate );
	}

	parseDate( dateParam ) {
		// Parse dd-mm-yyyy format
		const match = dateParam.match( /^(\d{1,2})-(\d{1,2})-(\d{4})$/ );
		if ( ! match ) {
			return null;
		}

		const day = parseInt( match[ 1 ] );
		const month = parseInt( match[ 2 ] ) - 1; // JavaScript months are 0-indexed
		const year = parseInt( match[ 3 ] );

		const date = new Date( year, month, day );

		// Validate the date is real
		if (
			date.getDate() !== day ||
			date.getMonth() !== month ||
			date.getFullYear() !== year
		) {
			return null;
		}

		return date;
	}

	formatDateForBooking() {
		// Format the checkin date as 'Friday, 26 September 2025'
		const selectedDate = this.parseDate( this.config.dateParam );
		if ( ! selectedDate ) {
			return '';
		}

		const options = {
			weekday: 'long',
			day: 'numeric',
			month: 'long',
			year: 'numeric',
		};

		return selectedDate.toLocaleDateString( 'en-GB', options );
	}

	processStep1( selectedDate ) {
		console.log( 'Processing step 1 for date:', selectedDate );

		// Fetch real booking periods from the API
		this.fetchBookingPeriods( selectedDate );
	}

	fetchBookingPeriods( selectedDate ) {
		// Show loading state
		this.showLoading();

		const formData = new FormData();
		formData.append( 'action', 'get_booking_periods' );
		formData.append( 'house_id', this.config.houseId );
		formData.append( 'date', this.config.dateParam );
		formData.append( 'nonce', this.config.nonce );

		fetch( this.config.ajaxUrl, {
			method: 'POST',
			body: formData,
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data.success ) {
					if ( data.data.no_breaks ) {
						this.showNoBreaksMessage(
							data.data.message,
							data.data.date_formatted
						);
					} else {
						this.renderStep1( selectedDate, data.data.periods );
					}
				} else {
					this.showError(
						'Unable to load booking periods: ' +
							( data.data || 'Unknown error' )
					);
				}
			} )
			.catch( ( error ) => {
				console.error( 'Booking periods fetch error:', error );
				this.showError(
					'Failed to load booking options. Please try again.'
				);
			} );
	}

	showNoBreaksMessage( message, dateFormatted ) {
		const selectedDate = this.parseDate( this.config.dateParam );
		const weekNumber = this.config.weekParam || 1;

		this.loadingEl.style.display = 'none';
		this.errorEl.style.display = 'none';
		this.containerEl.style.display = 'block';
		this.containerEl.innerHTML = `
			<div class="booking-progress-section">
				<div class="progress-labels">
					<p class="active">1. Select your booking</p>
					<p>2. Personal details</p>
					<p>3. All done</p>
				</div>
				<div class="progress">
					<div class="bar bar-info" style="width: 33.33%;"></div>
				</div>
			</div>

			<div class="booking-content">
				<div class="booking-info">
					<h2>Step 1: Select your booking</h2>
					<ul>
						<li><strong>Showing bookings beginning ${ this.formatDate(
							selectedDate
						) } for ${ this.config.houseName }</strong></li>
						<li>Please choose the booking that you would like to make from the options to the right.</li>
						<li>Week bookings usually begin on either a Friday or a Monday. If you would like to book a different period, please get in touch.</li>
						<li>Weekend bookings are available from Friday.</li>
						<li>The price includes all fees and taxes.</li>
						<li>Pricing shown may be for the minimum group size, supplements may apply for extra guests.</li>
					</ul>

					<div class="booking_details">
						<h3>Your Selection</h3>
						<p><span class="label">House:</span> <strong>${
							this.config.houseName
						}</strong></p>
						<p><span class="label">Date:</span> <strong>${ this.formatDate(
							selectedDate
						) }</strong></p>
					</div>
				</div>

				<div class="booking-options">
					<h3>Available Booking Periods</h3>
					<p class="no-periods">${ message }</p>
				</div>
			</div>
		`;
	}

	showLoading() {
		this.errorEl.style.display = 'none';
		this.containerEl.style.display = 'none';
		this.loadingEl.style.display = 'block';
	}

	renderStep1( selectedDate, periods = null ) {
		const weekNumber = this.config.weekParam || 1;

		// Use real periods or fallback to sample data for development
		const periodsToRender =
			periods || this.getSamplePeriods( selectedDate );

		const html = `
			<div class="booking-progress-section">
				<div class="progress-labels">
					<p class="active">1. Select your booking</p>
					<p>2. Personal details</p>
					<p>3. All done</p>
				</div>
				<div class="progress">
					<div class="bar bar-info" style="width: 33.33%;"></div>
				</div>
			</div>
			
			<div class="booking-content">
				<div class="booking-info">
					<h2>Step 1: Select your booking</h2>
					<ul>
						<li><strong>Showing bookings beginning ${ this.formatDate(
							selectedDate
						) } for ${ this.config.houseName }</strong></li>
						<li>Please choose the booking that you would like to make from the options to the right.</li>
						<li>Week bookings usually begin on either a Friday or a Monday. If you would like to book a different period, please get in touch.</li>
						<li>Weekend bookings are available from Friday.</li>
						<li>The price includes all fees and taxes.</li>
						<li>Pricing shown may be for the minimum group size, supplements may apply for extra guests.</li>
					</ul>
					
					<div class="booking_details">
						<h3>Your Selection</h3>
						<p><span class="label">House:</span> <strong>${
							this.config.houseName
						}</strong></p>
						<p><span class="label">Date:</span> <strong>${ this.formatDate(
							selectedDate
						) }</strong></p>
					</div>
				</div>
				
				<div class="booking-options">
					<h3>Available Booking Periods</h3>
					${ this.renderBookingPeriodsFromAPI( periodsToRender ) }
				</div>
			</div>
		`;

		this.containerEl.innerHTML = html;
		this.showContainer();

		// Add event listeners for period selection
		this.addPeriodEventListeners();
	}

	getSamplePeriods( selectedDate ) {
		// Fallback sample periods for development/testing
		return [
			{
				id: '2-night-weekend',
				name: '2 Night Weekend',
				description: 'Friday to Sunday',
				formatted_price: '£850',
				nights: 2,
				checkin_date: selectedDate.toISOString().split( 'T' )[ 0 ],
				checkout_date: new Date(
					selectedDate.getTime() + 2 * 24 * 60 * 60 * 1000
				)
					.toISOString()
					.split( 'T' )[ 0 ],
			},
			{
				id: '3-night-weekend',
				name: '3 Night Weekend',
				description: 'Friday to Monday',
				formatted_price: '£1,200',
				nights: 3,
				checkin_date: selectedDate.toISOString().split( 'T' )[ 0 ],
				checkout_date: new Date(
					selectedDate.getTime() + 3 * 24 * 60 * 60 * 1000
				)
					.toISOString()
					.split( 'T' )[ 0 ],
			},
			{
				id: 'week',
				name: 'Week',
				description: '7 nights',
				formatted_price: '£2,100',
				nights: 7,
				checkin_date: selectedDate.toISOString().split( 'T' )[ 0 ],
				checkout_date: new Date(
					selectedDate.getTime() + 7 * 24 * 60 * 60 * 1000
				)
					.toISOString()
					.split( 'T' )[ 0 ],
			},
		];
	}

	renderBookingPeriodsFromAPI( periods ) {
		if ( ! periods || periods.length === 0 ) {
			return '<p class="no-periods">No booking periods available for this date. Please try a different date or contact us directly.</p>';
		}

		return periods
			.map( ( period ) => {
				const checkinDate = new Date( period.checkin_date );
				const checkoutDate = new Date( period.checkout_date );

				return `
				<div class="booking-period" data-period-id="${ period.id }">
					<div class="period-details">
						<h4>${ period.name }</h4>
						<p class="period-description">${ period.description }</p>
						<p class="period-dates">
							Check-in: ${ this.formatDate( checkinDate ) }<br>
							Check-out: ${ this.formatDate( checkoutDate ) }
						</p>
					</div>
					<div class="period-price">
						${ period.formatted_price }
					</div>
				</div>
			`;
			} )
			.join( '' );
	}

	addPeriodEventListeners() {
		const periodElements =
			this.containerEl.querySelectorAll( '.booking-period' );

		periodElements.forEach( ( element ) => {
			element.addEventListener( 'click', () => {
				const periodId = element.dataset.periodId;
				console.log( 'Selected period:', periodId );

				// Store selected period info
				this.selectedPeriod = {
					id: periodId,
					element,
				};

				// Add selected styling
				periodElements.forEach( ( el ) =>
					el.classList.remove( 'selected' )
				);
				element.classList.add( 'selected' );

				// Proceed to step 2
				this.proceedToStep2();
			} );
		} );
	}

	proceedToStep2() {
		if ( ! this.selectedPeriod ) {
			this.showError( 'Please select a booking period first' );
			return;
		}

		// Get period details from the selected element
		const periodElement = this.selectedPeriod.element;
		const periodName = periodElement.querySelector( 'h4' ).textContent;
		const periodDescription = periodElement.querySelector(
			'.period-description'
		).textContent;
		const periodDates =
			periodElement.querySelector( '.period-dates' ).textContent;
		const periodPrice =
			periodElement.querySelector( '.period-price' ).textContent;

		this.renderStep2( {
			id: this.selectedPeriod.id,
			name: periodName,
			description: periodDescription,
			dates: periodDates,
			price: periodPrice,
		} );
	}

	renderStep2( selectedPeriod ) {
		const html = `
			<div class="booking-progress-section">
				<div class="progress-labels">
					<p>1. Select your booking</p>
					<p class="active">2. Personal details</p>
					<p>3. All done</p>
				</div>
				<div class="progress">
					<div class="bar bar-info" style="width: 66.66%;"></div>
				</div>
			</div>
			
			<div class="booking-content">
				<div class="booking-info">
					<h2>Step 2: Personal details</h2>
					<p>Please provide your details to complete the booking enquiry.</p>
					
					<div class="booking_details">
						<h3>Your Selected Booking</h3>
						<p><span class="label">House:</span> <strong>${
							this.config.houseName
						}</strong></p>
						<p><span class="label">Period:</span> <strong>${
							selectedPeriod.name
						}</strong></p>
						<p><span class="label">Description:</span> <strong>${
							selectedPeriod.description
						}</strong></p>
						<p><span class="label">Dates:</span> <strong>${
							selectedPeriod.dates
						}</strong></p>
						<p><span class="label">Price:</span> <strong>${
							selectedPeriod.price
						}</strong></p>
					</div>
				</div>
				
				<div class="booking-form">
					<form class="personal-details-form" id="personal-details-form">
						<div class="form-section">
							<h3>Lead Guest Details</h3>
							<div class="form-row">
								<div class="form-field">
									<label for="first-name">First Name *</label>
									<input type="text" id="first-name" name="first-name" required>
								</div>
								<div class="form-field">
									<label for="last-name">Last Name *</label>
									<input type="text" id="last-name" name="last-name" required>
								</div>
							</div>
							<div class="form-row">
								<div class="form-field">
									<label for="address-1">Address Line 1 *</label>
									<input type="text" id="address-1" name="address-1" required>
								</div>
								<div class="form-field">
									<label for="address-2">Address Line 2</label>
									<input type="text" id="address-2" name="address-2">
								</div>
							</div>
							<div class="form-row">
								<div class="form-field">
									<label for="address-3">Town/City *</label>
									<input type="text" id="address-3" name="address-3" required>
								</div>
								<div class="form-field">
									<label for="address-4">County</label>
									<input type="text" id="address-4" name="address-4">
								</div>
							</div>
							<div class="form-row">
								<div class="form-field">
									<label for="address-5">Post Code *</label>
									<input type="text" id="address-5" name="address-5" maxlength="9" required>
								</div>
							</div>
							<div class="form-row">
								<div class="form-field">
									<label for="email">Email Address *</label>
									<input type="email" id="email" name="email" required>
								</div>
								<div class="form-field">
									<label for="mobile">Mobile Number *</label>
									<input type="tel" id="mobile" name="mobile" required>
								</div>
							</div>
						</div>
						
						<div class="form-section">
							<h3>Number of Guests</h3>
							<div class="form-row">
								<div class="form-field">
									<label for="number-of-adults">Number of Adults *</label>
									<input type="text" id="number-of-adults" name="number-of-adults" placeholder="Number of Adults" required>
								</div>
								<div class="form-field">
									<label for="number-of-children">Number of Children 2-16 *</label>
									<input type="text" id="number-of-children" name="number-of-children" placeholder="Number of Children 2-16" required>
								</div>
							</div>
							<div class="form-row">
								<div class="form-field">
									<label for="number-of-infants">Number of Infants 0-2 *</label>
									<input type="text" id="number-of-infants" name="number-of-infants" placeholder="Number of Infants 0-2" required>
								</div>
								<div class="form-field">
									<label for="number-of-pets">Number of Dogs</label>
									<input type="text" id="number-of-pets" name="number-of-pets" placeholder="Number of Dogs">
								</div>
							</div>
							<div class="pet-details" id="pet-details" style="display:none;">
								<div class="form-row">
									<div class="form-field">
										<label for="breed-of-pets">Breed of Dog(s)</label>
										<input type="text" id="breed-of-pets" name="breed-of-pets" placeholder="Breed of dog(s)">
									</div>
									<div class="form-field">
										<label for="age-of-pets">Age of Dog(s)</label>
										<input type="text" id="age-of-pets" name="age-of-pets" placeholder="Age of dog(s)">
									</div>
								</div>
							</div>
						</div>
						
						<div class="form-section">
							<h3>Nature of Stay/Occasion</h3>
							<div class="form-row">
								<div class="form-field full-width">
									<label for="nature-of-stay">Nature of Stay/Occasion *</label>
									<select id="nature-of-stay" name="nature-of-stay" required>
										<option value="">Please select...</option>
										<option value="Holiday">Holiday</option>
										<option value="up to 29th Birthday Party">up to 29th Birthday Party</option>
										<option value="30-40th Birthday Party">30-40th Birthday Party</option>
										<option value="40+ Birthday Party">40+ Birthday Party</option>
										<option value="Celebration (non-wedding)">Celebration (non-wedding)</option>
										<option value="Hen Party">Hen Party</option>
										<option value="Stag Party">Stag Party</option>
										<option value="Corporate Event">Corporate Event</option>
										<option value="Wedding">Wedding</option>
										<option value="Wedding Accommodation">Wedding Accommodation</option>
									</select>
								</div>
							</div>
						</div>
						
						<div class="form-section">
							<h3>Age Range of Group (Approx.)</h3>
							<div class="form-row">
								<div class="form-field">
									<label for="age-range-from">From *</label>
									<input type="text" id="age-range-from" name="age-range-from" placeholder="from" required>
								</div>
								<div class="form-field">
									<label for="age-range-to">To *</label>
									<input type="text" id="age-range-to" name="age-range-to" placeholder="to" required>
								</div>
							</div>
						</div>
						
						<div class="form-section">
							<h3>Additional Information</h3>
							<div class="form-row">
								<div class="form-field full-width">
									<label for="special_requests">Special Requests or Requirements</label>
									<textarea id="special_requests" name="special_requests" rows="3" placeholder="Any special requirements, accessibility needs, or requests..."></textarea>
								</div>
							</div>
						</div>
						
						<div class="form-section">
							<h3>How did you hear about us?</h3>
							<div class="form-row">
								<div class="form-field full-width">
									<select id="how_heard" name="how_heard">
										<option value="">Please select...</option>
										<option value="google">Google Search</option>
										<option value="social_media">Social Media</option>
										<option value="friend_recommendation">Friend/Family Recommendation</option>
										<option value="previous_guest">Previous Guest</option>
										<option value="booking_site">Booking Website</option>
										<option value="advertisement">Advertisement</option>
										<option value="other">Other</option>
									</select>
								</div>
							</div>
						</div>
						
						<!-- Hidden fields for iPro API compatibility -->
						<div style="display: none;">
							<input type="hidden" name="property_name" value="${ this.config.houseName }">
							<input type="hidden" name="date_from" value="${ this.formatDateForBooking() }">
							<input type="hidden" name="period" value="${ selectedPeriod.name }">
							<input type="hidden" name="salutation" value="">
							<input type="hidden" name="post_id" value="${ this.config.houseId }">
						</div>
						
						<div class="booking-recaptcha" style="margin: 15px 0;"></div>

						<div class="form-actions">
							<button type="button" class="btn btn-secondary" id="back-to-step1">
								← Back to Period Selection
							</button>
							<button type="submit" class="btn btn-primary" id="submit-booking">
								Submit Booking Enquiry →
							</button>
						</div>
					</form>
				</div>
			</div>
		`;

		this.containerEl.innerHTML = html;
		this.showContainer();

		// Add event listeners for step 2
		this.addStep2EventListeners();
	}

	addStep2EventListeners() {
		const form = this.containerEl.querySelector( '#personal-details-form' );
		const backButton = this.containerEl.querySelector( '#back-to-step1' );
		const petsInput = this.containerEl.querySelector( '#number-of-pets' );
		const petDetails = this.containerEl.querySelector( '#pet-details' );

		// Handle form submission
		form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			this.handleStep2Submission( e );
		} );

		// Handle back button
		backButton.addEventListener( 'click', () => {
			this.goBackToStep1();
		} );

		// Handle pet details conditional display
		if ( petsInput && petDetails ) {
			petsInput.addEventListener( 'input', ( e ) => {
				const petsCount = parseInt( e.target.value ) || 0;
				if ( petsCount > 0 ) {
					petDetails.style.display = 'block';
				} else {
					petDetails.style.display = 'none';
				}
			} );
		}

		// Render the reCAPTCHA v2 checkbox widget.
		this.renderRecaptcha();
	}

	renderRecaptcha() {
		this.recaptchaWidgetId = null;

		const siteKey = this.config.recaptchaSiteKey;
		const container = this.containerEl.querySelector( '.booking-recaptcha' );
		if ( ! siteKey || ! container ) {
			return;
		}

		// The reCAPTCHA API (api.js) is loaded globally by the get-in-touch
		// plugin, but may not be ready the instant this step renders. Wait for it.
		const tryRender = ( attempts ) => {
			if (
				typeof window.grecaptcha !== 'undefined' &&
				typeof window.grecaptcha.render === 'function'
			) {
				try {
					this.recaptchaWidgetId = window.grecaptcha.render( container, {
						sitekey: siteKey,
					} );
				} catch ( e ) {
					// Already rendered into this container; ignore.
				}
			} else if ( attempts < 40 ) {
				setTimeout( () => tryRender( attempts + 1 ), 100 );
			}
		};
		tryRender( 0 );
	}

	resetRecaptcha() {
		if (
			this.recaptchaWidgetId !== null &&
			this.recaptchaWidgetId !== undefined &&
			typeof window.grecaptcha !== 'undefined' &&
			typeof window.grecaptcha.reset === 'function'
		) {
			try {
				window.grecaptcha.reset( this.recaptchaWidgetId );
			} catch ( e ) {
				// Widget no longer present; ignore.
			}
		}
	}

	goBackToStep1() {
		// Re-render step 1 with the previously fetched periods
		const selectedDate = this.parseDate( this.config.dateParam );
		if ( selectedDate ) {
			this.fetchBookingPeriods( selectedDate );
		}
	}

	handleStep2Submission( event ) {
		const form = event.target;
		const formData = new FormData( form );

		// Basic validation
		if ( ! this.validateStep2Form( form ) ) {
			return;
		}

		// reCAPTCHA: require a completed checkbox when a site key is configured.
		const siteKey = this.config.recaptchaSiteKey;
		const recaptchaContainer =
			this.containerEl.querySelector( '.booking-recaptcha' );
		const priorRecaptchaError = recaptchaContainer
			? recaptchaContainer.querySelector( '.recaptcha-error' )
			: null;
		if ( priorRecaptchaError ) {
			priorRecaptchaError.remove();
		}

		let recaptchaToken = '';
		if (
			siteKey &&
			typeof window.grecaptcha !== 'undefined' &&
			this.recaptchaWidgetId !== null &&
			this.recaptchaWidgetId !== undefined
		) {
			recaptchaToken = window.grecaptcha.getResponse(
				this.recaptchaWidgetId
			);
		}
		if ( siteKey && ! recaptchaToken ) {
			// Inline error keeps the form (and widget) visible for retry.
			if ( recaptchaContainer ) {
				const err = document.createElement( 'p' );
				err.className = 'field-error recaptcha-error';
				err.textContent = 'Please confirm you are not a robot.';
				recaptchaContainer.appendChild( err );
			}
			return;
		}

		// Show loading state
		this.showStep2Loading();

		// Add booking-specific data
		formData.append( 'action', 'submit_booking_enquiry' );
		formData.append( 'house_id', this.config.houseId );
		formData.append( 'selected_period', this.selectedPeriod.id );
		formData.append( 'checkin_date', this.config.dateParam );
		formData.append( 'nonce', this.config.nonce );

		if ( recaptchaToken ) {
			formData.append( 'g-recaptcha-response', recaptchaToken );
		}

		this.submitBooking( formData );
	}

	submitBooking( formData ) {
		fetch( this.config.ajaxUrl, {
			method: 'POST',
			body: formData,
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data.success ) {
					// this.renderStep3( data.data );
					window.location.href = '/thank-you';
				} else {
					// reCAPTCHA tokens are single-use; reset for retry.
					this.resetRecaptcha();
					this.showStep2Error(
						data.data ||
							'Unable to submit booking enquiry. Please try again.'
					);
				}
			} )
			.catch( ( error ) => {
				console.error( 'Booking submission error:', error );
				this.resetRecaptcha();
				this.showStep2Error(
					'Failed to submit booking enquiry. Please check your connection and try again.'
				);
			} );
	}

	validateStep2Form( form ) {
		let isValid = true;
		const errors = [];

		// Clear previous errors
		form.querySelectorAll( '.field-error' ).forEach( ( error ) =>
			error.remove()
		);
		form.querySelectorAll( '.form-field.error' ).forEach( ( field ) =>
			field.classList.remove( 'error' )
		);

		// Required fields validation
		const requiredFields = [
			{ name: 'first-name', label: 'First Name' },
			{ name: 'last-name', label: 'Last Name' },
			{ name: 'address-1', label: 'Address Line 1' },
			{ name: 'address-3', label: 'Town/City' },
			{ name: 'address-5', label: 'Post Code' },
			{ name: 'email', label: 'Email Address' },
			{ name: 'mobile', label: 'Mobile Number' },
			{ name: 'number-of-adults', label: 'Number of Adults' },
			{ name: 'number-of-children', label: 'Number of Children' },
			{ name: 'number-of-infants', label: 'Number of Infants' },
			{ name: 'nature-of-stay', label: 'Nature of Stay/Occasion' },
			{ name: 'age-range-from', label: 'Age Range From' },
			{ name: 'age-range-to', label: 'Age Range To' },
		];

		requiredFields.forEach( ( field ) => {
			const input = form.querySelector( `[name="${ field.name }"]` );
			if ( input ) {
				const value = input.value.trim();
				if ( ! value ) {
					this.addFieldError( input, `${ field.label } is required` );
					isValid = false;
				}
			}
		} );

		// Email validation
		const emailInput = form.querySelector( '[name="email"]' );
		if ( emailInput ) {
			const emailValue = emailInput.value.trim();
			if ( emailValue && ! this.isValidEmail( emailValue ) ) {
				this.addFieldError(
					emailInput,
					'Please enter a valid email address'
				);
				isValid = false;
			}
		}

		// Phone validation (basic)
		const phoneInput = form.querySelector( '[name="mobile"]' );
		if ( phoneInput ) {
			const phoneValue = phoneInput.value.trim();
			if ( phoneValue && phoneValue.length < 10 ) {
				this.addFieldError(
					phoneInput,
					'Please enter a valid phone number'
				);
				isValid = false;
			}
		}

		if ( ! isValid ) {
			// Scroll to first error
			const firstError = form.querySelector( '.form-field.error' );
			if ( firstError ) {
				firstError.scrollIntoView( {
					behavior: 'smooth',
					block: 'center',
				} );
			}
		}

		return isValid;
	}

	addFieldError( input, message ) {
		const fieldContainer = input.closest( '.form-field' );
		fieldContainer.classList.add( 'error' );

		const errorEl = document.createElement( 'span' );
		errorEl.className = 'field-error';
		errorEl.textContent = message;
		fieldContainer.appendChild( errorEl );
	}

	isValidEmail( email ) {
		const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return emailRegex.test( email );
	}

	showStep2Loading() {
		const submitButton =
			this.containerEl.querySelector( '#submit-booking' );
		const backButton = this.containerEl.querySelector( '#back-to-step1' );

		submitButton.disabled = true;
		submitButton.innerHTML = '<span class="spinner"></span> Submitting...';
		backButton.disabled = true;
	}

	showStep2Error( message ) {
		const submitButton =
			this.containerEl.querySelector( '#submit-booking' );
		const backButton = this.containerEl.querySelector( '#back-to-step1' );

		// Reset buttons
		submitButton.disabled = false;
		submitButton.innerHTML = 'Submit Booking Enquiry →';
		backButton.disabled = false;

		// Show error message
		this.showError( message );
	}

	renderStep3( responseData ) {
		const html = `
			<div class="booking-progress-section">
				<div class="progress-labels">
					<p>1. Select your booking</p>
					<p>2. Personal details</p>
					<p class="active">3. All done</p>
				</div>
				<div class="progress">
					<div class="bar bar-success" style="width: 100%;"></div>
				</div>
			</div>
			
			<div class="booking-content">
				<div class="booking-success">
					<div class="success-icon">
						<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="12" cy="12" r="10"/>
							<path d="m9 12 2 2 4-4"/>
						</svg>
					</div>
					
					<h2>Booking Enquiry Submitted!</h2>
					<p>Thank you for your booking enquiry. We've received your request and will be in touch shortly.</p>
					
					${
						responseData.reference_number
							? `
						<div class="reference-number">
							<p><strong>Reference Number:</strong> ${ responseData.reference_number }</p>
						</div>
					`
							: ''
					}
					
					<div class="next-steps">
						<h3>What happens next?</h3>
						<ul>
							<li>We'll review your booking request and check availability</li>
							<li>You'll receive a confirmation email within 24 hours</li>
							<li>If available, we'll send you booking terms and payment details</li>
							<li>For urgent enquiries, please call us on 01242 235151</li>
						</ul>
					</div>
					
					<div class="contact-info">
						<p>Questions? Contact us:</p>
						<p><strong>Phone:</strong> 01242 235151</p>
						<p><strong>Email:</strong> hello@kateandtoms.com</p>
					</div>
					
					<div class="form-actions">
						<a href="${ this.config.housesUrl || '/houses/' }" class="btn btn-primary">
							Browse Other Properties
						</a>
					</div>
				</div>
			</div>
		`;

		this.containerEl.innerHTML = html;
		this.showContainer();
	}

	formatDate( date ) {
		return date.toLocaleDateString( 'en-GB', {
			weekday: 'long',
			day: 'numeric',
			month: 'long',
			year: 'numeric',
		} );
	}

	showError( message ) {
		console.error( 'Booking flow error:', message );

		this.loadingEl.style.display = 'none';
		this.containerEl.style.display = 'none';

		const errorMessage = this.errorEl.querySelector( '.error-message' );
		if ( errorMessage ) {
			errorMessage.textContent = message;
		}

		this.errorEl.style.display = 'block';
	}

	showContainer() {
		this.loadingEl.style.display = 'none';
		this.errorEl.style.display = 'none';
		this.containerEl.style.display = 'block';
	}
}

// Helper function for error display
function showError( element, message ) {
	const loadingEl = element.querySelector( '.booking-loading' );
	const errorEl = element.querySelector( '.booking-error' );

	if ( loadingEl ) {
		loadingEl.style.display = 'none';
	}
	if ( errorEl ) {
		errorEl.style.display = 'block';
		const errorMsg = errorEl.querySelector( '.error-message' );
		if ( errorMsg ) {
			errorMsg.textContent = message;
		}
	}
}
