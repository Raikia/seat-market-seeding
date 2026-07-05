            var marketSeedingItemDetailTrendChart = null;

            function resetItemDetails() {
                $('#market-seeding-detail-current').text('Loading...');
                $('#market-seeding-detail-missing').text('Loading...');
                $('#market-seeding-detail-hero-missing').text('Loading...');
                $('#market-seeding-detail-local-price').text('Loading...');
                $('#market-seeding-detail-price-delta').text('vs Jita');
                $('#market-seeding-detail-jita-price').text('Loading...');
                $('#market-seeding-detail-seeded-value').text('Loading...');
                $('#market-seeding-detail-target-value').text('Loading...');
                $('#market-seeding-detail-restock-value').text('Loading...');
                $('#market-seeding-detail-restock-volume').text('Loading...');
                $('#market-seeding-detail-item-volume').text('Packaged m3');
                $('#market-seeding-detail-source-badges').empty();
                $('#market-seeding-detail-source-list').html('<div class="text-muted">Loading source details...</div>');
                $('#market-seeding-detail-trend-summary').text('Loading...');
                $('#market-seeding-edit-target-history').html('<tr><td colspan="5" class="text-muted">Loading transition history...</td></tr>');
                $('#market-seeding-edit-target-change-history').html('<tr><td colspan="5" class="text-muted">Loading target changes...</td></tr>');
                $('#market-seeding-edit-target-icon').addClass('d-none').attr('src', '').attr('alt', '');
                $('.edit-target-delta').text('').removeClass('is-positive is-negative');

                if (marketSeedingItemDetailTrendChart) {
                    marketSeedingItemDetailTrendChart.destroy();
                    marketSeedingItemDetailTrendChart = null;
                }
            }

            function loadItemDetails(url) {
                if (!url) {
                    $('#market-seeding-edit-target-error').removeClass('d-none').text('No item detail URL was provided.');
                    return;
                }

                $.getJSON(url)
                    .done(function (response) {
                        renderItemHeader(response.item || {});
                        renderItemDetails(response.details || {});
                        renderSourceDetails(response.source_details || {});
                        renderTrend(response.trend || {});
                        renderTransitionRows(response.events || []);
                        renderTargetChangeRows(response.target_history || []);
                    })
                    .fail(function () {
                        $('#market-seeding-edit-target-error').removeClass('d-none').text('Unable to load item details.');
                    });
            }

            function renderItemHeader(item) {
                var iconUrl = marketSeedingItemDetailTypeIconUrl(item.type_id, 64);

                $('#market-seeding-edit-target-item').text(item.type_name || $('#market-seeding-edit-target-item').text());
                $('#market-seeding-edit-target-market').text(
                    item.market_name ? item.market_name + ' - ' + (item.location_name || '') : $('#market-seeding-edit-target-market').text()
                );

                if (!iconUrl) {
                    return;
                }

                $('#market-seeding-edit-target-icon')
                    .removeClass('d-none')
                    .attr('src', iconUrl)
                    .attr('alt', (item.type_name || 'Item') + ' icon');
            }

            function renderItemDetails(details) {
                var current = parseInt(details.current_quantity || 0, 10);
                var desired = parseInt(details.desired_quantity || 0, 10);
                var missing = Math.max(0, desired - current);

                $('#market-seeding-detail-current').text(marketSeedingItemDetailWhole(current));
                $('#market-seeding-detail-missing').text(marketSeedingItemDetailWhole(missing));
                $('#market-seeding-detail-hero-missing').text(marketSeedingItemDetailWhole(missing));
                $('#market-seeding-detail-local-price').text(marketSeedingItemDetailMoney(details.local_price || details.jita_price));
                $('#market-seeding-detail-jita-price').text(marketSeedingItemDetailMoney(details.jita_price));
                $('#market-seeding-detail-seeded-value').text(marketSeedingItemDetailMoney(details.seeded_value));
                $('#market-seeding-detail-target-value').text(marketSeedingItemDetailMoney(details.desired_value));
                $('#market-seeding-detail-restock-value').text(marketSeedingItemDetailMoney(details.restock_cost));
                $('#market-seeding-detail-restock-volume').text(marketSeedingItemDetailDecimal(details.restock_volume, 2) + ' m3');
                $('#market-seeding-detail-item-volume').text(marketSeedingItemDetailDecimal(details.item_volume, 2) + ' m3 each, packaged');

                if (details.price_delta === null || typeof details.price_delta === 'undefined') {
                    $('#market-seeding-detail-price-delta').text(details.jita_price ? 'No local market price' : 'No Jita comparison');
                } else {
                    var delta = parseFloat(details.price_delta);
                    $('#market-seeding-detail-price-delta').text((delta > 0 ? '+' : '') + marketSeedingItemDetailDecimal(delta, 1) + '% vs Jita');
                }
            }

            function renderSourceDetails(sourceDetails) {
                var flags = sourceDetails.flags || {};
                var manualSources = sourceDetails.manual || [];
                var doctrines = sourceDetails.doctrines || [];
                var $badges = $('#market-seeding-detail-source-badges').empty();
                var $list = $('#market-seeding-detail-source-list').empty();

                if (flags.manual) {
                    $badges.append('<span class="badge badge-primary">Manual</span>');
                }

                if (flags.doctrine) {
                    $badges.append('<span class="badge badge-info">Doctrine</span>');
                }

                if (!flags.manual && !flags.doctrine) {
                    $badges.append('<span class="badge badge-secondary">Unknown</span>');
                    $list.html('<div class="text-muted">No source records were found for this item.</div>');
                    return;
                }

                $.each(manualSources, function (index, source) {
                    $list.append(
                        '<div class="edit-target-source-card">' +
                            '<div class="edit-target-source-name">' + marketSeedingItemDetailEscape(source.label || 'Manual add') + '</div>' +
                            '<div class="edit-target-source-meta">Target contribution ' + marketSeedingItemDetailWhole(source.quantity) +
                                ', warning ' + marketSeedingItemDetailWhole(source.warning_quantity || 0) + '</div>' +
                        '</div>'
                    );
                });

                $.each(doctrines, function (index, doctrine) {
                    var fitHtml = '';

                    $.each(doctrine.fits || [], function (fitIndex, fit) {
                        var shipIconUrl = marketSeedingItemDetailTypeRenderUrl(fit.ship_type_id, 64) || marketSeedingItemDetailTypeIconUrl(fit.ship_type_id, 64);
                        var shipIcon = shipIconUrl
                            ? '<img src="' + marketSeedingItemDetailEscapeAttr(shipIconUrl) + '" alt="' + marketSeedingItemDetailEscapeAttr((fit.ship_type_name || 'Ship') + ' image') + '" class="edit-target-ship-icon">'
                            : '';
                        var contributions = (fit.contributions || []).map(function (contribution) {
                            return '<span class="edit-target-source-contribution">' +
                                marketSeedingItemDetailEscape(contribution.kind || 'Item') + ': ' + marketSeedingItemDetailWhole(contribution.quantity) +
                            '</span>';
                        }).join('');

                        fitHtml +=
                            '<div class="edit-target-source-fit">' +
                                shipIcon +
                                '<div class="edit-target-source-fit-body">' +
                                    '<div class="edit-target-source-fit-name">' + marketSeedingItemDetailEscape(fit.ship_type_name || 'Unknown Ship') + '</div>' +
                                    '<div class="edit-target-source-fit-meta">' + marketSeedingItemDetailEscape(fit.fitting_name || 'Unnamed Fit') +
                                        ' &middot; ship x' + marketSeedingItemDetailWhole(fit.ship_multiplier || 0) +
                                        ' &middot; fit x' + marketSeedingItemDetailWhole(fit.fitting_multiplier || 0) + '</div>' +
                                    '<div class="edit-target-source-fit-meta mt-1">' + contributions + '</div>' +
                                '</div>' +
                            '</div>';
                    });

                    if (!fitHtml) {
                        fitHtml = '<div class="edit-target-source-fit-meta mt-1">No matching fit breakdown could be loaded.</div>';
                    }

                    $list.append(
                        '<div class="edit-target-source-card">' +
                            '<div class="d-flex justify-content-between align-items-start">' +
                                '<div>' +
                                    '<div class="edit-target-source-name">' + marketSeedingItemDetailEscape(doctrine.name || 'Tracked doctrine') + '</div>' +
                                    '<div class="edit-target-source-meta">Doctrine contribution ' + marketSeedingItemDetailWhole(doctrine.quantity) +
                                        ', warning ' + marketSeedingItemDetailWhole(doctrine.warning_quantity || 0) +
                                        ' &middot; merge ' + marketSeedingItemDetailEscape(doctrine.merge_mode || '-') +
                                        ' &middot; fits ' + marketSeedingItemDetailEscape(doctrine.fit_aggregation_mode || '-') + '</div>' +
                                '</div>' +
                                '<span class="badge badge-info">Doctrine</span>' +
                            '</div>' +
                            fitHtml +
                        '</div>'
                    );
                });
            }

            function renderTrend(trend) {
                var labels = trend.labels || [];
                var values = trend.values || [];

                $('#market-seeding-detail-trend-summary').text(
                    marketSeedingItemDetailWhole(trend.total || 0) + ' estimated sold over ' + marketSeedingItemDetailWhole(trend.days || labels.length || 0) + ' days'
                );

                if (marketSeedingItemDetailTrendChart) {
                    marketSeedingItemDetailTrendChart.destroy();
                    marketSeedingItemDetailTrendChart = null;
                }

                if (!window.Chart || !document.getElementById('market-seeding-detail-trend-chart')) {
                    return;
                }

                marketSeedingItemDetailTrendChart = new Chart(document.getElementById('market-seeding-detail-trend-chart'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Estimated Sold',
                            data: values,
                            backgroundColor: 'rgba(23, 162, 184, .18)',
                            borderColor: 'rgba(23, 162, 184, .95)',
                            borderWidth: 2,
                            fill: true,
                            tension: .28
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });
            }

            function renderTransitionRows(events) {
                var $body = $('#market-seeding-edit-target-history').empty();

                if (!events.length) {
                    $body.html('<tr><td colspan="5" class="text-muted">No stock transitions found for this item.</td></tr>');
                    return;
                }

                $.each(events, function (index, event) {
                    $body.append(
                        '<tr>' +
                            '<td>' + marketSeedingItemDetailEscape(event.created_at || '-') + '</td>' +
                            '<td>' + marketSeedingItemDetailStatusHtml(event.previous_status, event.current_status) + '</td>' +
                            '<td class="text-right">' + marketSeedingItemDetailWhole(event.current_quantity) + '</td>' +
                            '<td class="text-right">' + marketSeedingItemDetailWhole(event.warning_quantity) + '</td>' +
                            '<td class="text-right">' + marketSeedingItemDetailWhole(event.desired_quantity) + '</td>' +
                        '</tr>'
                    );
                });
            }

            function renderTargetChangeRows(rows) {
                var $body = $('#market-seeding-edit-target-change-history').empty();

                if (!rows.length) {
                    $body.html('<tr><td colspan="5" class="text-muted">No target changes found for this item.</td></tr>');
                    return;
                }

                $.each(rows, function (index, row) {
                    $body.append(
                        '<tr>' +
                            '<td>' + marketSeedingItemDetailEscape(row.created_at || '-') + '</td>' +
                            '<td>' + marketSeedingItemDetailEscape(row.change_type_label || row.change_type || '-') + '</td>' +
                            '<td>' + marketSeedingItemDetailEscape(row.user_name || 'System') + '</td>' +
                            '<td class="text-right">' + marketSeedingItemDetailWhole(row.old_target_quantity) + ' &rarr; ' + marketSeedingItemDetailWhole(row.new_target_quantity) + '</td>' +
                            '<td class="text-right">' + marketSeedingItemDetailWhole(row.old_warning_quantity) + ' &rarr; ' + marketSeedingItemDetailWhole(row.new_warning_quantity) + '</td>' +
                        '</tr>'
                    );
                });
            }

            function marketSeedingItemDetailStatusHtml(previousStatus, currentStatus) {
                var badgeClass = {
                    stocked: 'badge-success',
                    low: 'badge-warning',
                    empty: 'badge-danger'
                }[currentStatus] || 'badge-secondary';

                return '<span class="badge ' + badgeClass + '">' + marketSeedingItemDetailEscape(marketSeedingItemDetailCapitalize(currentStatus || 'unknown')) + '</span>' +
                    (previousStatus ? ' <span class="text-muted small">' + marketSeedingItemDetailEscape(previousStatus) + ' &rarr; ' + marketSeedingItemDetailEscape(currentStatus) + '</span>' : '');
            }

            function marketSeedingItemDetailCapitalize(value) {
                value = String(value || '');

                return value.charAt(0).toUpperCase() + value.slice(1);
            }

            function marketSeedingItemDetailTypeIconUrl(typeId, size) {
                typeId = parseInt(typeId || 0, 10);
                size = size || 64;

                return typeId > 0 ? 'https://images.evetech.net/types/' + typeId + '/icon?size=' + size : '';
            }

            function marketSeedingItemDetailTypeRenderUrl(typeId, size) {
                typeId = parseInt(typeId || 0, 10);
                size = size || 64;

                return typeId > 0 ? 'https://images.evetech.net/types/' + typeId + '/render?size=' + size : '';
            }

            function marketSeedingItemDetailDecimal(value, decimals) {
                value = parseFloat(value);

                if (!isFinite(value)) {
                    return '-';
                }

                return value.toLocaleString('en-US', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                });
            }

            function marketSeedingItemDetailMoney(value) {
                value = parseFloat(value);

                if (!isFinite(value) || value <= 0) {
                    return '-';
                }

                return value.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' ISK';
            }

            function marketSeedingItemDetailWhole(value) {
                value = parseInt(value || 0, 10);

                return value.toLocaleString('en-US', {
                    maximumFractionDigits: 0
                });
            }

            function marketSeedingItemDetailEscape(value) {
                return $('<div>').text(value || '').html();
            }

            function marketSeedingItemDetailEscapeAttr(value) {
                return marketSeedingItemDetailEscape(value).replace(/"/g, '&quot;');
            }
