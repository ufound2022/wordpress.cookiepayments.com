<?php
function p2p_pay_list_page(){
	add_menu_page( 
		'쿠키페이 결제내역',
		'쿠키페이 결제내역',
		'manage_options',
		'cookiepay_list',
		'cookiepay_pay_list_func',
		'',
		30
	); 
}
add_action( 'admin_menu', 'p2p_pay_list_page' );
function cookiepay_pay_list_func(){
	$sdate 		= (empty($_GET['sdate']) ? current_time('Y-m-01'):$_GET['sdate']);
	$edate 		= (empty($_GET['edate']) ? current_time('Y-m-t'):$_GET['edate']);
	$api_key 	= get_ck_key();
	$token 		= get_ck_token();
	if(!empty($token)){
		$cookiepayments_url = $api_key['search_url'];
		$request_data_array = array(
			'API_ID' => $api_key['ck_id'],
		 	'STD_DT' => $sdate,
		 	'END_DT' => $edate,
		);
		$list_res = wp_remote_post($cookiepayments_url, array(
	    	'body'    	=> json_encode($request_data_array,JSON_UNESCAPED_UNICODE),
		    'headers'     => [
				'Content-Type' 	=> 'application/json',
				'TOKEN'			=> $token,
			],
		) );
			
		if( !is_wp_error( $list_res ) ) {
			$list = json_decode( $list_res['body'], true );
			if ( !empty($list['RESULTCODE']) && in_array($list['RESULTCODE'],array('0000','E008') ) || $list['0']['RESULTCODE'] == '0000') {
				$pay_list = $list;
			}
			else{
				echo '(결제내역오류)'.$list['RESULTCODE'].':'.$list['RESULTMSG'];
			}
		}
		else{
			echo '결제내역을 가져올수 없습니다.(통신 오류)';
			die();
		}
	}
	else{
		echo 'API ID, API Key 값을 확인해주세요.(토큰통신 오류)';
		die();
	}
	
	$output = '
		<div class="wrap">
			<h1 class="wp-heading-inline">쿠키페이 결제내역</h1>
			<div class="tablenav top">
				<form id="posts-filter" method="get">
					<input type="hidden" name="page" value="cookiepay_list">
					<input type="text" name="sdate" class="datepicker" value="'.$sdate.'" placeholder="" />
					<span>~</span>
					<input type="text" name="edate" class="datepicker" value="'.$edate.'" placeholder="" />
					<button type="submit" class="button">검색</button>
					<a href="'.add_query_arg( array('wc-api'=>'wc_gateway_download','sdate'=>$sdate,'edate'=>$edate), site_url() ).'" class="button">엑셀다운로드</a>
				</form>
			</div>
			<table class="wp-list-table widefat fixed striped table-view-list pages">
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
						<th>전표보기</th>
					</tr>
				</thead>
				<tbody>
	';
	if(!empty($pay_list['RESULTCODE']) && $pay_list['RESULTCODE'] == 'E008'){
		$output .='
			<tr>
				<td colspan="21">검색 결과가 없습니다.</td>
			</tr>
		';
	}
	else{
		/*
		print_r('<pre>');
		print_r($pay_list);
		print_r('</pre>');
		*/
		foreach ($pay_list as $key => $value) {
			$order_id = $value['ORDERNO'];
			$order = wc_get_order( $order_id );
			$date = '';
			if(!empty($order)){
				$date = date('Y-m-d H:i:s',strtotime($order->get_date_created()));
			}
			$result = json_decode(get_post_meta($order_id,'ck_result',TRUE),TRUE);
			$period = $result['QUOTA'];
			if(empty($period)){
				$card_result = json_decode(get_post_meta($order_id,'ck_card_result',TRUE),TRUE);
				$period = $card_result['QUOTA'];
			}
            if(empty($period) && $value['PAYMETHOD'] == 'CARD') $period = '00';
			$output .='
				<tr>
					<td>'.$date.'</td>
					<td>'.ck_get_pg_arr()[$api_key['cookiepay_pg']].'</td>
					
					<td>'.$value['ORDERNO'].'</td>
					<td>'.number_format($value['AMOUNT']).'</td>
					<td>'.$value['BUYERNAME'].'</td>
					<td>'.$value['BUYEREMAIL'].'</td>
					<td>'.$value['PRODUCTNAME'].'</td>
					
					<td>'.$value['PRODUCTCODE'].'</td>
					<td>'.$value['PAYMETHOD'].'</td>
					<td>'.$value['BUYERID'].'</td>
					<td>'.$value['TID'].'</td>
					<td>'.$value['ACCEPTNO'].'</td>
					
					<td>'.$value['ACCEPTDATE'].'</td>
					<td>'.$value['CANCELDATE'].'</td>
					<td>'.$value['CANCELMSG'].'</td>
					<td>'.$value['ACCOUNTNO'].'</td>
					<td>'.$value['RECEIVERNAME'].'</td>
					
					<td>'.$value['DEPOSITENDDATE'].'</td>
					<td>'.$value['CARDNAME'].'('.$period.')</td>
					<td>'.$value['CARDCODE'].'</td>
					<td>
						<button type="button" class="button" onclick="receipt(\''.$value['TID'].'\')">전표보기</button>
					</td>
				</tr>
			';
		}
	}
	$output .='
				</tbody>
			</table>
		</div>
		<script>
		
		function base64_encode(str) {
		    return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function(match, p1) {
		        return String.fromCharCode("0x" + p1);
		    }));
		}
		
		function receipt(tid) {
		    var tid = base64_encode(tid);
		    window.open(
		        "'.$api_key['receipt_url'].'?tid="+tid,
		        "cookiepayments Receipt",
		        "width=468,height=750"
		    );
		}
		</script>
	';
	echo $output;	
}
?>