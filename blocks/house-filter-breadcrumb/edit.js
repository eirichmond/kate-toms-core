/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Editor component for the House Filter Breadcrumb block.
 * Shows a static preview of breadcrumb items.
 */
export default function Edit() {
	return (
		<div { ...useBlockProps( { className: 'house-filter-breadcrumb' } ) }>
			<span className="house-filter-breadcrumb__item">
				<svg
					viewBox="0 0 24 24"
					width="16"
					height="16"
					aria-hidden="true"
				>
					<path
						d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"
						fill="#999"
					/>
				</svg>
				<span>Sleeps 2-10</span>
			</span>
			<span className="house-filter-breadcrumb__item">
				<svg
					viewBox="0 0 24 24"
					width="16"
					height="16"
					aria-hidden="true"
				>
					<path
						d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"
						fill="#999"
					/>
				</svg>
				<span>Coast</span>
			</span>
			<span className="house-filter-breadcrumb__item">
				<svg
					viewBox="0 0 24 24"
					width="16"
					height="16"
					aria-hidden="true"
				>
					<path
						d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"
						fill="#999"
					/>
				</svg>
				<span>Hot Tub</span>
			</span>
		</div>
	);
}
