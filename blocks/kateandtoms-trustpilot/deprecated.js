/* eslint-disable react/jsx-no-target-blank --
 * One historical variant stored rel="noopener" without "noreferrer". These
 * save functions exist purely to reproduce markup that is already in the
 * database, so the value cannot be improved here without reintroducing the
 * validation failure this file exists to fix. The current save() emits
 * rel="noopener noreferrer", so a page is upgraded the moment it is re-saved.
 */

/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Markup this block saved before the widget theme was selectable.
 *
 * This is a static block, so its markup lives in post_content and the editor
 * validates what is stored against what save() now produces. 208 published
 * pages carry it, so without these entries every one of them would show
 * "This block contains unexpected or invalid content".
 *
 * Two variants are stored, because pages were built two different ways:
 *
 *   152 pages  rel="noopener noreferrer"  — inserted via the block itself
 *    60 pages  rel="noopener"             — inserted via the theme pattern,
 *                                           whose hand-written markup never
 *                                           matched the block's save output
 *
 * The second group was already failing validation before this change; covering
 * it here repairs it rather than leaving it broken.
 *
 * The markup must stay byte-for-byte identical to what is stored. Do not tidy
 * it, and do not consolidate the two variants — the whole point is that they
 * differ.
 */

const LEGACY_ATTRIBUTES = {
	widgetType: {
		type: 'string',
		default: 'micro-combo',
	},
};

/**
 * The theme used to be hardcoded per widget type — micro-combo had none,
 * micro-star was always dark. Carry that across so an existing block keeps
 * rendering exactly as it did.
 *
 * @param {Object} attributes Attributes parsed from the stored markup.
 * @return {Object} Attributes with the equivalent explicit theme.
 */
function migrate( attributes ) {
	return {
		...attributes,
		theme: attributes.widgetType === 'micro-star' ? 'dark' : 'light',
	};
}

/**
 * Build a legacy save function for a given `rel` value.
 *
 * @param {string} rel The `rel` attribute as stored.
 * @return {Function} The save function.
 */
function makeLegacySave( rel ) {
	return function LegacySave( { attributes } ) {
		const { widgetType } = attributes;

		if ( widgetType === 'micro-combo' ) {
			return (
				<div { ...useBlockProps.save() }>
					<div
						className="trustpilot-widget"
						data-locale="en-GB"
						data-template-id="5419b6ffb0d04a076446a9af"
						data-businessunit-id="5cd41de1c4dd7a0001be3a14"
						data-style-height="20px"
						data-style-width="100%"
					>
						<a
							href="https://uk.trustpilot.com/review/www.kateandtoms.com"
							target="_blank"
							rel={ rel }
						>
							Trustpilot
						</a>
					</div>
				</div>
			);
		}

		if ( widgetType === 'micro-star' ) {
			return (
				<div { ...useBlockProps.save() }>
					<div
						className="trustpilot-widget"
						data-locale="en-GB"
						data-template-id="5419b732fbfb950b10de65e5"
						data-businessunit-id="5cd41de1c4dd7a0001be3a14"
						data-style-height="24px"
						data-style-width="100%"
						data-theme="dark"
					>
						<a
							href="https://uk.trustpilot.com/review/www.kateandtoms.com"
							target="_blank"
							rel={ rel }
						>
							Trustpilot
						</a>
					</div>
				</div>
			);
		}

		return null;
	};
}

const v2 = {
	attributes: LEGACY_ATTRIBUTES,
	migrate,
	save: makeLegacySave( 'noopener noreferrer' ),
};

const v1 = {
	attributes: LEGACY_ATTRIBUTES,
	migrate,
	save: makeLegacySave( 'noopener' ),
};

export default [ v2, v1 ];
