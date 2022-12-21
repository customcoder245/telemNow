<?php
 
/**
 
 
 
Plugin Name: TelemdNow
 
Plugin URI: https://telemdnow.com/ 
 
Description: Used to send prospect patients to the Telemdnow System.
 
Version: 1.0.0
 
Author: Automattic
 
Author URI: https://automattic.com/wordpress-plugins/
 
License: GPLv2 or later
 
Text Domain: akismet

*/

//require_once('admin_setting.php');
define('WP_DEBUG_LOG', true);
 @error_reporting( E_ALL );

@ini_set( "log_errors_max_len", "0" );


define( "CONCATENATE_SCRIPTS", false );
define( "SAVEQUERIES", true );


function register_my_custom_submenu_page() {
    add_submenu_page( 'woocommerce', 'TelemdNow', 'TelemdNow', 'manage_options', 'telemdnow', 'my_custom_submenu_page_callback' ); 
}
function my_custom_submenu_page_callback() {
   require_once("general.php");
}
add_action('admin_menu', 'register_my_custom_submenu_page',99);


/**************** include js ***************/

add_action('admin_enqueue_scripts','telemdnow_script_init');

function telemdnow_script_init() {
    
	 wp_enqueue_script('my_custom_script', plugin_dir_url(__FILE__) . '/js/script.js',false, array(), true, true);
	 
	 wp_register_style( 'telemdnow-style', plugin_dir_url(__FILE__). '/css/telemdnow-style.css', false, '1.0.0' );
        wp_enqueue_style( 'telemdnow-style' );
	 
}

add_action("wp_ajax_talemdnow_api_auth", "authicate_telemdnow");
add_action("wp_ajax_nopriv_talemdnow_api_auth", "authicate_telemdnow");

function authicate_telemdnow() {
	global $wpdb;
	
       $affiliate_token=$_POST['affiliate_token'];
       $host=$_POST['eurl'];
       $username=$_POST['user_name'];
       $Password=$_POST['Password'];
     $table_name = $wpdb->prefix . "telemdnow_setting";
	 $tmd_action=$_POST['tmd_action'];
	 $affiliate_url=$_POST['affiliate_url'];
 $masterToken=base64_encode($username.':'.$Password);
$curl = curl_init();
$tel_url=get_option('eurl');
curl_setopt_array($curl, array(
  CURLOPT_URL => $tel_url.'/auth/client',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_HTTPHEADER => array(
    'Authorization: Basic '.$masterToken
  ),
));

$response = curl_exec($curl);

curl_close($curl);

if(!empty($response)){
	$res_data=json_decode($response);
         $token=$res_data->token;
	      update_option('master_token',$master_token);
		  update_option( 'eurl', $host);
		  if(!empty($affiliate_token)){
			 update_option( 'affiliate_token', $affiliate_token); 
		  }else{
			 update_option( 'affiliate_token', $token); 
		  }
		  update_option( 'affiliate', $token);
		  update_option( 'tel_user_name', $username);
		  update_option( 'tel_Password', $Password);
		  update_option( 'tmd_action', $tmd_action);
		  update_option( 'affiliate_url', $affiliate_url);
	 $msg='data save successfully';
}else{
	$msg='Something went wrong';
}

	 
	 
	 
	     
	
$res=array('code'=>200,'msg'=>$msg);
echo json_encode($res);
   die();
}



function get_telemdnow_product(){
  
	
		$curl = curl_init();
       
		$tel_url=get_option('eurl');
		 $pub_key=get_option('affiliate_token');
		curl_setopt_array($curl, array(
		  CURLOPT_URL =>$tel_url.'/products',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'GET',
		  CURLOPT_HTTPHEADER => array(
			"Authorization: Bearer ".$pub_key
		  ),
		));
 
		$response = curl_exec($curl);
  
		curl_close($curl);
		return json_decode($response);
	

}
function get_general_setting(){
	global $wpdb;
    $table_name = $wpdb->prefix . "telemdnow_setting";
	$user_id=get_current_user_id();
	$data=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$table_name.' WHERE  user_id='.$user_id));
	
	return $data;
}




