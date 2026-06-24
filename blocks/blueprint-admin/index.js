import { createRoot } from '@wordpress/element';
import BlueprintWizard from './components/BlueprintWizard';

const root = document.getElementById( 'kt-blueprint-root' );

if ( root ) {
	createRoot( root ).render( <BlueprintWizard /> );
}
