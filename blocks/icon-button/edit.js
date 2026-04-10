/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import {
	useBlockProps,
	RichText,
	InspectorControls,
	BlockControls,
	__experimentalLinkControl as LinkControl,
} from '@wordpress/block-editor';

import {
	PanelBody,
	ToggleControl,
	SelectControl,
	RangeControl,
	RadioControl,
	ToolbarGroup,
	ToolbarButton,
	Popover,
} from '@wordpress/components';

import { useState } from '@wordpress/element';
import { link, linkOff } from '@wordpress/icons';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @param  root0
 * @param  root0.attributes
 * @param  root0.setAttributes
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
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
		formType,
	} = attributes;

	const [ showLinkControl, setShowLinkControl ] = useState( false );

	// Available icons with inline SVG
	const availableIcons = [
		{ value: '', label: __( 'Select an icon', 'kate-toms-core' ) },
		{
			value: 'speech-bubble',
			label: __( 'Speech Bubble', 'kate-toms-core' ),
		},
		{
			value: 'magnifying-glass',
			label: __( 'Magnifying Glass', 'kate-toms-core' ),
		},
	];

	// Get SVG content for selected icon
	const getIconSvg = ( iconName ) => {
		switch ( iconName ) {
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
		if ( ! showIcon || ! selectedIcon ) {
			return null;
		}

		const svgContent = getIconSvg( selectedIcon );
		if ( ! svgContent ) {
			return null;
		}

		return (
			<span
				className="icon-button__icon"
				style={ { '--icon-size': `${ iconSize }px` } }
				dangerouslySetInnerHTML={ { __html: svgContent } }
			/>
		);
	};

	const blockProps = useBlockProps( {
		className: `icon-position-${ iconPosition }`,
		style: {
			'--icon-size': `${ iconSize }px`,
		},
	} );

	const TagName = url ? 'a' : 'div';
	const linkProps = url
		? {
				href: url,
				target: linkTarget,
				rel,
		  }
		: {};

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						name="link"
						icon={ link }
						title={ __( 'Link', 'kate-toms-core' ) }
						onClick={ () => setShowLinkControl( true ) }
						isActive={ !! url }
					/>
					{ url && (
						<ToolbarButton
							name="unlink"
							icon={ linkOff }
							title={ __( 'Unlink', 'kate-toms-core' ) }
							onClick={ () => {
								setAttributes( {
									url: undefined,
									linkTarget: undefined,
									rel: undefined,
								} );
							} }
							isActive={ false }
						/>
					) }
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody
					title={ __( 'Icon Settings', 'kate-toms-core' ) }
					initialOpen={ true }
				>
					<ToggleControl
						label={ __( 'Show Icon', 'kate-toms-core' ) }
						checked={ showIcon }
						onChange={ ( value ) =>
							setAttributes( { showIcon: value } )
						}
					/>

					{ showIcon && (
						<>
							<SelectControl
								label={ __( 'Select Icon', 'kate-toms-core' ) }
								value={ selectedIcon }
								options={ availableIcons }
								onChange={ ( value ) =>
									setAttributes( { selectedIcon: value } )
								}
							/>

							{ selectedIcon && (
								<>
									<RadioControl
										label={ __(
											'Icon Position',
											'kate-toms-core'
										) }
										selected={ iconPosition }
										options={ [
											{
												label: __(
													'Prepend (before text)',
													'kate-toms-core'
												),
												value: 'prepend',
											},
											{
												label: __(
													'Append (after text)',
													'kate-toms-core'
												),
												value: 'append',
											},
										] }
										onChange={ ( value ) =>
											setAttributes( {
												iconPosition: value,
											} )
										}
									/>

									<RangeControl
										label={ __(
											'Icon Size',
											'kate-toms-core'
										) }
										value={ iconSize }
										onChange={ ( value ) =>
											setAttributes( { iconSize: value } )
										}
										min={ 12 }
										max={ 48 }
										step={ 2 }
									/>
								</>
							) }
						</>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Form Settings', 'kate-toms-core' ) }>
					<ToggleControl
						label={ __( 'Enable Form', 'kate-toms-core' ) }
						checked={ showForm }
						onChange={ ( value ) =>
							setAttributes( { showForm: value } )
						}
					/>
					{ showForm && (
						<SelectControl
							label={ __( 'Form Type', 'kate-toms-core' ) }
							value={ formType }
							options={ [
								{ label: 'Contact Form', value: 'contact' },
								{ label: 'Booking Form', value: 'booking' },
							] }
							onChange={ ( value ) =>
								setAttributes( { formType: value } )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>

			<TagName { ...blockProps } { ...linkProps }>
				{ iconPosition === 'prepend' && renderIcon() }

				<RichText
					tagName="span"
					className="icon-button__text"
					value={ text }
					onChange={ ( value ) => setAttributes( { text: value } ) }
					placeholder={ __( 'Add text…', 'kate-toms-core' ) }
					allowedFormats={ [] }
				/>

				{ iconPosition === 'append' && renderIcon() }
			</TagName>

			{ showLinkControl && (
				<Popover
					placement="bottom"
					onClose={ () => setShowLinkControl( false ) }
					anchorRef={ blockProps.ref?.current }
					focusOnMount={ false }
				>
					<LinkControl
						className="wp-block-navigation-link__inline-link-input"
						value={ { url, target: linkTarget } }
						onChange={ ( value ) => {
							setAttributes( {
								url: value.url,
								linkTarget: value.target,
							} );
						} }
						onRemove={ () => {
							setAttributes( {
								url: undefined,
								linkTarget: undefined,
								rel: undefined,
							} );
							setShowLinkControl( false );
						} }
						forceIsEditingLink={ showLinkControl }
					/>
				</Popover>
			) }
		</>
	);
}
