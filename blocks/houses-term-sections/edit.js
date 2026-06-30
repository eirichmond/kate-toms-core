/**
 * Editor representation of the Houses Term Sections block.
 *
 * This is a dynamic, server-rendered block that depends on the queried taxonomy
 * term, which is not available in the editor — so we show an explanatory
 * placeholder rather than attempting to render the sections.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Placeholder
				icon="screenoptions"
				label={ __( 'Houses Term Sections', 'kate-toms-core' ) }
				instructions={ __(
					'On the front end this groups houses tagged with the current taxonomy term into the location regions (Cotswolds, Coast, Country, Town). Empty regions are hidden automatically.',
					'kate-toms-core'
				) }
			/>
		</div>
	);
}
