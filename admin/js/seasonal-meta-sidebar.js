/**
 * Seasonal Meta Sidebar
 * Adds a sidebar panel to edit seasonal post type meta fields
 */

(function (wp) {
	console.log('Seasonal Meta Sidebar script loaded');

	if (!wp || !wp.plugins || !wp.editor) {
		console.error('Required WordPress packages not available', { wp });
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { PanelRow } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;

	const SeasonalMetaSidebar = function() {
		const postType = useSelect(function(select) {
			return select('core/editor').getCurrentPostType();
		}, []);

		console.log('Current post type:', postType);

		// Only show for 'seasonal' post type
		if (postType !== 'seasonal') {
			console.log('Not a seasonal post, hiding panel');
			return null;
		}

		const { editPost } = useDispatch('core/editor');

		const meta = useSelect(function(select) {
			return select('core/editor').getEditedPostAttribute('meta') || {};
		}, []);

		console.log('Meta values:', meta);

		const beginning = meta.beginning || '';
		const ending = meta.ending || '';
		const periodsToInclude = meta.periods_to_include || [];

		// Period options (values must match legacy database format)
		const periodOptions = [
			{ label: __('Weeks', 'kate-toms-core'), value: 'Week' },
			{ label: __('Weekend (2 night)', 'kate-toms-core'), value: '2 night weekend' },
			{ label: __('Weekend (3 night)', 'kate-toms-core'), value: '3 night weekend' },
			{ label: __('Midweeks', 'kate-toms-core'), value: 'Midweek' },
			{ label: __('5 nights (Christmas)', 'kate-toms-core'), value: '5 night' },
		];

		// Update meta field
		const updateMeta = function(key, value) {
			editPost({ meta: { [key]: value } });
		};

		// Toggle period selection
		const togglePeriod = function(value) {
			const newPeriods = periodsToInclude.includes(value)
				? periodsToInclude.filter(function(p) { return p !== value; })
				: periodsToInclude.concat([value]);
			updateMeta('periods_to_include', newPeriods);
		};

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'seasonal-meta-panel',
				title: __('Seasonal Offer Settings', 'kate-toms-core'),
				className: 'seasonal-meta-panel'
			},
			// Beginning Date
			el(
				PanelRow,
				{},
				el(
					'div',
					{ style: { width: '100%' } },
					el(
						'label',
						{
							htmlFor: 'seasonal-beginning',
							style: { display: 'block', marginBottom: '8px', fontWeight: 600 }
						},
						__('Beginning Date', 'kate-toms-core')
					),
					el('input', {
						id: 'seasonal-beginning',
						type: 'date',
						value: beginning,
						onChange: function(e) { updateMeta('beginning', e.target.value); },
						style: { width: '100%' }
					})
				)
			),
			// Ending Date
			el(
				PanelRow,
				{},
				el(
					'div',
					{ style: { width: '100%', marginTop: '16px' } },
					el(
						'label',
						{
							htmlFor: 'seasonal-ending',
							style: { display: 'block', marginBottom: '8px', fontWeight: 600 }
						},
						__('Ending Date', 'kate-toms-core')
					),
					el('input', {
						id: 'seasonal-ending',
						type: 'date',
						value: ending,
						onChange: function(e) { updateMeta('ending', e.target.value); },
						style: { width: '100%' }
					})
				)
			),
			// Periods to Include
			el(
				PanelRow,
				{},
				el(
					'div',
					{ style: { width: '100%', marginTop: '16px' } },
					el(
						'label',
						{ style: { display: 'block', marginBottom: '8px', fontWeight: 600 } },
						__('Periods to Include', 'kate-toms-core')
					),
					el(
						'div',
						{ style: { display: 'flex', flexDirection: 'column', gap: '8px' } },
						periodOptions.map(function(option) {
							return el(
								'label',
								{
									key: option.value,
									style: { display: 'flex', alignItems: 'center', gap: '8px' }
								},
								el('input', {
									type: 'checkbox',
									checked: periodsToInclude.includes(option.value),
									onChange: function() { togglePeriod(option.value); }
								}),
								el('span', {}, option.label)
							);
						})
					)
				)
			)
		);
	};

	registerPlugin('seasonal-meta-sidebar', {
		render: SeasonalMetaSidebar,
		icon: 'calendar-alt',
	});
})(window.wp);
