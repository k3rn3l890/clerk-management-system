/**
 * Document Geolocation Tracking
 * This script handles collecting and sending geolocation data when users access documents
 */

// Function to get user's geolocation
function getUserLocation(callback) {
    if (navigator.geolocation) {
        // First check if permission was previously denied
        navigator.permissions.query({name:'geolocation'}).then(function(permissionStatus) {
            if (permissionStatus.state === 'denied') {
                console.warn('Geolocation permission was previously denied by the user');
                showGeolocationPrompt(function(allowed) {
                    if (allowed) {
                        requestGeolocation(callback);
                    } else {
                        callback(null);
                    }
                });
            } else {
                requestGeolocation(callback);
            }
        });
    } else {
        console.error('Geolocation is not supported by this browser');
        callback(null);
    }
}

// Function to request geolocation with proper options
function requestGeolocation(callback) {
    navigator.geolocation.getCurrentPosition(
        // Success callback
        function(position) {
            const locationData = {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
                accuracy: position.coords.accuracy,
                device: detectDeviceType()
            };
            
            // Get location name using reverse geocoding
            getLocationName(locationData.lat, locationData.lng)
                .then(locationName => {
                    locationData.loc = locationName;
                    // Store location in local storage for reuse (with expiration)
                    storeLocationData(locationData);
                    callback(locationData);
                })
                .catch(() => {
                    // If reverse geocoding fails, still return location data
                    callback(locationData);
                });
        },
        // Error callback with better error handling
        function(error) {
            let errorMessage = '';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage = 'User denied the request for geolocation';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage = 'Location information is unavailable';
                    break;
                case error.TIMEOUT:
                    errorMessage = 'Request to get user location timed out';
                    break;
                case error.UNKNOWN_ERROR:
                    errorMessage = 'An unknown error occurred';
                    break;
            }
            console.error('Geolocation error:', errorMessage);
            
            // Try to use cached location if available
            const cachedLocation = getCachedLocation();
            if (cachedLocation) {
                console.log('Using cached location data');
                callback(cachedLocation);
            } else {
                // If geolocation fails and no cached data, show prompt for user
                showGeolocationPrompt(function(allowed) {
                    if (allowed) {
                        requestGeolocation(callback);
                    } else {
                        callback(null);
                    }
                });
            }
        },
        // Options
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 300000 // 5 minutes
        }
    );
}

// Function to detect device type
function detectDeviceType() {
    const userAgent = navigator.userAgent.toLowerCase();
    
    if (/mobile|android|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(userAgent)) {
        return /ipad/i.test(userAgent) ? 'tablet' : 'mobile';
    }
    
    return 'desktop';
}

// Function to get location name using reverse geocoding
async function getLocationName(lat, lng) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
        const data = await response.json();
        
        if (data && data.display_name) {
            return data.display_name;
        } else {
            return 'Unknown Location';
        }
    } catch (error) {
        console.error('Error getting location name:', error);
        return 'Unknown Location';
    }
}

// Function to store location data with expiration
function storeLocationData(locationData) {
    const storageObject = {
        data: locationData,
        timestamp: Date.now()
    };
    localStorage.setItem('userLocationData', JSON.stringify(storageObject));
}

// Function to get cached location if not expired
function getCachedLocation() {
    const locationStorage = localStorage.getItem('userLocationData');
    if (locationStorage) {
        try {
            const storageObject = JSON.parse(locationStorage);
            // Check if stored location is less than 30 minutes old
            if (Date.now() - storageObject.timestamp < 30 * 60 * 1000) {
                return storageObject.data;
            }
        } catch (e) {
            console.error('Error parsing stored location data:', e);
        }
    }
    return null;
}

