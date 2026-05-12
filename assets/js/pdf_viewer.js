/**
 * PDF Viewer Integration
 * This script handles the integration of PDF.js for browser-based PDF viewing
 */

// Set up PDF.js worker
if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js';
}

// PDF Viewer Class
class PDFViewer {
    constructor(options) {
        this.container = options.container || document.getElementById('pdf-container');
        this.canvas = options.canvas || document.getElementById('pdf-canvas');
        this.ctx = this.canvas.getContext('2d');
        this.url = options.url || '';
        this.pageNum = 1;
        this.pageRendering = false;
        this.pageNumPending = null;
        this.scale = options.scale || 1.0;
        this.pdfDoc = null;
        this.annotations = options.annotations || [];
        this.watermarkText = options.watermarkText || '';
        
        // UI elements
        this.pageNumEl = document.getElementById('page-num');
        this.pageCountEl = document.getElementById('page-count');
        this.prevButton = document.getElementById('prev-page');
        this.nextButton = document.getElementById('next-page');
        this.zoomInButton = document.getElementById('zoom-in');
        this.zoomOutButton = document.getElementById('zoom-out');
        this.fitPageButton = document.getElementById('fit-page');
        this.fitWidthButton = document.getElementById('fit-width');
        this.zoomValueEl = document.getElementById('zoom-value');
        this.loadingIndicator = document.getElementById('loading-indicator');
        this.annotationsContainer = document.getElementById('annotations-container');
        
        // Initialize
        this.init();
    }
    
    init() {
        // Load PDF
        this.loadPDF();
        
        // Add event listeners
        this.addEventListeners();
    }
    
    loadPDF() {
        // Show loading indicator
        if (this.loadingIndicator) {
            this.loadingIndicator.style.display = 'block';
        }
        
        // Load PDF document
        pdfjsLib.getDocument(this.url).promise
            .then(pdfDoc => {
                this.pdfDoc = pdfDoc;
                
                // Update page count
                if (this.pageCountEl) {
                    this.pageCountEl.textContent = this.pdfDoc.numPages;
                }
                
                // Render first page
                this.renderPage(this.pageNum);
                
                // Hide loading indicator
                if (this.loadingIndicator) {
                    this.loadingIndicator.style.display = 'none';
                }
                
                // Notify parent window that PDF is loaded
                if (window.parent) {
                    window.parent.document.dispatchEvent(new CustomEvent('pdfLoaded', { 
                        detail: { totalPages: this.pdfDoc.numPages } 
                    }));
                }
            })
            .catch(error => {
                console.error('Error loading PDF:', error);
                if (this.loadingIndicator) {
                    this.loadingIndicator.innerHTML = 'Error loading PDF: ' + error.message;
                }
            });
    }
    
    renderPage(num) {
        this.pageRendering = true;
        
        // Update page number display
        if (this.pageNumEl) {
            this.pageNumEl.textContent = num;
        }
        
        // Get the page
        this.pdfDoc.getPage(num).then(page => {
            // Calculate viewport based on canvas container size
            const viewport = this.calculateViewport(page);
            
            // Set canvas dimensions
            this.canvas.height = viewport.height;
            this.canvas.width = viewport.width;
            
            // Render PDF page
            const renderContext = {
                canvasContext: this.ctx,
                viewport: viewport
            };
            
            const renderTask = page.render(renderContext);
            
            // Wait for rendering to finish
            renderTask.promise.then(() => {
                this.pageRendering = false;
                
                // Add watermark if specified
                if (this.watermarkText) {
                    this.addWatermark();
                }
                
                // Render annotations for this page
                this.renderAnnotations(num);
                
                // Check if there's a page pending to render
                if (this.pageNumPending !== null) {
                    this.renderPage(this.pageNumPending);
                    this.pageNumPending = null;
                }
            });
        });
    }
    
    calculateViewport(page) {
        const containerWidth = this.container.clientWidth - 40; // Subtract padding
        const containerHeight = this.container.clientHeight - 40;
        
        // Get original viewport
        const originalViewport = page.getViewport({ scale: 1 });
        
        // Calculate scale to fit container
        let scale = this.scale;
        
        // If fit page button is active
        if (this.fitPageButton && this.fitPageButton.classList.contains('active')) {
            // Calculate scale to fit page height
            const heightScale = containerHeight / originalViewport.height;
            const widthScale = containerWidth / originalViewport.width;
            scale = Math.min(heightScale, widthScale);
        } 
        // If fit width button is active
        else if (this.fitWidthButton && this.fitWidthButton.classList.contains('active')) {
            // Calculate scale to fit page width
            scale = containerWidth / originalViewport.width;
        }
        
        // Update zoom value display
        if (this.zoomValueEl) {
            this.zoomValueEl.textContent = Math.round(scale * 100);
        }
        
        // Return viewport with calculated scale
        return page.getViewport({ scale: scale });
    }
    
    queueRenderPage(num) {
        if (this.pageRendering) {
            this.pageNumPending = num;
        } else {
            this.renderPage(num);
        }
    }
    
    onPrevPage() {
        if (this.pageNum <= 1) {
            return;
        }
        this.pageNum--;
        this.queueRenderPage(this.pageNum);
    }
    
