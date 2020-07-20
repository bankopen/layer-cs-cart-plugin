<!DOCTYPE html>
<html lang="{$smarty.const.CART_LANGUAGE}">
    <head>
        <title>{__('checkout')}</title>
        {include file="meta.tpl"}
        <link href="{$logos.favicon.image.image_path}" rel="shortcut icon" type="{$logos.favicon.image.absolute_path|fn_get_mime_content_type}" />
        {include file="common/styles.tpl" include_dropdown=true}
        {include file="common/scripts.tpl"}
    </head>

    <body>
    <div class="tygh-header clearfix">
		{include file="blocks/static_templates/logo.tpl"}       
	</div>
		<div class="ty-mainbox-cart__body" style="width:30%; border-collapse:collapse; border:#39F thin solid; margin-top:20px;">
        <span class="ty-block ty-minicart-title__header ty-uppercase">Layer Payment for your Order:</span>
		<table class="ty-cart-content ty-table">
	    <tbody>
          <tr>
            <td class="ty-cart-content__product-elem ty-cart-content__description" style="width: 50%;">
            	Order#: {$woo_order_id}
            </td>
            <td class="ty-cart-content__product-elem ty-cart-content__price" id="price_display">
            	Total: {$currency} {$payment_token_amount}            
             </td>
             </tr>
    </tbody>
    </table>
    
        <div id="tygh_container" style=" margin-bottom:20px;">  
			{if $error eq ''}

			<form action='{$return_url}' method='post' style='display: none' name='layer_payment_int_form'>
			<input type='hidden' name='layer_pay_token_id' value='{$payment_token_id}'>
			<input type='hidden' name='woo_order_id' value='{$woo_order_id}'>
			<input type='hidden' name='layer_order_amount' value='{$payment_token_amount}'>
			<input type='hidden' id='layer_payment_id' name='layer_payment_id' value=''>
			<input type='hidden' id='fallback_url' name='fallback_url' value=''>
			<input type='hidden' name='hash' value='{$hash}'>
			</form>
			
			<script type="text/javascript">
				var script = document.createElement('script');
				script.setAttribute('src', '{$remote_script}');
				document.body.appendChild(script);
	
				function triggerLayer() {							 							
					Layer.checkout(
					{
						token: '{$payment_token_id}',
						accesskey: '{$apikey}'
					},
					function (response) {
						console.log(response)
						if(response !== null || response.length > 0 ){
							if(response.payment_id !== undefined){
								document.getElementById('layer_payment_id').value = response.payment_id;
							}
						}
						document.layer_payment_int_form.submit();
					},
					function (err) {
						//alert(err.message);
					});	
				}
				
				var checkExist = setInterval(function() {
					if (typeof Layer !== 'undefined') {
						console.log('Layer Loaded...');
						clearInterval(checkExist);
						triggerLayer();
					}
					else {
						console.log('Layer undefined...');
						}
					}, 1000);
			</script>
			{else}
				{$error}
			{/if}
        <!--tygh_container--></div>
        
    </div>
        
 {$smarty.capture.footers nofilter}       
    </body>

</html>
