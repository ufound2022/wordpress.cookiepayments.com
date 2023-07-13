jQuery(document).ready(function($) {
	$('.datepicker').datepicker({
		dateFormat : "yy-mm-dd"
	});
	$('.ck_cancel_order').click(function(){
		var result = confirm('주문을 취소하시겠습니까?');
        if(!result) {
        	return false;
        }
		var order_id = $(this).data('oid');
		$.ajax({
	    	url			: ajax_object.ajax_url,
            type		: "POST",
            dataType	: "text",
          	data:
            {
                action  : 'cancel_order',
                oid		: order_id,
            },
            success: function(data)
            {
                var result = jQuery.parseJSON(data);
                if(result.isSuccess){
                	alert("취소되었습니다.");
                	location.href=result.detail;
                }
                else{
                	alert(result.error);
                }
            },
            error: function(request,status,error){
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
	});
});