function creart_custom_action_tmd(){
	$action=get_option('tmd_action');
	if(!empty($action)){
		if($action=='new_order'){
	     add_action( 'woocommerce_thankyou', 'create_invoice_for_wc_order_order_create',  10, 5  );
		 add_action( 'woocommerce_new_order', 'custom_woocommerce_auto_complete_order' );
		 
		}
		
		

	}
}

add_action('init','creart_custom_action_tmd');



function action_woocommerce_order_status_changed( $order_id, $old_status, $new_status, $order ) {

    // Compare
	
	$action=get_option('tmd_action');
    if( ($new_status === 'processing') && ($action=="processing")) {
          create_invoice_for_wc_order($order_id);
    }else if( ($new_status === 'on-hold') && ($action=="on-hold")) {
          create_invoice_for_wc_order($order_id);
    }else if( ($new_status === 'completed') && ($action=="completed")) {
          create_invoice_for_wc_order($order_id);
    }
	
	
}

add_action( 'woocommerce_order_status_changed', 'action_woocommerce_order_status_changed', 10, 4 );

function custom_woocommerce_auto_complete_order( $order_id ) {
    if ( ! $order_id ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if( 'processing'== $order->get_status() ) {
        $order->update_status( 'on-hold' );
    }
}
add_action( 'woocommerce_payment_complete', 'custom_woocommerce_auto_complete_order' );

function create_invoice_for_wc_order( $order_id ) {
	$action=get_option('tmd_action');
   
   
   $order = wc_get_order( $order_id );
   
foreach ( $order->get_items() as $item_id => $item ) {
	 $product_id = $item->get_product_id();
	$variation_id = $item->get_variation_id();
}
	 
	 if(!empty(get_post_meta($product_id,'tmd_vid',true))){
	$tmd_vid=get_post_meta($product_id,'tmd_vid',true);
	$dob='01-01-80';
	$saddress1=!empty($order->get_shipping_address_1()) ? $order->get_shipping_address_1() :$order->get_billing_address_1();
	$saddress2=!empty($order->get_shipping_address_2()) ?  $order->get_shipping_address_2()  : $order->get_shipping_address_2();
	$scity=  !empty($order->get_shipping_city()) ? $order->get_shipping_city() : $order->get_shipping_city();
	$sstate= !empty($order->get_shipping_state()) ? $order->get_shipping_state() : $order->get_shipping_state();
	$spin= !empty($order->get_shipping_postcode()) ? $order->get_shipping_postcode() : $order->get_shipping_postcode();
	
	$data=array('dateOfBirth'=>$dob,'email'=>$order->get_billing_email(),'firstName'=>$order->get_billing_first_name(),'lastName'=>$order->get_billing_last_name(),'phone'=>$order->get_billing_phone(),
	'productVariations'=>array(array('productVariation'=>$tmd_vid)),'project'=>'','address'=>array('billing'=>array('address1'=>$order->get_billing_address_1(),'address2'=>$order->get_billing_address_2(),
	'city'=>$order->get_billing_city(),'state'=>$order->get_billing_state(),'zipcode'=>$order->get_billing_postcode()),'shipping'=>array('address1'=>$saddress1,'address2'=>$saddress2,
	'city'=>$scity,'state'=>$sstate,'zipcode'=>$spin)));
	
	$curl = curl_init();
  $keys=get_option('affiliate');

	$tel_url=get_option('eurl');
curl_setopt_array($curl, array(
  CURLOPT_URL => $tel_url.'/prospect-patient?access_token='.$keys,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>json_encode($data),
  CURLOPT_HTTPHEADER => array(
    'affiliate-token: '.$keys,
    'Content-Type: application/json'
  ),
));

$response = curl_exec($curl);

if(empty($response)){
	error_log("prospect-patient not generate", 0);
}

update_post_meta($order_id ,'telemdnow_order_res',$response);
$res=json_decode($response);
if(isset($res->id)){
update_post_meta($order_id,'telemdnow_prospect_patient_id',$res->id);
}
curl_close($curl);
	 }
}



function create_invoice_for_wc_order_order_create( $order_id ) {
	$action=get_option('tmd_action');
   
   if( ! get_post_meta( $order_id, '_thankyou_action_done', true ) ) {
   $order = wc_get_order( $order_id );
   
foreach ( $order->get_items() as $item_id => $item ) {
	 $product_id = $item->get_product_id();
	$variation_id = $item->get_variation_id();
}
	
	 if(!empty(get_post_meta($product_id,'tmd_vid',true))){
	$tmd_vid=get_post_meta($product_id,'tmd_vid',true);
	$dob='01-01-80';
	$saddress1=!empty($order->get_shipping_address_1()) ? $order->get_shipping_address_1() :$order->get_billing_address_1();
	$saddress2=!empty($order->get_shipping_address_2()) ?  $order->get_shipping_address_2()  : $order->get_shipping_address_2();
	$scity=  !empty($order->get_shipping_city()) ? $order->get_shipping_city() : $order->get_shipping_city();
	$sstate= !empty($order->get_shipping_state()) ? $order->get_shipping_state() : $order->get_shipping_state();
	$spin= !empty($order->get_shipping_postcode()) ? $order->get_shipping_postcode() : $order->get_shipping_postcode();
	
	$data=array('dateOfBirth'=>$dob,'email'=>$order->get_billing_email(),'firstName'=>$order->get_billing_first_name(),'lastName'=>$order->get_billing_last_name(),'phone'=>$order->get_billing_phone(),
	'productVariations'=>array(array('productVariation'=>$tmd_vid)),'project'=>'','address'=>array('billing'=>array('address1'=>$order->get_billing_address_1(),'address2'=>$order->get_billing_address_2(),
	'city'=>$order->get_billing_city(),'state'=>$order->get_billing_state(),'zipcode'=>$order->get_billing_postcode()),'shipping'=>array('address1'=>$saddress1,'address2'=>$saddress2,
	'city'=>$scity,'state'=>$sstate,'zipcode'=>$spin)));
	
	$curl = curl_init();
    $keys=get_option('affiliate');
	
	$tel_url=get_option('eurl');
curl_setopt_array($curl, array(
  CURLOPT_URL => $tel_url.'/prospect-patient?access_token='.$keys,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>json_encode($data),
  CURLOPT_HTTPHEADER => array(
    'affiliate-token: '.$keys,
    'Content-Type: application/json'
  ),
));

$response = curl_exec($curl);
$res=json_decode($response);
if(isset($res->id)){
update_post_meta($order_id,'telemdnow_prospect_patient_id',$res->id);
}
update_post_meta($order_id ,'telemdnow_order_res',$response);
curl_close($curl);
	 }
	 
   }
}







function slider_metaboxes() {
	global $post; global $wp_meta_boxes;
	add_meta_box('postfunctiondiv', __('Prospect Patient/Send '), 'slider_metaboxes_html', 'shop_order', 'normal', 'high');

}
function slider_metaboxes_html(){
	global $post;
	$order_id=$post->ID;
	$mdata=get_post_meta($order_id,'telemdnow_order_res',true);
	if(!empty($mdata)){
		$jmdata=json_decode($mdata);
		
		$html='';
		$html .='<table class="table_order">
		 <tr><th>ID</th><td>'.$jmdata->id.'</td></tr>
		  <tr><th>Affiliate ID</th><td>'.$jmdata->affiliate->_id.'</td></tr>
		  <tr><th>Affiliate Name</th><td>'.$jmdata->affiliate->name.'</td></tr>
		  <tr><th>Affiliate key</th><td>'.$jmdata->affiliate->affiliateKey.'</td></tr>
		  <tr><th>Project ID</th><td>'.$jmdata->project.'</td></tr>
		</table>';
		echo $html;
	    echo '<a href="#" data-id="'.$post->ID.'" class="btn btn-primary patirnd_email_remder">Patient Email Reminder</a>';
		echo '<style>
		
.table_order th:first-child {
    text-align: left;
    min-width: 110px;
}
#order_data .order_data_column .form-field .date-picker {
    width: 45%;
}
a.patirnd_email_remder {
    display: inline-block;
    text-decoration: none;
    font-size: 13px;
    line-height: 2.15384615;
    min-height: 30px;
    margin: 20px 0 0;
    padding: 0 10px;
    cursor: pointer;
    border-width: 1px;
    border-style: solid;
    -webkit-appearance: none;
    border-radius: 3px;
    white-space: nowrap;
    box-sizing: border-box;
}



</style>';
		echo '<script>
		 jQuery(".patirnd_email_remder").click(function(e){
			e.preventDefault();
            var id=jQuery(this).data("id");
				 jQuery.ajax({
						type : "POST",
						dataType : "json",
						url : "'. admin_url('admin-ajax.php') .'",
						data : {id:id,action:"tmd_order_reminder_email"},
						success: function(data) {
							if(data.code==200){
								
								alert(data.msg);
							}else if(data.code==201){
								alert(data.msg);
							}
						}
					});


      			
		 });
		</script>';
		
	}
}

