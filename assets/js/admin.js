jQuery(document).ready(function ($) {

    // Generate single report
    $(document).on('click', '.grg-generate-btn', function (e) {
        e.preventDefault();

        const btn = $(this);
        const orderId = btn.data('order-id');
        const uploadId = btn.data('upload-id');
        const productId = btn.data('product-id');
        const productName = btn.data('product-name');

        // Validate required data
        if (!orderId || !uploadId || !productName) {
            showNotice('Missing required data for report generation', 'error');
            return;
        }

        btn.prop('disabled', true).text('Generating...');
        showLoadingIndicator(btn);

        // Show progress
        const statusDiv = $('#grg-report-status');
        statusDiv.html('<div class="notice notice-info"><p>Generating report for ' + productName + '...</p></div>');

        // Log the request
        console.log('GRG: Starting report generation', {
            orderId: orderId,
            uploadId: uploadId,
            productId: productId,
            productName: productName
        });

        $.ajax({
            url: grg_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'grg_generate_report',
                order_id: orderId,
                upload_id: uploadId,
                product_id: productId,
                product_name: productName,
                nonce: grg_ajax.nonce
            },
            timeout: 300000, // 5 minutes timeout
            success: function (response) {
                console.log('GRG: Report generation response', response);

                if (response.success) {
                    statusDiv.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    // Reload page after 3 seconds to show updated status
                    setTimeout(function () {
                        location.reload();
                    }, 3000);
                } else {
                    statusDiv.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                    removeLoadingIndicator(btn);
                    btn.prop('disabled', false).text('Generate Report');
                }
            },
            error: function (xhr, status, error) {
                console.error('GRG: AJAX Error', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });

                let errorMsg = 'Request failed';
                if (status === 'timeout') {
                    errorMsg = 'Request timed out. The report may still be processing in the background.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Permission denied. Please refresh the page and try again.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Server error occurred. Please contact support if this persists.';
                } else if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        errorMsg = errorResponse.data || errorMsg;
                    } catch (e) {
                        errorMsg = 'Server error occurred';
                    }
                }

                statusDiv.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                removeLoadingIndicator(btn);
                btn.prop('disabled', false).text('Generate Report');
            }
        });
    });

    // Download report
    $(document).on('click', '.grg-download-btn', function (e) {
        e.preventDefault();

        const btn = $(this);
        const reportId = btn.data('report-id');

        if (!reportId) {
            showNotice('Missing report ID', 'error');
            return;
        }

        btn.prop('disabled', true).text('Downloading...');
        showLoadingIndicator(btn);

        console.log('GRG: Starting download for report ID:', reportId);

        $.ajax({
            url: grg_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'grg_download_report',
                report_id: reportId,
                nonce: grg_ajax.nonce
            },
            timeout: 60000, // 1 minute timeout for downloads
            success: function (response) {
                console.log('GRG: Download response received');

                if (response.success) {
                    // Create and trigger download
                    try {
                        const link = document.createElement('a');
                        link.href = 'data:application/pdf;base64,' + response.data.pdf_data;
                        link.download = response.data.filename || 'genetic_report.pdf';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        showNotice('Report downloaded successfully!', 'success');
                    } catch (e) {
                        console.error('GRG: Download creation failed', e);
                        showNotice('Failed to create download link', 'error');
                    }
                } else {
                    showNotice('Download failed: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('GRG: Download error', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });

                let errorMsg = 'Download request failed';
                if (status === 'timeout') {
                    errorMsg = 'Download timed out. Please try again.';
                } else if (xhr.status === 404) {
                    errorMsg = 'Report file not found. Please regenerate the report.';
                }

                showNotice(errorMsg, 'error');
            },
            complete: function () {
                removeLoadingIndicator(btn);
                btn.prop('disabled', false).text('Download Report');
            }
        });
    });

    // Generate all reports button
    $(document).on('click', '#grg-generate-all-reports', function (e) {
        e.preventDefault();

        const btn = $(this);
        const orderId = btn.data('order-id');
        const reportButtons = $('.grg-generate-btn:not(:disabled)');

        if (reportButtons.length === 0) {
            showNotice('No reports available to generate', 'warning');
            return;
        }

        if (!confirm('Are you sure you want to generate all missing reports? This may take several minutes.')) {
            return;
        }

        btn.prop('disabled', true).text('Generating All...');
        reportButtons.prop('disabled', true);

        const statusDiv = $('#grg-report-status');
        let completedCount = 0;
        let totalCount = reportButtons.length;

        statusDiv.html('<div class="notice notice-info"><p>Generating ' + totalCount + ' reports...</p></div>');

        console.log('GRG: Starting batch generation of', totalCount, 'reports');

        // Generate reports sequentially to avoid overwhelming the server
        generateReportsSequentially(reportButtons, 0, function (results) {
            const successful = results.filter(r => r.success).length;
            const failed = results.filter(r => !r.success).length;

            let message = 'Batch generation completed: ' + successful + ' successful';
            if (failed > 0) {
                message += ', ' + failed + ' failed';
            }

            statusDiv.html('<div class="notice notice-' + (failed > 0 ? 'warning' : 'success') + '"><p>' + message + '</p></div>');

            setTimeout(function () {
                location.reload();
            }, 3000);
        });
    });

    function generateReportsSequentially(buttons, index, callback) {
        const results = arguments[3] || [];

        if (index >= buttons.length) {
            callback(results);
            return;
        }

        const btn = $(buttons[index]);
        const statusDiv = $('#grg-report-status');
        const productName = btn.data('product-name') || 'Report ' + (index + 1);

        statusDiv.html('<div class="notice notice-info"><p>Generating report ' + (index + 1) + ' of ' + buttons.length + ': ' + productName + '</p></div>');

        console.log('GRG: Processing batch item', index + 1, 'of', buttons.length);

        $.ajax({
            url: grg_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'grg_generate_report',
                order_id: btn.data('order-id'),
                upload_id: btn.data('upload-id'),
                product_id: btn.data('product-id'),
                product_name: btn.data('product-name'),
                nonce: grg_ajax.nonce
            },
            timeout: 300000, // 5 minutes per report
            success: function (response) {
                results.push({
                    index: index + 1,
                    product_name: productName,
                    success: response.success
                });
                console.log('GRG: Batch item', index + 1, 'completed:', response.success);
            },
            error: function (xhr, status, error) {
                results.push({
                    index: index + 1,
                    product_name: productName,
                    success: false,
                    error: error
                });
                console.error('GRG: Batch item', index + 1, 'failed:', error);
            },
            complete: function () {
                // Wait 3 seconds before next report to avoid overwhelming the server
                setTimeout(function () {
                    generateReportsSequentially(buttons, index + 1, callback, results);
                }, 3000);
            }
        });
    }

    // Utility function to show notices
    function showNotice(message, type) {
        const noticeClass = 'notice notice-' + type;
        const notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');

        // Insert after the first h1 or at the top of .wrap
        const target = $('.wrap h1').first();
        if (target.length) {
            notice.insertAfter(target);
        } else {
            $('.wrap').prepend(notice);
        }

        // Make dismissible
        notice.on('click', '.notice-dismiss', function () {
            notice.fadeOut(function () {
                notice.remove();
            });
        });

        // Auto-remove success notices after 5 seconds
        if (type === 'success') {
            setTimeout(function () {
                notice.fadeOut(function () {
                    notice.remove();
                });
            }, 5000);
        }

        // Scroll to notice
        $('html, body').animate({
            scrollTop: notice.offset().top - 100
        }, 500);
    }

    // Add loading indicators
    function showLoadingIndicator(element) {
        if (!element.find('.spinner').length) {
            element.prepend('<span class="spinner is-active" style="float: left; margin-right: 5px;"></span>');
        }
    }

    function removeLoadingIndicator(element) {
        element.find('.spinner').remove();
    }

    // Enhanced error handling for all AJAX requests
    $(document).ajaxError(function (event, xhr, settings, thrownError) {
        // Only handle our plugin's AJAX requests
        if (settings.url === grg_ajax.ajax_url && settings.data && settings.data.indexOf('grg_') !== -1) {
            console.error('GRG Global AJAX Error:', {
                url: settings.url,
                data: settings.data,
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                thrownError: thrownError
            });
        }
    });

    // Auto-refresh for pending reports
    function checkPendingReports() {
        const pendingElements = $('.status-pending, .status-processing');

        if (pendingElements.length > 0) {
            console.log('GRG: Found', pendingElements.length, 'pending/processing reports, will refresh in 30 seconds');

            // Show a subtle indicator that auto-refresh is active
            if (!$('#auto-refresh-indicator').length) {
                $('body').append('<div id="auto-refresh-indicator" style="position: fixed; bottom: 20px; right: 20px; background: #0073aa; color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px; z-index: 9999;">Auto-refreshing for pending reports...</div>');
            }

            // Refresh after 30 seconds
            setTimeout(function () {
                console.log('GRG: Auto-refreshing page for pending reports');
                location.reload();
            }, 30000);
        }
    }

    // Initialize pending report checking
    checkPendingReports();

    // Form validation for settings
    $('form').on('submit', function (e) {
        const form = $(this);

        // Validate product IDs field
        const productIds = form.find('input[name="grg_reportable_products"]').val();
        if (productIds) {
            const ids = productIds.split(',');
            for (let i = 0; i < ids.length; i++) {
                const id = ids[i].trim();
                if (id && (isNaN(id) || parseInt(id) <= 0)) {
                    e.preventDefault();
                    showNotice('Invalid product ID: ' + id + '. Please enter valid numeric IDs separated by commas.', 'error');
                    return false;
                }
            }
        }

        // Validate subscription product ID
        const subscriptionId = form.find('input[name="grg_subscription_product"]').val();
        if (subscriptionId && (isNaN(subscriptionId) || parseInt(subscriptionId) <= 0)) {
            e.preventDefault();
            showNotice('Invalid subscription product ID. Please enter a valid numeric ID.', 'error');
            return false;
        }

        // Validate API URL
        const apiUrl = form.find('input[name="grg_api_url"]').val();
        if (apiUrl && !isValidUrl(apiUrl)) {
            e.preventDefault();
            showNotice('Invalid API URL. Please enter a valid URL starting with http:// or https://', 'error');
            return false;
        }
    });

    // URL validation helper
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    // Keyboard shortcuts
    $(document).on('keydown', function (e) {
        // Ctrl/Cmd + R to refresh (only on our admin pages)
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 82) {
            const currentPage = window.location.href;
            if (currentPage.indexOf('genetic-reports') !== -1) {
                e.preventDefault();
                console.log('GRG: Manual refresh triggered by keyboard shortcut');
                location.reload();
            }
        }

        // Escape key to dismiss notices
        if (e.keyCode === 27) {
            $('.notice.is-dismissible .notice-dismiss').click();
        }
    });

    // Enhanced table interactions
    $('.wp-list-table tbody tr').hover(
        function () {
            $(this).css('background-color', '#f9f9f9');
        },
        function () {
            $(this).css('background-color', '');
        }
    );

    // Add tooltips for status indicators
    $('.status-badge').each(function () {
        const status = $(this).text().toLowerCase().trim();
        let tooltip = '';

        switch (status) {
            case 'completed':
                tooltip = 'Report generated successfully and ready for download';
                break;
            case 'pending':
                tooltip = 'Report is queued for generation';
                break;
            case 'processing':
                tooltip = 'Report generation is currently in progress';
                break;
            case 'failed':
                tooltip = 'Report generation failed - click retry to try again';
                break;
        }

        if (tooltip) {
            $(this).attr('title', tooltip);
        }
    });

    // Report item interactions
    $('.grg-report-item').hover(
        function () {
            $(this).css('border-color', '#0073aa');
        },
        function () {
            $(this).css('border-color', '#ddd');
        }
    );

    // Progress tracking for batch operations
    function updateBatchProgress(current, total, operation) {
        const percentage = Math.round((current / total) * 100);
        const progressBar = $('#batch-progress');

        if (progressBar.length === 0) {
            // Create progress bar if it doesn't exist
            const progressHtml = `
                <div id="batch-progress" style="margin: 20px 0; padding: 15px; background: #f1f1f1; border-radius: 5px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span><strong>${operation}</strong></span>
                        <span id="progress-text">${current} of ${total} (${percentage}%)</span>
                    </div>
                    <div style="background: #e0e0e0; height: 20px; border-radius: 10px; overflow: hidden;">
                        <div id="progress-fill" style="background: #0073aa; height: 100%; width: ${percentage}%; transition: width 0.3s ease;"></div>
                    </div>
                </div>
            `;
            $('#grg-report-status').after(progressHtml);
        } else {
            // Update existing progress bar
            $('#progress-text').text(`${current} of ${total} (${percentage}%)`);
            $('#progress-fill').css('width', percentage + '%');
        }

        // Remove progress bar when complete
        if (current >= total) {
            setTimeout(function () {
                $('#batch-progress').fadeOut(function () {
                    $(this).remove();
                });
            }, 2000);
        }
    }

    // Local storage for user preferences (settings only, no sensitive data)
    function saveUserPreference(key, value) {
        try {
            if (typeof (Storage) !== "undefined") {
                localStorage.setItem('grg_' + key, JSON.stringify(value));
            }
        } catch (e) {
            console.log('GRG: Could not save user preference:', e);
        }
    }

    function getUserPreference(key, defaultValue) {
        try {
            if (typeof (Storage) !== "undefined") {
                const stored = localStorage.getItem('grg_' + key);
                return stored ? JSON.parse(stored) : defaultValue;
            }
        } catch (e) {
            console.log('GRG: Could not get user preference:', e);
        }
        return defaultValue;
    }

    // Remember filter settings
    $('select[name="status"], select[name="level"]').on('change', function () {
        const filterType = $(this).attr('name');
        const filterValue = $(this).val();
        saveUserPreference('filter_' + filterType, filterValue);
    });

    // Restore filter settings on page load
    $('select[name="status"], select[name="level"]').each(function () {
        const filterType = $(this).attr('name');
        const savedValue = getUserPreference('filter_' + filterType, '');
        if (savedValue && $(this).find('option[value="' + savedValue + '"]').length) {
            $(this).val(savedValue);
        }
    });

    // Advanced debugging console
    if (window.location.href.indexOf('debug=1') !== -1) {
        console.log('GRG: Debug mode enabled');

        // Add debug panel
        $('body').append(`
            <div id="grg-debug-panel" style="position: fixed; bottom: 0; right: 0; width: 300px; max-height: 200px; background: #000; color: #0f0; font-family: monospace; font-size: 11px; overflow-y: auto; z-index: 99999; padding: 10px;">
                <div style="color: #fff; margin-bottom: 5px;">GRG Debug Console</div>
                <div id="grg-debug-log"></div>
            </div>
        `);

        // Override console.log for our namespace
        const originalLog = console.log;
        console.log = function () {
            originalLog.apply(console, arguments);
            const args = Array.prototype.slice.call(arguments);
            const message = args.join(' ');
            if (message.indexOf('GRG:') === 0) {
                $('#grg-debug-log').append('<div>' + new Date().toLocaleTimeString() + ' - ' + message + '</div>');
                $('#grg-debug-panel').scrollTop($('#grg-debug-panel')[0].scrollHeight);
            }
        };
    }

    // Connection status monitoring
    let connectionLost = false;

    function checkConnection() {
        $.ajax({
            url: grg_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'heartbeat', // WordPress heartbeat
                nonce: grg_ajax.nonce
            },
            timeout: 10000,
            success: function () {
                if (connectionLost) {
                    showNotice('Connection restored', 'success');
                    connectionLost = false;
                }
            },
            error: function () {
                if (!connectionLost) {
                    showNotice('Connection lost. Please check your internet connection.', 'error');
                    connectionLost = true;
                }
            }
        });
    }

    // Check connection every 30 seconds
    setInterval(checkConnection, 30000);

    // Page visibility API to pause operations when tab is not active
    let pageVisible = true;

    document.addEventListener('visibilitychange', function () {
        pageVisible = !document.hidden;
        console.log('GRG: Page visibility changed:', pageVisible ? 'visible' : 'hidden');

        if (pageVisible) {
            // Resume operations when page becomes visible
            checkPendingReports();
        }
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function () {
        // Cancel any ongoing AJAX requests
        $.each($.ajax.pendingRequests || [], function (index, request) {
            if (request && request.readyState !== 4) {
                request.abort();
            }
        });
    });

    // Initialize everything
    console.log('GRG: Admin JavaScript initialized');

    // Log current page context
    console.log('GRG: Current page context:', {
        page: new URLSearchParams(window.location.search).get('page'),
        reportItems: $('.grg-report-item').length,
        pendingReports: $('.status-pending').length,
        processingReports: $('.status-processing').length
    });
});