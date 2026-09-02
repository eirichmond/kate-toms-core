/**
 * TrustBox widget definitions, shared by edit and save.
 *
 * Kept in one place so the editor preview and the saved markup cannot drift —
 * on a static block a mismatch between them is a block validation error, not
 * just a cosmetic difference.
 *
 * `data-locale`, the review link and `rel` deliberately keep the site's
 * existing values (en-GB, the uk. subdomain) rather than the en-US defaults
 * Trustpilot hands out when you copy a widget from their dashboard.
 */
export const WIDGETS = {
	'micro-combo': {
		templateId: '5419b6ffb0d04a076446a9af',
		height: '20px',
	},
	'micro-star': {
		templateId: '5419b732fbfb950b10de65e5',
		height: '24px',
	},
};

export const BUSINESS_UNIT_ID = '5cd41de1c4dd7a0001be3a14';
export const LOCALE = 'en-GB';
export const REVIEW_URL =
	'https://uk.trustpilot.com/review/www.kateandtoms.com';

/**
 * Build the `trustpilot-widget` element props for a widget type and theme.
 *
 * `data-theme` is emitted only for the dark variant. Trustpilot treats a
 * missing attribute as light, and omitting it keeps the light markup identical
 * to what the 208 pages already carrying this block have stored.
 *
 * @param {string} widgetType Widget type key, e.g. 'micro-combo'.
 * @param {string} theme      'light' or 'dark'.
 * @return {Object|null} Props for the widget element, or null when the widget
 *                       type is unrecognised.
 */
export function getWidgetProps( widgetType, theme ) {
	const widget = WIDGETS[ widgetType ];

	if ( ! widget ) {
		return null;
	}

	return {
		className: 'trustpilot-widget',
		'data-locale': LOCALE,
		'data-template-id': widget.templateId,
		'data-businessunit-id': BUSINESS_UNIT_ID,
		'data-style-height': widget.height,
		'data-style-width': '100%',
		...( theme === 'dark' ? { 'data-theme': 'dark' } : {} ),
	};
}
