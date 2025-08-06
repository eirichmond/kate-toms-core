import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
	PanelBody,
	TextControl,
	ToggleControl,
	RangeControl,
	Notice
} from "@wordpress/components";
// Custom calendar table icon to represent the availability calendar
const calendarTableIcon = (
	<svg 
		width="24" 
		height="24" 
		viewBox="0 0 24 24" 
		fill="none" 
		xmlns="http://www.w3.org/2000/svg"
	>
		{/* Table structure */}
		<rect x="2" y="4" width="20" height="16" rx="1" stroke="currentColor" strokeWidth="1.5" fill="none"/>
		
		{/* Header row */}
		<line x1="2" y1="8" x2="22" y2="8" stroke="currentColor" strokeWidth="1.5"/>
		
		{/* Vertical dividers for columns */}
		<line x1="6" y1="4" x2="6" y2="20" stroke="currentColor" strokeWidth="1"/>
		<line x1="10" y1="4" x2="10" y2="20" stroke="currentColor" strokeWidth="1"/>
		<line x1="14" y1="4" x2="14" y2="20" stroke="currentColor" strokeWidth="1"/>
		<line x1="18" y1="4" x2="18" y2="20" stroke="currentColor" strokeWidth="1"/>
		
		{/* Horizontal dividers for rows */}
		<line x1="2" y1="12" x2="22" y2="12" stroke="currentColor" strokeWidth="1"/>
		<line x1="2" y1="16" x2="22" y2="16" stroke="currentColor" strokeWidth="1"/>
		
		{/* Availability indicators - colored squares */}
		<rect x="3" y="9" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		<rect x="7" y="9" width="2" height="2" fill="#e3634b" rx="0.5"/> {/* Booked */}
		<rect x="11" y="9" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		<rect x="15" y="9" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		<rect x="19" y="9" width="2" height="2" fill="#e3634b" rx="0.5"/> {/* Booked */}
		
		<rect x="3" y="13" width="2" height="2" fill="#e3634b" rx="0.5"/> {/* Booked */}
		<rect x="7" y="13" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		<rect x="11" y="13" width="2" height="2" fill="#e3634b" rx="0.5"/> {/* Booked */}
		<rect x="15" y="13" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		<rect x="19" y="13" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		
		{/* Calendar indicator - small calendar icon in top corner */}
		<rect x="16" y="1" width="6" height="5" rx="0.5" stroke="currentColor" strokeWidth="1" fill="none"/>
		<line x1="17.5" y1="0.5" x2="17.5" y2="2.5" stroke="currentColor" strokeWidth="1"/>
		<line x1="20.5" y1="0.5" x2="20.5" y2="2.5" stroke="currentColor" strokeWidth="1"/>
		<line x1="16" y1="3.5" x2="22" y2="3.5" stroke="currentColor" strokeWidth="0.5"/>
	</svg>
);

import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const {
		houseId,
		showRates,
		monthsToShow,
		autoRefresh,
		refreshInterval,
	} = attributes;

	const blockProps = useBlockProps({
		className: "wp-block-kate-toms-core-house-calendar-availability",
	});

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Calendar Settings", "kate-toms-core")} initialOpen={true}>
					<TextControl
						label={__("House ID", "kate-toms-core")}
						value={houseId}
						onChange={(value) => setAttributes({ houseId: value })}
						help={__("Enter the house ID from your booking system", "kate-toms-core")}
					/>
					
					<RangeControl
						label={__("Months to Show", "kate-toms-core")}
						value={monthsToShow}
						onChange={(value) => setAttributes({ monthsToShow: value })}
						min={1}
						max={24}
					/>
					
					<ToggleControl
						label={__("Show Rates", "kate-toms-core")}
						checked={showRates}
						onChange={(value) => setAttributes({ showRates: value })}
					/>
				</PanelBody>
				
				<PanelBody title={__("Auto Refresh", "kate-toms-core")} initialOpen={false}>
					<ToggleControl
						label={__("Enable Auto Refresh", "kate-toms-core")}
						checked={autoRefresh}
						onChange={(value) => setAttributes({ autoRefresh: value })}
					/>
					
					{autoRefresh && (
						<RangeControl
							label={__("Refresh Interval (minutes)", "kate-toms-core")}
							value={refreshInterval}
							onChange={(value) => setAttributes({ refreshInterval: value })}
							min={5}
							max={60}
						/>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="calendar-preview">
					<div className="calendar-header">
						<div className="calendar-icon">
							{calendarTableIcon}
						</div>
						<h3>{__("House Calendar Availability", "kate-toms-core")}</h3>
					</div>
					
					<div className="calendar-settings-preview">
						{houseId ? (
							<>
								<p><strong>{__("House ID:", "kate-toms-core")}</strong> {houseId}</p>
								<p><strong>{__("Months to display:", "kate-toms-core")}</strong> {monthsToShow}</p>
								<p><strong>{__("Show rates:", "kate-toms-core")}</strong> {showRates ? __("Yes", "kate-toms-core") : __("No", "kate-toms-core")}</p>
								{autoRefresh && (
									<p><strong>{__("Auto refresh:", "kate-toms-core")}</strong> {__("Every", "kate-toms-core")} {refreshInterval} {__("minutes", "kate-toms-core")}</p>
								)}
							</>
						) : (
							<p className="calendar-placeholder">
								{__("Please configure the House ID in the block settings to display the calendar.", "kate-toms-core")}
							</p>
						)}
					</div>
				</div>
			</div>
		</>
	);
}