// Function to show geolocation permission prompt
function showGeolocationPrompt(callback) {
    // Check if we already have a prompt showing
    if (document.getElementById('geolocation-prompt')) {
        return;
    }
    
    // Create modal for permission request
    const modal = document.createElement('div');
    modal.id = 'geolocation-prompt';
    modal.className = 'geolocation-modal';
    modal.innerHTML = `
        <div class="geolocation-modal-content">
            <div class="geolocation-modal-header">
                <h5>Enable Location Tracking</h5>
                <button type="button" class="geolocation-close">&times;</button>
            </div>
            <div class="geolocation-modal-body">
                <p>This court system uses location data to track document access for security and legal purposes. Your location will be recorded when viewing, downloading, or modifying documents.</p>
                <p>Please enable location access when prompted by your browser.</p>
            </div>
            <div class="geolocation-modal-footer">
                <button type="button" class="geolocation-btn geolocation-btn-secondary" id="geolocation-deny">Continue without tracking</button>
                <button type="button" class="geolocation-btn geolocation-btn-primary" id="geolocation-allow">Enable location tracking</button>
            </div>
        </div>
    `;
    
    // Add styling
    const style = document.createElement('style');
    style.textContent = `
        .geolocation-modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .geolocation-modal-content {
            background-color: #fefefe;
            max-width: 500px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .geolocation-modal-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .geolocation-modal-body {
            padding: 15px;
        }
        .geolocation-modal-footer {
            padding: 15px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
        }
        .geolocation-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .geolocation-btn {
            padding: 8px 16px;
            margin-left: 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .geolocation-btn-primary {
            background-color: #4e73df;
            color: white;
            border: 1px solid #4e73df;
        }
        .geolocation-btn-secondary {
            background-color: #f8f9fc;
            border: 1px solid #d1d3e2;
            color: #6e707e;
        }
    `;
    
    document.head.appendChild(style);
    document.body.appendChild(modal);
    
    // Add event listeners
    document.getElementById('geolocation-allow').addEventListener('click', function() {
        document.body.removeChild(modal);
        callback(true);
    });
    
    document.getElementById('geolocation-deny').addEventListener('click', function() {
        document.body.removeChild(modal);
        callback(false);
    });
    
    document.querySelector('.geolocation-close').addEventListener('click', function() {
        document.body.removeChild(modal);
        callback(false);
    });
}

// Function to append geolocation data to URLs
function appendGeolocationToUrl(url, locationData) {
    if (!locationData) return url;
    
    const separator = url.includes('?') ? '&' : '?';
    let newUrl = `${url}${separator}lat=${locationData.lat}&lng=${locationData.lng}`;
    
    if (locationData.loc) {
        newUrl += `&loc=${encodeURIComponent(locationData.loc)}`;
    }
    
    if (locationData.device) {
        newUrl += `&device=${encodeURIComponent(locationData.device)}`;
    }
    
    return newUrl;
}

// Function to track document access
function trackDocumentAccess(documentId, actionType) {
    // First try to get cached location data
    const cachedLocation = getCachedLocation();
    
    if (cachedLocation) {
        // Use cached location data for quick response
        sendTrackingData(documentId, actionType, cachedLocation);
        
        // Then refresh the location data in the background
        getUserLocation(function(freshLocationData) {
            if (freshLocationData && 
                (freshLocationData.lat !== cachedLocation.lat || 
                 freshLocationData.lng !== cachedLocation.lng)) {
                // Location has changed, send updated data
                sendTrackingData(documentId, actionType, freshLocationData);
            }
        });
    } else {
        // No cached data, get fresh location
        getUserLocation(function(locationData) {
            sendTrackingData(documentId, actionType, locationData);
        });
    }
}

// Function to send the tracking data to the server
function sendTrackingData(documentId, actionType, locationData) {
    // Create data object for API
    const trackingData = {
        document_id: documentId,
        action_type: actionType
    };
    
    // Add location data if available
    if (locationData) {
        trackingData.latitude = locationData.lat;
        trackingData.longitude = locationData.lng;
        trackingData.location_name = locationData.loc || 'Unknown Location';
        trackingData.device_type = locationData.device || 'Unknown Device';
    }
    
    // Send data to server
    fetch('track_document_access.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(trackingData)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Document access tracked successfully');
    })
    .catch(error => {
        console.error('Error tracking document access:', error);
    });
}

// Initialize document tracking
document.addEventListener('DOMContentLoaded', function() {
    // Pre-fetch location on page load for faster subsequent operations
    getUserLocation(function(locationData) {
        if (locationData) {
            console.log('Location pre-fetched successfully');
        }
    });
    
    // Add geolocation to document download links
    const downloadLinks = document.querySelectorAll('.document-download-link');
    
    downloadLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const originalHref = this.getAttribute('href');
            const documentId = this.getAttribute('data-document-id');
            
            // Track the download action
            trackDocumentAccess(documentId, 'download');
            
            // Add location parameters to URL if available
            getUserLocation(function(locationData) {
                const newHref = locationData ? 
                    appendGeolocationToUrl(originalHref, locationData) : 
                    originalHref;
                
                window.location.href = newHref;
            });
        });
    });
    
    // Track document views on document view pages
    const documentViewPage = document.querySelector('.document-view-page');
    if (documentViewPage) {
        const documentId = documentViewPage.getAttribute('data-document-id');
        if (documentId) {
            trackDocumentAccess(documentId, 'view');
        }
    }
});
