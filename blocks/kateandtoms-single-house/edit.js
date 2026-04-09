import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	Button,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import ServerSideRender from '@wordpress/server-side-render';
import { useState, useEffect } from '@wordpress/element';

export default function Edit( { attributes, setAttributes } ) {
	const { selectedPostId, category } = attributes;
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ showSuggestions, setShowSuggestions ] = useState( false );
	const [ isSearching, setIsSearching ] = useState( false );
	const [ isUserTyping, setIsUserTyping ] = useState( false );

	const categoryOptions = [
		{ label: __( 'Coast', 'kate-toms-core' ), value: 'coast' },
		{ label: __( 'Cotswolds', 'kate-toms-core' ), value: 'cotswolds' },
		{ label: __( 'Country', 'kate-toms-core' ), value: 'country' },
		{ label: __( 'Town', 'kate-toms-core' ), value: 'town' },
	];

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

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'House Settings', 'kate-toms-core' ) }>
					<SelectControl
						label={ __( 'Category', 'kate-toms-core' ) }
						value={ category }
						options={ categoryOptions }
						onChange={ ( value ) =>
							setAttributes( { category: value } )
						}
					/>

					<div style={ { marginTop: '16px' } }>
						<label
							style={ {
								display: 'block',
								marginBottom: '8px',
								fontWeight: 'bold',
							} }
						>
							{ __( 'Search Houses', 'kate-toms-core' ) }
						</label>
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

							{ showSuggestions && searchResults.length > 0 && (
								<div
									style={ {
										position: 'absolute',
										top: '100%',
										left: 0,
										right: 0,
										backgroundColor: 'white',
										border: '1px solid #ccc',
										borderRadius: '4px',
										boxShadow: '0 2px 6px rgba(0,0,0,0.1)',
										zIndex: 1000,
										maxHeight: '200px',
										overflowY: 'auto',
									} }
								>
									{ searchResults.map( ( house ) => (
										<div
											key={ house.id }
											onClick={ () =>
												handleHouseSelect( house )
											}
											style={ {
												padding: '8px 12px',
												cursor: 'pointer',
												borderBottom: '1px solid #eee',
												backgroundColor:
													selectedPostId === house.id
														? '#e6f3ff'
														: 'white',
											} }
											onMouseEnter={ ( e ) =>
												( e.target.style.backgroundColor =
													'#f5f5f5' )
											}
											onMouseLeave={ ( e ) =>
												( e.target.style.backgroundColor =
													selectedPostId === house.id
														? '#e6f3ff'
														: 'white' )
											}
										>
											<strong>
												{ house.title.rendered }
											</strong>
											{ house.meta &&
												house.meta.location_text && (
													<div
														style={ {
															fontSize: '12px',
															color: '#666',
															marginTop: '2px',
														} }
													>
														{
															house.meta
																.location_text
														}
													</div>
												) }
										</div>
									) ) }
								</div>
							) }
						</div>
					</div>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="kate-toms-core/kateandtoms-single-house"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
