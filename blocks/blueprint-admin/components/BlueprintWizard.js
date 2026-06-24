import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import StepSearch from './StepSearch';
import StepReview from './StepReview';
import StepSuccess from './StepSuccess';

/**
 * Root wizard component managing the three-step Blueprint onboarding flow.
 *
 * Step 1 (search): CRM typeahead + optional title override.
 * Step 2 (review): Summary of pages to be created + create action.
 * Step 3 (success): Links to all created drafts + reset option.
 */
export default function BlueprintWizard() {
	const [ step, setStep ] = useState( 1 );
	const [ crmId, setCrmId ] = useState( null );
	const [ displayTitle, setDisplayTitle ] = useState( '' );
	const [ createdPosts, setCreatedPosts ] = useState( [] );

	function handleSearchConfirm( selectedCrmId, selectedTitle ) {
		setCrmId( selectedCrmId );
		setDisplayTitle( selectedTitle );
		setStep( 2 );
	}

	function handleCreated( posts ) {
		setCreatedPosts( posts );
		setStep( 3 );
	}

	function handleReset() {
		setCrmId( null );
		setDisplayTitle( '' );
		setCreatedPosts( [] );
		setStep( 1 );
	}

	return (
		<div className="kt-blueprint-wizard">
			{ step === 1 && (
				<StepSearch
					displayTitle={ displayTitle }
					onConfirm={ handleSearchConfirm }
				/>
			) }
			{ step === 2 && (
				<StepReview
					crmId={ crmId }
					displayTitle={ displayTitle }
					onBack={ () => setStep( 1 ) }
					onCreated={ handleCreated }
				/>
			) }
			{ step === 3 && (
				<StepSuccess
					posts={ createdPosts }
					onReset={ handleReset }
				/>
			) }
		</div>
	);
}
