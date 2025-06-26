import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
	PanelBody,
	TextControl,
	ToggleControl,
	RangeControl,
	Notice
} from "@wordpress/components";
import { calendar } from "@wordpress/icons";

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
							{calendar}
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
