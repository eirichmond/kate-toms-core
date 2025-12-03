/**
 * Houses Meta Sidebar
 * Adds a sidebar panel to edit houses post type meta fields
 */

(function (wp) {
	console.log('Houses Meta Sidebar script loaded');

	if (!wp || !wp.plugins || !wp.editor) {
		console.error('Required WordPress packages not available', { wp });
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { PanelRow, CheckboxControl } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;

	const HousesMetaSidebar = function() {
		const postType = useSelect(function(select) {
			return select('core/editor').getCurrentPostType();
		}, []);

		console.log('Current post type:', postType);

		// Only show for 'houses' post type
		if (postType !== 'houses') {
			console.log('Not a houses post, hiding panel');
			return null;
		}

		const { editPost } = useDispatch('core/editor');

		const meta = useSelect(function(select) {
			return select('core/editor').getEditedPostAttribute('meta') || {};
		}, []);

		console.log('Meta values:', meta);

		const signatureCollectionEnabled = meta._signature_collection_enabled === '1';

		// Update meta field
		const updateSignatureCollection = function(value) {
			console.log('Updating _signature_collection_enabled to:', value ? '1' : '0');
			editPost({ meta: { _signature_collection_enabled: value ? '1' : '0' } });
		};

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'houses-meta-panel',
				title: __('House Settings', 'kate-toms-core'),
				className: 'houses-meta-panel'
			},
			el(
				PanelRow,
				{},
				el(CheckboxControl, {
					label: __('Signature Collection', 'kate-toms-core'),
					help: __('Enable this house as part of the Signature Collection', 'kate-toms-core'),
					checked: signatureCollectionEnabled,
					onChange: updateSignatureCollection
				})
			)
		);
	};

	registerPlugin('houses-meta-sidebar', {
		render: HousesMetaSidebar,
		icon: 'admin-home',
	});
})(window.wp);
