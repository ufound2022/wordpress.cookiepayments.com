<?php

function ck_pay_log($message)
{
	$log_file = plugin_dir_path(__FILE__) . 'ck_log.txt';
	$current_time = date("Y-m-d H:i:s");
	$log_entry = "[$current_time] $message\n";
	file_put_contents($log_file, $log_entry, FILE_APPEND);
}

//결제 방법 추가
add_filter('woocommerce_payment_gateways', 'add_cookiepay_gateway_class');
function add_cookiepay_gateway_class($methods)
{
	$methods[] = 'WC_ck_PG';
	return $methods;
}
add_action('plugins_loaded', 'cookiepay_payment_gateway');
function cookiepay_payment_gateway()
{

	// ★ WooCommerce가 비활성화이거나 클래스가 정의되지 않았다면 조기 종료
	if (! class_exists('WC_Payment_Gateway')) {
		return; // 또는 그냥 return;
	}

	class WC_CK_PG extends WC_Payment_Gateway
	{
		function __construct()
		{
			$this->id 					= 'wc_cookiepay_pg';
			$this->method_title 		= '쿠키페이 결제';
			$this->title 				= '쿠키페이 결제';
			$this->has_fields 			= true;
			$this->method_description 	= '
            	<div style="position: relative;"><p>쿠키페이로 결제하세요.</p><div style="position: absolute;right: 20px;top: 0;"><a style="margin-right: 5px;display: inline-block;vertical-align: middle;background: #2271b1;color: #fff;text-decoration: none;padding: 5px 10px;border-radius: 4px;" href="https://www.cookiepayments.com" target="_blank">쿠키페이 가입하기</a><a style="display: inline-block;vertical-align: middle;background: #2271b1;color: #fff;text-decoration: none;padding: 5px 10px;border-radius: 4px;" href="https://cookiepayments.com/iroboard/view?bId=API_Devolper&wr_id=2171" target="_blank">우커머스에 쿠키페이 적용방법</a></div></div>
            ';
			$this->view_transaction_url  = '';
			//load the settings
			$this->init_form_fields();
			$this->init_settings();
			$this->enabled 		= $this->get_option('enabled');
			$this->title 		= $this->get_option('title');
			$this->description 	= $this->get_option('description');
			$this->cancel_status = $this->get_option('ck_cancel_status');
			$this->cookiepay_pg = $this->get_option('cookiepay_pg');
			$this->testmode 	= $this->get_option('testmode');

			$this->token_url	= 'https://www.cookiepayments.com/payAuth/token';
			$this->cancel_url	= 'https://www.cookiepayments.com/api/cancel';
			$this->receipt_url	= 'https://www.cookiepayments.com/api/receipt';
			$this->js_url 		= 'https://www.cookiepayments.com/js/cookiepayments-1.1.3.js';
			$this->search_url 	= 'https://www.cookiepayments.com/api/paysearch';

			if ($this->testmode == 'yes') {
				$this->token_url	= 'https://sandbox.cookiepayments.com/payAuth/token';
				$this->cancel_url	= 'https://sandbox.cookiepayments.com/api/cancel';
				$this->receipt_url	= 'https://sandbox.cookiepayments.com/api/receipt';
				$this->js_url 		= 'https://sandbox.cookiepayments.com/js/cookiepayments-1.1.3.js';
				$this->search_url 	= 'https://sandbox.cookiepayments.com/api/paysearch';
			}

			$this->cookiepay_api_id = $this->get_option('cookiepay_api_id');
			$this->cookiepay_api_key = $this->get_option('cookiepay_api_key');

			$this->ck_card 		= $this->get_option('ck_card');
			$this->ck_kakao 	= $this->get_option('ck_kakao');
			$this->ck_bank 		= $this->get_option('ck_bank');
			$this->ck_vacct 	= $this->get_option('ck_vacct');
			$this->ck_naver 	= $this->get_option('ck_naver');
			$this->ck_mobile 	= $this->get_option('ck_mobile');

			/*
			# 데모용에만 살려야함(S)
		 	//토스
		 	if($this->cookiepay_pg == 0){
				$this->cookiepay_api_id = 'sandbox_674xqh929i2';
				$this->cookiepay_api_key = 'sandbox_3249997bd2538aa53e01395dce0b757cabc7a1ecf9';
			}
			//이지
			elseif($this->cookiepay_pg == 1){
				$this->cookiepay_api_id = '';
				$this->cookiepay_api_key = '';
			}
			//키움
			elseif($this->cookiepay_pg == 2){
				$this->cookiepay_api_id = 'sandbox_pcm8jgv2';
				$this->cookiepay_api_key = 'sandbox_a267a97d4e163f07ea5c44eb42a8e8f2b834bc0d70';
			}
			//모빌
			elseif($this->cookiepay_pg == 3){
				$this->cookiepay_api_id = '';
				$this->cookiepay_api_key = '';
			}
			//다날
			elseif($this->cookiepay_pg == 4){
				$this->cookiepay_api_id = '';
				$this->cookiepay_api_key = '';
			}
			# 데모용에만 살려야함(E)
			*/

			$this->supports = array(
				'refunds',
			);

			// This action hook saves the settings
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
			// We need custom JavaScript to obtain a token
			add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
			add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'check_vbank_response'));
			add_action('woocommerce_api_wc_gateway_download', array($this, 'ck_download_response'));
		}
		public function init_form_fields()
		{
			$status = array('0' => '선택안함');
			foreach (wc_get_order_statuses() as $key => $value) {
				$status[$key] = $value;
			}
			$this->form_fields = array(
				'enabled' => array(
					'title'         => '활성화/비활성화',
					'type'          => 'checkbox',
					'label'         => '쿠키페이 결제를 활성화합니다.',
					'default'       => 'yes'
				),
				'title' => array(
					'title'         => '결제타이틀',
					'type'          => 'text',
					'description'   => '사용자가 결제방법을 선택할 때 입력한 제목이 표시됩니다.',
					'default'       => '쿠키페이 결제',
					'desc_tip'      => true,
				),
				'description' => array(
					'title'         => '결제안내문구',
					'type'          => 'textarea',
					'css'           => 'width:500px;',
					'default'       => '쿠키페이로 결제합니다.',
					'description'   => 'The message which you want it to appear to the customer in the checkout page.',
				),
				'testmode' => array(
					'title'       => '테스트 모드',
					'label'       => '테스트모드를 활성화합니다.<a target="_blank" href="https://cookiepayments.com/iroboard/view?bId=API_Devolper&wr_id=2166">(쿠키페이 연동 전 샌더박스 구축환경 바로가기)</a>',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'cookiepay_pg' => array(
					'title'       => '쿠키페이 결제대행사',
					'options'     => ck_get_pg_arr(),
					'type'        => 'select',
					'description' => '우커머스에서 사용할 쿠키페이 결제대행사를 선택합니다.',
				),
				# api_id, api_key 값 hidden 관련
				/*
				'test_cookiepay_api_id' => array(
					'title'       => 'Test API ID',
					'type'        => 'text'
				),
				'test_cookiepay_api_key' => array(
					'title'       => 'Test API KEY',
					'type'        => 'text',
				),*/

				'cookiepay_api_id' => array(
					'title'       => '연동 아이디',
					'type'        => 'text'
				),
				'cookiepay_api_key' => array(
					'title'       => '연동 시크릿 키',
					'type'        => 'text'
				),
				/*
				# 데모용에만 살려야함(S)
				'cookiepay_api_id' => array(
					'title'       => '연동 아이디',
					'type'        => 'hidden',
					'description'	=> '데모용 사이트에서는 입력, 수정 불가합니다.',
				),
				'cookiepay_api_key' => array(
					'title'       => '연동 시크릿 키',
					'type'        => 'hidden',
					'description'	=> '데모용 사이트에서는 입력, 수정 불가합니다.',
				),
				# 데모용에만 살려야함(E)
				*/

				'ck_cancel_status' 	=> array(
					'title'   	=> '결제취소 가능상태 설정',
					'type'    	=> 'multiselect',
					'options'   => $status,
					'default' 	=> '',
					'description'	=> '고객이 직접 결제취소할 수 있는 주문상태를 설정합니다. Ctrl키를 이용해서 취소할 수 있는 모든 주문상태를 선택해주세요.',
				),
				'ck_card' 	=> array(
					'title'   => '신용카드 결제 사용여부',
					'label'   => '신용카드 결제를 활성화합니다.',
					'type'    => 'checkbox',
					'default' => 'yes',
				),
				'ck_bank' 	=> array(
					'title'   => '계좌이체 결제 사용여부',
					'label'   => '계좌이체 결제를 활성화합니다.',
					'type'    => 'checkbox',
					'default' => 'yes',
				),
				'ck_vacct' 	=> array(
					'title'   => '가상계좌 결제 사용여부',
					'label'   => '가상계좌 결제를 활성화합니다.',
					'type'    => 'checkbox',
					'default' => 'yes',
				),
				'ck_vacct_url' => array(
					'title' 		=> '통지 URL(신용카드,실시간계좌이체,가상계좌)',
					'description' 	=> '쿠키페이먼츠 접속 후 > API 연동 메뉴 > PG사 조회 및 연동 설정 > PG 연동 버튼 클릭 > 통지 URL 입력란에 위 URL 복사 입력 후 설정 저장하시면 됩니다.',
					'type'    		=> 'text',
					'custom_attributes' => array('readonly' => 'true'),
					'default' 		=> add_query_arg('wc-api', 'WC_Gateway_' . $this->id, site_url())
				),
				'ck_kakao' 	=> array(
					'title'   => '카카오페이 결제 사용여부',
					'label'   => '카카오페이 결제를 활성화합니다.',
					'description' => '※ 간편결제는 PG사별로 이용 가능 여부가 다를 수 있으니 쿠키페이(02-6093-9233)로 문의하시기 바랍니다.',
					'type'    => 'checkbox',
					'default' => 'yes',
				),
				'ck_naver' 	=> array(
					'title'   => '네이버페이 결제 사용여부',
					'label'   => '네이버페이 결제를 활성화합니다.',
					'description' => '※ 간편결제는 PG사별로 이용 가능 여부가 다를 수 있으니 쿠키페이(02-6093-9233)로 문의하시기 바랍니다.',
					'type'    => 'checkbox',
					'default' => 'yes',
				),
				'ck_mobile' => array(
					'title'   => '휴대폰 결제 사용여부',
					'label'   => '휴대폰 결제를 활성화합니다.',
					'type'    => 'checkbox',
					'description' => '※ 휴대폰 결제는 PG사별로 이용 가능 여부가 다를 수 있으니 쿠키페이(02-6093-9233)로 문의하시기 바랍니다.',
					'default' => 'yes',
				),
			);
		}
		//결제하기 클릭 시 이동
		function process_payment($order_id)
		{
			global $woocommerce;
			$order 		= new WC_Order($order_id);
			//결제 팝업
			if ($_GET['ck_pay'] == 'true') {
			}
			//주문입력 창
			else {
				$baseUrl 	= $order->get_checkout_payment_url();
				if (strpos($baseUrl, '?') !== false) {
					$baseUrl .= '&';
				} else {
					$baseUrl .= '?';
				}
				$redirect_url = $baseUrl . 'ck_pay=true&order_id=' . $order_id . '&paymethod=' . $_POST['ck_paymethod'];
			}

			return array(
				'result' => 'success',
				'redirect' => $redirect_url,
			);
		}
		public function process_refund($order_id, $amount = null, $reason = '관리자 환불')
		{

			$ex_ck_id 	= get_post_meta($order_id, 'ck_id', TRUE);
			$ex_ck_key 	= get_post_meta($order_id, 'ck_key', TRUE);

			ck_pay_log("api_id : ".$ex_ck_id);
			ck_pay_log("api_key : ".$ex_ck_key);

			$token 		= get_ck_token($ex_ck_id, $ex_ck_key);
			$ck_key 	= get_ck_key();
			$result_tid = get_post_meta($order_id, 'ck_r_tid', TRUE);

			ck_pay_log("tid : ".$result_tid);
			ck_pay_log("reason : ".$reason);
			ck_pay_log("amount : ".$amount);

			//결제 취소 진행
			if (!empty($token)) {
				$cookiepayments_url = $ck_key['cancel_url'];

				ck_pay_log("cookiepayments_url : ".$cookiepayments_url);
				ck_pay_log("token : ".$token);
				ck_pay_log("ApiKey : ".$ex_ck_key);

				$request_data_array = array(
					'tid' 		=> $result_tid,
					'reason'	=> $reason,
					'amount'	=> $amount,
				);
				$cancel_response = wp_remote_post($cookiepayments_url, array(
					'body'    => json_encode($request_data_array, JSON_UNESCAPED_UNICODE),
					'headers'     => [
						'Content-Type' 	=> 'application/json',
						'TOKEN'			=> $token,
						'ApiKey'		=> $ex_ck_key,
					],
				));

				ck_pay_log("cancel_response[body] : ".$cancel_response['body']);

				//결제취소 통신 성공
				if (!is_wp_error($cancel_response)) {
					$cancel_res = json_decode($cancel_response['body'], true);
					//결제확인 통신 성공

					ck_pay_log("cancel_code : ".$cancel_res['cancel_code']);
					ck_pay_log("cancel_msg : ".$cancel_res['cancel_msg']);

					if ($cancel_res['cancel_code'] == '0000') {
						$refund_json = json_encode($cancel_res, JSON_UNESCAPED_UNICODE);
						update_post_meta($order_id, 'refund_json', $refund_json);
						return TRUE;
					} elseif (strpos($cancel_res['cancel_msg'], "이미 취소") !== false || strpos($cancel_res['cancel_msg'], "원거래 기취소") !== false) {
						$refund_json = json_encode($cancel_res, JSON_UNESCAPED_UNICODE);
						update_post_meta($order_id, 'refund_json', $refund_json);

						//주문 상태 변경
						//$order->update_status('cancelled');
						//$order->add_order_note( '쿠키페이 취소 완료(TID:'.$result_tid.',취소적용)', true );
						return TRUE;
					}
					//결제확인 통신 실패
					else {
						$refund_json = json_encode($cancel_res, JSON_UNESCAPED_UNICODE);
						update_post_meta($order_id, 'refund_json', $refund_json);
						return false;
					}
				}
				//결제취소 통신 실패
				else {
					$cancel_res = array('MSG' => '결제취소통신 실패');
					$refund_json = json_encode($cancel_res, JSON_UNESCAPED_UNICODE);
					update_post_meta($order_id, 'refund_json', $refund_json);
					return false;
				}
			}
			//토큰 실패
			else {
				$cancel_res = array('MSG' => '토큰통신 실패');
				$refund_json = json_encode($cancel_res, JSON_UNESCAPED_UNICODE);
				update_post_meta($order_id, 'refund_json', $refund_json);
				return false;
			}

			return false;
		}
		//this function lets you add fields that can collect payment information in the checkout page like card details and pass it on to your payment gateway API through the process_payment function defined above.
		public function payment_fields()
		{
			if (isset($_GET['ck_pay']) && $_GET['ck_pay'] == 'true') {
?>
				<style>
					#payment,
					#place_order {
						display: none;
					}
				</style>
			<?php
			} else {
			?>
				<fieldset>
					<select class="select ck_select" name="ck_paymethod">
						<?php
						if ($this->ck_card == 'yes') echo '<option value="CARD">신용카드</option>';
						if ($this->ck_bank == 'yes') echo '<option value="BANK">계좌이체</option>';
						if ($this->ck_vacct == 'yes') echo '<option value="VACCT">가상계좌</option>';
						if ($this->ck_kakao == 'yes') echo '<option value="KAKAOPAY">카카오페이</option>';
						if ($this->ck_naver == 'yes') echo '<option value="NAVERPAY">네이버페이</option>';
						if ($this->ck_mobile == 'yes') echo '<option value="MOBILE">휴대폰</option>';

						?>
					</select>
					<p class="form-row form-row-wide">
						<?php echo esc_attr($this->description); ?>
					</p>
					<div class="clear"></div>
				</fieldset>
<?php
			}
		}
		public function payment_scripts()
		{
			if (! is_cart() && ! is_checkout() && ! isset($_GET['pay_for_order'])) {
				return;
			}
			if ('no' === $this->enabled) {
				return;
			}
			if (empty($this->cookiepay_api_id) || empty($this->cookiepay_api_key)) {
				return;
			}
			if (! is_ssl()) {
				return;
			}
			wp_enqueue_script('cookiepay_js', $this->js_url);
		}
		public function thankyou_page($order_id)
		{
			ck_pay_log("order_id = " . $order_id);
			global $woocommerce;
			$order 		= new WC_Order($order_id);
			// $order2 	= wc_get_order($order_id);
			$order_detail_url = $order->get_view_order_url();
			$body = $_POST;
			//print_r($body);
			$result_code 	= $body['RESULTCODE'];
			$result_msg 	= $body['RESULTMSG'];
			$result_amount 	= $body['AMOUNT'];
			$result_tid 	= $body['TID'];
			$result_orderno	= $body['ORDERNO'];

			// 변수별로 상세 로그
			ck_pay_log("result_code = " . $result_code);
			ck_pay_log("result_msg = " . $result_msg);
			ck_pay_log("result_amount = " . $result_amount);
			ck_pay_log("result_tid = " . $result_tid);
			ck_pay_log("result_orderno = " . $result_orderno);
			$pay_method 	= get_post_meta($order_id, 'ck_method', TRUE);

			// 가상계좌일 때는, 주문이 '미결제(pending)' 상태인 경우에만 on-hold로 변경
			if (
				$pay_method == 'VACCT' 
				&& !empty($body['CARDNAME']) 
				&& $order->has_status('pending')
			) {
				$order->add_order_note('쿠키페이 가상계좌 발급', true);
				$order->update_status('on-hold');
				update_post_meta($order_id, 'ck_vacct_bank', $body['CARDNAME']);
				update_post_meta($order_id, 'ck_vacct_accountno', $body['ACCOUNTNO']);
				update_post_meta($order_id, 'ck_vacct_reveivername', $body['RECEIVERNAME']);
				update_post_meta($order_id, 'ck_vacct_depositdate', $body['DEPOSITENDDATE']);
				update_post_meta($order_id, 'ck_vacct_depositname', $body['DEPOSITNAME']);

				update_post_meta($order_id, 'ck_result', json_encode($body, JSON_UNESCAPED_UNICODE));

				// Remove cart
				$woocommerce->cart->empty_cart();

				echo '
					<script>
						if(self.opener){
							opener.location.href = "' . $order_detail_url . '";
							window.close();
						}
					</script>
				';
			}
			//결제 성공 시 결제금액 인증 진행
			elseif ($body['RESULTCODE'] == '0000') {
				
				$result_etc		= json_encode($body, JSON_UNESCAPED_UNICODE);
				ck_pay_log("결제 성공. result_etc = " . $result_etc);

				//실결제 금액 비교
				//최종 결제 성공
				if ($result_amount == $order->get_total()) {
					update_post_meta($order_id, 'ck_r_code', $result_code);
					update_post_meta($order_id, 'ck_r_msg', $result_msg);
					update_post_meta($order_id, 'ck_r_amount', $result_amount);
					update_post_meta($order_id, 'ck_r_tid', $result_tid);
					update_post_meta($order_id, 'ck_r_orderno', $result_orderno);
					update_post_meta($order_id, 'ck_result', $result_etc);

					ck_pay_log("payment_complete() 호출 전");
					$order->payment_complete();
					ck_pay_log("payment_complete() 호출 후");

					$order->reduce_order_stock();
					$order->add_order_note('쿠키페이 결제 완료(승인번호:' . $body['ACCEPT_NO'] . ')', true);
					// Remove cart
					$woocommerce->cart->empty_cart();

					echo '
						<script>
							if(self.opener){
								opener.location.href = "' . $order_detail_url . '";
								window.close();
							}
						</script>
					';
				}
				//결제 금액 오류
				else {
					ck_pay_log("결제 금액 불일치. result_etc = " . $result_etc);

					update_post_meta($order_id, 'ck_r_code', $result_code);
					update_post_meta($order_id, 'ck_r_msg', $result_msg . ' 금액 일치 오류 발생');
					update_post_meta($order_id, 'ck_r_amount', $result_amount);
					update_post_meta($order_id, 'ck_r_tid', $result_tid);
					update_post_meta($order_id, 'ck_r_orderno', $result_orderno);
					update_post_meta($order_id, 'ck_result', $result_etc);

					$token 	= get_ck_token();
					$ck_key = get_ck_key();
					$cookiepayments_url = $ck_key['cancel_url'];
					//결제 취소 진행
					if (!empty($token)) {
						$cookiepayments_url = $ck_key['cancel_url'];
						$request_data_array = array(
							'tid' 		=> $result_tid,
							'reason'	=> '결제금액오류',
							'amount'	=> $result_amount,
						);
						$cancel_response = wp_remote_post($cookiepayments_url, array(
							'body'    => json_encode($request_data_array, JSON_UNESCAPED_UNICODE),
							'headers'     => [
								'Content-Type' 	=> 'application/json',
								'TOKEN'			=> $token,
								'ApiKey'		=> $ck_key['ck_key'],
							],
						));
						//결제취소 통신 성공
						if (!is_wp_error($cancel_response)) {
							$cancel_res = json_decode($cancel_response['body'], true);
							//결제확인 통신 성공
							if ($cancel_res['cancel_code'] == '0000') {
								$refund_json = json_encode($cancel_res, JSON_UNESCAPED_UNICODE);
								update_post_meta($order_id, 'refund_json', $refund_json);
								echo '
									<script>
										if(self.opener){
											opener.location.href = "' . $order_detail_url . '";
											window.close();
										}
									</script>
								';
								return TRUE;
							}
							//결제확인 통신 실패
							else {
								$refund_json = json_encode($cancel_res, JSON_UNESCAPED_UNICODE);
								update_post_meta($order_id, 'refund_json', $refund_json);
								echo '
									<script>
										if(self.opener){
											opener.location.href = "' . $order_detail_url . '";
											window.close();
										}
									</script>
								';
								return false;
							}
						}
						//결제취소 통신 실패
						else {
							$cancel_res = array('MSG' => '결제취소통신 실패');
							$refund_json = json_encode($cancel_res, JSON_UNESCAPED_UNICODE);
							update_post_meta($order_id, 'refund_json', $refund_json);
							echo '
								<script>
									if(self.opener){
										opener.location.href = "' . $order_detail_url . '";
										window.close();
									}
								</script>
							';
							return false;
						}
					}
					//토큰 실패
					else {
						$cancel_res = array('MSG' => '토큰통신 실패');
						$refund_json = json_encode($cancel_res, JSON_UNESCAPED_UNICODE);
						update_post_meta($order_id, 'refund_json', $refund_json);
						echo '
							<script>
								if(self.opener){
									opener.location.href = "' . $order_detail_url . '";
									window.close();
								}
							</script>
						';
						return false;
					}
				}
			} else {
				ck_pay_log("결제 실패");
				ck_pay_log("result_etc = " . json_encode($body, JSON_UNESCAPED_UNICODE));

				$order->add_order_note('쿠키페이 결제실패(' . $result_code . ':' . $result_msg . ')', true);
				echo '
					<script>
						alert("결제 실패(관리자문의)");
						location.href="' . wc_get_checkout_url() . '";
					</script>
				';
			}
			ck_pay_log("=== thankyou_page END: order_id=$order_id ===");
		}
		//가상계좌입금통보
		public function check_vbank_response()
		{

			//로그 저장
			global $wpdb;
			$params = json_decode(file_get_contents('php://input'), true);

			/*
			$wpdb->replace(
				'ck_log',
				array(
					'reg_date'		=> current_time('Y-m-d H:i:s'),
					//'postdata'		=> $params,
					'post_json'		=> json_encode($params,JSON_UNESCAPED_UNICODE),
				)
			);
			*/

			if ($_GET['wc-api'] == 'WC_Gateway_' . $this->id) {
				global $woocommerce;
				$postdata 	= $params;

				$order_id 	= $postdata['ORDERNO'];
				$order 		= new WC_Order($order_id);
				$pay_method = get_post_meta($order_id, 'ck_method', TRUE);
				$result_tid	= $postdata['TID'];

				//가상계좌일때만 작동
				if ($pay_method == 'VACCT') {
					if ($order->has_status('on-hold')) {
						update_post_meta($order_id, 'ck_vacct_result', json_encode($postdata, JSON_UNESCAPED_UNICODE));
						// we received the payment
						$order->payment_complete();
						$order->reduce_order_stock();

						// some notes to customer (replace true with false to make it private)
						$order->add_order_note('가상결제 입금확인 완료(승인번호:' . $postdata['ACCEPT_NO'] . ')', true);
						exit;
					}
				}
				//신용카드 back
				else {
					update_post_meta($order_id, 'ck_card_result', json_encode($postdata, JSON_UNESCAPED_UNICODE));
					update_post_meta($order_id, 'ck_r_tid', $result_tid);
					if (!($order->has_status('processing'))) {
						// we received the payment
						$order->payment_complete();
						$order->reduce_order_stock();
					}
					// some notes to customer (replace true with false to make it private)
					$order->add_order_note($pay_method . ' 결제 확인 완료(승인번호:' . $postdata['ACCEPT_NO'] . ')', true);
					exit;
				}
				exit;
			}
		}
		//주문다운로드
		public function ck_download_response()
		{
			if (! current_user_can('manage_options')) {
				exit;
			}
			if ($_GET['wc-api'] == 'wc_gateway_download' && !empty($_GET['sdate']) && !empty($_GET['edate'])) {
				$sdate 		= (empty($_GET['sdate']) ? current_time('Y-m-01') : $_GET['sdate']);
				$edate 		= (empty($_GET['edate']) ? current_time('Y-m-t') : $_GET['edate']);
				$api_key 	= get_ck_key();
				$token 		= get_ck_token();
				if (!empty($token)) {
					$cookiepayments_url = $api_key['search_url'];
					$request_data_array = array(
						'API_ID' => $api_key['ck_id'],
						'STD_DT' => $sdate,
						'END_DT' => $edate,
					);
					$list_res = wp_remote_post($cookiepayments_url, array(
						'body'    	=> json_encode($request_data_array, JSON_UNESCAPED_UNICODE),
						'headers'     => [
							'Content-Type' 	=> 'application/json',
							'TOKEN'			=> $token,
						],
					));

					if (!is_wp_error($list_res)) {
						$list = json_decode($list_res['body'], true);
						if (!empty($list['RESULTCODE']) && in_array($list['RESULTCODE'], array('0000', 'E008')) || $list['0']['RESULTCODE'] == '0000') {
							$pay_list = $list;
						} else {
							echo '(결제내역오류)' . $list['RESULTCODE'] . ':' . $list['RESULTMSG'];
							die();
						}
					} else {
						echo '결제내역을 가져올수 없습니다.(통신 오류)';
						die();
					}
				} else {
					echo 'API ID, API Key 값을 확인해주세요.(토큰통신 오류)';
					die();
				}
				header("Content-type: application/vnd.ms-excel");
				header("Content-type: application/vnd.ms-excel; charset=utf-8");
				header("Content-Disposition: attachment; filename = orders.xls");
				header("Content-Description: PHP7 Generated Data");
				echo "<meta content=\"application/vnd.ms-excel; charset=UTF-8\" name=\"Content-type\">";
				$result = '<table border="1">';
				$result .= '
							<thead>
								<tr>
									<Th>주문일자</th>
									<Th>PG사</th>
									
									<th>주문번호</th>
									<th>결제금액</th>
									<th>고객명</th>
									<th>고객Email</th>
									<th>상품명</th>
									
									<th>상품코드</th>
									<th>결제수단</th>
									<th>고객ID</th>
									<th>TID</th>
									<th>승인번호</th>
									
									<th>승인일시</th>
									<th>취소날짜</th>
									<th>취소메시지</th>
									<th>가상계좌번호</th>
									<th>예금주성명</th>
									
									<th>계좌사용만료일</th>
									<th>은행명(할부)</th>
									<th>은행코드</th>
								</tr>
							</thead>
							<tbody>
				';
				if (!empty($pay_list['RESULTCODE']) || $pay_list['RESULTCODE'] == 'E008') {
					$result .= '
						<tr>
							<td colspan="21">검색 결과가 없습니다.</td>
						</tr>
					';
				} else {
					foreach ($pay_list as $key => $value) {
						$result .= '
							<tr>
								<td></td>
								<td></td>
								
								<td>' . $value['ORDERNO'] . '</td>
								<td>' . number_format($value['AMOUNT']) . '</td>
								<td>' . $value['BUYERNAME'] . '</td>
								<td>' . $value['BUYEREMAIL'] . '</td>
								<td>' . $value['PRODUCTNAME'] . '</td>
								
								<td>' . $value['PRODUCTCODE'] . '</td>
								<td>' . $value['PAYMETHOD'] . '</td>
								<td>' . $value['BUYERID'] . '</td>
								<td>' . $value['TID'] . '</td>
								<td>' . $value['ACCEPTNO'] . '</td>
								
								<td>' . $value['ACCEPTDATE'] . '</td>
								<td>' . $value['CANCELDATE'] . '</td>
								<td>' . $value['CANCELMSG'] . '</td>
								<td>' . $value['ACCOUNTNO'] . '</td>
								<td>' . $value['RECEIVERNAME'] . '</td>
								
								<td>' . $value['DEPOSITENDDATE'] . '</td>
								<td>' . $value['CARDNAME'] . '</td>
								<td>' . $value['CARDCODE'] . '</td>
							</tr>
						';
					}
				}
				$result .= '
							</tbody>
						</table>
				';
				echo $result;
			}
			exit;
		}
	}
}
add_action('woocommerce_pay_order_before_submit', function () {
	if ($_GET['ck_pay'] == 'true' && !empty($_GET['order_id'])) {
		$order_id 	= $_GET['order_id'];
		$order 		= new WC_Order($order_id);
		$order2 	= wc_get_order($order_id);
		$ck_key 	= get_ck_key($order);
		$paymethod	= $_GET['paymethod'];

		update_post_meta($order_id, 'ck_method', $paymethod);
		update_post_meta($order_id, 'ck_id', $ck_key['ck_id']);
		update_post_meta($order_id, 'ck_key', $ck_key['ck_key']);

		if (empty($paymethod)) $paymethod = 'CARD';
		// Customer billing information details
		$billing_email  	= $order2->get_billing_email();
		//$billing_phone  	= $order2->get_billing_phone();
		$billing_phone  	= str_replace("-", "", $order2->get_billing_phone());
		$billing_first_name = $order2->get_billing_first_name();
		$billing_last_name  = $order2->get_billing_last_name();
		$billing_address_1  = $order2->get_billing_address_1();
		$billing_address_2  = $order2->get_billing_address_2();
		$billing_postcode   = $order2->get_billing_postcode();

		$product_name 		= '';
		$product_id 		= 0;
		$items 				= $order->get_items();
		foreach ($items as $item) {
			$product_name = $item->get_name();
			$product_id = $item->get_product_id();
			break;
		}
		$user_id 	= get_current_user_id();
		$user 		= get_userdata($user_id);



		$baseUrl 	= $order->get_checkout_payment_url();
		if (strpos($baseUrl, '?') !== false) {
			$baseUrl .= '&';
		} else {
			$baseUrl .= '?';
		}
		$fail_url 	= $baseUrl . 'ck_pay=fail&order_id=' . $order_id;
		$return_url = $ck_key['thx_page'];
		/*
		$cookiepayments_url = "https://sandbox.cookiepayments.com/pay/ready";
		$request_data_array = array(
			'API_ID' 		=> $ck_key['ck_id'],
			'ORDERNO'		=> $order_id,
			'PRODUCTNAME'	=> $product_name,
			'AMOUNT'		=> $order->get_total(),
			'BUYERNAME'		=> $billing_first_name.' '.$billing_last_name,
			'BUYEREMAIL'	=> $billing_email,
			'RETURNURL'		=> $return_url,
			'PRODUCTCODE'	=> $product_id,
			'PAYMETHOD'		=> $paymethod,
			'BUYERID'		=> $user->user_login,
			'BUYERADDRESS'	=> $billing_address_1.' '.$billing_address_2,
			'BUYERPHONE'	=> $billing_phone,
			'ETC1'			=> $paymethod,
			'ETC2'			=> '',
			'ETC3'			=> '',
			'ETC4'			=> '',
			'ETC5'			=> '',
			'CLOSEURL'		=> $fail_url,
			'FAILURL'		=> $fail_url,
		);
		$response = wp_remote_post( $cookiepayments_url, array(
		    'body'    => json_encode($request_data_array,JSON_UNESCAPED_UNICODE),
		    'headers'     => [
				'Content-Type' 	=> 'application/json',
				'ApiKey'		=> $ck_key['ck_key'],
			],
		) );
		if( !is_wp_error( $response ) ) {
			$body = json_decode( $response['body'], true );
			if(!empty($body['RTN_CD'])){
				echo '
					<script>
						alert("'.$body['RTN_MSG'].'('.$body['RTN_CD'].'))");
						//location.href="'.wc_get_checkout_url().'";
					</script>
				';
			}
			else{
				var_dump($response['body']);
			}
		}
		else{
			echo '
				<script>
					alert("결제 요청 오류(관리자에게 문의해주세요.)");
					location.href="'.wc_get_checkout_url().'";
				</script>
			';
		}
		*/

		ck_pay_log("RETURNURL = " . $ck_key['thx_page']);
		ck_pay_log("FAILURL   = " . add_query_arg('ck_pay', 'fail', $order->get_checkout_payment_url()));

		$ptype = '';
		if (wp_is_mobile()) {
			$ptype = 'M';
		}
		echo '
			</div>
			</div>
			<div>
			<div>
			<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
			<script src="' . $ck_key['ck_js_url'] . '"></script>
			<script>
			cookiepayments.init({
			    api_id: \'' . $ck_key['ck_id'] . '\', //쿠키페이 결제 연동 id
			    api_key: \'' . $ck_key['ck_key'] . '\', //쿠키페이 결제 연동 key
			});
			
			function pay() {
			    
			    cookiepayments.payrequest({
			        ORDERNO: $("#ORDERNO").val(), //주문번호 (필수)
			        PRODUCTNAME: $("#PRODUCTNAME").val(), //상품명 (필수)
			        AMOUNT: $("#AMOUNT").val(), //결제 금액 (필수)
			        BUYERNAME: $("#BUYERNAME").val(), //고객명 (필수)
			        BUYEREMAIL: $("#BUYEREMAIL").val(), //고객 e-mail (필수)
			        PAYMETHOD: $("#PAYMETHOD").val(), //결제 수단 (선택)
			        PRODUCTCODE: $("#PRODUCTCODE").val(), //상품 코드 (선택)
			        BUYERID: $("#BUYERID").val(), //고객 아이디 (선택)
			        BUYERADDRESS: $("#BUYERADDRESS").val(), //고객 주소 (선택)
			        BUYERPHONE : $("#BUYERPHONE").val(), //고객 휴대폰번호 (선택, 웰컴페이는 필수)
			        HOMEURL: $("#HOMEURL").val(), //결제 완료 후 리다이렉트 url (필수)
			        RETURN_BACKURL: $("#RETURN_BACKURL").val(), //결제 완료(백단))
			        RETURNURL: $("#RETURNURL").val(), //결제 완료 후 리다이렉트 url (필수)
			        COMPLETE_URL: $("#COMPLETE_URL").val(), //결제 완료 후 확인버튼을 눌렀을떄 이동 URL
			        FAILURL: $("#FAILURL").val(), // 결제실패시
			        ETC1 : $("#ETC1").val(), //사용자 추가필드1 (선택)
			        ETC2 : $("#ETC2").val(), //사용자 추가필드2 (선택)
			        ETC3 : $("#ETC3").val(), //사용자 추가필드3 (선택)
			        ETC4 : $("#ETC4").val(), //사용자 추가필드4 (선택)
			        ETC5 : $("#ETC5").val(), //사용자 추가필드5 (선택)
			        MTYPE : $("#MTYPE").val(), //사용자 추가필드5 (선택)
			        PAY_VERSION : $("#PAY_VERSION").val(), //사용자 추가필드5 (선택)
			        PAY_TYPE : $("#PAY_TYPE").val(), //사용자 추가필드5 (선택)
			        FORWARD : $("#FORWARD").val(), //사용자 추가필드5 (선택)
			        CANCELURL : $("#CANCELURL").val(), 
			        ESCROW : $("#ESCROW").val(), // 에스크로 사용여부
			        ENG_FLAG : $("#ENG_FLAG").val(), // 해외원화결제창 한글사용여부
			        PTYPE : $("#PTYPE").val(), // 피시모바일구분
					ORDER_NO_CHECK : $("#ORDER_NO_CHECK").val(), // 주문번호 중복체크 
			    });
			    
			}
			
			</script>
			<div id="cookiepayform"></div>

			<form name="payform">
			    <input type="hidden" name="ORDERNO" id="ORDERNO" placeholder="주문번호" value="' . $order_id . '">
			    <input type="hidden" name="PRODUCTNAME" id="PRODUCTNAME" placeholder="상품명" value="' . $product_name . '">
			    <input type="hidden" name="PTYPE" id="PTYPE" placeholder="피시/모바일 구분" value="' . $ptype . '">
			    <input type="hidden" name="AMOUNT" id="AMOUNT" placeholder="금액" value="' . $order->get_total() . '">
			    <input type="hidden" name="BUYERNAME" id="BUYERNAME" placeholder="고객명" value="' . $billing_first_name . ' ' . $billing_last_name . '">
			    <input type="hidden" name="EMAIL" id="BUYEREMAIL" placeholder="고객 e-mail" value="' . $billing_email . '">
			    <input type="hidden" name="PAYMETHOD" id="PAYMETHOD" placeholder="결제수단" value="' . $paymethod . '">
			    <input type="hidden" name="PRODUCTCODE" id="PRODUCTCODE" placeholder="상품 코드" value="' . $product_id . '">
			    <input type="hidden" name="BUYERID" id="BUYERID" placeholder="고객 ID" value="' . $user->user_login . '">
			    <input type="hidden" name="BUYERADDRESS" id="BUYERADDRESS" placeholder="고객 주소" value="(' . $billing_postcode . ')' . $billing_address_1 . ' ' . $billing_address_2 . '">
			    <input type="hidden" name="BUYERPHONE" id="BUYERPHONE" placeholder="고객 휴대폰번호" value="' . $billing_phone . '">
			
			    <input type="hidden" name="RETURN_BACKURL" id="RETURN_BACKURL" placeholder="결제 완료 백단" value="' . add_query_arg('wc-api', 'WC_Gateway_wc_cookiepay_pg', site_url()) . '">
			
			    <input type="hidden" name="RETURNURL" id="RETURNURL" placeholder="결제 완료 후 리다이렉트 url" value="' . $return_url . '">
			    <input type="hidden" name="COMPLETE_URL" id="COMPLETE_URL" placeholder="결제 완료 후 리다이렉트 url" value="' . $return_url . '">
			    <input type="hidden" name="CANCELURL" id="CANCELURL" placeholder="결제중 취소시 리다이렉트 url" value="' . $fail_url . '">
			
			    <input type="hidden" name="FAILURL" id="FAILURL" placeholder="결제 완료 후 리다이렉트 url" value="' . $fail_url . '">
			    <input type="hidden" name="MTYPE" id="MTYPE" placeholder="웹뷰활성화" value="">
			    <input type="hidden" name="ETC1" id="ETC1" placeholder="사용자 추가필드 1" value="">
			    <input type="hidden" name="ETC2" id="ETC2" placeholder="사용자 추가필드 2" value="">
			    <input type="hidden" name="ETC3" id="ETC3" placeholder="사용자 추가필드 3" value="">
			    <input type="hidden" name="ETC4" id="ETC4" placeholder="사용자 추가필드 4" value="">
			    <input type="hidden" name="ETC5" id="ETC5" placeholder="사용자 추가필드 5" value="">
			    <input type="hidden" name="PAY_VERSION" id="PAY_VERSION" placeholder="사용자 추가필드 5" value="">
			    <input type="hidden" name="PAY_TYPE" id="PAY_TYPE" placeholder="원화결제" value="">
			    <input type="hidden" name="FORWARD" id="FORWARD" placeholder="웰컴페이 2차 결제창 팝업/부모여부" value="Y">
				<input type="hidden" name="ORDER_NO_CHECK" id="ORDER_NO_CHECK" placeholder="주문번호 중복체크" value="N">
			    <!-- Escrow -->
			    <input type="hidden" name="ESCROW" id="ESCROW" placeholder="에스크로결제 사용여부" value="N">
			</form>
			<a href="javascript:pay();" class="button">결제하기</a>
		';
	} elseif ($_GET['ck_pay'] == 'fail') {
		echo '
			<script>
				alert("결제에 실패하였습니다.");
				location.href="' . wc_get_checkout_url() . '";
			</script>
		';
	}
});

