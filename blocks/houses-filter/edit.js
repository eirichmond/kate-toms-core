/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	DatePicker,
	ButtonGroup,
	Button,
	ToggleControl,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * Edit component for the Houses Filter block.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const {
		date,
		dtype,
		size,
		local,
		feature,
		displayStyle
	} = attributes;

	// State for taxonomy terms
	const [locations, setLocations] = useState([]);
	const [features, setFeatures] = useState([]);

	// Fetch taxonomy terms on mount
	useEffect(() => {
		apiFetch({ path: '/wp/v2/location' }).then((terms) => {
			setLocations(terms.map(term => ({
				label: term.name,
				value: term.slug
			})));
		});

		apiFetch({ path: '/wp/v2/feature' }).then((terms) => {
			setFeatures(terms.map(term => ({
				label: term.name,
				value: term.slug
			})));
		});
	}, []);

	// Size options
	const sizeOptions = [
		{ label: __('2-10 People', 'kate-and-toms-houses-filter-search'), value: '2' },
		{ label: __('10-20 People', 'kate-and-toms-houses-filter-search'), value: '10' },
		{ label: __('20+ People', 'kate-and-toms-houses-filter-search'), value: '20' }
	];

	// Duration type options
	const durationOptions = [
		{ label: __('Weekend', 'kate-and-toms-houses-filter-search'), value: '1' },
		{ label: __('Week', 'kate-and-toms-houses-filter-search'), value: '2' },
		{ label: __('Midweek', 'kate-and-toms-houses-filter-search'), value: '3' }
	];

	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody title={__('Display Settings', 'kate-and-toms-houses-filter-search')}>
					<ToggleControl
						label={__('Use Dropdown Style', 'kate-and-toms-houses-filter-search')}
						checked={displayStyle === 'dropdown'}
						onChange={(isDropdown) => 
							setAttributes({ displayStyle: isDropdown ? 'dropdown' : 'buttons' })
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div className="houses-filter">
				{/* Date Picker */}
				<div className="houses-filter__field">
					<label className="houses-filter__label">
						{__('Select Date', 'kate-and-toms-houses-filter-search')}
					</label>
					<DatePicker
						currentDate={date}
						onChange={(newDate) => {
							const formattedDate = newDate ? new Date(newDate).toLocaleDateString('en-GB').split('/').join('-') : '';
							setAttributes({ date: formattedDate });
						}}
					/>
				</div>

				{/* Duration Type */}
				<div className="houses-filter__field">
					<label className="houses-filter__label">
						{__('Duration', 'kate-and-toms-houses-filter-search')}
					</label>
					{displayStyle === 'dropdown' ? (
						<SelectControl
							value={dtype}
							options={[
								{ label: __('Select Duration', 'kate-and-toms-houses-filter-search'), value: '' },
								...durationOptions
							]}
							onChange={(value) => setAttributes({ dtype: value })}
						/>
					) : (
						<ButtonGroup>
							{durationOptions.map((option) => (
								<Button
									key={option.value}
									isPrimary={dtype === option.value}
									onClick={() => setAttributes({ dtype: option.value })}
								>
									{option.label}
								</Button>
							))}
						</ButtonGroup>
					)}
				</div>

				{/* Size */}
				<div className="houses-filter__field">
					<label className="houses-filter__label">
						{__('Size', 'kate-and-toms-houses-filter-search')}
					</label>
					{displayStyle === 'dropdown' ? (
						<SelectControl
							value={size}
							options={[
								{ label: __('Select Size', 'kate-and-toms-houses-filter-search'), value: '' },
								...sizeOptions
							]}
							onChange={(value) => setAttributes({ size: value })}
						/>
					) : (
						<ButtonGroup>
							{sizeOptions.map((option) => (
								<Button
									key={option.value}
									isPrimary={size === option.value}
									onClick={() => setAttributes({ size: option.value })}
								>
									{option.label}
								</Button>
							))}
						</ButtonGroup>
					)}
				</div>

				{/* Location */}
				<div className="houses-filter__field">
					<label className="houses-filter__label">
						{__('Location', 'kate-and-toms-houses-filter-search')}
					</label>
					{displayStyle === 'dropdown' ? (
						<SelectControl
							value={local}
							options={[
								{ label: __('Select Location', 'kate-and-toms-houses-filter-search'), value: '' },
								...locations
							]}
							onChange={(value) => setAttributes({ local: value })}
						/>
					) : (
						<ButtonGroup>
							{locations.map((option) => (
								<Button
									key={option.value}
									isPrimary={local === option.value}
									onClick={() => setAttributes({ local: option.value })}
								>
									{option.label}
								</Button>
							))}
						</ButtonGroup>
					)}
				</div>

				{/* Features */}
				<div className="houses-filter__field">
					<label className="houses-filter__label">
						{__('Features', 'kate-and-toms-houses-filter-search')}
					</label>
					{displayStyle === 'dropdown' ? (
						<SelectControl
							value={feature}
							options={[
								{ label: __('Select Feature', 'kate-and-toms-houses-filter-search'), value: '' },
								...features
							]}
							onChange={(value) => setAttributes({ feature: value })}
						/>
					) : (
						<ButtonGroup>
							{features.map((option) => (
								<Button
									key={option.value}
									isPrimary={feature === option.value}
									onClick={() => setAttributes({ feature: option.value })}
								>
									{option.label}
								</Button>
							))}
						</ButtonGroup>
					)}
				</div>
			</div>
		</div>
	);
}
