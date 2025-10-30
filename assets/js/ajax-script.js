    jQuery(function($) {

        function fetchFreshNonce() {
            return $.ajax({
                url: maContactFormAjax.ajaxurl,
                method: 'POST',
                data: {
                    action: 'ma_get_fresh_nonce'
                },
                dataType: 'json'
            }).promise();
        }

        $('#ma-contact-form').on('submit', function(e) {
            e.preventDefault();
    
            var $form = $(this);
            var $response = $('#ma-form-response');
            $('.macf-error').text('');
            $response.removeClass('success error').text('');

            fetchFreshNonce().done(function(nonceRes) {
            
                if (!nonceRes.success || !nonceRes.data.nonce) {
                    $response.addClass('error').text('Could not get fresh security token. Please reload the page.');
                    return;
                }
    
                $form.find('input[name="ma_contact_form_nonce_field"]').val(nonceRes.data.nonce);
    
                var formData = $form.serialize();
        
                $.ajax({
                    url: maContactFormAjax.ajaxurl,
                    method: 'POST',
                    data: formData + '&action=' + maContactFormAjax.action,
                    dataType: 'json',
                    beforeSend: function() {
                        $response.text('Sending...').removeClass('error success');
                    },
                    success: function(res) {
                        $response.removeClass('success error').text('');
                        if (res.success) {
                            $response.addClass('success').text(res.data.message);
                            $form[0].reset();
                        } else if (res.data?.type === 'validation_errors' && res.data.errors) {
                            $.each(res.data.errors, function(field, msg) {
                                $('#error-' + field).text(msg);
                            });
                        } else {
                            $response.addClass('error').text(res.data?.message || 'An unexpected error occurred.');
                        }
                    },
                    error: function(xhr, status, error) {
                        $response.addClass('error').text('Something went wrong: ' + error);
                    }
                });
            }).fail(function() {
                $response.addClass('error').text('Network error while requesting security token.');
            });
        });
    });

    