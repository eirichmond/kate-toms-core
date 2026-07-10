import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	Button,
	DatePicker,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { dateI18n } from '@wordpress/date';
import { useState, useEffect } from '@wordpress/element';

/**
 * Human-readable label for a placeholder location key.
 *
 * @param {string} location Location key.
 *
 * @return {string} Display label, falling back to the raw key.
 */
function placeholderLocationLabel( location ) {
	const labels = {
		cotswolds: __( 'Cotswolds', 'kate-toms-core' ),
		coast: __( 'Coast', 'kate-toms-core' ),
		country: __( 'Country', 'kate-toms-core' ),
		town: __( 'Town', 'kate-toms-core' ),
	};

	return labels[ location ] || location;
}

export default function Edit( { attributes, setAttributes } ) {
	const {
		selectedPostId,
		offer,
		offerDate,
		isPlaceholder,
		placeholderLocation,
	} = attributes;
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ showSuggestions, setShowSuggestions ] = useState( false );
	const [ isUserTyping, setIsUserTyping ] = useState( false );

	const blockProps = useBlockProps( {
		className: 'kate-toms-single-house-editor',
	} );

	// Search functionality
	const searchResults = useSelect(
		( select ) => {
			if ( ! isUserTyping || ! searchTerm || searchTerm.length < 2 ) {
				return [];
			}
			const { getEntityRecords } = select( coreStore );
			return (
				getEntityRecords( 'postType', 'houses', {
					search: searchTerm,
					// Restrict matching to the post title only. The default
					// REST search for houses also weights location/content
					// (e.g. searching the village "Blockley" returns
					// "Wellacres House"), which is confusing here — editors
					// expect to find a house by typing its name. Scoped to
					// this block; global search is unaffected.
					search_columns: [ 'post_title' ],
					status: 'publish',
					parent: 0,
					per_page: 10,
				} ) || []
			);
		},
		[ searchTerm, isUserTyping ]
	);


	const selectedHouseData = useSelect(
		( select ) => {
			if ( ! selectedPostId ) {
				return null;
			}

			const { getEntityRecord } = select( coreStore );
			return getEntityRecord( 'postType', 'houses', selectedPostId );
		},
		[ selectedPostId ]
	);

	// Set initial search term from selected house
	useEffect( () => {
		if ( selectedHouseData && ! isUserTyping ) {
			setSearchTerm( selectedHouseData.title.rendered );
		}
	}, [ selectedHouseData, isUserTyping ] );

	// Handle search effect
	useEffect( () => {
		if ( isUserTyping && searchTerm && searchResults.length > 0 ) {
			setShowSuggestions( true );
		} else {
			setShowSuggestions( false );
		}
	}, [ searchResults, isUserTyping, searchTerm ] );

	const handleHouseSelect = ( house ) => {
		setAttributes( { selectedPostId: house.id } );
		setSearchTerm( house.title.rendered );
		setShowSuggestions( false );
		setIsUserTyping( false );
	};

	const handleClearSelection = () => {
		setAttributes( { selectedPostId: 0 } );
		setSearchTerm( '' );
		setShowSuggestions( false );
		setIsUserTyping( false );
	};

	const handleSearchChange = ( value ) => {
		setSearchTerm( value );
		setIsUserTyping( true );

		if ( selectedHouseData && value !== selectedHouseData.title.rendered ) {
			setAttributes( { selectedPostId: 0 } );
		}
	};

	const handleSearchFocus = () => {
		setIsUserTyping( true );
		if ( searchResults.length > 0 ) {
			setShowSuggestions( true );
		}
	};

	const handleSearchBlur = () => {
		setTimeout( () => {
			setShowSuggestions( false );
		}, 200 );
	};

	// Compact card summary values.
	const houseTitle = selectedHouseData?.title?.rendered || '';
	const formattedDate = offerDate ? dateI18n( 'j M Y', offerDate ) : '';
	const houseMeta = [ offer, formattedDate ].filter( Boolean ).join( ' · ' );

	// Flag offers whose date has passed so editors can spot them for removal
	// or updating. Mirrors the strict "before today" cutoff that
	// Kate_Toms_Special_Offers_Grid::order_cards() uses to drop expired
	// offers from the front end (today itself is not expired).
	let isExpired = false;
	if ( offerDate ) {
		const today = new Date();
		today.setHours( 0, 0, 0, 0 );
		const [ offerYear, offerMonth, offerDay ] = offerDate
			.slice( 0, 10 )
			.split( '-' )
			.map( Number );
		const offerDateOnly = new Date( offerYear, offerMonth - 1, offerDay );
		isExpired = offerDateOnly < today;
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'House Settings', 'kate-toms-core' ) }>
					<ToggleControl
						label={ __( 'Random Placeholder', 'kate-toms-core' ) }
						help={ __(
							'Override the house and show a random advert from the selected location instead.',
							'kate-toms-core'
						) }
						checked={ !! isPlaceholder }
						onChange={ ( value ) =>
							setAttributes( { isPlaceholder: value } )
						}
					/>

					{ isPlaceholder && (
						<div style={ { marginTop: '16px' } }>
							<SelectControl
								label={ __( 'Location', 'kate-toms-core' ) }
								value={ placeholderLocation }
								options={ [
									{
										label: __(
											'Select location…',
											'kate-toms-core'
										),
										value: '',
									},
									{
										label: __(
											'Cotswolds',
											'kate-toms-core'
										),
										value: 'cotswolds',
									},
									{
										label: __( 'Coast', 'kate-toms-core' ),
										value: 'coast',
									},
									{
										label: __(
											'Country',
											'kate-toms-core'
										),
										value: 'country',
									},
									{
										label: __( 'Town', 'kate-toms-core' ),
										value: 'town',
									},
								] }
								onChange={ ( value ) =>
									setAttributes( {
										placeholderLocation: value,
									} )
								}
							/>
						</div>
					) }

					{ ! isPlaceholder && (
						<>
							<div>
								<span
									className="kate-toms-single-house-field-label"
									style={ {
										display: 'block',
										marginBottom: '8px',
										fontWeight: 'bold',
									} }
								>
									{ __( 'Search Houses', 'kate-toms-core' ) }
								</span>
								<div style={ { position: 'relative' } }>
									<TextControl
										value={ searchTerm }
										onChange={ handleSearchChange }
										onFocus={ handleSearchFocus }
										onBlur={ handleSearchBlur }
										placeholder={ __(
											'Type to search houses…',
											'kate-toms-core'
										) }
										help={
											selectedPostId > 0
												? __(
														'Click to search for a different house',
														'kate-toms-core'
												  )
												: ''
										}
									/>

									{ selectedPostId > 0 && (
										<Button
											isSmall
											variant="secondary"
											onClick={ handleClearSelection }
											style={ { marginTop: '8px' } }
										>
											{ __(
												'Clear Selection',
												'kate-toms-core'
											) }
										</Button>
									) }

									{ showSuggestions &&
										searchResults.length > 0 && (
											<div
												style={ {
													position: 'absolute',
													top: '100%',
													left: 0,
													right: 0,
													backgroundColor: 'white',
													border: '1px solid #ccc',
													borderRadius: '4px',
													boxShadow:
														'0 2px 6px rgba(0,0,0,0.1)',
													zIndex: 1000,
													maxHeight: '200px',
													overflowY: 'auto',
												} }
											>
												{ searchResults.map(
													( house ) => (
														<div
															key={ house.id }
															role="button"
															tabIndex={ 0 }
															onClick={ () =>
																handleHouseSelect(
																	house
																)
															}
															onKeyDown={ (
																event
															) => {
																if (
																	'Enter' ===
																		event.key ||
																	' ' ===
																		event.key
																) {
																	event.preventDefault();
																	handleHouseSelect(
																		house
																	);
																}
															} }
															style={ {
																padding:
																	'8px 12px',
																cursor: 'pointer',
																borderBottom:
																	'1px solid #eee',
																backgroundColor:
																	selectedPostId ===
																	house.id
																		? '#e6f3ff'
																		: 'white',
															} }
															onMouseEnter={ (
																e
															) =>
																( e.target.style.backgroundColor =
																	'#f5f5f5' )
															}
															onMouseLeave={ (
																e
															) =>
																( e.target.style.backgroundColor =
																	selectedPostId ===
																	house.id
																		? '#e6f3ff'
																		: 'white' )
															}
														>
															<strong>
																{
																	house.title
																		.rendered
																}
															</strong>
															{ house.meta &&
																house.meta
																	.location_text && (
																	<div
																		style={ {
																			fontSize:
																				'12px',
																			color: '#666',
																			marginTop:
																				'2px',
																		} }
																	>
																		{
																			house
																				.meta
																				.location_text
																		}
																	</div>
																) }
														</div>
													)
												) }
											</div>
										) }
								</div>
							</div>

							<div style={ { marginTop: '16px' } }>
								<span
									className="kate-toms-single-house-field-label"
									style={ {
										display: 'block',
										marginBottom: '8px',
										fontWeight: 'bold',
									} }
								>
									{ __( 'Offer', 'kate-toms-core' ) }
								</span>
								<TextControl
									value={ offer }
									onChange={ ( value ) =>
										setAttributes( { offer: value } )
									}
									help={ __(
										'Enter the special offer text',
										'kate-toms-core'
									) }
								/>
							</div>

							<div style={ { marginTop: '16px' } }>
								<span
									className="kate-toms-single-house-field-label"
									style={ {
										display: 'block',
										marginBottom: '8px',
										fontWeight: 'bold',
									} }
								>
									{ __( 'Offer Date', 'kate-toms-core' ) }
								</span>
								<DatePicker
									currentDate={ offerDate }
									onChange={ ( value ) =>
										setAttributes( { offerDate: value } )
									}
								/>
							</div>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ isPlaceholder ? (
					<div className="kate-toms-single-house-card kate-toms-single-house-card--placeholder">
						<span
							className="kate-toms-single-house-card__icon"
							aria-hidden="true"
						>
							🖼️
						</span>
						<div className="kate-toms-single-house-card__body">
							<span className="kate-toms-single-house-card__title">
								{ __( 'Random placeholder', 'kate-toms-core' ) }
							</span>
							<span className="kate-toms-single-house-card__meta">
								{ placeholderLocation
									? placeholderLocationLabel(
											placeholderLocation
									  )
									: __(
											'Choose a location in the sidebar',
											'kate-toms-core'
									  ) }
							</span>
						</div>
					</div>
				) : (
					<div
						className={
							'kate-toms-single-house-card' +
							( selectedPostId
								? ''
								: ' kate-toms-single-house-card--empty' ) +
							( selectedPostId && isExpired
								? ' kate-toms-single-house-card--expired'
								: '' )
						}
					>
						<span
							className="kate-toms-single-house-card__icon"
							aria-hidden="true"
						>
							🏠
						</span>
						<div className="kate-toms-single-house-card__body">
							<span className="kate-toms-single-house-card__title">
								{ selectedPostId
									? houseTitle ||
									  __( 'Loading…', 'kate-toms-core' )
									: __(
											'No house selected',
											'kate-toms-core'
									  ) }
							</span>
							<span className="kate-toms-single-house-card__meta">
								{ selectedPostId
									? houseMeta ||
									  __(
											'Add an offer in the sidebar',
											'kate-toms-core'
									  )
									: __(
											'Choose a house in the sidebar',
											'kate-toms-core'
									  ) }
							</span>
						</div>
					</div>
				) }
			</div>
		</>
	);
}
