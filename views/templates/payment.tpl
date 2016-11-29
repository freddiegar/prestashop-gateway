<p class="payment_module">
    {if $hasPending}
        <a class="placetopay">
            <img src="https://www.placetopay.com/images/providers/placetopay_xh48.png"
                 alt="{l s='Pay with Place to Pay' mod='placetopaypayment'}"/>

            <b>{l s='Pay with Place to Pay' mod='placetopaypayment'}</b>

            <span>
				{l s='(credit cards and debits account)' mod='placetopaypayment'}
			</span>

            <br/>
            <br/>

            <font color="red">
                <b>&gt;&gt; {l s='Warning' mod='placetopaypayment'}</b>
            </font>

            <span class="main">
				<br/>
                {l s='At this time your order' mod='placetopaypayment'}
                <b>#{$lastOrder}</b>
                {l s='presents a checkout transaction which is pending to receive confirmation from your bank, please wait a few minutes and check back later to see if your payment was successfully confirmed.' mod='placetopaypayment'}
                <br/>
				<br/>
                {l s='For more information on the current state of your operation can contact our customer service line in' mod='placetopaypayment'}
                <b>{$storePhone}</b>
                {l s='or send your questions to' mod='placetopaypayment'}
                <b>{$storeEmail}</b>
                {l s='and ask for the status of the transaction:' mod='placetopaypayment'}
                <b>{$lastAuthorization|default:"N/D"}</b>
			</span>

            {if $cifinmessage}
                <span>
					<small>
						<br/>
						<br/>
                        {l s='Anyone who make a purchase on the site' mod='placetopaypayment'}
					</small>
					
					<b>{$sitename|escape:'htmlall':'UTF-8'}</b>
					
					<small>
						{l s=', acting freely and voluntarily consent to' mod='placetopaypayment'}
					</small>
					
					<b>{$companyname}</b>
					
					<small>
						{l s=', through the service provider' mod='placetopaypayment'}
					</small>
					
					<b>{l s='EGM Ingeniería Sin Fronteras S.A.S' mod='placetopaypayment'}</b>
					
					<small>
						{l s=' and / or ' mod='placetopaypayment'}
					</small>
					
					<b>{l s='Place to Pay' mod='placetopaypayment'}</b>

					<small>
						{l s='to consult and ask for ' mod='placetopaypayment'}
                        {l s='information from credit performance, financial , commercial and service to ' mod='placetopaypayment'}
                        {l s='third parties even in countries of a similar nature to the information center ' mod='placetopaypayment'}
					</small>
					
					<b>{l s='CIFIN S.A' mod='placetopaypayment'}</b>
					
					<small>
						{l s=', generating a ' mod='placetopaypayment'}
                        <u>{l s='consultation trace.' mod='placetopaypayment'}</u>
					</small>
				</span>
            {/if}
        </a>
    {else}
        <a class="placetopay" href="{$base_dir_ssl}modules/{$module}/redirect.php"
           title="{l s='Pay with Place to Pay' mod='placetopaypayment'}">
            <img src="https://www.placetopay.com/images/providers/placetopay_xh48.png"
                 alt="{l s='Pay with Place to Pay' mod='placetopaypayment'}"/>
            <b>
                {l s='Pay with Place to Pay' mod='placetopaypayment'}
            </b>

            <span>
				{l s='(credit cards and debits account)' mod='placetopaypayment'}
                <br/>
                {l s='Place to Pay secure web site will be displayed when you select this payment method.' mod='placetopaypayment'}
                <br/>
                {if $cifinmessage}
                    <br/>
                    <b>&gt;&gt; {l s='Warning' mod='placetopaypayment'}</b>
                    <br/>
                    <span>
						<small>
							{l s='Anyone who make a purchase on the site' mod='placetopaypayment'}
						</small>

						<b>{$sitename|escape:'htmlall':'UTF-8'}</b>

						<small>
							{l s=', acting freely and voluntarily consent to' mod='placetopaypayment'} 
						</small>

						<b>{$companyname}</b>

						<small>
							{l s=', through the service provider' mod='placetopaypayment'}
						</small>
						
						<b>{l s='EGM Ingeniería Sin Fronteras S.A.S' mod='placetopaypayment'}</b>
						
						<small>
							{l s=' and / or ' mod='placetopaypayment'} 
						</small>
						
						<b>{l s='Place to Pay' mod='placetopaypayment'}</b>
						
						<small>
							{l s='to consult and ask for ' mod='placetopaypayment'}
                            {l s='information from credit performance, financial , commercial and service to ' mod='placetopaypayment'}
                            {l s='third parties even in countries of a similar nature to the information center ' mod='placetopaypayment'}
						</small>
						
						<b>{l s='CIFIN S.A' mod='placetopaypayment'}</b>
						
						<small>
							{l s=', generating a ' mod='placetopaypayment'}
                            <u>{l s='consultation trace.' mod='placetopaypayment'}</u>
						</small>
					</span>
                {/if}
			</span>
        </a>
    {/if}
</p>