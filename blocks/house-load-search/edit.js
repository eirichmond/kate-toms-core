/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, Spinner, FormTokenField } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Edit function for House Load Search block
 */
export default function Edit({ attributes, setAttributes }) {
	const {
		postsPerPage = 20,
		locationTermIds = [],
		featureTermIds = [],
		sizeTermIds = [],
		typeTermIds = [],
		occasionTermIds = [],
	} = attributes;

	const blockProps = useBlockProps();

	const { taxonomyTerms, isLoading } = useSelect((select) => {
		const { getEntityRecords, isResolving } = select(coreStore);

		const defaultQuery = {
			per_page: -1,
			orderby: 'name',
			order: 'asc',
		};

		return {
			taxonomyTerms: {
				location: getEntityRecords('taxonomy', 'location', defaultQuery),
				feature: getEntityRecords('taxonomy', 'feature', defaultQuery),
				size: getEntityRecords('taxonomy', 'size', defaultQuery),
				type: getEntityRecords('taxonomy', 'type', defaultQuery),
				occasion: getEntityRecords('taxonomy', 'occasion', defaultQuery),
			},
			isLoading: {
				location: isResolving('getEntityRecords', ['taxonomy', 'location', defaultQuery]),
				feature: isResolving('getEntityRecords', ['taxonomy', 'feature', defaultQuery]),
				size: isResolving('getEntityRecords', ['taxonomy', 'size', defaultQuery]),
				type: isResolving('getEntityRecords', ['taxonomy', 'type', defaultQuery]),
				occasion: isResolving('getEntityRecords', ['taxonomy', 'occasion', defaultQuery]),
			},
		};
	}, []);

	// Helper function to build token field props for a taxonomy.
	const buildTokenFieldProps = (taxonomy, termIds, attributeName) => {
		const terms = taxonomyTerms[taxonomy] || [];
		const suggestions = terms.map((term) => term.name);
		const selectedNames = termIds
			.map((id) => terms.find((t) => t.id === id)?.name)
			.filter(Boolean);

		const onChange = (tokens) => {
			const newIds = tokens
				.map((token) => terms.find((t) => t.name === token)?.id)
				.filter(Boolean);
			setAttributes({ [attributeName]: newIds });
		};

		return { suggestions, selectedNames, onChange };
	};

	const locationProps = buildTokenFieldProps('location', locationTermIds, 'locationTermIds');
	const featureProps = buildTokenFieldProps('feature', featureTermIds, 'featureTermIds');
	const sizeProps = buildTokenFieldProps('size', sizeTermIds, 'sizeTermIds');
	const typeProps = buildTokenFieldProps('type', typeTermIds, 'typeTermIds');
	const occasionProps = buildTokenFieldProps('occasion', occasionTermIds, 'occasionTermIds');

	// Display text for preview.
	const formatDisplay = (names) => names.length > 0 ? names.join(', ') : __('None', 'kate-toms-core');

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Load Settings', 'kate-toms-core')}>
					<RangeControl
						label={__('Houses per load', 'kate-toms-core')}
						value={postsPerPage}
						onChange={(value) => setAttributes({ postsPerPage: value })}
						min={10}
						max={50}
						step={10}
						help={__('Number of houses to load initially and on each scroll', 'kate-toms-core')}
					/>
				</PanelBody>
				<PanelBody title={__('Filter Settings', 'kate-toms-core')}>
					{isLoading.location ? (
						<Spinner />
					) : (
						<FormTokenField
							label={__('Locations', 'kate-toms-core')}
							value={locationProps.selectedNames}
							suggestions={locationProps.suggestions}
							onChange={locationProps.onChange}
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
						/>
					)}
					{isLoading.feature ? (
						<Spinner />
					) : (
						<FormTokenField
							label={__('Features', 'kate-toms-core')}
							value={featureProps.selectedNames}
							suggestions={featureProps.suggestions}
							onChange={featureProps.onChange}
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
						/>
					)}
					{isLoading.size ? (
						<Spinner />
					) : (
						<FormTokenField
							label={__('Sizes', 'kate-toms-core')}
							value={sizeProps.selectedNames}
							suggestions={sizeProps.suggestions}
							onChange={sizeProps.onChange}
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
						/>
					)}
					{isLoading.type ? (
						<Spinner />
					) : (
						<FormTokenField
							label={__('Property Types', 'kate-toms-core')}
							value={typeProps.selectedNames}
							suggestions={typeProps.suggestions}
							onChange={typeProps.onChange}
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
						/>
					)}
					{isLoading.occasion ? (
						<Spinner />
					) : (
						<FormTokenField
							label={__('Occasions', 'kate-toms-core')}
							value={occasionProps.selectedNames}
							suggestions={occasionProps.suggestions}
							onChange={occasionProps.onChange}
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
						/>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="house-load-search-preview">
					<h3>{__('House Load Search Block', 'kate-toms-core')}</h3>
					<p>
						{__('Houses per load:', 'kate-toms-core')} <strong>{postsPerPage}</strong><br />
						{__('Ordering:', 'kate-toms-core')} <strong>{__('Sleeps Max (Descending)', 'kate-toms-core')}</strong><br />
						{__('Locations:', 'kate-toms-core')} <strong>{formatDisplay(locationProps.selectedNames)}</strong><br />
						{__('Features:', 'kate-toms-core')} <strong>{formatDisplay(featureProps.selectedNames)}</strong><br />
						{__('Sizes:', 'kate-toms-core')} <strong>{formatDisplay(sizeProps.selectedNames)}</strong><br />
						{__('Types:', 'kate-toms-core')} <strong>{formatDisplay(typeProps.selectedNames)}</strong><br />
						{__('Occasions:', 'kate-toms-core')} <strong>{formatDisplay(occasionProps.selectedNames)}</strong>
					</p>
					<p><em>{__('Houses will load with infinite scroll on the frontend.', 'kate-toms-core')}</em></p>
				</div>
			</div>
		</>
	);
}
