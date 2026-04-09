import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

export default function Edit( { attributes, setAttributes } ) {
	const { selectedHouse, displayStyle } = attributes;
	const [ houses, setHouses ] = useState( [] );

	const blockProps = useBlockProps();

	// Fetch houses for the dropdown (only parent houses)
	useEffect( () => {
		const fetchHouses = async () => {
			try {
				const response = await fetch(
					'/wp-json/wp/v2/houses?per_page=100&parent=0'
				);
				const housesData = await response.json();
				setHouses( housesData );
			} catch ( error ) {
				console.error( 'Error fetching houses:', error );
			}
		};
		fetchHouses();
	}, [] );

	// Get the selected house data
	const selectedHouseData = useSelect(
		( select ) => {
			if ( ! selectedHouse ) {
				return null;
			}
			return select( 'core' ).getEntityRecord(
				'postType',
				'houses',
				selectedHouse
			);
		},
		[ selectedHouse ]
	);

	const houseOptions = [
		{ label: __( 'Select a house…', 'kate-toms-core' ), value: 0 },
		...houses.map( ( house ) => ( {
			label: house.title.rendered,
			value: house.id,
		} ) ),
	];

	const styleOptions = [
		{ label: __( 'Coast', 'kate-toms-core' ), value: 'coast' },
		{ label: __( 'Cotswolds', 'kate-toms-core' ), value: 'cotswolds' },
		{ label: __( 'Country', 'kate-toms-core' ), value: 'country' },
		{ label: __( 'Town', 'kate-toms-core' ), value: 'town' },
	];

	const getStyleBackgroundColor = ( style ) => {
		switch ( style ) {
			case 'coast':
				return 'var(--wp--preset--color--coloreight)';
			case 'cotswolds':
				return 'var(--wp--preset--color--colorfive)';
			case 'country':
				return 'var(--wp--preset--color--titlecolorthree)';
			case 'town':
				return 'var(--wp--preset--color--coloreight)';
			default:
				return 'var(--wp--preset--color--coloreight)';
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'House Settings', 'kate-toms-core' ) }>
					<SelectControl
						label={ __( 'Select House', 'kate-toms-core' ) }
						value={ selectedHouse }
						options={ houseOptions }
						onChange={ ( value ) =>
							setAttributes( {
								selectedHouse: parseInt( value ),
							} )
						}
					/>
					<SelectControl
						label={ __( 'Display Style', 'kate-toms-core' ) }
						value={ displayStyle }
						options={ styleOptions }
						onChange={ ( value ) =>
							setAttributes( { displayStyle: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div
					className="wp-block-group has-white-background-color has-background"
					style={ { minHeight: '365px' } }
				>
					{ selectedHouseData ? (
						<>
							{ /* Featured Image Placeholder */ }
							<div
								style={ {
									height: '200px',
									backgroundColor: '#f0f0f0',
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'center',
								} }
							>
								<p>
									{ __( 'Featured Image', 'kate-toms-core' ) }
								</p>
							</div>

							{ /* Title */ }
							<div
								style={ {
									textAlign: 'center',
									padding: 'var(--wp--preset--spacing--40)',
									backgroundColor:
										getStyleBackgroundColor( displayStyle ),
									color: 'white',
									fontSize:
										'var(--wp--preset--font-size--small)',
									fontWeight: '600',
								} }
							>
								{ selectedHouseData.title.rendered }
							</div>

							{ /* Description */ }
							<div
								style={ {
									padding: 'var(--wp--preset--spacing--30)',
								} }
							>
								<p
									style={ {
										fontSize:
											'var(--wp--preset--font-size--x-small)',
									} }
								>
									{ selectedHouseData.meta
										?.brief_description ||
										__(
											'No description available',
											'kate-toms-core'
										) }
								</p>
							</div>

							{ /* Footer */ }
							<div
								style={ {
									padding: 'var(--wp--preset--spacing--30)',
									borderTop:
										'1px solid var(--wp--preset--color--tertiary)',
								} }
							>
								<div
									style={ {
										display: 'flex',
										justifyContent: 'space-between',
									} }
								>
									<div
										style={ {
											display: 'flex',
											gap: '0.2em',
										} }
									>
										<span
											style={ {
												fontSize:
													'var(--wp--preset--font-size--x-small)',
											} }
										>
											{ __( 'Sleeps', 'kate-toms-core' ) }{ ' ' }
											{ selectedHouseData.meta
												?.sleeps_min || 0 }{ ' ' }
											to{ ' ' }
											{ selectedHouseData.meta
												?.sleeps_max || 0 }
										</span>
									</div>
									<div>
										<span
											style={ {
												fontSize:
													'var(--wp--preset--font-size--x-small)',
											} }
										>
											{ selectedHouseData.meta
												?.location_text || '' }
										</span>
									</div>
								</div>
							</div>
						</>
					) : (
						<div style={ { padding: '20px', textAlign: 'center' } }>
							<p>
								{ selectedHouse
									? __(
											'Loading house data…',
											'kate-toms-core'
									  )
									: __(
											'Please select a house from the sidebar',
											'kate-toms-core'
									  ) }
							</p>
						</div>
					) }
				</div>
			</div>
		</>
	);
}
