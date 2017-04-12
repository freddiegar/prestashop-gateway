{if $status eq 'ok'}
    <h2>{l s='Completed payment' mod='placetopay'}</h2>
    <p>{l s='Dear customer, your payment is approved thank you for your purchase.' mod='placetopay'}</p>
{elseif $status eq 'fail'}
    <h2 style="color:red">{l s='Failed payment' mod='placetopay'}</h2>
    <p>{l s='We\'re sorry. Your payment has not been completed. You can try again or choose another payment method.' mod='placetopay'}</p>
{elseif $status eq 'rejected'}
    <h2 style="color:red">{l s='Rejected payment' mod='placetopay'}</h2>
    <p>{l s='We\'re sorry. Your payment has not been completed. You can try again or choose another payment method.' mod='placetopay'}</p>
{elseif $status eq 'pending'}
    <h2>{l s='Pending payment' mod='placetopay'}</h2>
    <p>{l s='Dear customer, your payment is being validated in the payment gateway, once this process has been completed will be informed of the operation.' mod='placetopay'}</p>
{/if}
<br/>
<table border="0" cellpadding="1" class="table response">
    <tr valign="top">
        <td>{l s='Company ID' mod='placetopay'}</td>
        <td><b>{$companyDocument}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Company Name' mod='placetopay'}</td>
        <td><b>{$companyName}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Order No.' mod='placetopay'}</td>
        <td><b>{$objOrder->id}</b></td>
    </tr>
    {if !empty($transaction['reference'])}
        <tr valign="top">
            <td>{l s='Reference' mod='placetopay'}</td>
            <td><b>{$transaction['reference']}</b></td>
        </tr>
    {/if}
    <tr valign="top">
        <td>{l s='Payment description' mod='placetopay'}</td>
        <td><b>{$paymentDescription}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Payer name' mod='placetopay'}</td>
        <td><b>{$payerName|default:""}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Payer email' mod='placetopay'}</td>
        <td><b>{$payerEmail|default:""}</b></td>
    </tr>
    {if !empty($transaction['ip_address'])}
        <tr valign="top">
            <td>{l s='IP Address' mod='placetopay'}</td>
            <td><b>{$transaction['ip_address']}</b></td>
        </tr>
    {/if}
    <tr valign="top">
        <td>{l s='Transaction date' mod='placetopay'}</td>
        <td><b>{$transaction['date']}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Status' mod='placetopay'}</td>
        <td><b>{$status_description}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Reason' mod='placetopay'}</td>
        <td><b>{$transaction['reason']} - {$transaction['reason_description']}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Total amount' mod='placetopay'}</td>
        <td><b>{$currency_iso|default:""} {displayPrice price=$transaction['amount']}</b></td>
    </tr>
    <tr valign="top">
        <td>{l s='Tax' mod='placetopay'}</td>
        <td><b>{$currency_iso|default:""} {displayPrice price=$transaction['tax']}</b></td>
    </tr>
    {if !empty($transaction['franchise_name'])}
        <tr valign="top">
            <td>{l s='Franchise' mod='placetopay'}</td>
            <td><b>{$transaction['franchise_name']}</b></td>
        </tr>
    {/if}
    {if !empty($transaction['bank'])}
        <tr valign="top">
            <td>{l s='Bank name' mod='placetopay'}</td>
            <td><b>{$transaction['bank']}</b></td>
        </tr>
    {/if}
    {if !empty($transaction['authcode'])}
        <tr valign="top">
            <td>{l s='Authorization' mod='placetopay'}</td>
            <td><b>{$transaction['authcode']}</b></td>
        </tr>
    {/if}
    {if !empty($transaction['receipt'])}
        <tr valign="top">
            <td>{l s='Receipt' mod='placetopay'}</td>
            <td><b>{$transaction['receipt']}</b></td>
        </tr>
    {/if}
</table>

<br/>

<p>
    {l s='If you have any question please contact us on our phone' mod='placetopay'} {$storePhone}
    {l s='or using our email' mod='placetopay'} {mailto address=$storeEmail}
</p>

<br/>

<p>
    <a href='javascript:window.print()'>
        <img src="{$placetopayImgUrl}b_print.png" alt="{l s='Print' mod='placetopay'}" width="32" height="32"
             border="0"/>
        {l s='Print' mod='placetopay'}
    </a>

    {if ($status neq 'ok') and ($status neq 'pending')}
        {if isset($opc) && $opc}
            <a href="{$link->getPageLink('order-opc', true, NULL, "submitReorder&id_order={$objOrder->id|intval}")|escape:'html':'UTF-8'}"><img
                        src="{$placetopayImgUrl}retry.png" alt="{l s='Retry payment' mod='placetopay'}" width="32"
                        height="32" border="0"/>
                {l s='Retry payment' mod='placetopay'}
            </a>
        {else}
            <a href="{$link->getPageLink('order', true, NULL, "submitReorder&id_order={$objOrder->id|intval}")|escape:'html':'UTF-8'}"><img
                        src="{$placetopayImgUrl}retry.png" alt="{l s='Retry payment' mod='placetopay'}" width="32"
                        height="32" border="0"/>
                {l s='Retry payment' mod='placetopay'}
            </a>
        {/if}
    {/if}
    <a href="{$link->getPageLink('history', true)|escape:'html':'UTF-8'}"> <img src="{$placetopayImgUrl}history.png"
                                                                                alt="{l s='Print' mod='placetopay'}"
                                                                                width="32" height="32"
                                                                                border="0"/> {l s='Payment History' mod='placetopay'}
    </a>
</p>

<br/>