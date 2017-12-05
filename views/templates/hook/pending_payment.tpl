<div>
    <div class="alert alert-{if $allow_payment}warning{else}danger{/if}">
        {if $allow_payment}
            <b>&gt;&gt; {l s='Warning' mod='placetopaypayment'}</b>
            <br/>
            {l s='You can continue with process payment, but.' mod='placetopaypayment'}
        {/if}
        {l s='At this time your order' mod='placetopaypayment'}
        <b># {$last_order}</b>
        {l s='presents a checkout transaction which is PENDING to receive confirmation from your bank, please wait a few minutes and check back later to see if your payment was successfully confirmed.' mod='placetopaypayment'}
        <br/>
        <br/>
        {l s='For more information on the current state of your operation can contact our customer service line in' mod='placetopaypayment'}
        <b>{$telephone_contact}</b>
        {l s='or send a email to' mod='placetopaypayment'}
        <b>{$email_contact}</b>
        {l s='and ask for the status of the transaction:' mod='placetopaypayment'}
        <b>{$last_authorization|default:"N/D"}</b>
    </div>
</div>