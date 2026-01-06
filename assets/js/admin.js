/**
 * Admin JavaScript
 * Internal Linking Manager
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Auto-dismiss notices after 5 seconds
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut();
        }, 5000);

        // Confirm delete actions
        $('.ilm-delete-target').on('click', function(e) {
            if (!confirm('Are you sure you want to delete this target? This will not remove existing links.')) {
                e.preventDefault();
                return false;
            }
        });

        // Form validation
        $('form[action*="ilm_save_target"]').on('submit', function(e) {
            var url = $('#url').val().trim();
            var primaryAnchor = $('#primary_anchor').val().trim();

            if (!url || !primaryAnchor) {
                alert('URL and Primary Anchor are required fields.');
                e.preventDefault();
                return false;
            }
        });

        // Initialize tooltips if available
        if (typeof $.fn.tooltip !== 'undefined') {
            $('[data-toggle="tooltip"]').tooltip();
        }
    });

})(jQuery);
