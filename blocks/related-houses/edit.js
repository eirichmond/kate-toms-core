/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	Button,
	Notice,
	Spinner,
	ToolbarGroup,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { house1Id, house2Id, house3Id, house4Id, saveToSubPages } = attributes;
	const [ isSaving, setIsSaving ] = useState( false );
	const [ saveStatus, setSaveStatus ] = useState( null );
	
	// Lightweight approach: Only load houses when needed, with pagination/search
	const [ housesOptions, setHousesOptions ] = useState( [
		{ label: __( 'Select a house...', 'kate-toms-core' ), value: 0 }
	] );
	const [ selectedHouseTitles, setSelectedHouseTitles ] = useState( {} );
	const [ isLoadingOptions, setIsLoadingOptions ] = useState( false );

	// Load houses on demand with a lighter query (only ID and title)
	const loadHousesOptions = async () => {
		if ( housesOptions.length > 1 ) return; // Already loaded
		
		setIsLoadingOptions( true );
		try {
			const houses = await apiFetch( {
				path: '/wp/v2/houses?status=publish&per_page=-1&_fields=id,title&orderby=title&order=asc&parent=0'
			} );
			
			const options = [
				{ label: __( 'Select a house...', 'kate-toms-core' ), value: 0 },
				...houses.map( house => ( {
					label: house.title.rendered,
					value: house.id,
				} ) ),
			];
			
			setHousesOptions( options );
			
			// Cache selected house titles for display
			const titleMap = {};
			houses.forEach( house => {
				titleMap[house.id] = house.title.rendered;
			} );
			setSelectedHouseTitles( prev => ({ ...prev, ...titleMap }) );
			
		} catch ( error ) {
			console.error( 'Failed to load houses:', error );
		} finally {
			setIsLoadingOptions( false );
		}
	};

	// Load individual house titles for selected houses not in cache
	const loadMissingHouseTitles = async () => {
		const selectedIds = [ house1Id, house2Id, house3Id, house4Id ].filter( id => 
			id > 0 && ! selectedHouseTitles[id] 
		);
		
		if ( selectedIds.length === 0 ) return;
		
		try {
			const houses = await apiFetch( {
				path: `/wp/v2/houses?include=${selectedIds.join(',')}&_fields=id,title&parent=0`
			} );
			
			const titleMap = {};
			houses.forEach( house => {
				titleMap[house.id] = house.title.rendered;
			} );
			setSelectedHouseTitles( prev => ({ ...prev, ...titleMap }) );
		} catch ( error ) {
			console.error( 'Failed to load house titles:', error );
		}
	};

	// Load missing titles when component mounts or IDs change
	useEffect( () => {
		loadMissingHouseTitles();
	}, [ house1Id, house2Id, house3Id, house4Id ] );

	// Get current post (if editing from a house page)
	const currentPost = useSelect( ( select ) => {
		return select( 'core/editor' ).getCurrentPost();
	} );

	const blockProps = useBlockProps();

	// Handle save to sub pages
	const handleSaveToSubPages = async () => {
		if ( ! currentPost?.id ) {
			setSaveStatus( { type: 'error', message: __( 'Unable to determine current post', 'kate-toms-core' ) } );
			return;
		}

		setIsSaving( true );
		setSaveStatus( null );

		try {
			const response = await apiFetch( {
				path: '/kate-toms/v1/related-houses/save-to-subpages',
				method: 'POST',
				data: {
					parent_id: currentPost.id,
					house1_id: house1Id,
					house2_id: house2Id,
					house3_id: house3Id,
					house4_id: house4Id,
				},
			} );

			if ( response.success ) {
				setSaveStatus( { 
					type: 'success', 
					message: __( `Successfully updated ${response.updated_count} sub pages`, 'kate-toms-core' )
				} );
			} else {
				setSaveStatus( { 
					type: 'error', 
					message: response.message || __( 'Failed to save to sub pages', 'kate-toms-core' )
				} );
			}
		} catch ( error ) {
			setSaveStatus( { 
				type: 'error', 
				message: __( 'Error saving to sub pages: ', 'kate-toms-core' ) + error.message
			} );
		} finally {
			setIsSaving( false );
		}
	};

	// Show loading state if houses are being fetched for the first time
	if ( isLoadingOptions ) {
		return (
			<div { ...blockProps }>
				<div style={ { textAlign: 'center', padding: '2rem' } }>
					<Spinner />
					<p>{ __( 'Loading houses...', 'kate-toms-core' ) }</p>
				</div>
			</div>
		);
	}

	return (
		<>
			<BlockControls>
				{ saveToSubPages && currentPost?.post_type === 'houses' && (
					<ToolbarGroup>
						<Button
							onClick={ handleSaveToSubPages }
							disabled={ isSaving }
							variant="secondary"
							icon={ isSaving ? 'update' : 'update-alt' }
						>
							{ isSaving ? __( 'Saving...', 'kate-toms-core' ) : __( 'Save to Sub Pages', 'kate-toms-core' ) }
						</Button>
					</ToolbarGroup>
				) }
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'House Selection', 'kate-toms-core' ) }>
					<SelectControl
						label={ __( 'House 1', 'kate-toms-core' ) }
						value={ house1Id }
						options={ housesOptions }
						onFocus={ loadHousesOptions }
						onChange={ ( value ) => setAttributes( { house1Id: parseInt( value ) } ) }
					/>
					<SelectControl
						label={ __( 'House 2', 'kate-toms-core' ) }
						value={ house2Id }
						options={ housesOptions }
						onFocus={ loadHousesOptions }
						onChange={ ( value ) => setAttributes( { house2Id: parseInt( value ) } ) }
					/>
					<SelectControl
						label={ __( 'House 3', 'kate-toms-core' ) }
						value={ house3Id }
						options={ housesOptions }
						onFocus={ loadHousesOptions }
						onChange={ ( value ) => setAttributes( { house3Id: parseInt( value ) } ) }
					/>
					<SelectControl
						label={ __( 'House 4', 'kate-toms-core' ) }
						value={ house4Id }
						options={ housesOptions }
						onFocus={ loadHousesOptions }
						onChange={ ( value ) => setAttributes( { house4Id: parseInt( value ) } ) }
					/>
				</PanelBody>

				{ currentPost?.post_type === 'houses' && (
					<PanelBody title={ __( 'Sub Pages', 'kate-toms-core' ) }>
						<ToggleControl
							label={ __( 'Save to Sub Pages', 'kate-toms-core' ) }
							help={ __( 'When enabled, this block will be saved to all sub pages of this house, replacing any existing related houses blocks.', 'kate-toms-core' ) }
							checked={ saveToSubPages }
							onChange={ ( value ) => setAttributes( { saveToSubPages: value } ) }
						/>
						
						{ saveToSubPages && (
							<>
								<Button
									onClick={ handleSaveToSubPages }
									disabled={ isSaving || ( house1Id === 0 && house2Id === 0 && house3Id === 0 && house4Id === 0 ) }
									variant="primary"
									isBusy={ isSaving }
								>
									{ isSaving ? __( 'Saving...', 'kate-toms-core' ) : __( 'Save to Sub Pages Now', 'kate-toms-core' ) }
								</Button>

								{ saveStatus && (
									<Notice
										status={ saveStatus.type }
										isDismissible={ true }
										onRemove={ () => setSaveStatus( null ) }
									>
										{ saveStatus.message }
									</Notice>
								) }
							</>
						) }
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{/* Match the frontend pattern structure exactly */}
				<div className="wp-block-group alignfull" style={{
					paddingTop: 0,
					paddingRight: 'var(--wp--preset--spacing--40)',
					paddingBottom: 'var(--wp--preset--spacing--60)',
					paddingLeft: 'var(--wp--preset--spacing--40)'
				}}>
					<h2 className="wp-block-heading has-text-align-center has-x-large-font-size" style={{
						marginTop: 'var(--wp--preset--spacing--50)',
						marginBottom: 'var(--wp--preset--spacing--50)',
						fontStyle: 'normal',
						fontWeight: '300'
					}}>
						{ __( 'Houses you may also like...', 'kate-toms-core' ) }
					</h2>
					
					<div className="wp-block-columns alignwide" style={{
						gap: 'var(--wp--preset--spacing--40)'
					}}>
						{ [ house1Id, house2Id, house3Id, house4Id ].filter( id => id > 0 ).map( ( houseId, index ) => (
							<div key={ houseId } className="wp-block-column">
								<div className="house-preview" style={{
									minHeight: '200px',
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'center',
									background: 'var(--wp--preset--color--tertiary)',
									borderRadius: '8px',
									padding: 'var(--wp--preset--spacing--30)'
								}}>
									<span style={{ 
										color: 'var(--wp--preset--color--charcoal)', 
										fontSize: 'var(--wp--preset--font-size--small)',
										textAlign: 'center'
									}}>
										{ selectedHouseTitles[houseId] || __( 'Loading...', 'kate-toms-core' ) }
									</span>
								</div>
							</div>
						) ) }
					</div>

					{ saveToSubPages && currentPost?.post_type === 'houses' && (
						<div style={{ marginTop: 'var(--wp--preset--spacing--40)' }}>
							<Notice status="info" isDismissible={ false }>
								{ __( 'This block will be saved to all sub pages of this house.', 'kate-toms-core' ) }
							</Notice>
						</div>
					) }
				</div>
			</div>
		</>
	);
}