// (1) '결제대기' 상태의 재결제 링크에 ck_pay, order_id, paymethod 등을 강제로 붙인
add_filter( 'woocommerce_get_checkout_payment_url', 'my_force_cookiepay_reorder_params', 10, 2 );
function my_force_cookiepay_reorder_params( $pay_url, $order ) {
    // $order 객체가 없거나 이미 결제된(Order가 결제 필요 없음) 상태라면 처리 X
    if ( ! $order || ! $order->needs_payment() ) {
        return $pay_url;
    }

    // (2) 만약 기존에 '어떤 결제수단'으로 결제 시도했는지 메타에 있으면 가져온다.
    //     없으면 그냥 '' 빈값 -> 오류 발생 처리
    $existing_paymethod = get_post_meta( $order->get_id(), 'ck_method', true );
    if ( empty($existing_paymethod) ) {
        $existing_paymethod = ''; 
    }

    // (3) order-pay 링크에, 우리가 원하는 파라미터를 추가
    $args_to_add = array(
        'ck_pay'    => 'true',              // 기존 코드에서 결제창을 띄우는 트리거
        'order_id'  => $order->get_id(),    // process_payment에서 사용
        'paymethod' => $existing_paymethod, // process_payment 시 스크립트에서 사용
    );

    $pay_url = add_query_arg( $args_to_add, $pay_url );
    
    return $pay_url;
}