    onNextPage() {
        if (this.pageNum >= this.pdfDoc.numPages) {
            return;
        }
        this.pageNum++;
        this.queueRenderPage(this.pageNum);
    }
    
    onZoomIn() {
        // Remove active class from fit buttons
        if (this.fitPageButton) this.fitPageButton.classList.remove('active');
        if (this.fitWidthButton) this.fitWidthButton.classList.remove('active');
        
        this.scale *= 1.2;
        this.queueRenderPage(this.pageNum);
    }
    
    onZoomOut() {
        // Remove active class from fit buttons
        if (this.fitPageButton) this.fitPageButton.classList.remove('active');
        if (this.fitWidthButton) this.fitWidthButton.classList.remove('active');
        
        this.scale /= 1.2;
        this.queueRenderPage(this.pageNum);
    }
    
    onFitPage() {
        // Toggle active class
        if (this.fitPageButton) {
            this.fitPageButton.classList.toggle('active');
            if (this.fitPageButton.classList.contains('active') && this.fitWidthButton) {
                this.fitWidthButton.classList.remove('active');
            }
        }
        
        this.queueRenderPage(this.pageNum);
    }
    
    onFitWidth() {
        // Toggle active class
        if (this.fitWidthButton) {
            this.fitWidthButton.classList.toggle('active');
            if (this.fitWidthButton.classList.contains('active') && this.fitPageButton) {
                this.fitPageButton.classList.remove('active');
            }
        }
        
        this.queueRenderPage(this.pageNum);
    }
    
    addWatermark() {
        const ctx = this.ctx;
        const canvas = this.canvas;
        
        // Save context state
        ctx.save();
        
        // Configure watermark text
        ctx.globalAlpha = 0.15;
        ctx.font = '20px Arial';
        ctx.fillStyle = 'gray';
        ctx.textAlign = 'center';
        
        // Rotate canvas for diagonal watermark
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate(-Math.PI / 4);
        
        // Draw watermark multiple times
        for (let i = -2; i <= 2; i++) {
            for (let j = -2; j <= 2; j++) {
                ctx.fillText(
                    this.watermarkText,
                    i * 300,
                    j * 300
                );
            }
        }
        
        // Restore context state
        ctx.restore();
    }
    
    renderAnnotations(pageNum) {
        if (!this.annotationsContainer) return;
        
        // Clear previous annotations
        this.annotationsContainer.innerHTML = '';
        
        // Filter annotations for current page
        const pageAnnotations = this.annotations.filter(a => a.page_number === pageNum);
        
        // Create annotation elements
        pageAnnotations.forEach(annotation => {
            const annotEl = document.createElement('div');
            annotEl.className = 'annotation-marker';
            annotEl.dataset.id = annotation.annotation_id;
            annotEl.style.left = `${annotation.position_x}px`;
            annotEl.style.top = `${annotation.position_y}px`;
            annotEl.style.width = `${annotation.width}px`;
            annotEl.style.height = `${annotation.height}px`;
            
            // Create tooltip
            const tooltip = document.createElement('div');
            tooltip.className = 'annotation-tooltip';
            tooltip.innerHTML = `
                <strong>${annotation.created_by_name}</strong>
                <small>${new Date(annotation.created_at).toLocaleString()}</small>
                <p>${annotation.content}</p>
            `;
            
            // Add event listeners
            annotEl.addEventListener('mouseenter', () => {
                tooltip.style.display = 'block';
            });
            
            annotEl.addEventListener('mouseleave', () => {
                tooltip.style.display = 'none';
            });
            
            // Append to container
            annotEl.appendChild(tooltip);
            this.annotationsContainer.appendChild(annotEl);
        });
    }
    
    addEventListeners() {
        // Navigation buttons
        if (this.prevButton) {
            this.prevButton.addEventListener('click', () => this.onPrevPage());
        }
        
        if (this.nextButton) {
            this.nextButton.addEventListener('click', () => this.onNextPage());
        }
        
        // Zoom buttons
        if (this.zoomInButton) {
            this.zoomInButton.addEventListener('click', () => this.onZoomIn());
        }
        
        if (this.zoomOutButton) {
            this.zoomOutButton.addEventListener('click', () => this.onZoomOut());
        }
        
        // Fit buttons
        if (this.fitPageButton) {
            this.fitPageButton.addEventListener('click', () => this.onFitPage());
        }
        
        if (this.fitWidthButton) {
            this.fitWidthButton.addEventListener('click', () => this.onFitWidth());
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === ' ') {
                this.onNextPage();
            } else if (e.key === 'ArrowLeft') {
                this.onPrevPage();
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            this.queueRenderPage(this.pageNum);
        });
    }
}

// Initialize PDF viewer when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in a PDF viewer page
    const pdfCanvas = document.getElementById('pdf-canvas');
    if (!pdfCanvas) return;
    
    // Get PDF URL from data attribute or query parameter
    const pdfUrl = pdfCanvas.dataset.pdfUrl || getQueryParam('file');
    if (!pdfUrl) return;
    
    // Get watermark text if available
    const watermarkText = pdfCanvas.dataset.watermark || '';
    
    // Initialize PDF viewer
    const viewer = new PDFViewer({
        url: pdfUrl,
        watermarkText: watermarkText
    });
});

// Helper function to get query parameters
function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
} 