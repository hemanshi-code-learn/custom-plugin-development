jQuery(function($) {
    // --- Admin Submissions Pagination ---
    
    // This function handles the AJAX call to load a new page of submissions
    function loadSubmissions(paged) {
        var $container = $('#macf-submissions-container');
        var $loading = $('#macf-loading-indicator');

        // Check if the Admin localization object is available
        // macfAdminAjax is guaranteed to be available because we only load this script
        // on the correct admin page and localize it immediately before.
        if (typeof macfAdminAjax === 'undefined') {
             $container.html('<p class="error">Admin AJAX configuration missing. Please check plugin setup.</p>');
             return;
        }

        $.ajax({
            url: macfAdminAjax.ajaxurl,
            method: 'POST',
            data: {
                action: macfAdminAjax.action, // 'ma_get_submissions'
                nonce: macfAdminAjax.nonce,
                paged: paged
            },
            dataType: 'json',
            beforeSend: function() {
                $container.css('opacity', 0.5);
                $loading.show();
            },
            success: function(res) {
                if (res.success && res.data.html) {
                    $container.html(res.data.html);
                } else {
                    $container.html('<p class="error">Failed to load submissions: ' + (res.data?.message || 'Unknown error') + '</p>');
                }
            },
            error: function() {
                $container.html('<p class="error">A network error occurred while fetching submissions.</p>');
            },
            complete: function() {
                $container.css('opacity', 1);
                $loading.hide();
            }
        });
    }

    // Event listener for pagination links
    $(document).on('click', '#macf-submissions-container .tablenav-pages a', function(e) {
        e.preventDefault();
        var paged = $(this).data('page');
        if (paged) {
            loadSubmissions(paged);
        }
    });
    
    
});