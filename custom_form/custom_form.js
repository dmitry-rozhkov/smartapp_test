jQuery(function(jQuery){
    jQuery('#custom_form').submit(function(){
        jQuery('.cf_send').val('Sending...');
        jQuery.ajax({
            url:cf_ajaxurl,
            data: jQuery('#custom_form').serialize() + "&formSubmit=true",
            type:'POST',
            success:function(ajax_data){
                if( ajax_data ) {
                    return_date = JSON.parse(ajax_data);
                    if(return_date['error']){
                        jQuery('.cf_send').val('Send');
                    }else{
                        jQuery('#custom_form input').hide();
                        jQuery('#custom_form textarea').hide();
                    }
                    jQuery('.cf_message').html(return_date['message']);
                }
            }
        });
        return false;
    });
});