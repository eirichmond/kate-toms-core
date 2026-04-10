import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { reviews, textAlign, reviewFontSize, reviewerFontSize } = attributes;

	const blockProps = useBlockProps.save( {
		className: `has-text-align-${ textAlign }`,
	} );

	return (
		<div { ...blockProps }>
			<div className="reviews-container">
				{ reviews.map( ( item, index ) => (
					<div
						key={ index }
						className="review-item"
						data-index={ index }
					>
						<blockquote
							className="review-text"
							style={ { fontSize: reviewFontSize } }
						>
							{ item.review }
						</blockquote>
						<cite
							className="reviewer-name"
							style={ { fontSize: reviewerFontSize } }
						>
							{ item.reviewer }
						</cite>
					</div>
				) ) }
			</div>
		</div>
	);
}
