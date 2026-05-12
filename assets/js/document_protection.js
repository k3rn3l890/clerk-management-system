/**
 * Document Protection Script
 * Prevents downloading of documents and enhances in-browser viewing
 */

document.addEventListener('DOMContentLoaded', function() {
    // Prevent right-click context menu on document viewer
    const pdfViewer = document.getElementById('pdf-viewer');
    if (pdfViewer) {
        pdfViewer.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            showNotification('Right-click is disabled to prevent document downloads.', 'warning');
            return false;
        });
    }

    // Prevent keyboard shortcuts for saving/printing
    document.addEventListener('keydown', function(e) {
        // Disable Ctrl+S, Ctrl+P, Ctrl+Shift+S, etc.
        if ((e.ctrlKey || e.metaKey) && 
            (e.key === 's' || e.key === 'p' || e.key === 'S' || e.key === 'P' || e.keyCode === 83 || e.keyCode === 80)) {
            e.preventDefault();
            showNotification('Saving and printing have been disabled for this document.', 'warning');
            return false;
        }
    });

    // Add a watermark overlay to PDF viewer
    function addWatermark() {
        const viewer = document.querySelector('.pdf-viewer-container');
        if (viewer) {
            const watermark = document.createElement('div');
            watermark.className = 'watermark';
            watermark.innerHTML = 'VIEW ONLY - ' + document.title;
            viewer.style.position = 'relative';
            viewer.appendChild(watermark);
        }
    }

    // Show a notification to the user
    function showNotification(message, type = 'info') {
        // Check if toast notification system exists
        if (typeof Toast !== 'undefined') {
            Toast.fire({
                icon: type,
                title: message
            });
        } else {
            // Fallback to alert
            alert(message);
        }
    }

    // Initialize when page loads
    addWatermark();

    // Add CSS for watermark
    const style = document.createElement('style');
    style.textContent = `
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 3em;
            opacity: 0.1;
            pointer-events: none;
            white-space: nowrap;
            color: #000;
            font-weight: bold;
            z-index: 1000;
        }
        .pdf-viewer-container {
            position: relative;
            overflow: hidden;
        }
        /* Disable text selection for PDF viewer */
        .pdf-viewer-container * {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
    `;
    document.head.appendChild(style);
});
