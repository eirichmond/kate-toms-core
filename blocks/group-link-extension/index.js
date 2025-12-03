/**
 * Group Link Extension
 *
 * Extends the core Group block to add link functionality.
 * Adds custom controls to the Group block to enable linking the entire
 * group container. When enabled, the entire group becomes clickable.
 *
 * This extension:
 * 1. Adds three new attributes to the core Group block:
 *    - href (string): The link URL
 *    - linkTarget (string): Link target (_blank, _self, etc.)
 *    - linkDestination (string): Type of link (custom, post, etc.)
 *
 * 2. Adds new controls to the Block Editor toolbar:
 *    - A link button to add/edit/remove links
 *    - LinkControl popover for URL editing
 *
 * 3. Uses WordPress's Higher Order Components pattern to:
 *    - Extend the existing Group block without modifying core code
 *    - Add custom BlockControls in the toolbar
 *    - Maintain all existing Group block functionality
 *
 * @see {@link https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/} Block Registration
 * @see {@link https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/} Block Filters
 *
 * @package   KateTomsCore
 * @author    Kate and Toms
 * @copyright 2025 Kate and Toms
 * @license   GPL-2.0-or-later
 *
 * @wordpress-plugin
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarButton, Popover } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { link, linkOff } from '@wordpress/icons';
import { useState } from '@wordpress/element';

// Import LinkControl - handle both stable and experimental versions
const LinkControl = wp.blockEditor.__experimentalLinkControl || wp.blockEditor.LinkControl;

// Add custom attributes to core/group
addFilter(
    'blocks.registerBlockType',
    'kate-toms-core/group-link-attributes',
    (settings, name) => {
        if (name !== 'core/group') {
            return settings;
        }

        return {
            ...settings,
            attributes: {
                ...settings.attributes,
                href: {
                    type: 'string'
                },
                linkTarget: {
                    type: 'string'
                },
                linkDestination: {
                    type: 'string'
                }
            }
        };
    }
);

// Add custom toolbar controls to core/group
const withBlockControls = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        if (props.name !== 'core/group') {
            return <BlockEdit {...props} />;
        }

        const { attributes, setAttributes } = props;
        const { href, linkTarget } = attributes;
        const [isEditingURL, setIsEditingURL] = useState(false);
        const [popoverAnchor, setPopoverAnchor] = useState(null);

        const isURLSet = !!href;

        const openLinkControl = () => {
            setIsEditingURL(true);
        };

        const unlinkGroup = () => {
            setAttributes({
                href: undefined,
                linkTarget: undefined,
                linkDestination: undefined
            });
        };

        const linkValue = {
            url: href,
            opensInNewTab: linkTarget === '_blank'
        };

        return (
            <>
                <BlockEdit {...props} />
                <BlockControls group="block">
                    <ToolbarButton
                        ref={setPopoverAnchor}
                        name="link"
                        icon={link}
                        title={__('Link', 'kate-toms-core')}
                        onClick={openLinkControl}
                        isActive={isURLSet}
                    />
                    {isURLSet && (
                        <ToolbarButton
                            name="unlink"
                            icon={linkOff}
                            title={__('Unlink', 'kate-toms-core')}
                            onClick={unlinkGroup}
                            isActive={false}
                        />
                    )}
                </BlockControls>
                {isEditingURL && (
                    <Popover
                        anchor={popoverAnchor}
                        onClose={() => setIsEditingURL(false)}
                        placement="bottom"
                        shift
                    >
                        <LinkControl
                            value={linkValue}
                            onChange={(newValue) => {
                                setAttributes({
                                    href: newValue.url,
                                    linkTarget: newValue.opensInNewTab ? '_blank' : undefined
                                });
                            }}
                            onRemove={() => {
                                unlinkGroup();
                                setIsEditingURL(false);
                            }}
                            settings={[
                                {
                                    id: 'opensInNewTab',
                                    title: __('Open in new tab', 'kate-toms-core')
                                }
                            ]}
                        />
                    </Popover>
                )}
            </>
        );
    };
}, 'withBlockControls');

addFilter(
    'editor.BlockEdit',
    'kate-toms-core/group-link-controls',
    withBlockControls
);
