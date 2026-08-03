import { useState } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

const { pages: BLUEPRINT_PAGES = [], missingMasters: MISSING_MASTERS = [] } =
	window.ktBlueprintData || {};

/**
 * Derives the post title for a page from its label and the parent title.
 *
 * Mirrors Kate_Toms_Blueprint_Templates::build_title() on the PHP side.
 *
 * @param {string} displayTitle Parent display title.
 * @param {string} label        Page label (empty for the parent page).
 * @return {string} Page title.
 */
function buildTitle( displayTitle, label ) {
	if ( ! label ) return displayTitle;
	return `${ displayTitle } | ${ label } | kate & tom's`;
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

			{ MISSING_MASTERS.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<p>
						{ __(
							'These MASTER patterns could not be found, so their pages will be created empty:',
							'kate-toms-core'
						) }
					</p>
					<ul>
						{ MISSING_MASTERS.map( ( title ) => (
							<li key={ title }>
								<code>{ title }</code>
							</li>
						) ) }
					</ul>
				</Notice>
			) }

			<table className="widefat striped kt-blueprint-review-table">
				<thead>
					<tr>
						<th>{ __( 'Title', 'kate-toms-core' ) }</th>
						<th>{ __( 'Slug', 'kate-toms-core' ) }</th>
						<th>{ __( 'Content from', 'kate-toms-core' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ BLUEPRINT_PAGES.map( ( page ) => (
						<tr key={ page.key }>
							<td>{ buildTitle( displayTitle, page.label ) }</td>
							<td>
								<code>
									{ page.slug || __( '(parent)', 'kate-toms-core' ) }
								</code>
							</td>
							<td>{ page.source }</td>
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
						{ sprintf(
							/* translators: 1: existing house title, 2: existing post ID. */
							__( 'A house named "%1$s" already exists (post #%2$d).', 'kate-toms-core' ),
							duplicate.existingTitle,
							duplicate.existingId
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
