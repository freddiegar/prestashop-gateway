<div class="p2pcontent">

    <h4>
        <img src="https://www.placetopay.com/images/providers/placetopay.full.png" alt="Place to Pay" height="48"/>
        Version: {$version}
    </h4>

    <b>
        {l s='This module allows you to accept payments by Place to Pay.' mod='placetopaypayment'}
    </b>

    <br/>

    {l s='You need to configure your Place to Pay account before using this module.' mod='placetopaypayment'}

    <br/>
    <br/>

    <form enctype="multipart/form-data" method="post" action="{$action_url}">
        <fieldset>
            <legend>
                <img src="../img/admin/cog.gif"
                     alt="{l s='Company data' mod='placetopaypayment'}"/>{l s='Company data' mod='placetopaypayment'}
            </legend>

            <label class="control-label" for="company_document">
                {l s='Company ID' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="text"
                       id="company_document"
                       name="company_document"
                       value="{$company_document}"
                       width="120"
                       autocomplete="off"/>
            </div>

            <label class="control-label" for="company_name">
                {l s='Company Name' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="text"
                       id="company_name"
                       name="company_name"
                       value="{$company_name}"
                       width="120"
                       autocomplete="off"/>
            </div>

            <label class="control-label" for="description">
                {l s='Payment description' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="text"
                       id="description"
                       name="description"
                       value="{$description}"
                       width="120"
                       autocomplete="off"/>
            </div>

            <label class="control-label" for="email">
                {l s='Email contact' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="email"
                       id="email"
                       name="email"
                       value="{$email}"
                       width="120"
                       autocomplete="off"/>
            </div>

            <label class="control-label" for="telephone">
                {l s='Telephone contact' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="text"
                       id="telephone"
                       name="telephone"
                       value="{$telephone}"
                       width="120"
                       autocomplete="off"/>
            </div>
        </fieldset>

        <fieldset>
            <legend>
                <img src="../img/admin/cog.gif"
                     alt="{l s='Configuration' mod='placetopaypayment'}"/>{l s='Configuration' mod='placetopaypayment'}
            </legend>

            <label class="control-label">
                {l s='URL Notification' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <span style="font-size: 16px;">{$url_notification}</span><br/>
                {l s='Return URL where PlacetoPay will send status payment\'s  to Prestashop' mod='placetopaypayment'}
            </div>

            <label class="control-label">
                {l s='Path schudele task' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <span style="font-size: 16px;">{$schedule_task}</span><br/>
                {l s='Set this task to validate payments with pending status in your site' mod='placetopaypayment'}
            </div>

            <label class="control-label" for="history_customized">
                {l s='Enable Order History Customized' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="history_customized" name="history_customized">
                    <option value="{$enabled}"
                            {if $history_customized eq $enabled}selected="selected"{/if}>{l s='Yes' mod='placetopaypayment'}
                    </option>
                    <option value="{$disabled}"
                            {if $history_customized eq $disabled}selected="selected"{/if}>{l s='No' mod='placetopaypayment'}
                    </option>
                </select>
            </div>
            <br/>

            <label class="control-label" for="cifin_message">
                {l s='Enable CIFIN message' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="cifin_message" name="cifin_message">
                    <option value="{$enabled}"
                            {if $cifin_message eq $enabled}selected="selected"{/if}>{l s='Yes' mod='placetopaypayment'}
                    </option>
                    <option value="{$disabled}"
                            {if $cifin_message eq $disabled}selected="selected"{/if}>{l s='No' mod='placetopaypayment'}
                    </option>
                </select>
            </div>

            <label class="control-label" for="stock_re_inject">
                {l s='Reinject stock on declination?' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="stock_re_inject" name="stock_re_inject">
                    <option value="{$enabled}"
                            {if $stock_re_inject eq $enabled}selected="selected"{/if}>{l s='Yes' mod='placetopaypayment'}
                    </option>
                    <option value="{$disabled}"
                            {if $stock_re_inject eq $disabled}selected="selected"{/if}>{l s='No' mod='placetopaypayment'}
                    </option>
                </select>
            </div>

            <label class="control-label" for="allow_buy_with_pending_payments">
                {l s='Allow buy with pending payments?' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="allow_buy_with_pending_payments"
                        name="allow_buy_with_pending_payments">
                    <option value="{$enabled}"
                            {if $allow_buy_with_pending_payments eq $enabled}selected="selected"{/if}>{l s='Yes' mod='placetopaypayment'}
                    </option>
                    <option value="{$disabled}"
                            {if $allow_buy_with_pending_payments ne $enabled}selected="selected"{/if}>{l s='No' mod='placetopaypayment'}
                    </option>
                </select>
            </div>
        </fieldset>

        <fieldset>
            <legend>
                <img src="../img/admin/cog.gif"
                     alt="{l s='Configuration Connection' mod='placetopaypayment'}"/>{l s='Configuration Connection' mod='placetopaypayment'}
            </legend>

            <label class="control-label" for="environment">
                {l s='Environment' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="environment" name="environment" required>
                    <option value="{$production}"
                            {if $environment eq $production}selected="selected"{/if}>{l s='Production' mod='placetopaypayment'}
                    </option>
                    <option value="{$test}"
                            {if $environment eq $test}selected="selected"{/if}>{l s='Test' mod='placetopaypayment'}
                    </option>
                    <option value="{$development}"
                            {if $environment eq $development}selected="selected"{/if}>{l s='Development' mod='placetopaypayment'}
                    </option>
                </select>
            </div>

            <label class="control-label" for="login">
                {l s='Login Place to Pay' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="text"
                       required
                       id="login"
                       name="login"
                       value="{$login}"
                       width="40"
                       autocomplete="off"/>
            </div>

            <label class="control-label" for="tranKey">
                {l s='Trankey Place to Pay' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="password"
                       required
                       id="tranKey"
                       name="tranKey"
                       value="{$tranKey}"
                       width="40"
                       autocomplete="off"/>
            </div>

            <label class="control-label" for="expiration_time_minutes">
                {l s='Expiration time minutes' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="number"
                       required
                       id="expiration_time_minutes"
                       name="expiration_time_minutes"
                       value="{$expiration_time_minutes}"
                       autocomplete="off"/>
            </div>

            <label class="control-label" for="fill_tax_information">
                {l s='Fill TAX information?' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="fill_tax_information" name="fill_tax_information">
                    <option value="{$enabled}"
                            {if $fill_tax_information eq $enabled}selected="selected"{/if}>{l s='Yes' mod='placetopaypayment'}
                    </option>
                    <option value="{$disabled}"
                            {if $fill_tax_information eq $disabled}selected="selected"{/if}>{l s='No' mod='placetopaypayment'}
                    </option>
                </select>
            </div>

            <label class="control-label" for="connection_type">
                {l s='Connection type' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="connection_type" name="connection_type" required>
                    <option value="{$soap}"
                            {if $connection_type eq $soap}selected="selected"{/if}>{l s='SOAP' mod='placetopaypayment'}
                    </option>
                    <option value="{$rest}"
                            {if $connection_type eq $rest}selected="selected"{/if}>{l s='REST' mod='placetopaypayment'}
                    </option>
                </select>
            </div>

            <label class="control-label" for="fill_buyer_information">
                {l s='Fill buyer information?' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="fill_buyer_information" name="fill_buyer_information" required>
                    <option value="{$enabled}"
                            {if $fill_buyer_information eq $enabled}selected="selected"{/if}>{l s='Yes' mod='placetopaypayment'}
                    </option>
                    <option value="{$disabled}"
                            {if $fill_buyer_information eq $disabled}selected="selected"{/if}>{l s='No' mod='placetopaypayment'}
                    </option>
                </select>
            </div>

            <div class="margin-form">
                <input class="btn btn-primary pull-right"
                       type="submit"
                       value="{l s='Update configuration' mod='placetopaypayment'}"
                       id="submitPlacetoPayConfiguraton"
                       name="submitPlacetoPayConfiguraton"/>
            </div>

        </fieldset>
    </form>
    <div style="clear:both;">&nbsp;</div>
</div>