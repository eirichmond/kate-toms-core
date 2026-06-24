import { useState } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const { pages: BLUEPRINT_PAGES = {} } = window.ktBlueprintData || {};

/**
 * Derives the display title for a child page from its key and parent title.
 *
 * @param {string} displayTitle Parent display title.
 * @param {string} key          Page key (e.g. 'availability').
 * @return {string} Child page title.
 */
function buildChildTitle( displayTitle, key ) {
	if ( key === 'more' ) return displayTitle;
	return `${ displayTitle } - ${ key } - Kate and Tom's`;
}

/**
 * Step 2: Review the pages to be created and trigger blueprint creation.
 *
 * @param {Object}   props
 * @param {number}   props.crmId        CRM property ID.
 * @param {string}   props.displayTitle Display title chosen in step 1.
 * @param {Function} props.onBack       Navigate back to step 1.
 * @param {Function} props.onCreated    Called with created posts array on success.
 */
export default function StepReview( { crmId, displayTitle, onBack, onCreated } ) {
	const [ isCreating, setIsCreating ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ duplicate, setDuplicate ] = useState( null );

	const pageEntries = Object.entries( BLUEPRINT_PAGES );

	async function handleCreate( force = false ) {
		setIsCreating( true );
		setError( null );
		setDuplicate( null );

		try {
			const result = await apiFetch( {
				path: '/kate-toms/v1/blueprint/create',
				method: 'POST',
				data: { crm_id: crmId, display_title: displayTitle, force },
			} );
			onCreated( result );
		} catch ( err ) {
			if ( err?.data?.status === 409 ) {
				setDuplicate( {
					existingTitle: err.data.existing_title || displayTitle,
					existingId: err.data.existing_post_id,
				} );
			} else {
				setError( err?.message || __( 'Creation failed. Please try again.', 'kate-toms-core' ) );
			}
		} finally {
			setIsCreating( false );
		}
	}

	return (
		<div className="kt-blueprint-step kt-blueprint-step--review">
			<h2>{ __( 'Step 2: Review', 'kate-toms-core' ) }</h2>
			<p>
				{ __( 'The following pages will be created as drafts:', 'kate-toms-core' ) }
			</p>

			<table className="widefat striped kt-blueprint-review-table">
				<thead>
					<tr>
						<th>{ __( 'Page', 'kate-toms-core' ) }</th>
						<th>{ __( 'Title', 'kate-toms-core' ) }</th>
						<th>{ __( 'Slug', 'kate-toms-core' ) }</th>
						<th>{ __( 'Patterns', 'kate-toms-core' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ pageEntries.map( ( [ key, config ] ) => (
						<tr key={ key }>
							<td>{ key }</td>
							<td>
								{ key === 'parent'
									? displayTitle
									: buildChildTitle( displayTitle, key ) }
							</td>
							<td>
								<code>{ key === 'parent' ? '(parent)' : key }</code>
							</td>
							<td>{ config.patterns.length }</td>
						</tr>
					) ) }
				</tbody>
			</table>

			<p className="description">
				{ __( 'CRM ID: ', 'kate-toms-core' ) }
				<strong>{ crmId }</strong>
			</p>

			{ duplicate && (
				<Notice status="warning" isDismissible={ false }>
					<p>
						{ __(
							`A house named "${ duplicate.existingTitle }" already exists (post #${ duplicate.existingId }).`,
							'kate-toms-core'
						) }
					</p>
					<div className="kt-blueprint-duplicate-actions">
						<Button variant="secondary" onClick={ onBack }>
							{ __( '← Change Name', 'kate-toms-core' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ () => handleCreate( true ) }
							disabled={ isCreating }
						>
							{ __( 'Create Anyway', 'kate-toms-core' ) }
						</Button>
					</div>
				</Notice>
			) }

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
					<Button
						variant="link"
						onClick={ () => handleCreate( false ) }
					>
						{ __( 'Try Again', 'kate-toms-core' ) }
					</Button>
				</Notice>
			) }

			<div className="kt-blueprint-actions">
				<Button variant="secondary" onClick={ onBack } disabled={ isCreating }>
					{ __( '← Back', 'kate-toms-core' ) }
				</Button>

				{ ! duplicate && (
					<Button
						variant="primary"
						onClick={ () => handleCreate( false ) }
						disabled={ isCreating }
					>
						{ isCreating ? (
							<>
								<Spinner />
								{ __( 'Creating…', 'kate-toms-core' ) }
							</>
						) : (
							__( 'Create Blueprint', 'kate-toms-core' )
						) }
					</Button>
				) }
			</div>
		</div>
	);
}
