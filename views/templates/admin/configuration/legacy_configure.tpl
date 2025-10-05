{*
 * Legacy fallback configuration page for RJ Multicarrier
 *}

<div class="bootstrap">
  <div class="row">
    <div class="col-lg-8">
      <div class="panel">
        <div class="panel-heading">
          <i class="icon icon-building"></i>
          {l s='Sender details' d='Modules.RjMulticarrier.Admin'}
        </div>
        <div class="panel-body">
          <form method="post" class="defaultForm form-horizontal">
            <input type="hidden" name="token" value="{$admin_token|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="id_infoshop" value="{$info_shop.id_infoshop|intval}">

            <div class="form-group">
              <label class="control-label col-lg-3" for="firstname">{l s='First name' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <input type="text" id="firstname" name="firstname" value="{$info_shop.firstname|escape:'htmlall':'UTF-8'}" class="form-control" required>
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="lastname">{l s='Last name' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <input type="text" id="lastname" name="lastname" value="{$info_shop.lastname|escape:'htmlall':'UTF-8'}" class="form-control" required>
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="company">{l s='Company' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <input type="text" id="company" name="company" value="{$info_shop.company|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="additionalname">{l s='Additional name' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <input type="text" id="additionalname" name="additionalname" value="{$info_shop.additionalname|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="email">{l s='Email' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <input type="email" id="email" name="email" value="{$info_shop.email|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="phone">{l s='Phone' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <input type="text" id="phone" name="phone" value="{$info_shop.phone|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="vatnumber">{l s='VAT number' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <input type="text" id="vatnumber" name="vatnumber" value="{$info_shop.vatnumber|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="isbusiness">{l s='Business shipper' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <span class="switch prestashop-switch fixed-width-lg">
                  <input type="checkbox" name="isbusiness" id="isbusiness" value="1" {if $info_shop.isbusiness}checked="checked"{/if}>
                  <span class="slide-button"></span>
                </span>
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="street">{l s='Street' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <input type="text" id="street" name="street" value="{$info_shop.street|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="number">{l s='Number' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-3">
                <input type="text" id="number" name="number" value="{$info_shop.number|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
              <label class="control-label col-lg-3" for="postcode">{l s='Postcode' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-3">
                <input type="text" id="postcode" name="postcode" value="{$info_shop.postcode|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="city">{l s='City' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-4">
                <input type="text" id="city" name="city" value="{$info_shop.city|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
              <label class="control-label col-lg-2" for="state">{l s='State/Region' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-3">
                <input type="text" id="state" name="state" value="{$info_shop.state|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="id_country">{l s='Country' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <select name="id_country" id="id_country" class="form-control">
                  <option value="0">{l s='Select a country' d='Modules.RjMulticarrier.Admin'}</option>
                  {foreach from=$countries item=country}
                    <option value="{$country.id|intval}" {if $country.id == $info_shop.id_country}selected="selected"{/if}>{$country.name|escape:'htmlall':'UTF-8'}</option>
                  {/foreach}
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-3" for="additionaladdress">{l s='Additional address' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-9">
                <textarea id="additionaladdress" name="additionaladdress" class="form-control" rows="2">{$info_shop.additionaladdress|escape:'htmlall':'UTF-8'}</textarea>
              </div>
            </div>

            <div class="panel-footer">
              <button type="submit" name="submit_info_shop_legacy" value="1" class="btn btn-primary pull-right">
                <i class="icon icon-save"></i> {l s='Save sender' d='Modules.RjMulticarrier.Admin'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="panel">
        <div class="panel-heading">
          <i class="icon icon-cogs"></i>
          {l s='Extra configuration' d='Modules.RjMulticarrier.Admin'}
        </div>
        <div class="panel-body">
          <form method="post" class="defaultForm form-horizontal">
            <input type="hidden" name="token" value="{$admin_token|escape:'htmlall':'UTF-8'}">

            <div class="form-group">
              <label class="control-label col-lg-4" for="RJ_ETIQUETA_TRANSP_PREFIX">{l s='Label prefix' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-8">
                <input type="text" id="RJ_ETIQUETA_TRANSP_PREFIX" name="RJ_ETIQUETA_TRANSP_PREFIX" value="{$extra_config.RJ_ETIQUETA_TRANSP_PREFIX|escape:'htmlall':'UTF-8'}" class="form-control">
              </div>
            </div>

            <div class="form-group">
              <label class="control-label col-lg-4" for="RJ_MODULE_CONTRAREEMBOLSO">{l s='Cash on delivery module' d='Modules.RjMulticarrier.Admin'}</label>
              <div class="col-lg-8">
                <select name="RJ_MODULE_CONTRAREEMBOLSO" id="RJ_MODULE_CONTRAREEMBOLSO" class="form-control">
                  <option value="">{l s='Select a module' d='Modules.RjMulticarrier.Admin'}</option>
                  {foreach from=$module_choices item=choice}
                    <option value="{$choice.value|escape:'htmlall':'UTF-8'}" {if $choice.value == $extra_config.RJ_MODULE_CONTRAREEMBOLSO}selected="selected"{/if}>{$choice.label|escape:'htmlall':'UTF-8'}</option>
                  {/foreach}
                </select>
              </div>
            </div>

            <div class="panel-footer">
              <button type="submit" name="submit_extra_config_legacy" value="1" class="btn btn-default pull-right">
                <i class="icon icon-save"></i> {l s='Save extra configuration' d='Modules.RjMulticarrier.Admin'}
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="alert alert-info">
        <p>
          <strong>{l s='Looking for the modern interface?' d='Modules.RjMulticarrier.Admin'}</strong><br>
          {l s='If Symfony routing is available you will be redirected automatically. Otherwise you can continue trabajando desde esta vista clásica.' d='Modules.RjMulticarrier.Admin'}
        </p>
      </div>
    </div>
  </div>
</div>
