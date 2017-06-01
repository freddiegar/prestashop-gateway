{if $status eq 'ok'}
    <h2>{l s='Completed payment' mod='placetopaypayment'}</h2>
    <p>{l s='Dear customer, your payment is approved thank you for your purchase.' mod='placetopaypayment'}</p>
{elseif $status eq 'fail'}
    <h2 style="color:red">{l s='Failed payment' mod='placetopaypayment'}</h2>
    <p>{l s='We\'re sorry. Your payment has not been completed. You can try again or choose another payment method.' mod='placetopaypayment'}</p>
{elseif $status eq 'rejected'}
    <h2 style="color:red">{l s='Rejected payment' mod='placetopaypayment'}</h2>
    <p>{l s='We\'re sorry. Your payment has not been completed. You can try again or choose another payment method.' mod='placetopaypayment'}</p>
{elseif $status eq 'pending'}
    <h2>{l s='Pending payment' mod='placetopaypayment'}</h2>
    <p>{l s='Dear customer, your payment is being validated in the payment gateway, once this process has been completed will be informed of the operation.' mod='placetopaypayment'}</p>
{/if}
<br/>
<table border="0" cellpadding="1" class="table response">
    <tr valign="top">
        <td>{l s='Company ID' mod='placetopaypayment'}</td>
        <td><b>{$company_document}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Company Name' mod='placetopaypayment'}</td>
        <td><b>{$company_name}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Order No.' mod='placetopaypayment'}</td>
        <td><b>{$objOrder->id}</b></td>
    </tr>
    {if !empty($transaction['reference'])}
        <tr valign="top">
            <td>{l s='Reference' mod='placetopaypayment'}</td>
            <td><b>{$transaction['reference']}</b></td>
        </tr>
    {/if}
    <tr valign="top">
        <td>{l s='Payment description' mod='placetopaypayment'}</td>
        <td><b>{$payment_description}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Payer name' mod='placetopaypayment'}</td>
        <td><b>{$payer_name|default:""}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Payer email' mod='placetopaypayment'}</td>
        <td><b>{$payer_email|default:""}</b></td>
    </tr>
    {if !empty($transaction['ip_address'])}
        <tr valign="top">
            <td>{l s='IP Address' mod='placetopaypayment'}</td>
            <td><b>{$transaction['ip_address']}</b></td>
        </tr>
    {/if}
    <tr valign="top">
        <td>{l s='Transaction date' mod='placetopaypayment'}</td>
        <td><b>{$transaction['date']}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Status' mod='placetopaypayment'}</td>
        <td><b>{$status_description}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Reason' mod='placetopaypayment'}</td>
        <td><b>{$transaction['reason']} - {$transaction['reason_description']}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Total amount' mod='placetopaypayment'}</td>
        <td><b>{$currency_iso|default:""} {displayPrice price=$transaction['amount']}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Tax' mod='placetopaypayment'}</td>
        <td><b>{$currency_iso|default:""} {displayPrice price=$transaction['tax']}</b></td>
    </tr>
    {if !empty($transaction['franchise_name'])}
        <tr valign="top">
            <td>{l s='Franchise' mod='placetopaypayment'}</td>
            <td><b>{$transaction['franchise_name']}</b></td>
        </tr>
    {/if}
    {if !empty($transaction['bank'])}
        <tr valign="top">
            <td>{l s='Bank name' mod='placetopaypayment'}</td>
            <td><b>{$transaction['bank']}</b></td>
        </tr>
    {/if}
    {if !empty($transaction['authcode'])}
        <tr valign="top">
            <td>{l s='Authorization/CUS' mod='placetopaypayment'}</td>
            <td><b>{$transaction['authcode']}</b></td>
        </tr>
    {/if}
    {if !empty($transaction['receipt'])}
        <tr valign="top">
            <td>{l s='Receipt' mod='placetopaypayment'}</td>
            <td><b>{$transaction['receipt']}</b></td>
        </tr>
    {/if}
</table>

<br/>

<p>
    {l s='If you have any question please contact us on our phone' mod='placetopaypayment'} {$store_phone}
    {l s='or using our email' mod='placetopaypayment'} {mailto address=$store_email}
</p>

<br/>

<p>
    <a href='javascript:window.print()'>
        <img src="{$placetopay_img_url}b_print.png" alt="{l s='Print' mod='placetopaypayment'}" width="32" height="32"
             border="0"/>
        {l s='Print' mod='placetopaypayment'}
    </a>

    {if ($status neq 'ok') and ($status neq 'pending')}
        {if isset($opc) && $opc}
            <a href="{$link->getPageLink('order-opc', true, NULL, "submitReorder&id_order={$objOrder->id|intval}")|escape:'html':'UTF-8'}"><img
                        src="{$placetopay_img_url}retry.png" alt="{l s='Retry payment' mod='placetopaypayment'}"
                        width="32"
                        height="32" border="0"/>
                {l s='Retry payment' mod='placetopaypayment'}
            </a>
        {else}
            <a href="{$link->getPageLink('order', true, NULL, "submitReorder&id_order={$objOrder->id|intval}")|escape:'html':'UTF-8'}"><img
                        src="{$placetopay_img_url}retry.png" alt="{l s='Retry payment' mod='placetopaypayment'}"
                        width="32"
                        height="32" border="0"/>
                {l s='Retry payment' mod='placetopaypayment'}
            </a>
        {/if}
    {/if}

    <a href="{$link->getPageLink('history', true)|escape:'html':'UTF-8'}">
        <img src="{$placetopay_img_url}history.png"
             alt="{l s='Payment History' mod='placetopaypayment'}"
             width="32" height="32"
             border="0"/>
        {l s='Payment History' mod='placetopaypayment'}
    </a>
</p>

<br/>