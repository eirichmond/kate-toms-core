import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	BlockControls,
	AlignmentToolbar,
	FontSizePicker,
} from '@wordpress/block-editor';
import {
	Button,
	TextControl,
	Panel,
	PanelBody,
	ToolbarGroup,
	PanelRow,
} from '@wordpress/components';
import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const {
		reviews = [],
		textAlign,
		reviewFontSize,
		reviewerFontSize,
	} = attributes;

	const blockProps = useBlockProps( {
		className: `has-text-align-${ textAlign }`,
	} );

	const addReview = () => {
		setAttributes( {
			reviews: [ ...reviews, { review: '', reviewer: '' } ],
		} );
	};

	const updateReview = ( index, key, value ) => {
		const newReviews = [ ...reviews ];
		newReviews[ index ] = { ...newReviews[ index ], [ key ]: value };
		setAttributes( { reviews: newReviews } );
	};

	const removeReview = ( index ) => {
		const newReviews = reviews.filter( ( _, i ) => i !== index );
		setAttributes( { reviews: newReviews } );
	};

	return (
		<>
			<BlockControls>
				<AlignmentToolbar
					value={ textAlign }
					onChange={ ( newAlign ) =>
						setAttributes( { textAlign: newAlign } )
					}
				/>
			</BlockControls>

			<div { ...blockProps }>
				<Panel>
					<PanelBody
						title={ __(
							'Typography Settings',
							'kateandtoms-reviews'
						) }
						initialOpen={ false }
					>
						<PanelRow>
							<FontSizePicker
								label={ __(
									'Review Text Size',
									'kateandtoms-reviews'
								) }
								value={ reviewFontSize }
								onChange={ ( size ) =>
									setAttributes( { reviewFontSize: size } )
								}
							/>
						</PanelRow>
						<PanelRow>
							<FontSizePicker
								label={ __(
									'Reviewer Name Size',
									'kateandtoms-reviews'
								) }
								value={ reviewerFontSize }
								onChange={ ( size ) =>
									setAttributes( { reviewerFontSize: size } )
								}
							/>
						</PanelRow>
					</PanelBody>
				</Panel>

				<div className="reviews-container">
					{ reviews.map( ( item, index ) => (
						<Panel key={ index } className="review-panel">
							<PanelBody
								title={ __(
									`Review ${ index + 1 }`,
									'kateandtoms-reviews'
								) }
								initialOpen={ true }
							>
								<TextControl
									label={ __(
										'Review Text',
										'kateandtoms-reviews'
									) }
									value={ item.review }
									onChange={ ( value ) =>
										updateReview( index, 'review', value )
									}
									placeholder={ __(
										'Enter review text…',
										'kateandtoms-reviews'
									) }
								/>
								<TextControl
									label={ __(
										'Reviewer Name',
										'kateandtoms-reviews'
									) }
									value={ item.reviewer }
									onChange={ ( value ) =>
										updateReview( index, 'reviewer', value )
									}
									placeholder={ __(
										'Enter reviewer name…',
										'kateandtoms-reviews'
									) }
								/>
								<Button
									isDestructive
									onClick={ () => removeReview( index ) }
									variant="secondary"
								>
									{ __(
										'Remove Review',
										'kateandtoms-reviews'
									) }
								</Button>
							</PanelBody>
						</Panel>
					) ) }
					<Button
						variant="primary"
						onClick={ addReview }
						className="add-review-button"
					>
						{ __( 'Add Review', 'kateandtoms-reviews' ) }
					</Button>
				</div>
			</div>
		</>
	);
}
