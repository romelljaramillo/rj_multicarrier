/**
 * Multi Carrier - Backend JavaScript
 *
 * @author    Romell Jaramillo
 * @copyright 2025 Romell Jaramillo
 * @license   MIT License
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize backend functionality
        initCarrierManagement();
    });

    function initCarrierManagement() {
        const configForm = document.querySelector('form[name="rj_multicarrier_config"]');
        
        if (configForm) {
            configForm.addEventListener('submit', function(e) {
                // Validate form before submission
                const enabled = document.querySelector('input[name="RJ_MULTICARRIER_ENABLED"]:checked');
                
                if (!enabled) {
                    e.preventDefault();
                    alert('Please select whether to enable or disable the module.');
                    return false;
                }
            });
        }

        // Handle carrier list actions
        const carrierActions = document.querySelectorAll('.carrier-action');
        
        carrierActions.forEach(function(action) {
            action.addEventListener('click', function(e) {
                e.preventDefault();
                
                const actionType = this.getAttribute('data-action');
                const carrierId = this.getAttribute('data-carrier-id');
                
                handleCarrierAction(actionType, carrierId);
            });
        });
    }

    function handleCarrierAction(action, carrierId) {
        console.log('Carrier action:', action, 'for carrier:', carrierId);
        
        // Implement specific actions (edit, delete, etc.)
        switch (action) {
            case 'edit':
                // Handle edit action
                break;
            case 'delete':
                // Handle delete action
                if (confirm('Are you sure you want to delete this carrier configuration?')) {
                    // Perform delete
                }
                break;
            default:
                break;
        }
    }
})();
