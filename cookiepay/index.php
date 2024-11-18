<?php
/*
Plugin Name: CookiePay for woocommerce 
Plugin URI: https://cookiepayments.com/page/form
Description: CookiePay for woocommerce 
Version: 1.0.1
Author: CookiePay
*/

define( 'PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); 
include (PLUGIN_DIR.'gateway/cookiepay_gateway.php');
include (PLUGIN_DIR.'inc/ck_pay_list.php');

//css, js include
function load_custom_wp_admin_style() {
	wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_register_style( 'jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css' );
    wp_enqueue_style( 'jquery-ui' );
	
	wp_enqueue_script( 'ck_common_js', plugins_url( '/js/common.js', __FILE__ ), array(), '1.0.1', true );
	wp_localize_script( 'ck_common_js', 'ajax_object',array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
}
add_action( 'admin_enqueue_scripts', 'load_custom_wp_admin_style' );

function ck_enqueue_scripts() {
	wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_register_style( 'jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css' );
    wp_enqueue_style( 'jquery-ui' );
	
	wp_enqueue_script( 'ck_common_js', plugins_url( '/js/common.js', __FILE__ ), array(), '1.0.1', true );
	wp_localize_script( 'ck_common_js', 'ajax_object',array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
}
add_action( 'wp_enqueue_scripts', 'ck_enqueue_scripts' );

add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields_03' );
//주문자 정보 항목 수정
function custom_override_checkout_fields_03( $fields ) {
     //unset($fields['billing']['billing_first_name']);
     unset($fields['billing']['billing_last_name']); 
     unset($fields['billing']['billing_company']); 
     //unset($fields['billing']['billing_address_1']); 
     //unset($fields['billing']['billing_address_2']); 
     unset($fields['billing']['billing_city']); 
     //unset($fields['billing']['billing_postcode']); 
     unset($fields['billing']['billing_country']); 
     unset($fields['billing']['billing_state']); 
     //unset($fields['billing']['billing_email']); 
     //unset($fields['billing']['billing_phone']); 
     //unset($fields['shipping']['shipping_first_name']);
     unset($fields['shipping']['shipping_last_name']);
     unset($fields['shipping']['shipping_company']);
     //unset($fields['shipping']['shipping_address_1']);
     //unset($fields['shipping']['shipping_address_2']);
     unset($fields['shipping']['shipping_city']);
     //unset($fields['shipping']['shipping_postcode']);
     unset($fields['shipping']['shipping_country']);
     unset($fields['shipping']['shipping_state']);
     
     //unset($fields['order']['order_comments']); // 주문 메모 필드 제거

     return $fields;
}
function get_ck_paymethod(){
	return array(
		'CARD'		=> '카드',
		'KAKAOPAY'	=>'카카오페이',
		'BANK'		=>'계좌이체',
		'VACCT'		=>'가상계좌',
		'MOBILE'	=>'휴대폰',
		'NAVERPAY'	=> '네이버페이',
	);
}
function get_ck_token($id='',$key=''){
	$api_key = get_ck_key();
	
	if(!empty($id)) $api_key['ck_id'] = $id;
	if(!empty($key)) $api_key['ck_key'] = $key;
	
	$token_url = $api_key['token_url'];
	$token_request_data = array(
		'pay2_id' => $api_key['ck_id'],
		'pay2_key'=> $api_key['ck_key'],
	);
	$response = wp_remote_post($token_url, array(
	    'body'    => json_encode($token_request_data,JSON_UNESCAPED_UNICODE),
	    'headers'     => [
			'Content-Type' 	=> 'application/json',
		],
	) );
	if( !is_wp_error( $response ) ) {
		$body = json_decode( $response['body'], true );
		if ( $body['RTN_CD'] == '0000' ) {
			return $body['TOKEN'];
		}
		else{
			return false;
		}
	}
	else{
		return false;
	}
}
function get_ck_key($order = null){
	$payment_gateway_id = 'wc_cookiepay_pg';
	$payment_gateways   = WC_Payment_Gateways::instance();
	$payment_gateway    = $payment_gateways->payment_gateways()[$payment_gateway_id];
	return array(
		'ck_id'		=> $payment_gateway->cookiepay_api_id,
		'ck_key'	=> $payment_gateway->cookiepay_api_key,
		'thx_page'	=> $payment_gateway->get_return_url($order),
		'status'	=> $payment_gateway->cancel_status,
		'ck_js_url'	=> $payment_gateway->js_url,
		'receipt_url'=> $payment_gateway->receipt_url,
		'token_url'	=> $payment_gateway->token_url,
		'cancel_url'=> $payment_gateway->cancel_url,
		'search_url'=> $payment_gateway->search_url,
		'cookiepay_pg'	=>$payment_gateway->cookiepay_pg,
	);
}
add_filter( 'woocommerce_locate_template', 'woo_adon_plugin_template', 1, 3 );
   function woo_adon_plugin_template( $template, $template_name, $template_path ) {
     global $woocommerce;
     $_template = $template;
     if ( ! $template_path ) 
        $template_path = $woocommerce->template_url;
 
     $plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) )  . '/template/woocommerce/';
 
    // Look within passed path within the theme - this is priority
    $template = locate_template(
    array(
      $template_path . $template_name,
      $template_name
    )
   );
 
   if( ! $template && file_exists( $plugin_path . $template_name ) )
    $template = $plugin_path . $template_name;
 
   if ( ! $template )
    $template = $_template;

   return $template;
}
//주문페이지 컬럼 추가
add_filter( 'manage_edit-shop_order_columns', 'ck_shop_order_column', 10 );
function ck_shop_order_column($columns)
{
    $reordered_columns = array();
    foreach( $columns as $key => $column){
        $reordered_columns[$key] = $column;
		if( $key ==  'order_status' ){
			$reordered_columns['ck_name'] 		= '고객명';
		    $reordered_columns['ck_user_id'] 	= '고객ID';
			$reordered_columns['ck_card'] 		= '카드사(할부)';
			$reordered_columns['ck_tid'] 		= '승인번호';		
		}
    }
    return $reordered_columns;
}
add_action( 'manage_shop_order_posts_custom_column' , 'ck_shop_order_column_content', 10, 2 );
function ck_shop_order_column_content( $column, $post_id )
{
	$order 			= wc_get_order( $post_id );
	$result 		= json_decode(get_post_meta($post_id,'ck_result',TRUE),TRUE);
	$card_result 	= json_decode(get_post_meta($post_id,'ck_card_result',TRUE),TRUE);
	$pay_method 	= get_post_meta($post_id,'ck_method',TRUE);
    switch ( $column )
    {
        case 'ck_name' :
			if(!empty($order->get_billing_first_name())){
				echo $order->get_billing_first_name();
			}
            break;
        case 'ck_user_id' :
            if(!empty($order->get_user_id())){
				echo $order->get_user_id();
			}
            break;
		case 'ck_card' :
			$card 	= $result['CARDNAME'];
			$period = $result['QUOTA'];
			// 230713 추가 > 리턴값 없으면 백단값으로 가저온다.
			if(empty($card)){
				$card	 	= $card_result['CARDNAME'];
				$period 	= $card_result['QUOTA'];
			}
            if(!empty($card)){
            	if(empty($period) && $pay_method == 'CARD') $period = '00';
				echo $card.'('.$period.')';
			}
            break;
		case 'ck_tid' :
			$acc_no = $result['ACCEPTNO'];
			// 230713 추가 > 리턴값 없으면 백단값으로 가저온다.
			if(empty($acc_no)){
				$acc_no = $card_result['ACCEPTNO'];
			}
            if(!empty($acc_no)){
				echo $acc_no;
			}
            break;
    }
}
function ck_display_order_data_in_admin( $order ){
	$result = json_decode(get_post_meta($order->id,'ck_result',TRUE),TRUE);  
	?>
    <div class="ck_order_data">
        <ul>
        	<?php
        	$pay_method = get_post_meta($order->id,'ck_method',TRUE);
			$result = json_decode(get_post_meta($order->id,'ck_result',TRUE),TRUE);
        	if(!empty($pay_method)){
        	?>
        	<li><label>결제방법</label><span><?=(get_ck_paymethod()[$pay_method])?></span></li>
        	<?php
        	}
			if($pay_method == 'VACCT'){
				$bank 			= get_post_meta($order->id,'ck_vacct_bank',TRUE);
				$accountno 		= get_post_meta($order->id,'ck_vacct_accountno',TRUE);
				$reveivername 	= get_post_meta($order->id,'ck_vacct_reveivername',TRUE);
				$depositdate 	= get_post_meta($order->id,'ck_vacct_depositdate',TRUE);
				$depositname 	= get_post_meta($order->id,'ck_vacct_depositname',TRUE);
			?>
				<li><label>입금은행</label><span><?=$bank?></span></li>
	        	<li><label>입금계좌번호</label><span><?=$accountno?>(<?=$reveivername?>)</span></li>
	        	<li><label>입금마감일</label><span><?=$depositdate?></span></li>
	        	<li><label>입금자명</label><span><?=$depositname?></span></li>
			<?php
			}
			else{
				$card 	= $result['CARDNAME'];
				$period = $result['QUOTA'];
				$acc_no = $result['ACCEPTNO'];
				
				if(empty($period)){
					$card_result = json_decode(get_post_meta($order->id,'ck_card_result',TRUE),TRUE);
					$period = $card_result['QUOTA'];
				}
	            if(!empty($card)){
	            	if(empty($period) && $pay_method == 'CARD') $period = '00';
				}
			?>
				<li><label>카드사/은행</label><span><?=($card)?></span></li>
	        	<li><label>할부기간</label><span><?=($period)?></span></li>
	        	<li><label>승인번호</label><span><?=($acc_no)?></span></li>
			<?php	
			}
        	?>
        	
        	
        </ul>
    </div>
    <style>
    	.ck_order_data{
    		float: left;
    		margin: 10px 0;
		    width: 100%;
    	}
    	.ck_order_data ul{
    		
    	}
    	.ck_order_data ul li{
    		
    	}
    	.ck_order_data ul li label{
    		min-width: 100px;
		    display: inline-block;
		    vertical-align: middle;
		    font-size: 13px;
		    line-height: 13px;
		    padding: 5px 0;
    	} 
    	.ck_order_data ul li span{
    		display: inline-block;
		    vertical-align: middle;
		    font-size: 13px;
		    line-height: 13px;
		    padding: 5px 0;
    	}
    </style>
<?php }
add_action( 'woocommerce_admin_order_data_after_order_details', 'ck_display_order_data_in_admin' );
//전표보기 metabox
add_action( 'add_meta_boxes', 'ck_add_meta_boxes' );
function ck_add_meta_boxes(){
    add_meta_box( 'ck_receipt', '전표보기', 'ck_receipt_func', 'shop_order', 'side', 'core' );
}
function ck_receipt_func(){
    global $post;
	$order_id 		= $post->ID;
	$pay_method 	= get_post_meta($order_id,'ck_method',TRUE);
	$order 			= wc_get_order( $order_id );
	$tid = get_post_meta($post->ID,'ck_r_tid',true);
	$ck_key = get_ck_key();
	if(!empty($tid) && $order->has_status(array('completed','processing','cancelled','refunded'))){
		echo '
			<button type="button" class="button" onclick="receipt(\''.$tid.'\')">전표보기</button>
			<script>
				function base64_encode(str) {
				    return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function(match, p1) {
				        return String.fromCharCode("0x" + p1);
				    }));
				}
				
				function receipt(tid) {
				    var tid = base64_encode(tid);
				    window.open(
				        "'.$ck_key['receipt_url'].'?tid="+tid,
				        "cookiepayments Receipt",
				        "width=468,height=750"
				    );
				}
			</script>
		';
	}
	else{
		echo '전표가 존재하지 않습니다.';
	}
}
//고객 주문 상세페이지 정보 추가
add_action('woocommerce_order_details_after_order_table',function($order){
	//쿠키페이로 결제시에만 노출
	$pay_method = $order->get_payment_method();
	if($pay_method == 'wc_cookiepay_pg'):
		$pay_method = get_post_meta($order->id,'ck_method',TRUE);
		$result 	= json_decode(get_post_meta($order->id,'ck_result',TRUE),TRUE);
		$tid 		= get_post_meta($order->id,'ck_r_tid',TRUE);
		if(!empty($pay_method)){
			$output = '
				<div id="ck_pay_table">
					<h2 class="woocommerce-order-details__title">쿠키페이 결제 정보</h2>
					<table class="shop_table">
						<tr>
							<td>쿠키페이 결제방법</td>
							<td>'.(get_ck_paymethod()[$pay_method]).'</td>
						</tr>
			';
			if($pay_method == 'VACCT'){
				$bank 			= get_post_meta($order->id,'ck_vacct_bank',TRUE);
				$accountno 		= get_post_meta($order->id,'ck_vacct_accountno',TRUE);
				$reveivername 	= get_post_meta($order->id,'ck_vacct_reveivername',TRUE);
				$depositdate 	= get_post_meta($order->id,'ck_vacct_depositdate',TRUE);
				$depositname 	= get_post_meta($order->id,'ck_vacct_depositname',TRUE);
				
				$output .='
						<tr>
							<td>입금은행</td>
							<td>'.$bank.'</td>
						</tr>
						<tr>
							<td>입금계좌번호</td>
							<td>'.$accountno.'('.$reveivername.')</td>
						</tr>
						<tr>
							<td>입금마감일</td>
							<td>'.$depositdate.'</td>
						</tr>
						<tr>
							<td>입금자명</td>
							<td>'.$depositname.'</td>
						</tr>
				';
			}
			else{
				$card 	= $result['CARDNAME'];
				$period = $result['QUOTA'];
				$acc_no = $result['ACCEPTNO'];
				
				if(empty($period)){
					$card_result = json_decode(get_post_meta($order->id,'ck_card_result',TRUE),TRUE);
					$period = $card_result['QUOTA'];
				}
	            if(!empty($card)){
	            	if(empty($period) && $pay_method == 'CARD') $period = '00';
				}
				
				$output .='
						<tr>
							<Td>카드사/은행</td>
							<Td>'.$card.'</td>
						</tr>
						<tr>
							<Td>할부기간</td>
							<Td>'.$period.'</td>
						</tr>
						<tr>
							<Td>승인번호</td>
							<Td>'.$acc_no.'</td>
						</tr>
				';
			}
			$output .='
				</table>
				<div id="ck_btn_wrap">
			';
			$gateway_info = get_ck_key();
			$order_status  = $order->get_status();
			//결제 취소가능상태일 때 출력(가상계좌 제외)
			if($pay_method != 'VACCT' && !empty($tid) && (in_array($order_status, $gateway_info['status']) || in_array('wc-'.$order_status, $gateway_info['status']))){
				$output .='
					<button class="button ck_cancel_order" data-oid="'.$order->id.'">주문취소</button>
				';
			}
			//완료상태일때 전표 출력
			$tid 	= get_post_meta($order->id,'ck_r_tid',true);
			$ck_key = get_ck_key();
			if(!empty($tid) && $order->has_status(array('completed','processing','cancelled','refunded'))){
				$output .='
					<button type="button" class="button" onclick="receipt(\''.$tid.'\')">전표보기</button>
					<script>
						function base64_encode(str) {
						    return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function(match, p1) {
						        return String.fromCharCode("0x" + p1);
						    }));
						}
						
						function receipt(tid) {
						    var tid = base64_encode(tid);
						    window.open(
						        "'.$ck_key['receipt_url'].'?tid="+tid,
						        "cookiepayments Receipt",
						        "width=468,height=750"
						    );
						}
					</script>
				';	
			}
			$output .='
					</div>
				</div>
			';
			echo $output;
		}
	endif;
},10,1);
function cancel_order_call() {
	$order_id 			= $_POST['oid'];
	$order 				= new WC_Order( $order_id );
	$order_detail_url 	= $order->get_view_order_url();
	// JSON 배열 생성
	$response = array(
		'isSuccess'	=> TRUE,
		'detail'	=> $order_detail_url,
	);
	
	//취소API 실행
	$amount 	= $order->get_total();
	
	$ex_ck_id 	= get_post_meta($order_id,'ck_id',TRUE);
	$ex_ck_key 	= get_post_meta($order_id,'ck_key',TRUE);
	
	$token 		= get_ck_token($ex_ck_id,$ex_ck_key);
	$ck_key 	= get_ck_key();
	$result_tid = get_post_meta($order_id,'ck_r_tid',TRUE);
	
	//결제 취소 진행
	if(!empty($token)){
		$cookiepayments_url = $ck_key['cancel_url'];
		$request_data_array = array(
			'tid' 		=> $result_tid,
			'reason'	=> '고객 직접 취소',
			'amount'	=> $amount,
		);
		$cancel_response = wp_remote_post( $cookiepayments_url, array(
		    'body'    => json_encode($request_data_array,JSON_UNESCAPED_UNICODE),
		    'headers'     => [
				'Content-Type' 	=> 'application/json',
				'TOKEN'			=> $token,
				'ApiKey'		=> $ex_ck_key,
			],
		) );
		//결제취소 통신 성공
		if( !is_wp_error( $cancel_response ) ) {
			$cancel_res = json_decode( $cancel_response['body'], true );
			//결제확인 통신 성공
			if($cancel_res['cancel_code'] == '0000'){
				$refund_json = json_encode($cancel_res,JSON_UNESCAPED_UNICODE);
				update_post_meta($order_id,'refund_json',$refund_json);
				//주문 상태 변경
				$order->update_status('cancelled');
				$order->add_order_note( '쿠키페이 취소 완료(TID:'.$result_tid.')', true );
			}
			elseif(strpos($cancel_res['cancel_msg'], "이미 취소" ) !== false || strpos($cancel_res['cancel_msg'], "원거래 기취소" ) !== false){
				$refund_json = json_encode($cancel_res,JSON_UNESCAPED_UNICODE);
				update_post_meta($order_id,'refund_json',$refund_json);
				//주문 상태 변경
				$order->update_status('cancelled');
				$order->add_order_note( '쿠키페이 취소 완료(TID:'.$result_tid.',취소적용)', true );
			}
			//결제확인 통신 실패
			else{
				$refund_json = json_encode($cancel_res,JSON_UNESCAPED_UNICODE);
				update_post_meta($order_id,'refund_json',$refund_json);
				// JSON 배열 생성
				$response = array(
					'isSuccess'	=> FALSE,
					'error'		=> '결제취소확인 통신 실패',
				);
			}
		}
		//결제취소 통신 실패
		else{
			// JSON 배열 생성
			$response = array(
				'isSuccess'	=> FALSE,
				'error'		=> '결제취소통신 실패',
			);
		}
	}
	//토큰 실패
	else{
		// JSON 배열 생성
		$response = array(
			'isSuccess'	=> FALSE,
			'error'		=> '토큰통신 실패',
		);
	}
	
	
	header( "Content-Type: application/json" );
	die( json_encode($response) );
}
add_action('wp_ajax_cancel_order', 'cancel_order_call');
add_action('wp_ajax_nopriv_cancel_order', 'cancel_order_call');
function ck_get_pg_arr(){
	return array('토스','이지페이','키움페이','모빌페이','다날','웰컴1차','이롬페이');
}
?>