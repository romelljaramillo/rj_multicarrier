{*
* Multi Carrier - Admin Tab Content
*
* @author    Romell Jaramillo
* @copyright 2025 Romell Jaramillo
* @license   MIT License
*}

<div class="tab-pane fade" id="rj_multicarrier_content" role="tabpanel" aria-labelledby="rj-multicarrier-tab">
    <div class="card">
        <div class="card-header">
            <h3 class="card-header-title">
                {l s='Carrier Information' mod='rj_multicarrier'}
            </h3>
        </div>
        <div class="card-body">
            {if isset($carriers) && !empty($carriers)}
                <table class="table">
                    <thead>
                        <tr>
                            <th>{l s='ID' mod='rj_multicarrier'}</th>
                            <th>{l s='Name' mod='rj_multicarrier'}</th>
                            <th>{l s='Delay' mod='rj_multicarrier'}</th>
                            <th>{l s='Active' mod='rj_multicarrier'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$carriers item=carrier}
                            <tr>
                                <td>{$carrier.id_carrier|intval}</td>
                                <td>{$carrier.name|escape:'htmlall':'UTF-8'}</td>
                                <td>{$carrier.delay|escape:'htmlall':'UTF-8'}</td>
                                <td>
                                    {if $carrier.active}
                                        <span class="badge badge-success">{l s='Yes' mod='rj_multicarrier'}</span>
                                    {else}
                                        <span class="badge badge-danger">{l s='No' mod='rj_multicarrier'}</span>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            {else}
                <div class="alert alert-warning">
                    {l s='No carriers available for this order.' mod='rj_multicarrier'}
                </div>
            {/if}
        </div>
    </div>
</div>
