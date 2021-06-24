<?php
/*
 * Plugin Name: Custom Form
 * Description: Custom Form ...
 * Author: 		Dmitry Rozhkov
 * Version:     1.0
 */
define("CF_DEBUG", true);
define("HUBSPOT_API_KEY", "019eb3dc-604c-4a42-859d-0f101c05ac2d");

add_shortcode( 'custom_form', 'Custom_Form_output' );

add_action('wp_ajax_custom_form', 'Custom_Form::cf_send_message');
add_action('wp_ajax_nopriv_custom_form', 'Custom_Form::cf_send_message');

function Custom_Form_output($atts){
    $form = new Custom_Form();
}

class Custom_Form{
    public function __construct(){
        wp_enqueue_style( 'custom_form_css', plugins_url('custom_form.css', __FILE__) );
        wp_enqueue_script('custom_form_js', plugins_url('custom_form.js', __FILE__), array('jquery') );
        $this->form_output();
    }

    public static function cf_send_message(){

        if (strpos($_SERVER['HTTP_REFERER'], get_site_url()) === false) return;
        $cf_out = [];

        // Разобратьстя кому и как отправлять

        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];

        $subject = $_POST['subject'];
        $message = $_POST['message'];

        $email_to =  filter_var($_POST['email'], FILTER_VALIDATE_EMAIL); // отправляем сообщение на почту из формы
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
            }

        }else{
            $cf_out['error'] = true;
            $cf_out['message'] = "Error in sending email";
        }
        // CREATING CONTACT
        /* https://packagist.org/packages/hubspot/api-client */
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.hubapi.com/crm/v3/objects/contacts?hapikey=".HUBSPOT_API_KEY,
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
            if(CF_DEBUG) $cf_out['message'] .= "Crating contact hubspot.com: Error #:" . $err;
        }

        die(json_encode($cf_out ));
    }

    public function form_output(){
        ?>
        <script>
            var cf_ajaxurl = "<?php echo site_url().'/wp-admin/admin-ajax.php'; ?>";
        </script>
        <form id="custom_form" action="/" method="post" data-ajax="">
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
}

