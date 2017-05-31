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

    <form enctype="multipart/form-data" method="post" action="{$actionURL}">
        <fieldset>
            <legend>
                <img src="../img/admin/cog.gif"
                     alt="{l s='Company data' mod='placetopaypayment'}"/>{l s='Company data' mod='placetopaypayment'}
            </legend>

            <label class="control-label" for="companydocument">
                {l s='Company ID' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="text"
                       id="companydocument"
                       name="companydocument"
                       value="{$companydocument}"
                       width="120"
                       autocomplete="off"/>
            </div>

            <label class="control-label" for="companyname">
                {l s='Company Name' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="text"
                       id="companyname"
                       name="companyname"
                       value="{$companyname}"
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
                <span style="font-size: 16px;">{$urlnotification}</span><br/>
                {l s='Return URL where PlacetoPay will send status payment\'s  to Prestashop' mod='placetopaypayment'}
            </div>

            <label class="control-label">
                {l s='Path schudele task' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <span style="font-size: 16px;">{$schudeletask}</span><br/>
                {l s='Set this task to validate payments with pending status in your site' mod='placetopaypayment'}
            </div>

            <label class="control-label" for="login">
                {l s='Login Place to Pay' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="text"
                       id="login"
                       name="login"
                       value="{$login}"
                       width="40"
                       autocomplete="off"/>
            </div>

            <label class="control-label" for="trankey">
                {l s='Trankey Place to Pay' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <input class="form-control"
                       type="password"
                       id="trankey"
                       name="trankey"
                       value="{$trankey}"
                       width="40"
                       autocomplete="off"/>
            </div>

            <label class="control-label" for="environment">
                {l s='Environment' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="environment" name="environment">
                    <option value="PRODUCTION"
                            {if $environment eq 'PRODUCTION'}selected="selected"{/if}>{l s='Production' mod='placetopaypayment'}
                    </option>
                    <option value="TEST"
                            {if $environment eq 'TEST'}selected="selected"{/if}>{l s='Test' mod='placetopaypayment'}
                    </option>
                    <option value="DEVELOPMENT"
                            {if $environment eq 'DEVELOPMENT'}selected="selected"{/if}>{l s='Development' mod='placetopaypayment'}
                    </option>
                </select>
            </div>

            <label class="control-label" for="historycustomized">
                {l s='Enable Order History Customized' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="historycustomized" name="historycustomized">
                    <option value="1"
                            {if $historycustomized eq '1'}selected="selected"{/if}>{l s='Yes' mod='placetopaypayment'}
                    </option>
                    <option value="0"
                            {if $historycustomized ne '1'}selected="selected"{/if}>{l s='No' mod='placetopaypayment'}
                    </option>
                </select>
            </div>
            <br/>

            <label class="control-label" for="cifinmessage">
                {l s='Enable CIFIN message' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="cifinmessage" name="cifinmessage">
                    <option value="1"
                            {if $cifinmessage eq '1'}selected="selected"{/if}>{l s='Yes' mod='placetopaypayment'}
                    </option>
                    <option value="0"
                            {if $cifinmessage ne '1'}selected="selected"{/if}>{l s='No' mod='placetopaypayment'}
                    </option>
                </select>
            </div>

            <label class="control-label" for="stockreinject">
                {l s='Reinject stock on declination?' mod='placetopaypayment'}
            </label>
            <div class="margin-form">
                <select class="form-control" id="stockreinject" name="stockreinject">
                    <option value="1"
                            {if $stockreinject eq '1'}selected="selected"{/if}>{l s='Yes' mod='placetopaypayment'}
                    </option>
                    <option value="0"
                            {if $stockreinject ne '1'}selected="selected"{/if}>{l s='No' mod='placetopaypayment'}
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