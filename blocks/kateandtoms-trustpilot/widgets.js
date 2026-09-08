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
/**
 * Alignments the TrustBox app accepts.
 *
 * Taken from the widget's own source, which defines
 * `styleAlignmentPositions = [ 'left', 'right' ]`. There is no explicit
 * centre — that is what you get when the attribute is absent.
 *
 * @type {string[]}
 */
export const ALIGNMENTS = [ 'left', 'right' ];

export const REVIEW_URL =
	'https://uk.trustpilot.com/review/www.kateandtoms.com';

/**
 * Build the `trustpilot-widget` element props for a widget type and theme.
 *
 * `data-theme` is emitted only for the dark variant, and
 * `data-style-alignment` only for left or right. Trustpilot treats a missing
 * theme as light and a missing alignment as centred, so omitting both keeps
 * the default markup identical to what pages already carrying this block have
 * stored.
 *
 * The widget renders in a fixed-height iframe with overflow hidden, so a
 * height too small for the content silently clips it — which is what happened
 * when the footer's micro combo wrapped onto a second line inside a narrow
 * column. `height` overrides the per-widget default for exactly that case.
 *
 * @param {string} widgetType Widget type key, e.g. 'micro-combo'.
 * @param {string} theme      'light' or 'dark'.
 * @param {string} alignment  'left', 'right', or anything else for centred.
 * @param {string} height     CSS height, or empty for the widget default.
 * @return {Object|null} Props for the widget element, or null when the widget
 *                       type is unrecognised.
 */
export function getWidgetProps( widgetType, theme, alignment, height ) {
	const widget = WIDGETS[ widgetType ];

	if ( ! widget ) {
		return null;
	}

	return {
		className: 'trustpilot-widget',
		'data-locale': LOCALE,
		'data-template-id': widget.templateId,
		'data-businessunit-id': BUSINESS_UNIT_ID,
		'data-style-height': height || widget.height,
		'data-style-width': '100%',
		...( theme === 'dark' ? { 'data-theme': 'dark' } : {} ),
		...( ALIGNMENTS.includes( alignment )
			? { 'data-style-alignment': alignment }
			: {} ),
	};
}
