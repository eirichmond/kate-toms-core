import { useState, useCallback } from '@wordpress/element';
import { TextControl, Button, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { debounce } from '@wordpress/compose';

const MIN_QUERY_LENGTH = 2;

/**
 * Step 1: CRM house search with debounced typeahead and title override.
 *
 * @param {Object}   props
 * @param {string}   props.displayTitle Current display title (preserved on back).
 * @param {Function} props.onConfirm    Called with (crmId, displayTitle) on Next.
 */
export default function StepSearch( { displayTitle: initialTitle, onConfirm } ) {
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ isSearching, setIsSearching ] = useState( false );
	const [ searchError, setSearchError ] = useState( null );
	const [ selectedHouse, setSelectedHouse ] = useState( null );
	const [ titleOverride, setTitleOverride ] = useState( initialTitle );

	const runSearch = useCallback(
		debounce( async ( searchQuery ) => {
			if ( searchQuery.length < MIN_QUERY_LENGTH ) {
				setResults( [] );
				return;
			}
			setIsSearching( true );
			setSearchError( null );
			try {
				const data = await apiFetch( {
					path: `/kate-toms/v1/blueprint/crm-search?query=${ encodeURIComponent( searchQuery ) }`,
				} );
				setResults( data );
			} catch ( err ) {
				setSearchError( err?.message || __( 'Search failed. Please try again.', 'kate-toms-core' ) );
				setResults( [] );
			} finally {
				setIsSearching( false );
			}
		}, 350 ),
		[]
	);

	function handleQueryChange( value ) {
		setQuery( value );
		setSelectedHouse( null );
		runSearch( value );
	}

	function handleSelect( house ) {
		setSelectedHouse( house );
		setTitleOverride( house.crm_title );
		setResults( [] );
		setQuery( house.crm_title );
	}

	function handleNext() {
		if ( ! selectedHouse ) return;
		onConfirm( selectedHouse.crm_id, titleOverride.trim() || selectedHouse.crm_title );
	}

	const canProceed = selectedHouse && titleOverride.trim().length > 0;

	return (
		<div className="kt-blueprint-step kt-blueprint-step--search">
			<h2>{ __( 'Step 1: Find a House', 'kate-toms-core' ) }</h2>
			<p className="description">
				{ __( 'Search for a house in the CRM by name. The first search loads the full property list and may take up to 30 seconds.', 'kate-toms-core' ) }
			</p>

			<div className="kt-blueprint-search-field">
				<TextControl
					label={ __( 'Search CRM by house name', 'kate-toms-core' ) }
					value={ query }
					onChange={ handleQueryChange }
					placeholder={ __( 'Type at least 2 characters…', 'kate-toms-core' ) }
					autoFocus
				/>
				{ isSearching && <Spinner /> }
			</div>

			{ searchError && (
				<Notice status="error" isDismissible={ false }>
					{ searchError }
				</Notice>
			) }

			{ results.length > 0 && (
				<ul className="kt-blueprint-results">
					{ results.map( ( house ) => (
						<li key={ house.crm_id }>
							<Button
								variant="tertiary"
								onClick={ () => handleSelect( house ) }
							>
								{ house.crm_title }
								<span className="kt-blueprint-crm-id">
									{ ` (ID: ${ house.crm_id })` }
								</span>
							</Button>
						</li>
					) ) }
				</ul>
			) }

			{ selectedHouse && (
				<div className="kt-blueprint-selected">
					<p>
						<strong>{ __( 'Selected:', 'kate-toms-core' ) }</strong>{ ' ' }
						{ selectedHouse.crm_title }{ ' ' }
						<span className="kt-blueprint-crm-id">
							{ `(CRM ID: ${ selectedHouse.crm_id })` }
						</span>
					</p>

					<TextControl
						label={ __( 'Display title (edit to override)', 'kate-toms-core' ) }
						value={ titleOverride }
						onChange={ setTitleOverride }
						help={ __( 'This will be used as the page title on the website. Defaults to the CRM name.', 'kate-toms-core' ) }
					/>
				</div>
			) }

			<div className="kt-blueprint-actions">
				<Button
					variant="primary"
					onClick={ handleNext }
					disabled={ ! canProceed }
				>
					{ __( 'Next: Review →', 'kate-toms-core' ) }
				</Button>
			</div>
		</div>
	);
}
