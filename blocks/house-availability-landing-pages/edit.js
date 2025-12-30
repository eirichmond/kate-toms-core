/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';

/**
 * Edit function for House Availability Landing Pages block
 */
export default function Edit({ attributes, setAttributes, context }) {
    const { postsPerPage = -1, orderBy = 'meta_value_num', order = 'desc', metaKey = 'sleeps_max', patternStyle = 'coast' } = attributes;

    const blockProps = useBlockProps();

    // Get current post ID (availability post)
    const postId = useSelect((select) => {
        return select(editorStore).getCurrentPostId();
    }, []);

    // Get availability meta from current post
    const { availabilityMeta, postType } = useSelect((select) => {
        if (!postId) return { availabilityMeta: {}, postType: null };

        const currentPostType = select(editorStore).getCurrentPostType();

        // Only fetch meta if we're on an availability post
        if (currentPostType !== 'availability') {
            return { availabilityMeta: {}, postType: currentPostType };
        }

        const post = select(coreStore).getEditedEntityRecord('postType', 'availability', postId);

        return {
            availabilityMeta: {
                rolling_upcoming_period: post?.meta?.rolling_upcoming_period || '6',
                periods_to_include: post?.meta?.periods_to_include || []
            },
            postType: currentPostType
        };
    }, [postId]);

    // Map pattern styles to location taxonomy slugs
    const patternConfig = {
        'coast': { slug: 'sea', name: 'By the Coast' },
        'cotswolds': { slug: 'cotswold', name: 'In the Cotswolds' },
        'country': { slug: 'country', name: 'In the Country' },
        'town': { slug: 'town', name: 'In town' }
    };

    const locationConfig = patternConfig[patternStyle] || patternConfig['coast'];

    // Format period names for display
    const periodLabels = {
        'weeks': 'Weeks',
        'Week': 'Weeks',
        'week': 'Weeks',
        '2 night weekend': 'Weekend (2 night)',
        '3 night weekend': 'Weekend (3 night)',
        'Midweek': 'Midweeks',
        '5 night': '5 nights (Christmas)'
    };

    const formatPeriods = (periods) => {
        if (!Array.isArray(periods) || periods.length === 0) return 'None selected';
        return periods.map(p => periodLabels[p] || p).join(', ');
    };

    // Calculate date range from rolling period
    const calculateDateRange = (weeks) => {
        const today = new Date();
        const endDate = new Date();
        endDate.setDate(endDate.getDate() + (parseInt(weeks) * 7));

        return {
            start: today.toLocaleDateString('en-GB'),
            end: endDate.toLocaleDateString('en-GB')
        };
    };

    const dateRange = calculateDateRange(availabilityMeta.rolling_upcoming_period);

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Query Settings', 'kate-toms-core')}>
                    <SelectControl
                        label={__('Order by', 'kate-toms-core')}
                        value={orderBy}
                        onChange={(value) => setAttributes({ orderBy: value })}
                        options={[
                            { label: __('Sleeps (Meta Value)', 'kate-toms-core'), value: 'meta_value_num' },
                            { label: __('Date', 'kate-toms-core'), value: 'date' },
                            { label: __('Title', 'kate-toms-core'), value: 'title' }
                        ]}
                    />
                    <SelectControl
                        label={__('Order', 'kate-toms-core')}
                        value={order}
                        onChange={(value) => setAttributes({ order: value })}
                        options={[
                            { label: __('Descending (High to Low)', 'kate-toms-core'), value: 'desc' },
                            { label: __('Ascending (Low to High)', 'kate-toms-core'), value: 'asc' }
                        ]}
                    />
                    <TextControl
                        label={__('Posts Per Page', 'kate-toms-core')}
                        value={postsPerPage}
                        onChange={(value) => {
                            // Convert to number, default to -1 if empty
                            const numValue = value === '' || value === '-1' ? -1 : parseInt(value, 10);
                            setAttributes({ postsPerPage: numValue });
                        }}
                        type="number"
                        help={__('Number of houses to display. Use -1 to show all matching houses.', 'kate-toms-core')}
                    />
                </PanelBody>
                <PanelBody title={__('Card Style', 'kate-toms-core')}>
                    <SelectControl
                        label={__('House Card Pattern', 'kate-toms-core')}
                        value={patternStyle}
                        onChange={(value) => setAttributes({ patternStyle: value })}
                        options={[
                            { label: __('Coast Style', 'kate-toms-core'), value: 'coast' },
                            { label: __('Cotswolds Style', 'kate-toms-core'), value: 'cotswolds' },
                            { label: __('Country Style', 'kate-toms-core'), value: 'country' },
                            { label: __('Town Style', 'kate-toms-core'), value: 'town' }
                        ]}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <div className="house-availability-landing-pages-preview">
                    <h3>{__('House Availability Landing Pages Block', 'kate-toms-core')}</h3>

                    {postType !== 'availability' ? (
                        <div style={{ padding: '20px', background: '#fff3cd', border: '1px solid #ffc107', borderRadius: '4px' }}>
                            <p><strong>{__('⚠️ This block only works on Availability post types', 'kate-toms-core')}</strong></p>
                            <p>{__('Please add this block to an availability post to see houses filtered by rolling availability periods.', 'kate-toms-core')}</p>
                        </div>
                    ) : (
                        <>
                            <div style={{ padding: '15px', background: '#f0f0f1', borderRadius: '4px', marginBottom: '10px' }}>
                                <p><strong>{__('Availability Criteria:', 'kate-toms-core')}</strong></p>
                                <p>
                                    {__('Rolling Period:', 'kate-toms-core')} <strong>{availabilityMeta.rolling_upcoming_period} weeks</strong><br />
                                    {__('Date Range (calculated):', 'kate-toms-core')} <strong>{dateRange.start}</strong> → <strong>{dateRange.end}</strong><br />
                                    {__('Periods:', 'kate-toms-core')} <strong>{formatPeriods(availabilityMeta.periods_to_include)}</strong>
                                </p>
                            </div>

                            <p>
                                {__('Location:', 'kate-toms-core')} <strong>{locationConfig.name}</strong><br />
                                {__('Ordering by:', 'kate-toms-core')} <strong>{orderBy === 'meta_value_num' ? 'Sleeps (Meta Value)' : orderBy}</strong> -
                                <strong> {order === 'desc' ? 'High to Low' : 'Low to High'}</strong><br />
                                {__('Posts Per Page:', 'kate-toms-core')} <strong>{postsPerPage === -1 ? __('All', 'kate-toms-core') : postsPerPage}</strong>
                            </p>

                            <div style={{ padding: '15px', background: '#e7f5ff', border: '1px solid #339af0', borderRadius: '4px' }}>
                                <p><em>{__('Houses will be filtered based on availability in the next ' + availabilityMeta.rolling_upcoming_period + ' weeks, matching the selected periods. Preview shows configuration only - actual filtering happens on the frontend.', 'kate-toms-core')}</em></p>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
