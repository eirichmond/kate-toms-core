import { useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Step 3: Success screen listing all created draft posts with editor links.
 *
 * Also pops up a reminder with the parent house's WordPress post ID: the
 * client wants this pasted into the Property Reference field in iPro for
 * their own reporting purposes. Booking/availability no longer depend on
 * Property Reference (see House_Calendar_Manager::get_wp_house_id_from_property_id(),
 * which now reads ipro_property_id meta directly) — this is purely to keep
 * iPro's own records in sync, at the client's request.
 *
 * @param {Object}   props
 * @param {Array}    props.posts   Array of { page_key, post_id, edit_url, title }.
 * @param {Function} props.onReset Resets the wizard to step 1.
 */
export default function StepSuccess( { posts, onReset } ) {
	const parentPost = posts.find( ( post ) => post.page_key === 'parent' );
	const [ isReminderOpen, setIsReminderOpen ] = useState( true );
	const [ isCopied, setIsCopied ] = useState( false );

	function handleCopyParentId() {
		if ( ! parentPost ) {
			return;
		}
		window.navigator.clipboard
			.writeText( String( parentPost.post_id ) )
			.then( () => {
				setIsCopied( true );
				setTimeout( () => setIsCopied( false ), 2000 );
			} );
	}

	return (
		<div className="kt-blueprint-step kt-blueprint-step--success">
			{ isReminderOpen && parentPost && (
				<Modal
					title={ __(
						'Update iPro before you finish',
						'kate-toms-core'
					) }
					onRequestClose={ () => setIsReminderOpen( false ) }
					className="kt-blueprint-ipro-reminder"
				>
					<p>
						{ sprintf(
							/* translators: %d: WordPress parent house post ID. */
							__(
								'Blueprint generated — parent house ID is %d.',
								'kate-toms-core'
							),
							parentPost.post_id
						) }
					</p>
					<p>
						{ __(
							'Copy and paste this into the Property Reference field for this house in iPro.',
							'kate-toms-core'
						) }
					</p>
					<div
						className="kt-blueprint-ipro-id-row"
						style={ {
							display: 'flex',
							alignItems: 'center',
							gap: '12px',
							margin: '16px 0',
						} }
					>
						<code
							className="kt-blueprint-ipro-id"
							style={ {
								fontSize: '1.5em',
								padding: '4px 12px',
								background: '#f0f0f1',
							} }
						>
							{ parentPost.post_id }
						</code>
						<Button
							variant="secondary"
							onClick={ handleCopyParentId }
						>
							{ isCopied
								? __( 'Copied!', 'kate-toms-core' )
								: __( 'Copy', 'kate-toms-core' ) }
						</Button>
					</div>
					<Button
						variant="primary"
						onClick={ () => setIsReminderOpen( false ) }
					>
						{ __( 'Got it', 'kate-toms-core' ) }
					</Button>
				</Modal>
			) }

			<h2>{ __( '✓ Blueprint Created', 'kate-toms-core' ) }</h2>
			<p>
				{ __(
					'All pages have been created as drafts. Click any title to open it in the block editor.',
					'kate-toms-core'
				) }
			</p>

			{ parentPost && ! isReminderOpen && (
				<p className="kt-blueprint-ipro-recap">
					{ sprintf(
						/* translators: %d: WordPress parent house post ID. */
						__(
							'Reminder: parent house ID %d still needs adding to iPro’s Property Reference field.',
							'kate-toms-core'
						),
						parentPost.post_id
					) }{ ' ' }
					<Button
						variant="link"
						onClick={ () => setIsReminderOpen( true ) }
					>
						{ __( 'Show again', 'kate-toms-core' ) }
					</Button>
				</p>
			) }

			<ul className="kt-blueprint-created-list">
				{ posts.map( ( post ) => (
					<li key={ post.post_id }>
						<a
							href={ post.edit_url }
							target="_blank"
							rel="noreferrer"
						>
							{ post.title }
						</a>
						<span className="kt-blueprint-page-key">
							{ ` (${ post.page_key })` }
						</span>
					</li>
				) ) }
			</ul>

			<div className="kt-blueprint-actions">
				<Button variant="primary" onClick={ onReset }>
					{ __( 'Create Another Blueprint', 'kate-toms-core' ) }
				</Button>
			</div>
		</div>
	);
}
