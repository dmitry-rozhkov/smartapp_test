<?php
/*
 * Plugin Name: Custom Form
 * Description: Test work for "Smart App". Use shortcode [custom_form]
 * Author: 		Dmitry Rozhkov
 * Version:     1.0
 */

define("CF_DEBUG", true);

add_shortcode( 'custom_form', 'cf_form_output' );

add_action('wp_ajax_custom_form', 'cf_send_message');
add_action('wp_ajax_nopriv_custom_form', 'cf_send_message');
add_action('wp_enqueue_scripts', 'cf_add_js_variables');

// OPTIONS
add_action('admin_menu', 'cf_add_options_page');
add_action( 'admin_init', 'cf_options_init' );

function cf_add_options_page(){
    add_options_page( 'Custom Form options', 'Custom Form', 'manage_options', 'custom_form-options-page', 'cf_options_page_output' );
}

function cf_options_page_output(){
    ?>
    <form action='options.php' method='post'>
        <?php
        settings_fields( 'custom_form_opt' );
        do_settings_sections( 'custom_form_opt' );
        submit_button();
        ?>
    </form>
    <?php
}

function cf_options_init( ) {
    register_setting( 'custom_form_opt', 'cf_options' );
    add_settings_section( 'custom_form_section', 'Custom Form Options', '', 'custom_form_opt' );

    add_settings_field(
        'cf_subject',
        "Email subject",
        'cf_subject_render',
        'custom_form_opt',
        'custom_form_section'
    );

    add_settings_field(
        'cf_message',
        "Email message",
        'cf_message_render',
        'custom_form_opt',
        'custom_form_section'
    );

    add_settings_field(
        'cf_api_key',
        "HUBSPOT_API_KEY",
        'cf_api_key_render',
        'custom_form_opt',
        'custom_form_section'
    );

}

function cf_subject_render( ) {
    $options = get_option( 'cf_options' );
    ?>
    <input type='text' name='cf_options[cf_subject]' value='<?php echo $options['cf_subject']; ?>'>
    <?php
}

function cf_message_render( ) {
    $options = get_option( 'cf_options' );
    ?>
    <textarea name='cf_options[cf_message]'><?php echo $options['cf_message']; ?></textarea>
    <?php
}

function cf_api_key_render( ) {
    $options = get_option( 'cf_options' );
    ?>
    <input type='text' name='cf_options[cf_api_key]' value='<?php echo $options['cf_api_key']; ?>'>
    <?php
}

// FORM
function cf_add_js_variables(){
    wp_localize_script( 'jquery', 'cf_ajaxurl', site_url().'/wp-admin/admin-ajax.php' );
}

function cf_form_output($atts){
    wp_enqueue_style( 'custom_form_css', plugins_url('custom_form.css', __FILE__) );
    wp_enqueue_script('custom_form_js', plugins_url('custom_form.js', __FILE__), array('jquery') );
    ?>
    <form class="cf_form" action="/" method="post" data-ajax="">
        <?php wp_nonce_field("cf_act", "cf_nonce"); ?>
        <input name="first_name" type="text" placeholder="Your First Name" required="">
        <input name="last_name" type="text" placeholder="Your Last Name" required="">
        <input name="subject" type="text" placeholder="Subject" required="">
        <textarea name="message" placeholder="Your message" required=""></textarea>
        <input name="email" type="email" placeholder="Your email" required="">
        <div class="cf_message"></div>
        <input type="hidden" name="action" value="custom_form">
        <input type="submit" value="Send" class="cf_send">
    </form>
    <?php
}


function cf_send_message(){

    if (strpos($_SERVER['HTTP_REFERER'], get_site_url()) === false) return;
    if(!isset($_POST['cf_nonce']) || !wp_verify_nonce( $_POST['cf_nonce'], 'cf_act')) return;

    $cf_out = [];

    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];

    $options = get_option( 'cf_options' );
    $subject = $options['cf_subject'];
    $message = $options['cf_message'];

    $email_to = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL); // отправляем сообщение на почту из формы
    if(!$email_to){
        $cf_out['error'] = true;
        $cf_out['message'] = "Email have a wrong format";
        die(json_encode($cf_out ));
    }

    // SEND EMAIL
    if(wp_mail( $email_to, $subject, $message)){
        $cf_out['error'] = false;
        $cf_out['message'] = "Email was sent successfully.";
        if(CF_DEBUG) {
            $cf_out['message'] .= "<br>TO: " . $email_to;
            $cf_out['message'] .= "<br>SUBJECT: " . $subject;
            $cf_out['message'] .= "<br>MESSAGE: " . $message;
        }

        // SAVE TO LOG
        $log_file_path = ABSPATH . 'wp-content/plugins/custom_form/custom_form.log';
        $log_record = "\n" . current_time("Y-m-d H:i:s")." Email was sent to:". $email_to;
        if(file_put_contents($log_file_path, $log_record,FILE_APPEND) == FALSE) {
            if(CF_DEBUG)  $cf_out['message'] .= "<br>ERROR in writing log: " . $log_file_path;
        }else{
            if(CF_DEBUG)  $cf_out['message'] .= "<br>Writing log: OK";
        }

    }else{
        $cf_out['error'] = true;
        $cf_out['message'] = "Error in sending email";
    }

    // CREATING CONTACT
    // https://packagist.org/packages/hubspot/api-client
    if(empty($options['cf_api_key'])) {
        if(CF_DEBUG) $cf_out['message'] .= "<br>Crating contact hubspot.com: Error: No API KEY";
    }else{
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.hubapi.com/crm/v3/objects/contacts?hapikey=".$options['cf_api_key'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"properties\":{\"email\":\"".$email_to."\",\"firstname\":\"".$first_name."\",\"lastname\":\"".$last_name."\"}}",
            CURLOPT_HTTPHEADER => array(
                "accept: application/json",
                "content-type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            if(CF_DEBUG) $cf_out['message'] .= "<br>Crating contact hubspot.com: Error #:" . $err;
        }else{
            if(CF_DEBUG) $cf_out['message'] .= "<br>Crating contact hubspot.com: OK";
        }
    }
    die(json_encode($cf_out ));
}