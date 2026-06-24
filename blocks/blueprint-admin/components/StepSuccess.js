import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Step 3: Success screen listing all created draft posts with editor links.
 *
 * @param {Object}   props
 * @param {Array}    props.posts   Array of { page_key, post_id, edit_url, title }.
 * @param {Function} props.onReset Resets the wizard to step 1.
 */
export default function StepSuccess( { posts, onReset } ) {
	return (
		<div className="kt-blueprint-step kt-blueprint-step--success">
			<h2>{ __( '✓ Blueprint Created', 'kate-toms-core' ) }</h2>
			<p>
				{ __( 'All pages have been created as drafts. Click any title to open it in the block editor.', 'kate-toms-core' ) }
			</p>

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
