<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

<div class="panel">
    <div>
        <img src="https://www.placetopay.com/images/providers/placetopay.full.png"
             style="float:left; margin-right:15px;" alt="Place to Pay" height="48">
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
    </div>

    <div>
        <table class="table response">
            <tr valign="top">
                <td>{l s='Merchant ID' mod='placetopaypayment'}</td>
                <td><b>{$company_document}</b></td>
            </tr>
            <tr valign="top">
                <td>{l s='Legal Name' mod='placetopaypayment'}</td>
                <td><b>{$company_name}</b></td>
            </tr>
            <tr valign="top">
                <td>{l s='Order No.' mod='placetopaypayment'}</td>
                <td><b>{$orderId}</b></td>
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
                <td><b>{Tools::displayPrice($transaction['amount'])}</b></td>
            </tr>
            <tr valign="top">
                <td>{l s='Tax' mod='placetopaypayment'}</td>
                <td><b>{Tools::displayPrice($transaction['tax'])}</b></td>
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
    </div>

    <div>
        <p>
            {l s='If you have any question please contact us on our phone' mod='placetopaypayment'} {$store_phone}
            {l s='or using our email' mod='placetopaypayment'} {mailto address=$store_email}
        </p>
    </div>

    <div>
        <p>
            <a href='javascript:window.print()' class="text-default">
                <i class="fa fa-print fa-3x text-info"></i>
                {l s='Print' mod='placetopaypayment'}
            </a>

            {if ($status neq 'ok') and ($status neq 'pending')}
                {if isset($opc) && $opc}
                    <a href="{$link->getPageLink('order-opc', true, NULL, "submitReorder&id_order={$orderId|intval}")|escape:'html':'UTF-8'}">
                        <i class="fa fa-money fa-3x text-warning"></i>
                        {l s='Retry payment' mod='placetopaypayment'}
                    </a>
                {else}
                    <a href="{$link->getPageLink('order', true, NULL, "submitReorder&id_order={$orderId|intval}")|escape:'html':'UTF-8'}">
                        <i class="fa fa-money fa-3x text-warning"></i>
                        {l s='Retry payment' mod='placetopaypayment'}
                    </a>
                {/if}
            {/if}

            <a href="{$link->getPageLink('history', true)|escape:'html':'UTF-8'}" class="text-primary">
                <i class="fa fa-history fa-3x text-success"></i>
                {l s='Payment History' mod='placetopaypayment'}
            </a>
        </p>
    </div>
</div>