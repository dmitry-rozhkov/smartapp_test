jQuery(function(jQuery){
    jQuery('.cf_form').submit(function(){
        cf_form = jQuery(this);
        cf_form.find('.cf_send').val('Sending...');
        jQuery.ajax({
            url:cf_ajaxurl,
            data: cf_form.serialize() + "&formSubmit=true",
            type:'POST',
            success:function(ajax_data){
                if( ajax_data ) {
                    return_date = JSON.parse(ajax_data);
                    if(return_date['error']){
                        cf_form.find('.cf_send').val('Send');
                    }else{
                        cf_form.find('input').hide();
                        cf_form.find('textarea').hide();
                    }
                    cf_form.find('.cf_message').html(return_date['message']);
                }
            }
        });
        return false;
    });
});