//add_meta_boxes_slider => add_meta_boxes_{custom post type}
add_action( 'add_meta_boxes_shop_order', 'slider_metaboxes' );


add_action("wp_ajax_affiliate_product_action", "affiliate_product_action");
add_action("wp_ajax_nopriv_affiliate_product_action", "affiliate_product_action");
function affiliate_product_action(){
	
	
	foreach($_POST['tmd'] as $tmd){
		
		if(!empty($tmd['product']) && !empty($tmd['vid'])){
			
			update_post_meta($tmd['product'],'tmd_vid',$tmd['vid']);
			update_post_meta($tmd['product'],'telemdnow_prospect_patient_id',$tmd['vid']);
		}
	}
	$res=array('code'=>200,'msg'=>'Product update successfully');
	echo json_encode($res);
	die();
	
}
add_action("wp_ajax_tmd_order_reminder_email", "tmd_order_reminder_email");
add_action("wp_ajax_nopriv_tmd_order_reminder_email", "tmd_order_reminder_email");
function tmd_order_reminder_email(){
	
	$order_id=$_POST['id'];
	$mdata=get_post_meta($order_id,'telemdnow_order_res',true);
	$jmdata=json_decode($mdata);
	$token=get_option('affiliate_token');
	$curl = curl_init();
   $p_id=$jmdata->id;
   $tel_url=get_option('eurl');
curl_setopt_array($curl, array(
  CURLOPT_URL => $tel_url.'/prospect-patient/'.$p_id.'/reminder?access_token='.$token,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_HTTPHEADER => array(
    'affiliate-token: '.$token
  ),
));

$response = curl_exec($curl);

curl_close($curl);
if(!empty($response)){
 $res=array('code'=>200,'msg'=>'send mail');
}else{
	$res=array('code'=>201,'msg'=>'Something Went Wrong');
}
echo json_encode($res);
die();
	
}

add_shortcode('get_the_affiliate_link','get_the_affiliate_link');
function get_the_affiliate_link(){
	$orderid=$_GET['order_id'];
	$url="#";
	if(!empty($orderid)){
		if(!empty(get_post_meta($orderid,'telemdnow_prospect_patient_id',true))){
			$p_id=get_post_meta($orderid,'telemdnow_prospect_patient_id',true);
			$url=get_option('affiliate_url').$p_id;
			
		}
		
		
	}
	return $url;
}




if ( ! function_exists( 'plugin_log' ) ) {
  function plugin_log( $entry, $mode = 'a', $file = 'plugin' ) { 
    // Get WordPress uploads directory.
    $upload_dir = wp_upload_dir();
    $upload_dir = $upload_dir['basedir'];
    // If the entry is array, json_encode.
    if ( is_array( $entry ) ) { 
      $entry = json_encode( $entry ); 
    } 
    // Write the log file.
    $file  = $upload_dir . '/' . $file . '.log';
    $file  = fopen( $file, $mode );
    $bytes = fwrite( $file, current_time( 'mysql' ) . "::" . $entry . "\n" ); 
    fclose( $file ); 
    return $bytes;
  }
}