/**
 * Availability Meta Sidebar
 * Adds a sidebar panel to edit availability post type meta fields
 * @param wp
 */

( function ( wp ) {
	console.log( 'Availability Meta Sidebar script loaded' );

	if ( ! wp || ! wp.plugins || ! wp.editor ) {
		console.error( 'Required WordPress packages not available', { wp } );
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { PanelRow } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;

	const AvailabilityMetaSidebar = function () {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		console.log( 'Current post type:', postType );

		// Only show for 'availability' post type
		if ( postType !== 'availability' ) {
			console.log( 'Not an availability post, hiding panel' );
			return null;
		}

		const { editPost } = useDispatch( 'core/editor' );

		const meta = useSelect( function ( select ) {
			return (
				select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}
			);
		}, [] );

		console.log( 'Meta values:', meta );

		const rollingUpcomingPeriod = meta.rolling_upcoming_period || '6';
		const periodsToInclude = meta.periods_to_include || [];

		// Rolling period options (in weeks)
		const rollingPeriodOptions = [
			{ label: __( '1 Week', 'kate-toms-core' ), value: '1' },
			{ label: __( '4 Weeks', 'kate-toms-core' ), value: '4' },
			{ label: __( '6 Weeks', 'kate-toms-core' ), value: '6' },
			{ label: __( '8 Weeks', 'kate-toms-core' ), value: '8' },
			{ label: __( '12 Weeks', 'kate-toms-core' ), value: '12' },
		];

		// Period options (values must match legacy database format)
		const periodOptions = [
			{ label: __( 'Weeks', 'kate-toms-core' ), value: 'Week' },
			{
				label: __( 'Weekend (2 night)', 'kate-toms-core' ),
				value: '2 night weekend',
			},
			{
				label: __( 'Weekend (3 night)', 'kate-toms-core' ),
				value: '3 night weekend',
			},
			{ label: __( 'Midweeks', 'kate-toms-core' ), value: 'Midweek' },
			{
				label: __( '5 nights (Christmas)', 'kate-toms-core' ),
				value: '5 night',
			},
		];

		// Update meta field
		const updateMeta = function ( key, value ) {
			editPost( { meta: { [ key ]: value } } );
		};

		// Toggle period selection
		const togglePeriod = function ( value ) {
			const newPeriods = periodsToInclude.includes( value )
				? periodsToInclude.filter( function ( p ) {
						return p !== value;
				  } )
				: periodsToInclude.concat( [ value ] );
			updateMeta( 'periods_to_include', newPeriods );
		};

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'availability-meta-panel',
				title: __( 'Availability Settings', 'kate-toms-core' ),
				className: 'availability-meta-panel',
			},
			// Rolling Upcoming Period
			el(
				PanelRow,
				{},
				el(
					'div',
					{ style: { width: '100%' } },
					el(
						'label',
						{
							htmlFor: 'availability-rolling-period',
							style: {
								display: 'block',
								marginBottom: '8px',
								fontWeight: 600,
							},
						},
						__( 'Rolling Upcoming Period', 'kate-toms-core' )
					),
					el(
						'select',
						{
							id: 'availability-rolling-period',
							value: rollingUpcomingPeriod,
							onChange( e ) {
								updateMeta(
									'rolling_upcoming_period',
									e.target.value
								);
							},
							style: { width: '100%' },
						},
						rollingPeriodOptions.map( function ( option ) {
							return el(
								'option',
								{
									key: option.value,
									value: option.value,
								},
								option.label
							);
						} )
					)
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
						{
							style: {
								display: 'block',
								marginBottom: '8px',
								fontWeight: 600,
							},
						},
						__( 'Periods to Include', 'kate-toms-core' )
					),
					el(
						'div',
						{
							style: {
								display: 'flex',
								flexDirection: 'column',
								gap: '8px',
							},
						},
						periodOptions.map( function ( option ) {
							return el(
								'label',
								{
									key: option.value,
									style: {
										display: 'flex',
										alignItems: 'center',
										gap: '8px',
									},
								},
								el( 'input', {
									type: 'checkbox',
									checked: periodsToInclude.includes(
										option.value
									),
									onChange() {
										togglePeriod( option.value );
									},
								} ),
								el( 'span', {}, option.label )
							);
						} )
					)
				)
			)
		);
	};

	registerPlugin( 'availability-meta-sidebar', {
		render: AvailabilityMetaSidebar,
		icon: 'calendar-alt',
	} );
} )( window.wp );
