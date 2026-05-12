<?php
/**
 * PDF Watermarker Class
 * Handles adding watermarks to PDF documents
 */

class PdfWatermarker {
    private $pdf;
    private $font = 'Helvetica';
    private $fontSize = 10;
    private $opacity = 0.5;
    private $color = '#999999';
    
    /**
     * Constructor
     * 
     * @param string $filePath Path to the PDF file
     * @throws Exception If TCPDF or FPDI is not available
     */
    public function __construct() {
        // Check if required extensions are available
        if (!class_exists('TCPDF')) {
            require_once('tcpdf/tcpdf.php');
        }
        if (!class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
            require_once('fpdi/src/autoload.php');
        }
        
        if (!class_exists('TCPDF') || !class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
            throw new Exception('TCPDF and FPDI are required for PDF watermarking');
        }
    }
    
    /**
     * Add watermark to a PDF file
     * 
     * @param string $inputFile Path to the input PDF file
     * @param string $outputFile Path to save the watermarked PDF (if null, outputs to browser)
     * @param string $watermarkText Text to use as watermark
     * @return string|bool Path to watermarked file or false on failure
     */
    public function addWatermark($inputFile, $outputFile = null, $watermarkText) {
        if (!file_exists($inputFile)) {
            throw new Exception("Input file does not exist: $inputFile");
        }
        
        // Initialize FPDI
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        
        try {
            // Import the PDF
            $pageCount = $pdf->setSourceFile($inputFile);
            
            // Process each page
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Import the page
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                
                // Add a page with the same orientation and size as the original
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, array($size['width'], $size['height']));
                
                // Use the imported page
                $pdf->useTemplate($templateId);
                
                // Get the page dimensions
                $pageWidth = $pdf->getPageWidth();
                $pageHeight = $pdf->getPageHeight();
                
                // Set watermark properties
                $pdf->SetFont($this->font, '', $this->fontSize);
                $pdf->SetTextColor(hexdec(substr($this->color, 1, 2)), 
                                 hexdec(substr($this->color, 3, 2)), 
                                 hexdec(substr($this->color, 5, 2)));
                $pdf->SetAlpha($this->opacity);
                
                // Calculate text width and position
                $textWidth = $pdf->GetStringWidth($watermarkText);
                $x = ($pageWidth - $textWidth) / 2;
                $y = $pageHeight - 20; // 20 units from bottom
                
                // Add the watermark text
                $pdf->Text($x, $y, $watermarkText);
                
                // Reset alpha to default
                $pdf->SetAlpha(1);
            }
            
            // Output or save the watermarked PDF
            if ($outputFile === null) {
                // Output to browser
                return $pdf->Output('watermarked.pdf', 'I');
            } else {
                // Save to file
                $pdf->Output($outputFile, 'F');
                return $outputFile;
            }
            
        } catch (Exception $e) {
            error_log("Error adding watermark to PDF: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set watermark font
     * 
     * @param string $font Font family name
     * @return $this
     */
    public function setFont($font) {
        $this->font = $font;
        return $this;
    }
    
    /**
     * Set watermark font size
     * 
     * @param int $size Font size in points
     * @return $this
     */
    public function setFontSize($size) {
        $this->fontSize = (int)$size;
        return $this;
    }
    
    /**
     * Set watermark opacity
     * 
     * @param float $opacity Opacity value (0.0 to 1.0)
     * @return $this
     */
    public function setOpacity($opacity) {
        $this->opacity = max(0.0, min(1.0, (float)$opacity));
        return $this;
    }
    
    /**
     * Set watermark color
     * 
     * @param string $color Color in hex format (e.g., '#FF0000' for red)
     * @return $this
     */
    public function setColor($color) {
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            $this->color = $color;
        }
        return $this;
    }
}
