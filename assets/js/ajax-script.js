jQuery(document).ready(function($) {
    $('#ma-contact-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $response = $('#ma-form-response');
        // Clear old errors
        $('.macf-error').text('');
        $response.removeClass('success error').text('');

        var formData = $form.serialize();

        $.ajax({
            url: maContactFormAjax.ajaxurl,
            method: 'POST',
            data: formData + '&action=ma_submit_form',
            dataType: 'json',
            beforeSend: function() {
                // Optionally show a loading message
                $response.text('Sending...').removeClass('error success');
            },
            success: function(res) {
                if (res.success) {
                    $response.addClass('success').text(res.data.message);
                    $form[0].reset();
                } else {
                    if (res.data && res.data.type === 'validation_errors' && res.data.errors) {
                        // Show validation errors near fields
                        $.each(res.data.errors, function(field, msg) {
                            $('#error-' + field).text(msg);
                        });
                    } else if (res.data && res.data.message) {
                        $response.addClass('error').text(res.data.message);
                    } else {
                        $response.addClass('error').text('An unexpected error occurred.');
                    }
                }
            },
            error: function(xhr, status, error) {
                var msg = 'Something went wrong: ' + error;
                $response.addClass('error').text(msg);
            }
        });
    });
});


