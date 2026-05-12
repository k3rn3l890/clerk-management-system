/**
 * Geolocation tracking for court document management system
 * This script handles collecting and sending geolocation data when users interact with documents
 */

// Global variable to store location data
let userLocationData = {
    latitude: null,
    longitude: null,
    accuracy: null,
    locationName: null
};

/**
 * Initialize geolocation tracking
 */
function initGeolocation() {
    // Check if geolocation is supported by the browser
    if (navigator.geolocation) {
        // Get user's position
        navigator.geolocation.getCurrentPosition(
            // Success callback
            function(position) {
                userLocationData.latitude = position.coords.latitude;
                userLocationData.longitude = position.coords.longitude;
                userLocationData.accuracy = position.coords.accuracy;
                
                // Get location name using reverse geocoding
                reverseGeocode(position.coords.latitude, position.coords.longitude);
                
                // Store location in session storage for quick access
                sessionStorage.setItem('userLocationData', JSON.stringify(userLocationData));
                
                console.log("Geolocation initialized successfully");
            },
            // Error callback
            function(error) {
                console.log("Geolocation error: " + error.message);
                // Still proceed without location data
            },
            // Options
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            }
        );
    } else {
        console.log("Geolocation is not supported by this browser");
    }
}

/**
 * Perform reverse geocoding to get location name from coordinates
 */
function reverseGeocode(latitude, longitude) {
    // Use OpenStreetMap Nominatim API for reverse geocoding
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`)
        .then(response => response.json())
        .then(data => {
            if (data && data.display_name) {
                userLocationData.locationName = data.display_name;
                sessionStorage.setItem('userLocationData', JSON.stringify(userLocationData));
            }
        })
        .catch(error => {
            console.log("Reverse geocoding error: " + error);
        });
}

/**
 * Log document access with geolocation data
 * @param {number} documentId - ID of the document being accessed
 * @param {string} actionType - Type of action (view, download, etc.)
 */
function logDocumentAccess(documentId, actionType) {
    // Get location data from session storage or use current data
    let locationData = sessionStorage.getItem('userLocationData') 
        ? JSON.parse(sessionStorage.getItem('userLocationData')) 
        : userLocationData;
    
    // Get device information
    const deviceType = getDeviceType();
    
    // Prepare data for the server
    const accessData = {
        document_id: documentId,
        action_type: actionType,
        latitude: locationData.latitude,
        longitude: locationData.longitude,
        location_accuracy: locationData.accuracy,
        location_name: locationData.locationName,
        device_type: deviceType
    };
    
    // Send data to the server
    fetch('document_track.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(accessData)
    })
    .then(response => response.json())
    .then(data => {
        console.log("Document access logged successfully", data);
    })
    .catch(error => {
        console.log("Error logging document access: " + error);
    });
}

/**
 * Log case activity with geolocation data
 * @param {number} caseId - ID of the case
 * @param {string} activityType - Type of activity
 * @param {string} description - Description of the activity
 */
function logCaseActivity(caseId, activityType, description) {
    // Get location data from session storage or use current data
    let locationData = sessionStorage.getItem('userLocationData') 
        ? JSON.parse(sessionStorage.getItem('userLocationData')) 
        : userLocationData;
    
    // Prepare data for the server
    const activityData = {
        case_id: caseId,
        activity_type: activityType,
        description: description,
        latitude: locationData.latitude,
        longitude: locationData.longitude,
        location_name: locationData.locationName
    };
    
    // Send data to the server
    fetch('case_track.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(activityData)
    })
    .then(response => response.json())
    .then(data => {
        console.log("Case activity logged successfully", data);
    })
    .catch(error => {
        console.log("Error logging case activity: " + error);
    });
}

/**
 * Get the user's device type
 * @returns {string} - Device type (desktop, tablet, mobile)
 */
function getDeviceType() {
    const userAgent = navigator.userAgent;
    
    if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(userAgent)) {
        return 'tablet';
    }
    
    if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(userAgent)) {
        return 'mobile';
    }
    
    return 'desktop';
}

// Initialize geolocation when the page loads
document.addEventListener('DOMContentLoaded', function() {
    initGeolocation();
});
