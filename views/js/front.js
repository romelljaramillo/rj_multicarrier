/**
 * Multi Carrier - Frontend JavaScript
 *
 * @author    Romell Jaramillo
 * @copyright 2025 Romell Jaramillo
 * @license   MIT License
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const carrierList = document.getElementById('rj-multicarrier-list');
        
        if (!carrierList) {
            return;
        }

        // Handle carrier selection
        const carrierOptions = carrierList.querySelectorAll('.carrier-option');
        
        carrierOptions.forEach(function(option) {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                carrierOptions.forEach(function(opt) {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Get carrier ID
                const carrierId = this.getAttribute('data-carrier-id');
                
                // Trigger custom event for carrier selection
                const event = new CustomEvent('carrierSelected', {
                    detail: { carrierId: carrierId }
                });
                document.dispatchEvent(event);
            });
        });

        // Listen for carrier selected event
        document.addEventListener('carrierSelected', function(e) {
            console.log('Carrier selected:', e.detail.carrierId);
        });
    });
})();
