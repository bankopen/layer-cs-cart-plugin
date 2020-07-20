{assign var="r_url" value="payment_notification.return?payment=layerpayment"|fn_url:'C':'http'}
<p>Return URL: {$r_url}</p>
<hr />
<div class="control-group">
	<label class="control-label" for="layerpayment_mode">Gateway Mode:</label>
	<div class="controls">
		<select name="payment_data[processor_params][layerpayment_mode]" id="layerpayment_mode">
			<option value="test"{if $processor_params.layerpayment_mode eq "test"} selected="selected"{/if}>Test			
			<option value="live"{if $processor_params.layerpayment_mode eq "live"} selected="selected"{/if}>Live
		</select>
	</div>
</div>
<div class="control-group">
	<label class="control-label" for="layerpayment_apikey">API Key:</label>
	<div class="controls">
		<input type="text" name="payment_data[processor_params][layerpayment_apikey]" id="layerpayment_apikey" value="{$processor_params.layerpayment_apikey}" class="input-text" size="50" />
	</div>
</div>
<div class="control-group">
	<label class="control-label" for="layerpayment_secretkey">Secret Key:</label>
	<div class="controls">
		<input type="text" name="payment_data[processor_params][layerpayment_secretkey]" id="layerpayment_secretkey" value="{$processor_params.layerpayment_secretkey}" class="input-text" size="50" />
	</div>
</div>
