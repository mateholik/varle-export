jQuery(document).ready(function ($) {
  // Enhanced XML generation with better feedback
  $('#generate-xml').on('click', function (e) {
    e.preventDefault();
    generateXML();
  });

  // Download XML button
  $('#download-xml').on('click', function (e) {
    e.preventDefault();
    downloadXML();
  });

  // Test accessibility button
  $('#test-accessibility').on('click', function (e) {
    e.preventDefault();
    testFileAccessibility();
  });

  /**
   * Generate XML file with enhanced progress tracking
   */
  function generateXML() {
    var $button = $('#generate-xml');
    var $status = $('#generation-status');

    // Show loading state
    $button.addClass('varle-loading').prop('disabled', true);
    $button.html(
      '<span class="varle-spinner"></span>' + varle_ajax.generating_text
    );

    $status.removeClass('success error').addClass('loading').show();
    $status.html(
      '<div class="progress-container"><div class="progress-bar" style="width: 20%;"></div></div><p><span class="varle-spinner"></span>' +
        varle_ajax.generating_text +
        '</p>'
    );

    // Simulate progress updates
    var progress = 20;
    var progressInterval = setInterval(function () {
      progress += Math.random() * 15;
      if (progress > 90) progress = 90;
      $status.find('.progress-bar').css('width', progress + '%');
    }, 500);

    // Make AJAX request
    $.ajax({
      url: varle_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'varle_generate_xml',
        nonce: varle_ajax.nonce,
      },
      timeout: 120000, // 2 minutes timeout
      success: function (response) {
        clearInterval(progressInterval);
        $status.find('.progress-bar').css('width', '100%');

        setTimeout(function () {
          if (response.success) {
            showSuccess(response.data.message, response.data.file_url);
            updatePageInfo(response.data);
          } else {
            showError(response.data || varle_ajax.error_text);
          }
        }, 500);
      },
      error: function (xhr, status, error) {
        clearInterval(progressInterval);

        if (status === 'timeout') {
          showError(
            'Generation timed out. This may happen with large product catalogs. Please try again.'
          );
        } else {
          showError(varle_ajax.error_text + ': ' + error);
        }
      },
      complete: function () {
        // Reset button state
        $button.removeClass('varle-loading').prop('disabled', false);
        $button.html(varle_ajax.button_text || 'Generate XML File');
      },
    });
  }

  /**
   * Download XML file
   */
  function downloadXML() {
    var form = $('<form>', {
      method: 'POST',
      action: varle_ajax.ajax_url,
      target: '_blank',
    });

    form.append(
      $('<input>', {
        type: 'hidden',
        name: 'action',
        value: 'varle_export_xml',
      })
    );

    form.append(
      $('<input>', {
        type: 'hidden',
        name: 'nonce',
        value: varle_ajax.nonce,
      })
    );

    $('body').append(form);
    form.submit();
    form.remove();

    showNotification('Download started...', 'info');
  }

  /**
   * Test file accessibility
   */
  function testFileAccessibility() {
    var $button = $('#test-accessibility');
    var originalText = $button.text();

    $button.prop('disabled', true).text('Testing...');

    $.ajax({
      url: varle_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'varle_test_accessibility',
        nonce: varle_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          showNotification('✓ File is accessible!', 'success');
        } else {
          showNotification('✗ File not accessible: ' + response.data, 'error');
        }
      },
      error: function () {
        showNotification('Failed to test accessibility', 'error');
      },
      complete: function () {
        $button.prop('disabled', false).text(originalText);
      },
    });
  }

  /**
   * Show success message with enhanced UI
   */
  function showSuccess(message, fileUrl) {
    var $status = $('#generation-status');

    $status.removeClass('loading error').addClass('success');

    var html = '<div class="success-content">';
    html += '<h4>✓ ' + message + '</h4>';

    if (fileUrl) {
      html += '<p><strong>File URL:</strong> <code>' + fileUrl + '</code></p>';
      html += '<p>';
      html +=
        '<a href="' +
        fileUrl +
        '" target="_blank" class="button">View XML</a> ';
      html +=
        '<button type="button" class="button copy-url-btn" data-url="' +
        fileUrl +
        '">Copy URL</button>';
      html += '</p>';
    }

    html += '</div>';
    $status.html(html);

    // Update button state
    var $button = $('#generate-xml');
    $button.removeClass('varle-loading').addClass('varle-success');
    $button.html('✓ Generated Successfully');

    setTimeout(function () {
      $button.removeClass('varle-success').html('Generate XML File');
    }, 3000);

    // Show notification
    showNotification('XML file generated successfully!', 'success');

    // Update last generated time
    updateLastGeneratedTime();
  }

  /**
   * Show error message with helpful suggestions
   */
  function showError(message) {
    var $status = $('#generation-status');

    $status.removeClass('loading success').addClass('error');

    var html = '<div class="error-content">';
    html += '<h4>✗ Generation Failed</h4>';
    html += '<p><strong>Error:</strong> ' + message + '</p>';

    // Add helpful suggestions based on error type
    if (message.includes('permission') || message.includes('Permission')) {
      html += '<div class="error-suggestions">';
      html += '<h5>Possible Solutions:</h5>';
      html += '<ul>';
      html += '<li>Contact your hosting provider about file permissions</li>';
      html +=
        '<li>Try generating again - the plugin will attempt alternative storage methods</li>';
      html += '<li>Check if your uploads directory is writable</li>';
      html += '</ul>';
      html += '</div>';
    } else if (message.includes('timeout') || message.includes('memory')) {
      html += '<div class="error-suggestions">';
      html += '<h5>Possible Solutions:</h5>';
      html += '<ul>';
      html +=
        '<li>You may have too many products - try reducing the catalog size</li>';
      html +=
        '<li>Contact your hosting provider about increasing PHP limits</li>';
      html += '<li>Try again during off-peak hours</li>';
      html += '</ul>';
      html += '</div>';
    }

    html += '</div>';
    $status.html(html);

    // Update button state
    var $button = $('#generate-xml');
    $button.removeClass('varle-loading').addClass('varle-error');
    $button.html('✗ Generation Failed');

    setTimeout(function () {
      $button.removeClass('varle-error').html('Generate XML File');
    }, 4000);

    // Show notification
    showNotification('XML generation failed: ' + message, 'error');
  }

  /**
   * Update page information after successful generation
   */
  function updatePageInfo(data) {
    // Refresh the page after a short delay to show updated info
    setTimeout(function () {
      window.location.reload();
    }, 2000);
  }

  /**
   * Update last generated time display
   */
  function updateLastGeneratedTime() {
    var now = new Date();
    var timeString =
      now.getFullYear() +
      '-' +
      String(now.getMonth() + 1).padStart(2, '0') +
      '-' +
      String(now.getDate()).padStart(2, '0') +
      ' ' +
      String(now.getHours()).padStart(2, '0') +
      ':' +
      String(now.getMinutes()).padStart(2, '0') +
      ':' +
      String(now.getSeconds()).padStart(2, '0');

    var $lastGenerated = $('p:contains("Last Generated:")');
    if ($lastGenerated.length) {
      $lastGenerated.html('<strong>Last Generated:</strong> ' + timeString);
    }
  }

  /**
   * Show notification popup
   */
  function showNotification(message, type) {
    type = type || 'info';

    var $notification = $(
      '<div class="varle-notification ' + type + '">' + message + '</div>'
    );
    $('body').append($notification);

    // Trigger animation
    setTimeout(function () {
      $notification.addClass('show');
    }, 100);

    // Auto-hide after 4 seconds
    setTimeout(function () {
      $notification.removeClass('show');
      setTimeout(function () {
        $notification.remove();
      }, 300);
    }, 4000);
  }

  /**
   * Enhanced copy to clipboard functionality
   */
  $(document).on(
    'click',
    '.copy-url-btn, [onclick*="navigator.clipboard.writeText"]',
    function (e) {
      e.preventDefault();

      var $button = $(this);
      var url =
        $button.data('url') || $button.attr('onclick').match(/'([^']+)'/)[1];

      copyToClipboard(url, $button);
    }
  );

  /**
   * Copy text to clipboard with fallback
   */
  function copyToClipboard(text, $button) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard
        .writeText(text)
        .then(function () {
          showCopySuccess($button);
        })
        .catch(function () {
          fallbackCopyToClipboard(text, $button);
        });
    } else {
      fallbackCopyToClipboard(text, $button);
    }
  }

  /**
   * Show copy success feedback
   */
  function showCopySuccess($button) {
    var originalText = $button.text();
    $button.text('✓ Copied!').addClass('varle-success');

    setTimeout(function () {
      $button.text(originalText).removeClass('varle-success');
    }, 2000);

    showNotification('URL copied to clipboard!', 'success');
  }

  /**
   * Fallback copy method for older browsers
   */
  function fallbackCopyToClipboard(text, $button) {
    var textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      var successful = document.execCommand('copy');
      if (successful) {
        showCopySuccess($button);
      } else {
        showNotification('Copy failed. Please copy manually: ' + text, 'error');
      }
    } catch (err) {
      showNotification(
        'Copy not supported. Please copy manually: ' + text,
        'error'
      );
    }

    document.body.removeChild(textArea);
  }

  /**
   * Auto-refresh storage diagnostics
   */
  function refreshStorageDiagnostics() {
    var $diagnosticsTable = $('.wp-list-table tbody');
    if ($diagnosticsTable.length === 0) return;

    $.ajax({
      url: varle_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'varle_refresh_diagnostics',
        nonce: varle_ajax.nonce,
      },
      success: function (response) {
        if (response.success && response.data) {
          $diagnosticsTable.html(response.data);
        }
      },
    });
  }

  /**
   * Form validation and enhancement
   */
  $('form').on('submit', function (e) {
    var hasErrors = false;

    // Validate XML filename
    var $xmlFileName = $('input[name="varle_export_settings[xml_file_name]"]');
    if ($xmlFileName.length) {
      var fileName = $xmlFileName.val().trim();
      if (!fileName) {
        showFieldError($xmlFileName, 'XML filename is required');
        hasErrors = true;
      } else if (!fileName.match(/\.xml$/i)) {
        showFieldError($xmlFileName, 'XML filename must end with .xml');
        hasErrors = true;
      } else {
        clearFieldError($xmlFileName);
      }
    }

    // Validate delivery text
    var $deliveryText = $('input[name="varle_export_settings[delivery_text]"]');
    if ($deliveryText.length && !$deliveryText.val().trim()) {
      showFieldError($deliveryText, 'Delivery text is required');
      hasErrors = true;
    } else if ($deliveryText.length) {
      clearFieldError($deliveryText);
    }

    if (hasErrors) {
      e.preventDefault();
      $('html, body').animate(
        {
          scrollTop: $('.field-error').first().offset().top - 100,
        },
        500
      );

      showNotification('Please fix the form errors before saving', 'error');
    }
  });

  /**
   * Show field error
   */
  function showFieldError($field, message) {
    clearFieldError($field);
    $field.addClass('field-error');
    $field.after(
      '<span class="error-message" style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;">' +
        message +
        '</span>'
    );
  }

  /**
   * Clear field error
   */
  function clearFieldError($field) {
    $field.removeClass('field-error').next('.error-message').remove();
  }

  /**
   * Initialize tooltips
   */
  function initTooltips() {
    $('[data-tooltip]').addClass('tooltip');
  }

  /**
   * Auto-save settings (enhanced)
   */
  var autoSaveTimeout;
  $('.form-table input, .form-table select').on('change', function () {
    clearTimeout(autoSaveTimeout);
    var $field = $(this);

    // Show saving indicator
    $field.after(
      '<span class="saving-indicator" style="color: #666; font-size: 11px; margin-left: 5px;">Saving...</span>'
    );

    autoSaveTimeout = setTimeout(function () {
      $('.saving-indicator').remove();
      showNotification('Settings auto-saved', 'info');
    }, 1000);
  });

  /**
   * Keyboard shortcuts
   */
  $(document).on('keydown', function (e) {
    // Ctrl/Cmd + G = Generate XML
    if ((e.ctrlKey || e.metaKey) && e.which === 71) {
      e.preventDefault();
      $('#generate-xml').trigger('click');
    }

    // Ctrl/Cmd + D = Download XML
    if ((e.ctrlKey || e.metaKey) && e.which === 68) {
      e.preventDefault();
      $('#download-xml').trigger('click');
    }
  });

  /**
   * Initialize everything
   */
  function init() {
    initTooltips();

    // Show keyboard shortcuts info
    if (localStorage.getItem('varle_shortcuts_shown') !== 'true') {
      setTimeout(function () {
        showNotification(
          'Tip: Use Ctrl+G to generate XML, Ctrl+D to download',
          'info'
        );
        localStorage.setItem('varle_shortcuts_shown', 'true');
      }, 3000);
    }

    // Refresh diagnostics every 30 seconds
    setInterval(refreshStorageDiagnostics, 30000);
  }

  // Initialize when page loads
  init();

  // Handle settings form success
  if (window.location.search.indexOf('settings-updated=true') !== -1) {
    setTimeout(function () {
      showNotification('Settings saved successfully!', 'success');
    }, 500);
  }

  // Handle URL hash actions
  if (window.location.hash === '#generate') {
    setTimeout(function () {
      $('#generate-xml').trigger('click');
    }, 1000);
  }

  // Add loading state for form submissions
  $('form').on('submit', function () {
    var $submitButton = $(this).find('input[type="submit"]');
    $submitButton.prop('disabled', true).val('Saving...');
  });

  // Real-time file size estimation
  function estimateFileSize() {
    if ($('#product-count').length === 0) return;

    $.ajax({
      url: varle_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'varle_estimate_size',
        nonce: varle_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          var estimate = response.data;
          $('#file-size-estimate').html(
            '<p><strong>Estimated XML size:</strong> ' +
              estimate.size +
              ' (' +
              estimate.products +
              ' products)</p>'
          );
        }
      },
    });
  }

  // Call size estimation on page load
  estimateFileSize();

  /**
   * Advanced error reporting
   */
  window.addEventListener('error', function (e) {
    if (e.filename && e.filename.includes('varle-export')) {
      console.error('Varle Export JavaScript Error:', e.error);

      // Send error report to admin (optional)
      if (varle_ajax.debug_mode) {
        $.ajax({
          url: varle_ajax.ajax_url,
          type: 'POST',
          data: {
            action: 'varle_report_js_error',
            error: e.error.toString(),
            line: e.lineno,
            file: e.filename,
            nonce: varle_ajax.nonce,
          },
        });
      }
    }
  });

  /**
   * Progressive enhancement for older browsers
   */
  function checkBrowserSupport() {
    var warnings = [];

    if (!window.fetch) {
      warnings.push(
        'Your browser is outdated. Some features may not work properly.'
      );
    }

    if (!navigator.clipboard) {
      warnings.push(
        'Clipboard access not available. Copy buttons will use fallback method.'
      );
    }

    if (warnings.length > 0) {
      setTimeout(function () {
        showNotification(warnings.join(' '), 'warning');
      }, 2000);
    }
  }

  checkBrowserSupport();
});
