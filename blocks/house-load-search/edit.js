/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';

/**
 * Edit function for House Load Search block
 */
export default function Edit({ attributes, setAttributes }) {
	const { postsPerPage = 20 } = attributes;

	const blockProps = useBlockProps();

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
			</InspectorControls>

			<div {...blockProps}>
				<div className="house-load-search-preview">
					<h3>{__('House Load Search Block', 'kate-toms-core')}</h3>
					<p>
						{__('Houses per load:', 'kate-toms-core')} <strong>{postsPerPage}</strong><br />
						{__('Ordering:', 'kate-toms-core')} <strong>{__('Sleeps Max (Descending)', 'kate-toms-core')}</strong><br />
						{__('Style:', 'kate-toms-core')} <strong>{__('Cotswolds', 'kate-toms-core')}</strong>
					</p>
					<p><em>{__('Houses will load with infinite scroll on the frontend.', 'kate-toms-core')}</em></p>
				</div>
			</div>
		</>
	);
}
