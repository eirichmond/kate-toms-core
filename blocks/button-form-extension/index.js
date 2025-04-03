/**
 * Button Form Extension
 *
 * Extends the core Button block to add form loading functionality.
 * Adds custom controls to the Button block to enable form loading and
 * select form types. When enabled, clicking the button will trigger
 * a slide-in form panel.
 *
 * This extension:
 * 1. Adds two new attributes to the core Button block:
 *    - showForm (boolean): Toggles form functionality
 *    - formType (string): Selects between 'contact' or 'booking' forms
 * 
 * 2. Adds new controls to the Block Editor sidebar:
 *    - A toggle switch to enable/disable the form
 *    - A dropdown to select the form type
 * 
 * 3. Uses WordPress's Higher Order Components pattern to:
 *    - Extend the existing Button block without modifying core code
 *    - Add custom Inspector Controls in the sidebar
 *    - Maintain all existing Button block functionality
 *
 * @see {@link https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/} Block Registration
 * @see {@link https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/} Block Filters
 * 
 * @package   KateTomsCore
 * @author    Kate and Toms
 * @copyright 2024 Kate and Toms
 * @license   GPL-2.0-or-later
 *
 * @wordpress-plugin
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

// Add custom attributes to core/button
addFilter(
    'blocks.registerBlockType',
    'kate-toms-core/button-attributes',
    (settings, name) => {
        if (name !== 'core/button') {
            return settings;
        }

        return {
            ...settings,
            attributes: {
                ...settings.attributes,
                showForm: {
                    type: 'boolean',
                    default: false
                },
                formType: {
                    type: 'string',
                    default: 'contact'
                }
            }
        };
    }
);

// Add custom controls to core/button
const withInspectorControls = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        if (props.name !== 'core/button') {
            return <BlockEdit {...props} />;
        }

        const { attributes, setAttributes } = props;

        return (
            <>
                <BlockEdit {...props} />
                <InspectorControls>
                    <PanelBody title={__('Form Settings', 'kate-toms-core')}>
                        <ToggleControl
                            label={__('Enable Form', 'kate-toms-core')}
                            checked={attributes.showForm}
                            onChange={(value) => setAttributes({ showForm: value })}
                        />
                        {attributes.showForm && (
                            <SelectControl
                                label={__('Form Type', 'kate-toms-core')}
                                value={attributes.formType}
                                options={[
                                    { label: 'Contact Form', value: 'contact' },
                                    { label: 'Booking Form', value: 'booking' }
                                ]}
                                onChange={(value) => setAttributes({ formType: value })}
                            />
                        )}
                    </PanelBody>
                </InspectorControls>
            </>
        );
    };
}, 'withInspectorControls');

addFilter(
    'editor.BlockEdit',
    'kate-toms-core/button-inspector',
    withInspectorControls
); 
