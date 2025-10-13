jQuery(document).ready(function($){
    $('#ma-contact-form').on('submit', function(e){
        e.preventDefault();

        
        var formData = $(this).serializeArray(); 

        formData.push({
            name:'action',
            value:'ma_submit_form'
        });

        var $responseDiv = $('#ma-form-response');
        $responseDiv.html('Sending...').removeClass('error success').addClass('sending');

        $.ajax({
            type: 'POST',
            url: maContactFormAjax.ajaxurl,
            data:formData,
            success: function(response){
                if(response.success){
                    $responseDiv.html(response.data.message).removeClass('sending error').addClass('success');
                    $('#ma-contact-form')[0].reset();
                } else {
                    
                    $responseDiv.html(response.data.message).removeClass('sending success').addClass('error'); 
                }
            },
            
            error: function(){ 
                $responseDiv.html('An unexpected error occurred.').removeClass('sending success').addClass('error');
            }
        });
    });
});