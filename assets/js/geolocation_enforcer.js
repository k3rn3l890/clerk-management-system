/**
 * Geolocation Permission Enforcer
 * This script enforces location permission requirements for the court system
 * It prevents users from accessing the system until location access is granted
 */

// Global permission state
let locationPermissionGranted = false;

// Check on page load if we should enforce permissions
document.addEventListener('DOMContentLoaded', function() {
    // Skip permission enforcement for login page
    if (isLoginPage()) {
        console.log('Login page detected - skipping geolocation enforcement');
        return;
    }
    
    // Skip permission enforcement for forgot/reset password pages
    if (isForgotPasswordPage()) {
        console.log('Password recovery page detected - skipping geolocation enforcement');
        return;
    }
    
    // Check if permission was already granted and stored
    if (sessionStorage.getItem('locationPermissionGranted') === 'true') {
        locationPermissionGranted = true;
        console.log('Location permission already granted');
        return;
    }
    
    // Otherwise enforce permission
    enforceLocationPermission();
});

/**
 * Check if current page is the login page
 */
function isLoginPage() {
    return window.location.pathname.includes('login.php') || 
           window.location.pathname.endsWith('/') ||
           window.location.pathname.endsWith('/index.php');
}

/**
 * Check if current page is for password recovery
 */
function isForgotPasswordPage() {
    return window.location.pathname.includes('forgot_password.php') || 
           window.location.pathname.includes('reset_password.php');
}

/**
 * Show a blocking modal requiring location permission
 */
function enforceLocationPermission() {
    // Prevent interaction with the page by adding an overlay
    createOverlay();
    
    // Check if permissions API is supported
    if (navigator.permissions && navigator.permissions.query) {
        // Check current permission state
        navigator.permissions.query({name: 'geolocation'}).then(function(permissionStatus) {
            if (permissionStatus.state === 'granted') {
                // Permission already granted
                handlePermissionGranted();
                return;
            } else if (permissionStatus.state === 'denied') {
                // Permission previously denied - show instructions to reset
                showPermissionDeniedInstructions();
                return;
            }
            
            // Otherwise show permission request modal
            showPermissionModal();
            
            // Listen for permission state changes
            permissionStatus.onchange = function() {
                if (this.state === 'granted') {
                    handlePermissionGranted();
                }
            };
        });
    } else {
        // Permissions API not supported, fall back to direct geolocation request
        showPermissionModal();
    }
}

/**
 * Create a blocking overlay
 */
function createOverlay() {
    let overlay = document.createElement('div');
    overlay.id = 'permission-overlay';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
    overlay.style.zIndex = '9998';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    document.body.appendChild(overlay);
}

/**
 * Show modal requesting permission
 */
function showPermissionModal() {
    let modal = document.createElement('div');
    modal.id = 'permission-modal';
    modal.style.backgroundColor = '#fff';
    modal.style.borderRadius = '8px';
    modal.style.padding = '20px';
    modal.style.maxWidth = '500px';
    modal.style.width = '90%';
    modal.style.zIndex = '9999';
    modal.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
    
    modal.innerHTML = `
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="assets/images/Coat_of_arms_of_Ghana.svg" width="80" height="80" alt="Ghana Court System">
        </div>
        <h2 style="margin-top: 0; color: #333; text-align: center; font-size: 24px;">Location Access Required</h2>
        <p style="line-height: 1.5; color: #444; margin-bottom: 15px;">
            The Ghana Court Clerk Management System requires location access for legal and security purposes.
        </p>
        <p style="line-height: 1.5; color: #444; margin-bottom: 15px;">
            Your location will be recorded when accessing, viewing, or modifying court documents to ensure proper 
            document tracking and maintain chain of custody.
        </p>
        <p style="line-height: 1.5; color: #444; font-weight: bold; margin-bottom: 15px;">
            You must allow location access to continue using this system.
        </p>
        <div style="display: flex; justify-content: center; margin-top: 30px;">
            <button id="allow-location-btn" style="background-color: #4e73df; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold;">
                Enable Location Access
            </button>
        </div>
        <p style="font-size: 12px; color: #666; text-align: center; margin-top: 20px;">
            If you experience problems enabling location, please contact your system administrator.
        </p>
    `;
    
    document.getElementById('permission-overlay').appendChild(modal);
    
    document.getElementById('allow-location-btn').addEventListener('click', function() {
        requestGeolocationPermission();
    });
}

/**
 * Show instructions when permission was previously denied
 */
