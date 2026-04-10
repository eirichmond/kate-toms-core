import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Placeholder } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

export default function Edit( { attributes, setAttributes } ) {
	const { houseId } = attributes;
	const [ availabilityNotes, setAvailabilityNotes ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );

	const blockProps = useBlockProps( {
		className: 'availability-notes-block',
	} );

	// Fetch availability notes when houseId changes
	useEffect( () => {
		if ( ! houseId ) {
			setAvailabilityNotes( '' );
			return;
		}

		setIsLoading( true );

		// Use localized AJAX data for availability notes
		const ajaxUrl =
			window.availabilityNotesAjax?.ajaxUrl ||
			window.ajaxurl ||
			'/wp-admin/admin-ajax.php';
		const baseUrl = ajaxUrl.startsWith( 'http' )
			? ajaxUrl
			: window.location.origin + ajaxUrl;
		const url = new URL( baseUrl );
		url.searchParams.append( 'action', 'get_availability_notes' );
		url.searchParams.append( 'house_id', houseId );
		url.searchParams.append(
			'nonce',
			window.availabilityNotesAjax?.nonce || ''
		);

		fetch( url, {
			method: 'GET',
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data.success && data.data.AvailabilityNotes ) {
					setAvailabilityNotes( data.data.AvailabilityNotes );
				} else {
					setAvailabilityNotes( '' );
				}
			} )
			.catch( ( error ) => {
				console.error( 'Error fetching availability notes:', error );
				setAvailabilityNotes( '' );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [ houseId ] );

	if ( ! houseId ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="info"
					label={ __( 'Availability Notes', 'kate-toms-core' ) }
					instructions={ __(
						'Please select a house to display availability notes.',
						'kate-toms-core'
					) }
				>
					<InspectorControls>
						<PanelBody title={ __( 'Settings', 'kate-toms-core' ) }>
							<TextControl
								label={ __( 'House ID', 'kate-toms-core' ) }
								value={ houseId }
								onChange={ ( value ) =>
									setAttributes( { houseId: value } )
								}
								help={ __(
									'Enter the house ID to display availability notes for.',
									'kate-toms-core'
								) }
							/>
						</PanelBody>
					</InspectorControls>
				</Placeholder>
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'kate-toms-core' ) }>
					<TextControl
						label={ __( 'House ID', 'kate-toms-core' ) }
						value={ houseId }
						onChange={ ( value ) =>
							setAttributes( { houseId: value } )
						}
						help={ __(
							'Enter the house ID to display availability notes for.',
							'kate-toms-core'
						) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ isLoading ? (
					<div className="availability-notes-loading">
						<p>
							{ __(
								'Loading availability notes…',
								'kate-toms-core'
							) }
						</p>
					</div>
				) : availabilityNotes ? (
					<div
						className="availability-notes-content"
						dangerouslySetInnerHTML={ {
							__html: availabilityNotes,
						} }
					/>
				) : (
					<div className="availability-notes-empty">
						<p>
							{ __(
								'No availability notes found for this house.',
								'kate-toms-core'
							) }
						</p>
					</div>
				) }
			</div>
		</>
	);
}
