{*
* Multi Carrier - Carrier List Template
*
* @author    Romell Jaramillo
* @copyright 2025 Romell Jaramillo
* @license   MIT License
*}

<div id="rj-multicarrier-list" class="rj-multicarrier">
    {if isset($carriers) && !empty($carriers)}
        <div class="carriers-list">
            <h3>{l s='Available Carriers' mod='rj_multicarrier'}</h3>
            <ul class="carriers-options">
                {foreach from=$carriers item=carrier}
                    <li class="carrier-option" data-carrier-id="{$carrier.id_carrier|intval}">
                        <div class="carrier-info">
                            {if isset($carrier.logo) && $carrier.logo}
                                <img src="{$carrier.logo|escape:'htmlall':'UTF-8'}" alt="{$carrier.name|escape:'htmlall':'UTF-8'}" class="carrier-logo">
                            {/if}
                            <span class="carrier-name">{$carrier.name|escape:'htmlall':'UTF-8'}</span>
                            {if isset($carrier.delay) && $carrier.delay}
                                <span class="carrier-delay">{$carrier.delay|escape:'htmlall':'UTF-8'}</span>
                            {/if}
                        </div>
                        {if isset($carrier.price) && $carrier.price}
                            <div class="carrier-price">
                                <span class="price">{$carrier.price|escape:'htmlall':'UTF-8'}</span>
                            </div>
                        {/if}
                    </li>
                {/foreach}
            </ul>
        </div>
    {else}
        <p class="warning">{l s='No carriers available.' mod='rj_multicarrier'}</p>
    {/if}
</div>
