<?php
/**
 * Confidential Mode Settings Page
 * Admin configuration for hiding/blurring sensitive financial data
 */

// Start session
session_start();

// Check authentication
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Check admin role
if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Load dependencies
require_once '../config.php';
require_once '../includes/database.php';

// Set page title for header
$pageTitle = 'Confidential Mode Settings';

include 'header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-user-secret me-2"></i>
                    Confidential Mode Settings
                </h2>
                <a href="settings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Settings
                </a>
            </div>

            <!-- Info Alert -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>About Confidential Mode:</strong> This feature automatically blurs or hides sensitive financial amounts across the system.
                Only administrators with the correct password can unlock and view the data. Perfect for presentations or when sharing your screen.
            </div>

            <!-- Settings Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-cog me-2"></i>Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <form id="confidentialSettingsForm">

                        <!-- Enable/Disable -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <h6 class="mb-2">
                                    <i class="fas fa-toggle-on me-2 text-primary"></i>Enable Confidential Mode
                                </h6>
                                <p class="text-muted small mb-0">
                                    When enabled, all financial amounts will be automatically hidden across the system.
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input" type="checkbox" id="enableConfidentialMode"
                                           style="width: 60px; height: 30px; cursor: pointer;">
                                    <label class="form-check-label ms-2" for="enableConfidentialMode" id="enableLabel">
                                        Disabled
                                    </label>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Blur Style -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-palette me-2 text-primary"></i>Display Style
                            </label>
                            <p class="text-muted small">Choose how confidential data should be hidden</p>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card border" style="cursor: pointer;" onclick="selectBlurStyle('blur')">
                                        <div class="card-body text-center">
                                            <input type="radio" name="blurStyle" value="blur" id="styleBlur" checked>
                                            <label for="styleBlur" class="d-block mt-2">
                                                <i class="fas fa-eye-slash fa-2x mb-2 text-primary"></i>
                                                <h6>Blur Effect</h6>
                                                <p class="small text-muted">Blur the text</p>
                                                <div style="filter: blur(5px);">₱ 12,345.67</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border" style="cursor: pointer;" onclick="selectBlurStyle('asterisk')">
                                        <div class="card-body text-center">
                                            <input type="radio" name="blurStyle" value="asterisk" id="styleAsterisk">
                                            <label for="styleAsterisk" class="d-block mt-2">
                                                <i class="fas fa-asterisk fa-2x mb-2 text-primary"></i>
                                                <h6>Asterisks</h6>
                                                <p class="small text-muted">Replace with ****</p>
                                                <div>₱ ********</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border" style="cursor: pointer;" onclick="selectBlurStyle('redacted')">
                                        <div class="card-body text-center">
                                            <input type="radio" name="blurStyle" value="redacted" id="styleRedacted">
                                            <label for="styleRedacted" class="d-block mt-2">
                                                <i class="fas fa-ban fa-2x mb-2 text-primary"></i>
                                                <h6>Redacted</h6>
                                                <p class="small text-muted">Black box</p>
                                                <div style="background: #000; color: #000; padding: 3px 10px; border-radius: 3px;">₱ 12,345.67</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Auto-Lock -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-clock me-2 text-primary"></i>Auto-Lock After Inactivity
                            </label>
                            <p class="text-muted small">Automatically lock confidential data after period of inactivity (0 = never)</p>
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <input type="range" class="form-range" id="autoLockSlider" min="0" max="600" step="30" value="300">
                                </div>
                                <div class="col-md-6">
                                    <input type="number" class="form-control" id="autoLockValue" min="0" max="3600" value="300">
                                    <small class="text-muted">seconds (0 = disabled)</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Unlock Duration -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-hourglass-half me-2 text-primary"></i>Unlock Duration
                            </label>
                            <p class="text-muted small">How long data stays unlocked after successful authentication</p>
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <input type="range" class="form-range" id="unlockDurationSlider" min="300" max="7200" step="300" value="1800">
                                </div>
                                <div class="col-md-6">
                                    <input type="number" class="form-control" id="unlockDurationValue" min="60" max="7200" value="1800">
                                    <small class="text-muted">seconds (min: 60, max: 7200)</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Save Button -->
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary me-2" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Demo Card -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-vial me-2"></i>Test Confidential Mode
                    </h5>
                </div>
                <div class="card-body">
                    <p>Test the confidential mode with sample financial data:</p>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Cash on Hand</td>
                                    <td class="text-end amount" data-confidential="true">₱ 125,450.00</td>
                                </tr>
                                <tr>
                                    <td>Accounts Receivable</td>
                                    <td class="text-end amount" data-confidential="true">₱ 89,320.50</td>
                                </tr>
                                <tr>
                                    <td>Total Revenue</td>
                                    <td class="text-end amount" data-confidential="true">₱ 450,890.75</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-warning" onclick="ConfidentialMode.checkStatus()">
                        <i class="fas fa-sync me-2"></i>Refresh Demo
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Load current settings
function loadSettings() {
    fetch('../api/confidential_mode.php?action=get_settings')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const settings = data.settings;

            // Enable checkbox
            document.getElementById('enableConfidentialMode').checked = settings.enabled;
            updateEnableLabel(settings.enabled);

            // Blur style
            document.querySelector(`input[name="blurStyle"][value="${settings.blur_style}"]`).checked = true;

            // Auto-lock
            document.getElementById('autoLockSlider').value = settings.auto_lock;
            document.getElementById('autoLockValue').value = settings.auto_lock;

            // Unlock duration
            document.getElementById('unlockDurationSlider').value = settings.unlock_duration;
            document.getElementById('unlockDurationValue').value = settings.unlock_duration;
        }
    })
    .catch(error => {
        console.error('Error loading settings:', error);
        showAlert('Error loading settings', 'danger');
    });
}

