/**
 * Gutenberg Editor Sidebar
 * Internal Linking Manager
 */

(function(wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { PanelBody, Button, Spinner, Notice } = wp.components;
    const { createElement: el, Component, Fragment } = wp.element;
    const { withSelect } = wp.data;
    const { compose } = wp.compose;

    /**
     * Main Sidebar Component
     */
    class LinkManagerSidebar extends Component {
        constructor(props) {
            super(props);

            this.state = {
                opportunities: [],
                loading: true,
                error: null,
                successMessage: null
            };

            this.scanPost = this.scanPost.bind(this);
            this.insertLink = this.insertLink.bind(this);
        }

        componentDidMount() {
            this.scanPost();
        }

        componentDidUpdate(prevProps) {
            // Re-scan when post content changes significantly
            if (prevProps.postId !== this.props.postId) {
                this.scanPost();
            }
        }

        scanPost() {
            const { postId } = this.props;

            if (!postId) {
                return;
            }

            this.setState({ loading: true, error: null });

            wp.apiFetch({
                path: `/internal-linking/v1/scan/${postId}`,
                method: 'GET'
            })
            .then(response => {
                this.setState({
                    opportunities: response.opportunities || [],
                    loading: false,
                    error: null
                });
            })
            .catch(error => {
                this.setState({
                    loading: false,
                    error: error.message || 'Failed to scan content'
                });
            });
        }

        insertLink(opportunity, matchIndex) {
            const { postId } = this.props;

            this.setState({ loading: true, error: null, successMessage: null });

            wp.apiFetch({
                path: '/internal-linking/v1/insert-link',
                method: 'POST',
                data: {
                    post_id: postId,
                    target_id: opportunity.target_id,
                    anchor_text: opportunity.anchor_text,
                    target_url: opportunity.target_url,
                    occurrence_index: matchIndex
                }
            })
            .then(response => {
                // Parse the new content into blocks
                const blocks = wp.blocks.parse(response.content);

                // Replace the editor blocks with the updated blocks
                wp.data.dispatch('core/block-editor').resetBlocks(blocks);

                this.setState({
                    loading: false,
                    successMessage: 'Link inserted successfully!'
                });

                // Clear success message after 3 seconds
                setTimeout(() => {
                    this.setState({ successMessage: null });
                }, 3000);

                // Re-scan to update opportunities
                setTimeout(() => {
                    this.scanPost();
                }, 500);
            })
            .catch(error => {
                this.setState({
                    loading: false,
                    error: error.message || 'Failed to insert link'
                });
            });
        }

        render() {
            const { opportunities, loading, error, successMessage } = this.state;

            return el(Fragment, {},
                el(PluginSidebarMoreMenuItem, {
                    target: 'internal-linking-sidebar',
                    icon: 'admin-links'
                }, 'Link Manager'),

                el(PluginSidebar, {
                    name: 'internal-linking-sidebar',
                    title: 'Link Manager',
                    icon: 'admin-links'
                },
                    el('div', { className: 'ilm-editor-sidebar' },
                        el('div', { className: 'ilm-sidebar-header' },
                            el('h2', {}, 'Internal Linking'),
                            el('p', { className: 'description' }, 'Review and insert internal links')
                        ),

                        successMessage && el(Notice, {
                            status: 'success',
                            isDismissible: true,
                            onRemove: () => this.setState({ successMessage: null })
                        }, successMessage),

                        error && el(Notice, {
                            status: 'error',
                            isDismissible: true,
                            onRemove: () => this.setState({ error: null })
                        }, error),

                        loading && el('div', { className: 'ilm-loading' },
                            el(Spinner)
                        ),

                        !loading && opportunities.length === 0 && el('div', { className: 'ilm-empty-state' },
                            el('p', {}, 'No linking opportunities found.'),
                            el('p', { style: { fontSize: '13px', color: '#757575' } },
                                'Add target pages in the Link Manager to start finding opportunities.'),
                            el(Button, {
                                variant: 'secondary',
                                onClick: this.scanPost,
                                style: { marginTop: '12px' }
                            }, 'Refresh')
                        ),

                        !loading && opportunities.length > 0 && el(Fragment, {},
                            el('div', { style: { padding: '16px' } },
                                el(Button, {
                                    variant: 'secondary',
                                    onClick: this.scanPost,
                                    style: { width: '100%' }
                                }, 'Refresh Opportunities')
                            ),

                            el('div', { className: 'ilm-opportunities' },
                                opportunities.map((opp, index) =>
                                    el(OpportunityItem, {
                                        key: `${opp.target_id}-${index}`,
                                        opportunity: opp,
                                        onInsert: this.insertLink
                                    })
                                )
                            )
                        )
                    )
                )
            );
        }
    }

    /**
     * Opportunity Item Component
     */
    class OpportunityItem extends Component {
        render() {
            const { opportunity, onInsert } = this.props;

            return el('div', {
                className: `ilm-opportunity ${opportunity.already_linked ? 'already-linked' : ''}`
            },
                // Header
                el('div', { className: 'ilm-opportunity-header' },
                    el('a', {
                        className: 'ilm-target-url',
                        href: opportunity.target_url,
                        target: '_blank',
                        rel: 'noopener noreferrer'
                    }, opportunity.target_url),
                    el('div', { className: 'ilm-anchor-text' },
                        opportunity.anchor_text,
                        el('span', {
                            className: `ilm-anchor-badge ${opportunity.is_primary ? 'primary' : 'variation'}`
                        }, opportunity.is_primary ? 'Primary' : 'Variation')
                    )
                ),

                // Already linked badge
                opportunity.already_linked && el('div', { style: { marginBottom: '12px' } },
                    el('span', { className: 'ilm-already-linked-badge' }, 'Already Linked')
                ),

                // Stats
                !opportunity.already_linked && el('div', { className: 'ilm-stats' },
                    el('div', { className: 'ilm-stats-item' },
                        `Total uses: ${opportunity.stats.total}`
                    ),
                    opportunity.is_primary && opportunity.stats.total > 0 &&
                        el('div', { className: 'ilm-stats-item' },
                            (() => {
                                const primaryStat = opportunity.stats.by_anchor.find(
                                    s => s.anchor_text === opportunity.target_primary
                                );
                                const primaryCount = primaryStat ? parseInt(primaryStat.count) : 0;
                                const percentage = opportunity.stats.total > 0
                                    ? Math.round((primaryCount / opportunity.stats.total) * 100)
                                    : 0;
                                return `Primary anchor: ${percentage}%`;
                            })()
                        )
                ),

                // Warnings
                opportunity.stats.warnings && opportunity.stats.warnings.length > 0 &&
                    opportunity.stats.warnings.map((warning, i) =>
                        el('div', {
                            key: i,
                            className: 'ilm-warning'
                        }, warning.message)
                    ),

                // Matches
                !opportunity.already_linked && opportunity.matches && opportunity.matches.length > 0 &&
                    el('div', { className: 'ilm-matches' },
                        el('div', { className: 'ilm-matches-header' },
                            `Found ${opportunity.matches.length} occurrence${opportunity.matches.length > 1 ? 's' : ''}`
                        ),
                        opportunity.matches.map((match, matchIndex) =>
                            el('div', {
                                key: matchIndex,
                                className: 'ilm-match'
                            },
                                el('div', {
                                    className: 'ilm-match-context',
                                    dangerouslySetInnerHTML: {
                                        __html: match.context.replace(
                                            new RegExp(match.text, 'gi'),
                                            '<mark>$&</mark>'
                                        )
                                    }
                                }),
                                el('div', { className: 'ilm-match-actions' },
                                    el(Button, {
                                        variant: 'primary',
                                        size: 'small',
                                        onClick: () => onInsert(opportunity, matchIndex)
                                    }, 'Insert Link'),
                                    el(Button, {
                                        variant: 'secondary',
                                        size: 'small'
                                    }, 'Skip')
                                )
                            )
                        )
                    )
            );
        }
    }

    /**
     * Connect to WordPress data
     */
    const ConnectedSidebar = compose([
        withSelect((select) => {
            return {
                postId: select('core/editor').getCurrentPostId(),
                postType: select('core/editor').getCurrentPostType()
            };
        })
    ])(LinkManagerSidebar);

    /**
     * Register the plugin
     */
    registerPlugin('internal-linking-manager', {
        render: ConnectedSidebar
    });

})(window.wp);
