<div class="panel">
    <div class="alert alert-info">
        <img src="https://www.placetopay.com/images/providers/placetopay.full.png"
             style="float:left; margin-right:15px;" alt="Place to Pay" height="48">
        <p>
            <strong>
                {l s='This module allows you to accept payments by Place to Pay.' mod='placetopaypayment'}
            </strong>
        </p>
        <p>
            Vr: {$version}
        </p>
    </div>

    {if !$is_set_credentials}
        <div class="alert alert-warning">
            <p>
                {l s='You need to configure your Place to Pay account before using this module.' mod='placetopaypayment'}
            </p>
        </div>
    {/if}

    <div class="panel-body">
        <form class="form-horizontal">
            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='URL Notification' mod='placetopaypayment'}
                </label>
                <div class="col-lg-9">
                    <span style="font-size: 16px;">{$url_notification}</span>
                    <p class="help-block">
                        {l s='Return URL where PlacetoPay will send status payment\'s  to Prestashop' mod='placetopaypayment'}
                    </p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Path schudele task' mod='placetopaypayment'}
                </label>
                <div class="col-lg-9">
                    <span style="font-size: 16px;">{$schedule_task}</span>
                    <p class="help-block">
                        {l s='Set this task to validate payments with pending status in your site' mod='placetopaypayment'}
                    </p>
                </div>
            </div>
        </form>
    </div>
</div>
