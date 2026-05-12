/**
 * PDF.js Viewer Wrapper
 * Provides a secure way to view PDFs in the browser
 */

class PDFViewer {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.pdfDoc = null;
        this.pageNum = 1;
        this.pageRendering = false;
        this.pageNumPending = null;
        this.scale = options.scale || 1.5;
        this.canvas = document.createElement('canvas');
        this.ctx = this.canvas.getContext('2d');
        
        // Initialize the viewer
        this.init();
    }

    async init() {
        try {
            // Set up the container
            this.container.innerHTML = '';
            this.container.appendChild(this.canvas);
            
            // Add controls
            this.addControls();
            
            // Load the PDF
            const pdfUrl = this.container.getAttribute('data-pdf-url');
            await this.loadPdf(pdfUrl);
            
        } catch (error) {
            console.error('Error initializing PDF viewer:', error);
            this.showError('Failed to load PDF. Please try again.');
        }
    }
    
    async loadPdf(url) {
        try {
            // Load the PDF document
            const loadingTask = pdfjsLib.getDocument({
                url: url,
                disableAutoFetch: true,
                disableStream: true,
                disableRange: true
            });
            
            this.pdfDoc = await loadingTask.promise;
            
            // Reset page number and render the first page
            this.pageNum = 1;
            this.updatePageCount();
            this.renderPage(this.pageNum);
            
        } catch (error) {
            console.error('Error loading PDF:', error);
            this.showError('Failed to load PDF. The document may be corrupted or inaccessible.');
        }
    }
    
    async renderPage(num) {
        try {
            this.pageRendering = true;
            
            // Get the page
            const page = await this.pdfDoc.getPage(num);
            
            // Set scale to fit container width
            const viewport = page.getViewport({ scale: this.scale });
            this.canvas.height = viewport.height;
            this.canvas.width = viewport.width;
            
            // Render the page
            const renderContext = {
                canvasContext: this.ctx,
                viewport: viewport
            };
            
            await page.render(renderContext).promise;
            this.pageRendering = false;
            
            // Update page number display
            this.updatePageCount();
            
            // If there's a pending page, render that now
            if (this.pageNumPending !== null) {
                this.renderPage(this.pageNumPending);
                this.pageNumPending = null;
            }
            
        } catch (error) {
            console.error('Error rendering PDF page:', error);
            this.showError('Error displaying this page of the document.');
            this.pageRendering = false;
        }
    }
    
    queueRenderPage(num) {
        if (this.pageRendering) {
            this.pageNumPending = num;
        } else {
            this.renderPage(num);
        }
    }
    
    onPrevPage() {
        if (this.pageNum <= 1) return;
        this.pageNum--;
        this.queueRenderPage(this.pageNum);
    }
    
    onNextPage() {
        if (this.pageNum >= this.pdfDoc.numPages) return;
        this.pageNum++;
        this.queueRenderPage(this.pageNum);
    }
    
    updatePageCount() {
        const pageCount = document.getElementById('page-count');
        if (pageCount) {
            pageCount.textContent = `Page ${this.pageNum} of ${this.pdfDoc ? this.pdfDoc.numPages : '?'}`;
        }
    }
    
    addControls() {
        // Create controls container
        const controls = document.createElement('div');
        controls.className = 'pdf-controls d-flex justify-content-between align-items-center p-2 bg-light border-bottom';
        
        // Add previous button
        const prevBtn = document.createElement('button');
        prevBtn.className = 'btn btn-sm btn-outline-secondary';
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i> Previous';
        prevBtn.onclick = () => this.onPrevPage();
        
        // Add page counter
        const counter = document.createElement('div');
        counter.id = 'page-count';
        counter.className = 'text-muted';
        counter.textContent = 'Page 1 of ?';
        
        // Add next button
        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn btn-sm btn-outline-primary';
        nextBtn.innerHTML = 'Next <i class="fas fa-chevron-right"></i>';
        nextBtn.onclick = () => this.onNextPage();
        
        // Add zoom controls
        const zoomIn = document.createElement('button');
        zoomIn.className = 'btn btn-sm btn-outline-secondary ms-2';
        zoomIn.innerHTML = '<i class="fas fa-search-plus"></i>';
        zoomIn.onclick = () => {
            this.scale *= 1.2;
            this.renderPage(this.pageNum);
        };
        
        const zoomOut = document.createElement('button');
        zoomOut.className = 'btn btn-sm btn-outline-secondary';
        zoomOut.innerHTML = '<i class="fas fa-search-minus"></i>';
        zoomOut.onclick = () => {
            if (this.scale > 0.5) {
                this.scale *= 0.8;
                this.renderPage(this.pageNum);
            }
        };
        
        // Assemble controls
        const leftGroup = document.createElement('div');
        leftGroup.appendChild(prevBtn);
        
        const rightGroup = document.createElement('div');
        rightGroup.appendChild(zoomOut);
        rightGroup.appendChild(zoomIn);
        rightGroup.appendChild(document.createTextNode(' '));
        rightGroup.appendChild(nextBtn);
        
        controls.appendChild(leftGroup);
        controls.appendChild(counter);
        controls.appendChild(rightGroup);
        
        // Insert controls before the canvas
        this.container.insertBefore(controls, this.canvas);
    }
    
    showError(message) {
        this.container.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
            </div>
        `;
    }
}

// Initialize viewer when document is ready
document.addEventListener('DOMContentLoaded', function() {
    const viewerElement = document.getElementById('pdf-viewer-container');
    if (viewerElement) {
        // Load PDF.js library dynamically
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js';
        script.integrity = 'sha512-ml/QKfGx+7+4rJ4g4+4Q4f0gq9f0w8aXv5XeE+5J5x5YwKWPUy0R+Q6bJ6Z4UH5UoE/fcU0FJwGdLQpFJgoBA==';
        script.crossOrigin = 'anonymous';
        script.referrerPolicy = 'no-referrer';
        
        script.onload = function() {
            // Set worker path
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
            
            // Initialize the viewer
            new PDFViewer('pdf-viewer-container', {
                scale: 1.5
            });
        };
        
        document.head.appendChild(script);
    }
});