function showPermissionDeniedInstructions() {
    let modal = document.createElement('div');
    modal.id = 'permission-denied-modal';
    modal.style.backgroundColor = '#fff';
    modal.style.borderRadius = '8px';
    modal.style.padding = '20px';
    modal.style.maxWidth = '500px';
    modal.style.width = '90%';
    modal.style.zIndex = '9999';
    modal.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
    
    modal.innerHTML = `
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="assets/images/Coat_of_arms_of_Ghana.svg" width="80" height="80" alt="Ghana Court System">
        </div>
        <h2 style="margin-top: 0; color: #d32f2f; text-align: center; font-size: 24px;">Location Access Blocked</h2>
        <p style="line-height: 1.5; color: #444; margin-bottom: 15px;">
            You have previously denied location access to this site. To use the system, you must enable location access in your browser settings.
        </p>
        <div style="background-color: #f9f9f9; border-left: 4px solid #4e73df; padding: 15px; margin: 15px 0; border-radius: 4px;">
            <h3 style="margin-top: 0; font-size: 18px;">How to enable location:</h3>
            <ul style="padding-left: 20px; line-height: 1.6;">
                <li><strong>Chrome:</strong> Click the lock/info icon in the address bar → Site settings → Location → Allow</li>
                <li><strong>Firefox:</strong> Click the lock icon in the address bar → Clear Permission → Reload page</li>
                <li><strong>Safari:</strong> Safari menu → Preferences → Websites → Location → Allow</li>
                <li><strong>Edge:</strong> Click the lock icon in the address bar → Site permissions → Location → Allow</li>
            </ul>
        </div>
        <p style="line-height: 1.5; color: #444; font-weight: bold; margin-bottom: 15px;">
            After enabling location, reload this page to continue.
        </p>
        <div style="display: flex; justify-content: center; margin-top: 30px;">
            <button onclick="window.location.reload()" style="background-color: #4e73df; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold;">
                Reload Page
            </button>
        </div>
    `;
    
    document.getElementById('permission-overlay').appendChild(modal);
}

/**
 * Request geolocation permission from the browser
 */
function requestGeolocationPermission() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            // Success callback
            function(position) {
                handlePermissionGranted();
            },
            // Error callback
            function(error) {
                if (error.code === error.PERMISSION_DENIED) {
                    showPermissionDeniedInstructions();
                } else {
                    showGeolocationError(error);
                }
            },
            // Options
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    } else {
        showUnsupportedBrowserMessage();
    }
}

/**
 * Handle permission granted state
 */
function handlePermissionGranted() {
    locationPermissionGranted = true;
    sessionStorage.setItem('locationPermissionGranted', 'true');
    
    // Remove overlay and modal
    const overlay = document.getElementById('permission-overlay');
    if (overlay) {
        document.body.removeChild(overlay);
    }
    
    // Store the user's location in session storage for future use
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const locationData = {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
                accuracy: position.coords.accuracy,
                timestamp: Date.now()
            };
            sessionStorage.setItem('userLocation', JSON.stringify(locationData));
            
            // Record the policy agreement on the server
            recordLocationAgreement(locationData);
        });
    }
}

/**
 * Record the user's agreement to location tracking
 */
function recordLocationAgreement(locationData) {
    // Create the agreement data
    const agreementData = {
        ip_address: null, // Will be set by server
        location_data: locationData,
        browser_info: {
            userAgent: navigator.userAgent,
            platform: navigator.platform,
            language: navigator.language,
            timestamp: new Date().toISOString()
        }
    };
    
    // Send to server via fetch API
    fetch('location_agreement_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(agreementData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Location policy agreement recorded successfully');
        } else {
            console.error('Error recording location policy agreement:', data.error);
        }
    })
    .catch(error => {
        console.error('Error sending location agreement data:', error);
    });
}

/**
 * Show error message when geolocation fails
 */
function showGeolocationError(error) {
    let errorMsg = '';
    
    switch(error.code) {
        case error.PERMISSION_DENIED:
            errorMsg = 'You have denied the request for geolocation.';
            break;
        case error.POSITION_UNAVAILABLE:
            errorMsg = 'Location information is unavailable. Please check your device settings.';
            break;
        case error.TIMEOUT:
            errorMsg = 'The request to get your location timed out. Please try again.';
            break;
        case error.UNKNOWN_ERROR:
            errorMsg = 'An unknown error occurred while getting your location.';
            break;
    }
    
    const errorModal = document.getElementById('permission-modal');
    if (errorModal) {
        errorModal.innerHTML = `
            <h2 style="margin-top: 0; color: #d32f2f; text-align: center;">Location Error</h2>
            <p style="line-height: 1.5; color: #444; margin-bottom: 20px;">
                ${errorMsg}
            </p>
            <p style="line-height: 1.5; color: #444; font-weight: bold; margin-bottom: 20px;">
                You must allow location access to continue using this system.
            </p>
            <div style="display: flex; justify-content: center; margin-top: 30px;">
                <button id="retry-location-btn" style="background-color: #4e73df; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-size: 16px;">
                    Try Again
                </button>
            </div>
        `;
        
        document.getElementById('retry-location-btn').addEventListener('click', function() {
            requestGeolocationPermission();
        });
    }
}

/**
 * Show message for browsers that don't support geolocation
 */
function showUnsupportedBrowserMessage() {
    const modal = document.getElementById('permission-modal');
    if (modal) {
        modal.innerHTML = `
            <h2 style="margin-top: 0; color: #d32f2f; text-align: center;">Browser Not Supported</h2>
            <p style="line-height: 1.5; color: #444; margin-bottom: 20px;">
                Your browser does not support geolocation, which is required for this system.
            </p>
            <p style="line-height: 1.5; color: #444; margin-bottom: 20px;">
                Please use a modern browser such as Chrome, Firefox, Safari, or Edge.
            </p>
            <div style="display: flex; justify-content: center; margin-top: 30px;">
                <button onclick="window.location.href='index.php'" style="background-color: #6c757d; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-size: 16px;">
                    Go Back
                </button>
            </div>
        `;
    }
} 