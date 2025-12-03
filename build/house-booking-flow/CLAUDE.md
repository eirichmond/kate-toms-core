# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **House Booking Flow** block, a WordPress Gutenberg block that handles the complete multi-step booking process for Kate & Tom's holiday rental houses. The block is part of the larger Kate & Toms Core plugin and provides an interactive booking experience that processes URL parameters and displays appropriate booking steps.

## Block Architecture

### Core Structure
- **Block Type**: Dynamic server-side rendered block with JavaScript interaction
- **Namespace**: `kate-toms-core/house-booking-flow`
- **API Version**: 3 (latest WordPress block API)
- **Pattern**: Uses WordPress Interactivity API approach with vanilla JavaScript

### Key Files
- `block.json` - Block configuration and metadata
- `index.js` - Editor component registration and edit interface
- `render.php` - Server-side rendering logic with PHP
- `view.js` - Frontend JavaScript for multi-step booking interaction
- `style.scss` - Complete styling including responsive design and form validation
- `editor.scss` - Editor-specific styles

## Development Workflow

### Build Commands
```bash
# From plugin root directory (/wp-content/plugins/kate-toms-core/)
npm run build        # Production build of all blocks
npm run start        # Development watch mode for all blocks
npm run lint:js      # JavaScript linting 
npm run lint:css     # CSS/SCSS linting
```

### Block Development Process
1. **Edit Phase**: Modify source files in `/blocks/house-booking-flow/`
2. **Build Phase**: Run `npm run build` to compile to `/build/house-booking-flow/`
3. **WordPress Registration**: Block auto-registered via plugin's admin class

## Functionality Overview

### Booking Flow Steps
1. **Step 1: Period Selection** - Display available booking periods based on selected date
2. **Step 2: Personal Details** - Collect guest information and booking requirements  
3. **Step 3: Confirmation** - Show success message with reference number

### URL Parameter Processing
- **Required**: `d` parameter (date in dd-mm-yyyy format)
- **Optional**: `week` parameter for week-based bookings
- **Validation**: Date parsing with comprehensive format validation
- **Context**: Only renders on pages named 'book' with a parent house

### API Integration Points
- `get_booking_periods` - Fetches available booking periods for selected date
- `submit_booking_enquiry` - Processes complete booking form submission
- **AJAX Endpoints**: Configured in main plugin via Houses Calendar Availability API class
- **Nonce Security**: WordPress nonce verification on all AJAX calls

## Technical Implementation Details

### Frontend JavaScript Architecture
- **Class-Based**: `HouseBookingFlow` class handles complete flow
- **State Management**: Local state for selected periods and form data
- **Error Handling**: Comprehensive error states with user-friendly messages
- **Validation**: Client-side form validation with field-level error display
- **Loading States**: Progressive loading indicators for each step

### Form Processing
- **Multi-Section Forms**: Lead guest details, booking details, marketing preferences
- **Field Validation**: Required fields, email format, phone number validation
- **User Experience**: Smooth transitions, back navigation, field error highlighting
- **Accessibility**: Proper form labeling and error announcement

### Styling System
- **Responsive Design**: Mobile-first approach with flexbox layouts
- **Progress Indicators**: Visual step progression with active state styling
- **Form Styling**: Consistent input styling with focus states and error indicators
- **Loading Animations**: CSS-based spinners and transition effects

### Security & Performance
- **WordPress Nonces**: All AJAX requests include nonce verification
- **Data Sanitization**: PHP input sanitization via WordPress functions
- **Conditional Loading**: Only loads on appropriate booking pages
- **Block-Specific Assets**: CSS/JS loaded only when block is present

## Development Considerations

### Error Handling Strategy
- **Graceful Degradation**: Shows editor notices when not on booking pages
- **API Failures**: Fallback to sample data in development environment
- **Form Validation**: Both client-side and server-side validation
- **Debug Mode**: Console logging and HTML comments when WP_DEBUG enabled

### Data Flow Architecture
```
URL Parameters -> PHP Render -> JavaScript Localization -> 
Frontend Class -> AJAX Calls -> API Responses -> UI Updates
```

### Integration Dependencies
- **Parent Plugin**: Requires Kate & Toms Core plugin activation
- **House Post Type**: Integrates with custom 'houses' post type
- **iPro Integration**: Uses iPro Property ID for booking system integration
- **Calendar API**: Depends on Houses Calendar Availability API class

## Common Development Tasks

### Adding New Form Fields
1. Update `renderStep2()` method in `view.js:276`
2. Add validation in `validateStep2Form()` method in `view.js:472`
3. Update AJAX handler in main plugin's PHP booking submission

### Modifying Booking Periods Display
1. Edit `renderBookingPeriodsFromAPI()` in `view.js:203`
2. Update styling in `style.scss` for `.booking-period` class
3. Adjust API response handling if needed

### Styling Customizations
- **Progress Bar**: Modify `.booking-progress-section` styles
- **Form Elements**: Update `.form-field` and related classes  
- **Mobile Responsive**: Adjust media queries throughout stylesheet
- **Brand Colors**: Update color variables and theme-specific styling