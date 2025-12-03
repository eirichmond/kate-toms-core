/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, FormTokenField } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Edit function for House Landing Pages block
 */
export default function Edit({ attributes, setAttributes }) {
    const { postsPerPage = -1, orderBy = 'meta_value_num', order = 'desc', metaKey = 'sleeps_max', patternStyle = 'coast', taxonomyFilters = {} } = attributes;
    
    const blockProps = useBlockProps();
    
    // Map pattern styles to location taxonomy slugs
    const patternConfig = {
        'coast': { slug: 'sea', name: 'By the Coast' },
        'cotswolds': { slug: 'cotswold', name: 'In the Cotswolds' },
        'country': { slug: 'country', name: 'In the Country' },
        'town': { slug: 'town', name: 'In town' }
    };
    
    const locationConfig = patternConfig[patternStyle] || patternConfig['coast'];

    // Fetch taxonomy terms for the multi-select controls
    const { taxonomyTerms } = useSelect((select) => {
        const { getEntityRecords } = select(coreStore);
        return {
            taxonomyTerms: {
                location: getEntityRecords('taxonomy', 'location', { per_page: -1 }) || [],
                size: getEntityRecords('taxonomy', 'size', { per_page: -1 }) || [],
                type: getEntityRecords('taxonomy', 'type', { per_page: -1 }) || [],
                occasion: getEntityRecords('taxonomy', 'occasion', { per_page: -1 }) || [],
                feature: getEntityRecords('taxonomy', 'feature', { per_page: -1 }) || [],
            }
        };
    }, []);

    // Query houses with proper filtering
    const { houses, hasResolved } = useSelect((select) => {
        const query = {
            per_page: postsPerPage === -1 ? 100 : postsPerPage,
            status: 'publish',
        };

        // Add taxonomy filters using REST API format (taxonomy IDs, not slugs)
        // First, we need to get the taxonomy term IDs for the slugs we want to filter by
        
        // For now, let's add location filter if we have taxonomy terms loaded
        if (locationConfig.slug && taxonomyTerms.location) {
            const locationTerm = taxonomyTerms.location.find(term => term.slug === locationConfig.slug);
            if (locationTerm) {
                query.location = locationTerm.id;
            }
        }

        // Add additional taxonomy filters using term IDs
        Object.keys(taxonomyFilters).forEach(taxonomy => {
            if (taxonomyFilters[taxonomy] && taxonomyFilters[taxonomy].length > 0 && taxonomyTerms[taxonomy]) {
                const termIds = taxonomyFilters[taxonomy]
                    .map(slug => {
                        const term = taxonomyTerms[taxonomy].find(t => t.slug === slug);
                        return term ? term.id : null;
                    })
                    .filter(id => id !== null);
                
                if (termIds.length > 0) {
                    query[taxonomy] = termIds.join(',');
                }
            }
        });

        // Add ordering back (but not meta_value_num which REST API doesn't support well)
        if (orderBy !== 'meta_value_num') {
            query.orderby = orderBy;
            query.order = order;
        }

        const houses = select(coreStore).getEntityRecords('postType', 'houses', query) || [];
        const hasResolved = select(coreStore).hasFinishedResolution('getEntityRecords', ['postType', 'houses', query]);
        
        return {
            houses: houses,
            hasResolved: hasResolved
        };
    }, [postsPerPage, orderBy, order, metaKey, patternStyle, taxonomyFilters, taxonomyTerms]);
    
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
                <PanelBody title={__('Additional Filters', 'kate-toms-core')}>
                    <FormTokenField
                        label={__('Location', 'kate-toms-core')}
                        value={(taxonomyFilters.location || []).map(slug => {
                            const term = taxonomyTerms.location.find(t => t.slug === slug);
                            return term ? term.name : slug;
                        })}
                        suggestions={(taxonomyTerms.location || []).map(term => term.name)}
                        onChange={(tokens) => {
                            const slugs = tokens.map(token => {
                                const term = (taxonomyTerms.location || []).find(t => t.name === token);
                                return term ? term.slug : token;
                            });
                            setAttributes({
                                taxonomyFilters: { ...taxonomyFilters, location: slugs }
                            });
                        }}
                    />
                    <FormTokenField
                        label={__('Size', 'kate-toms-core')}
                        value={(taxonomyFilters.size || []).map(slug => {
                            const term = taxonomyTerms.size.find(t => t.slug === slug);
                            return term ? term.name : slug;
                        })}
                        suggestions={(taxonomyTerms.size || []).map(term => term.name)}
                        onChange={(tokens) => {
                            const slugs = tokens.map(token => {
                                const term = (taxonomyTerms.size || []).find(t => t.name === token);
                                return term ? term.slug : token;
                            });
                            setAttributes({
                                taxonomyFilters: { ...taxonomyFilters, size: slugs }
                            });
                        }}
                    />
                    <FormTokenField
                        label={__('Property Types', 'kate-toms-core')}
                        value={(taxonomyFilters.type || []).map(slug => {
                            const term = taxonomyTerms.type.find(t => t.slug === slug);
                            return term ? term.name : slug;
                        })}
                        suggestions={(taxonomyTerms.type || []).map(term => term.name)}
                        onChange={(tokens) => {
                            const slugs = tokens.map(token => {
                                const term = (taxonomyTerms.type || []).find(t => t.name === token);
                                return term ? term.slug : token;
                            });
                            setAttributes({
                                taxonomyFilters: { ...taxonomyFilters, type: slugs }
                            });
                        }}
                    />
                    <FormTokenField
                        label={__('Occasion', 'kate-toms-core')}
                        value={(taxonomyFilters.occasion || []).map(slug => {
                            const term = taxonomyTerms.occasion.find(t => t.slug === slug);
                            return term ? term.name : slug;
                        })}
                        suggestions={(taxonomyTerms.occasion || []).map(term => term.name)}
                        onChange={(tokens) => {
                            const slugs = tokens.map(token => {
                                const term = (taxonomyTerms.occasion || []).find(t => t.name === token);
                                return term ? term.slug : token;
                            });
                            setAttributes({
                                taxonomyFilters: { ...taxonomyFilters, occasion: slugs }
                            });
                        }}
                    />
                    <FormTokenField
                        label={__('Features', 'kate-toms-core')}
                        value={(taxonomyFilters.feature || []).map(slug => {
                            const term = taxonomyTerms.feature.find(t => t.slug === slug);
                            return term ? term.name : slug;
                        })}
                        suggestions={(taxonomyTerms.feature || []).map(term => term.name)}
                        onChange={(tokens) => {
                            const slugs = tokens.map(token => {
                                const term = (taxonomyTerms.feature || []).find(t => t.name === token);
                                return term ? term.slug : token;
                            });
                            setAttributes({
                                taxonomyFilters: { ...taxonomyFilters, feature: slugs }
                            });
                        }}
                    />
                </PanelBody>
            </InspectorControls>
            
            <div {...blockProps}>
                <div className="house-landing-pages-preview">
                    <h3>{__('House Landing Pages Block', 'kate-toms-core')}</h3>
                    <p>
                        {__('Location:', 'kate-toms-core')} <strong>{locationConfig.name}</strong><br />
                        {__('Ordering by:', 'kate-toms-core')} <strong>{orderBy === 'meta_value_num' ? 'Sleeps (Meta Value)' : orderBy}</strong> - 
                        <strong> {order === 'desc' ? 'High to Low' : 'Low to High'}</strong><br />
                        {Object.keys(taxonomyFilters).map(taxonomy => {
                            if (taxonomyFilters[taxonomy] && taxonomyFilters[taxonomy].length > 0) {
                                return (
                                    <span key={taxonomy}>
                                        <strong>{taxonomy.charAt(0).toUpperCase() + taxonomy.slice(1)}:</strong> {taxonomyFilters[taxonomy].join(', ')}<br />
                                    </span>
                                );
                            }
                            return null;
                        })}
                    </p>
                    
                    {!hasResolved ? (
                        <p>{__('Loading houses...', 'kate-toms-core')}</p>
                    ) : (
                        <div className="houses-preview">
                            <p><strong>{__('Found:', 'kate-toms-core')} {houses.length === 100 && postsPerPage === -1 ? '100+' : houses.length} {__('houses', 'kate-toms-core')}</strong></p>
                            <p><em>{__('Note: Editor preview shows REST API results, which may differ slightly from frontend due to query limitations.', 'kate-toms-core')}</em></p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
