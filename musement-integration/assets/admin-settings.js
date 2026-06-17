jQuery(document).ready(function($) {
    // === TABS NAVIGATION ===
    const activeTabKey = 'musement_active_settings_tab';
    
    // Read active tab from localStorage or default to connectivity
    let activeTab = localStorage.getItem(activeTabKey) || 'connectivity';
    
    // Switch to active tab on load
    switchTab(activeTab);
    
    $('.musement-tab-btn').on('click', function(e) {
        e.preventDefault();
        const tabName = $(this).data('tab');
        switchTab(tabName);
        localStorage.setItem(activeTabKey, tabName);
    });
    
    function switchTab(tabName) {
        // Update tabs buttons
        $('.musement-tab-btn').removeClass('is-active');
        $(`.musement-tab-btn[data-tab="${tabName}"]`).addClass('is-active');
        
        // Update tabs panes
        $('.musement-tab-pane').removeClass('is-active');
        $(`#tab-${tabName}`).addClass('is-active');
    }
    
    // === SEARCH FILTERING FOR TOURS ===
    $('#musement-tour-search').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        let matchCount = 0;
        
        $('.musement-tour-row').each(function() {
            const row = $(this);
            const tourTitle = row.find('strong').text().toLowerCase();
            const tourUuid = row.find('small').text().toLowerCase();
            
            if (tourTitle.includes(query) || tourUuid.includes(query)) {
                row.show();
                matchCount++;
            } else {
                row.hide();
            }
        });
        
        // Show/hide empty state message
        let noResultsRow = $('#musement-no-tours-found');
        if (matchCount === 0) {
            if (noResultsRow.length === 0) {
                $('.musement-tours-table tbody').append(`
                    <tr id="musement-no-tours-found">
                        <td colspan="4" style="text-align: center; color: #64748b; padding: 30px !important;">
                            <span class="dashicons dashicons-search" style="font-size: 24px; width: 24px; height: 24px; display: inline-block; margin-bottom: 8px;"></span>
                            <p style="margin: 0; font-size: 14px;">No se encontraron actividades que coincidan con la búsqueda.</p>
                        </td>
                    </tr>
                `);
            } else {
                noResultsRow.show();
            }
        } else {
            if (noResultsRow.length > 0) {
                noResultsRow.hide();
            }
        }
    });
    
    // === REAL-TIME PRICE CALCULATION AND MARGIN INPUT FORMATTING ===
    $('.musement-margin-input').each(function() {
        const input = $(this);
        
        // Format initial value (replace dot with comma)
        if (input.value && input.value.includes('.')) {
            input.value = input.value.replace(/\./g, ',');
        }
        
        input.on('input', function() {
            let value = $(this).val();
            
            // Normalize characters (replace dot with comma)
            value = value.replace(/\./g, ',');
            // Only allow numbers, comma, and minus sign
            value = value.replace(/[^0-9,-]/g, '');
            
            // Ensure only one comma
            const parts = value.split(',');
            if (parts.length > 2) {
                value = parts[0] + ',' + parts.slice(1).join('');
            }
            
            $(this).val(value);
            
            // Perform live price calculation
            calculateLivePrice($(this));
        });
        
        input.on('blur', function() {
            let value = $(this).val();
            if (value === '' || value === '-') {
                $(this).val('');
            } else if (value.endsWith(',')) {
                $(this).val(value.slice(0, -1));
            }
            calculateLivePrice($(this));
        });
    });
    
    function calculateLivePrice(inputElement) {
        const row = inputElement.closest('tr');
        const originalPrice = parseFloat(inputElement.data('original-price')) || 0;
        
        // Parse margin (replace comma with dot for JS calculation)
        const marginVal = inputElement.val().trim().replace(',', '.');
        const margin = parseFloat(marginVal);
        
        const priceLabel = row.find('.musement-final-price');
        
        if (isNaN(margin) || marginVal === '' || originalPrice <= 0) {
            priceLabel.text('-').css('color', '#94a3b8').parent().find('strong').css('color', '#94a3b8');
            return;
        }
        
        // Calculate final price: original + (original * margin / 100)
        const finalPrice = originalPrice * (1 + (margin / 100));
        
        if (finalPrice > 0) {
            // Format number to Spanish format (decimals with comma)
            const formatted = finalPrice.toFixed(2).replace('.', ',') + ' €';
            priceLabel.text(formatted).css('color', '#10b981').css('font-weight', '700');
        } else {
            priceLabel.text('-').css('color', '#94a3b8');
        }
    }
});
