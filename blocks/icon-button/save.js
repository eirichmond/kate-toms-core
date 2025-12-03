/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @return {Element} Element to render.
 */
export default function save({ attributes }) {
	const {
		text,
		selectedIcon,
		iconPosition,
		iconSize,
		showIcon,
		url,
		linkTarget,
		rel,
		showForm,
		formType
	} = attributes;

	// Get SVG content for selected icon
	const getIconSvg = (iconName) => {
		switch (iconName) {
			case 'speech-bubble':
				return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
			case 'magnifying-glass':
				return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.35-4.35"></path></svg>';
			default:
				return '';
		}
	};

	// Render icon if selected and visible
	const renderIcon = () => {
		if (!showIcon || !selectedIcon) return null;

		const svgContent = getIconSvg(selectedIcon);
		if (!svgContent) return null;

		return (
			<span 
				className="icon-button__icon"
				style={{ '--icon-size': `${iconSize}px` }}
				dangerouslySetInnerHTML={{ __html: svgContent }}
				aria-hidden={text ? "true" : "false"}
			/>
		);
	};

	const blockProps = useBlockProps.save({
		className: `icon-position-${iconPosition}`,
		style: {
			'--icon-size': `${iconSize}px`
		},
		...(showForm && {
			'data-show-form': 'true',
			'data-form-type': formType
		})
	});

	const TagName = url ? 'a' : 'button';
	const linkProps = url ? {
		href: url,
		target: linkTarget,
		rel: rel
	} : {
		type: 'button'
	};

	// Add aria-label if text is empty but icon is shown
	const accessibilityProps = {};
	if (showIcon && selectedIcon && !text) {
		accessibilityProps['aria-label'] = selectedIcon.replace('-', ' ');
	}

	return (
		<TagName {...blockProps} {...linkProps} {...accessibilityProps}>
			{iconPosition === 'prepend' && renderIcon()}
			
			{text && (
				<RichText.Content
					tagName="span"
					className="icon-button__text"
					value={text}
				/>
			)}
			
			{iconPosition === 'append' && renderIcon()}
		</TagName>
	);
}