// Update enable label
function updateEnableLabel(enabled) {
    const label = document.getElementById('enableLabel');
    label.textContent = enabled ? 'Enabled' : 'Disabled';
    label.className = 'form-check-label ms-2 ' + (enabled ? 'text-success fw-bold' : 'text-muted');
}

// Toggle enable
document.getElementById('enableConfidentialMode').addEventListener('change', function() {
    updateEnableLabel(this.checked);
});

// Sync sliders and inputs
document.getElementById('autoLockSlider').addEventListener('input', function() {
    document.getElementById('autoLockValue').value = this.value;
});

document.getElementById('autoLockValue').addEventListener('input', function() {
    document.getElementById('autoLockSlider').value = this.value;
});

document.getElementById('unlockDurationSlider').addEventListener('input', function() {
    document.getElementById('unlockDurationValue').value = this.value;
});

document.getElementById('unlockDurationValue').addEventListener('input', function() {
    document.getElementById('unlockDurationSlider').value = this.value;
});

// Select blur style
function selectBlurStyle(style) {
    document.getElementById('style' + style.charAt(0).toUpperCase() + style.slice(1)).checked = true;
}

// Save settings
document.getElementById('confidentialSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const enabled = document.getElementById('enableConfidentialMode').checked;
    const blurStyle = document.querySelector('input[name="blurStyle"]:checked').value;
    const autoLock = document.getElementById('autoLockValue').value;
    const unlockDuration = document.getElementById('unlockDurationValue').value;

    const formData = new FormData();
    formData.append('action', 'update_settings');
    formData.append('enabled', enabled ? '1' : '0');
    formData.append('blur_style', blurStyle);
    formData.append('auto_lock', autoLock);
    formData.append('unlock_duration', unlockDuration);

    fetch('../api/confidential_mode.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Settings saved successfully!', 'success');
            // Reload confidential mode
            ConfidentialMode.checkStatus();
        } else {
            showAlert('Error: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Error saving settings:', error);
        showAlert('Network error. Please try again.', 'danger');
    });
});

// Reset form
function resetForm() {
    loadSettings();
    showAlert('Settings reset to saved values', 'info');
}

// Show alert
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.row'));

    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Load on page load
document.addEventListener('DOMContentLoaded', loadSettings);
</script>

<?php include 'footer.php'; ?>
