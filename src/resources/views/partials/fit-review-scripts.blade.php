<script>
    (function (window, $) {
        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        function escapeAttr(value) {
            return escapeHtml(value).replace(/"/g, '&quot;');
        }

        function formatWhole(value) {
            value = parseInt(value || 0, 10);

            return value.toLocaleString();
        }

        function typeIconUrl(typeId, size) {
            typeId = parseInt(typeId || 0, 10);
            size = size || 32;

            return typeId > 0 ? 'https://images.evetech.net/types/' + typeId + '/icon?size=' + size : '';
        }

        function typeRenderUrl(typeId, size) {
            typeId = parseInt(typeId || 0, 10);
            size = size || 64;

            return typeId > 0 ? 'https://images.evetech.net/types/' + typeId + '/render?size=' + size : '';
        }

        function groupFitItems(items) {
            var groups = {};

            $.each(items || [], function (index, item) {
                var group = item.slot_group || 'Other';
                groups[group] = groups[group] || [];
                groups[group].push(item);
            });

            return groups;
        }

        function fitInfoButton(options, typeId, typeName) {
            if (typeof options.infoButton !== 'function') {
                return '';
            }

            return options.infoButton(options.marketId || null, typeId, typeName);
        }

        function renderFitPanel(fit, options) {
            options = options || {};

            var groups = groupFitItems(fit.items || []);
            var order = ['High Slots', 'Medium Slots', 'Low Slots', 'Rigs', 'Cargo', 'Drone Bay', 'Service Slots', 'Other'];
            var labels = {
                'High Slots': 'High power',
                'Medium Slots': 'Medium power',
                'Low Slots': 'Low power',
                'Rigs': 'Rig Slot',
                'Cargo': 'Charges',
                'Drone Bay': 'Drones',
                'Service Slots': 'Service Slot',
                'Other': 'Other'
            };
            var slotGroups = '';

            $.each(order, function (index, groupName) {
                var items = groups[groupName] || [];

                if (!items.length) {
                    return;
                }

                slotGroups += '<div class="market-seeding-fit-slot-group">' +
                    '<div class="market-seeding-fit-slot-group-title">' + escapeHtml(labels[groupName] || groupName) + '</div>' +
                    $.map(items, function (item) {
                        var iconUrl = typeIconUrl(item.type_id, 32);
                        var icon = iconUrl
                            ? '<img src="' + escapeAttr(iconUrl) + '" alt="' + escapeAttr((item.type_name || 'Item') + ' icon') + '" class="market-seeding-fit-item-icon">'
                            : '';
                        var infoButton = fitInfoButton(options, item.type_id, item.type_name);

                        return '<div class="market-seeding-fit-slot-row">' +
                            icon +
                            '<span class="market-seeding-fit-slot-name">' +
                                '<span class="text-muted">' + formatWhole(item.quantity) + 'x</span> ' + escapeHtml(item.type_name) +
                            '</span>' +
                            '<span class="market-seeding-fit-row-status">' + infoButton + '</span>' +
                        '</div>';
                    }).join('') +
                '</div>';
            });

            if (!slotGroups) {
                slotGroups = '<div class="text-muted">No fitting items found.</div>';
            }

            var shipIconUrl = typeRenderUrl(fit.ship_type_id, 64) || typeIconUrl(fit.ship_type_id, 64);
            var shipIcon = shipIconUrl
                ? '<img src="' + escapeAttr(shipIconUrl) + '" alt="' + escapeAttr((fit.ship_type_name || 'Ship') + ' image') + '" class="market-seeding-fit-ship-icon">'
                : '';
            var shipInfoButton = fitInfoButton(options, fit.ship_type_id, fit.ship_type_name);

            return '<div class="market-seeding-fit-panel">' +
                '<div class="market-seeding-fit-window-bar">' +
                    '<i class="fas fa-paper-plane"></i>' +
                    '<span>Ship Fitting: ' + escapeHtml(fit.ship_type_name || 'Unknown Ship') + '</span>' +
                '</div>' +
                '<div class="market-seeding-fit-header">' +
                    shipIcon +
                    '<div class="market-seeding-fit-ship">' +
                        '<div class="market-seeding-fit-name-label">Fitting Name</div>' +
                        '<div class="market-seeding-fit-name-box">' +
                            '<span>' + escapeHtml(fit.fitting_name || 'Unnamed Fit') + '</span>' +
                            '<span class="market-seeding-fit-name-actions">' + shipInfoButton + '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="market-seeding-fit-tabs"><span class="market-seeding-fit-tab-active">Fittings</span></div>' +
                '<div class="market-seeding-fit-slots">' + slotGroups + '</div>' +
            '</div>';
        }

        window.marketSeedingTypeIconUrl = typeIconUrl;
        window.marketSeedingTypeRenderUrl = typeRenderUrl;
        window.marketSeedingFitPanel = renderFitPanel;
    })(window, jQuery);
</script>
