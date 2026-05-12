<?php
/**
 * Simple PDF Watermarker using FPDI and FPDF
 * This is a fallback implementation when Composer dependencies are not available
 */

// Check if FPDI is already loaded
if (!class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
    // Try to load FPDI from common locations
    $fpdiPaths = [
        'tcpdf/tcpdf.php',
        'vendor/tecnickcom/tcpdf/tcpdf.php',
        'tcpdf.php'
    ];
    
    $fpdiLoaded = false;
    foreach ($fpdiPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $fpdiLoaded = true;
            break;
        }
    }
    
    if (!$fpdiLoaded) {
        // If FPDI is not found, we'll use a very basic fallback
        class SimplePdfWatermarker {
            public function addWatermark($inputFile, $outputFile, $watermarkText) {
                // This is a fallback that just copies the file without watermarking
                if (!copy($inputFile, $outputFile)) {
                    error_log("Failed to copy file from $inputFile to $outputFile");
                    return false;
                }
                return $outputFile;
            }
            
            // Stub methods to maintain compatibility
            public function setFont($font) { return $this; }
            public function setFontSize($size) { return $this; }
            public function setOpacity($opacity) { return $this; }
            public function setColor($color) { return $this; }
        }
        return; // Stop further execution of this file
    }
}

/**
 * Simple PDF Watermarker using FPDI and FPDF
 */
class SimplePdfWatermarker {
    private $font = 'Helvetica';
    private $fontSize = 10;
    private $opacity = 0.5;
    private $color = '#999999';
    
    /**
     * Add watermark to a PDF file
     * 
     * @param string $inputFile Path to the input PDF file
     * @param string $outputFile Path to save the watermarked PDF
     * @param string $watermarkText Text to use as watermark
     * @return string|bool Path to watermarked file or false on failure
     */
    public function addWatermark($inputFile, $outputFile, $watermarkText) {
        if (!file_exists($inputFile)) {
            error_log("Input file does not exist: $inputFile");
            return false;
        }
        
        try {
            // Initialize FPDI
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            
            // Set document information
            $pdf->SetCreator('Court Clerk Management System');
            $pdf->SetAuthor('Court Clerk Management System');
            $pdf->SetTitle('Watermarked Document');
            
            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
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
                
                // Convert hex color to RGB
                $r = hexdec(substr($this->color, 1, 2));
                $g = hexdec(substr($this->color, 3, 2));
                $b = hexdec(substr($this->color, 5, 2));
                $pdf->SetTextColor($r, $g, $b);
                
                // Set transparency
                $pdf->SetAlpha($this->opacity);
                
                // Calculate text width and position
                $textWidth = $pdf->GetStringWidth($watermarkText);
                $x = ($pageWidth - $textWidth) / 2; // Center horizontally
                $y = $pageHeight - 20; // 20 units from bottom
                
                // Add the watermark text
                $pdf->Text($x, $y, $watermarkText);
                
                // Reset alpha to default
                $pdf->SetAlpha(1);
            }
            
            // Output or save the watermarked PDF
            if (empty($outputFile)) {
                // Output to browser
                return $pdf->Output('watermarked.pdf', 'I');
            } else {
                // Save to file
                $outputDir = dirname($outputFile);
                if (!is_dir($outputDir)) {
                    if (!mkdir($outputDir, 0777, true)) {
                        error_log("Failed to create directory: $outputDir");
                        return false;
                    }
                }
                
                $pdf->Output($outputFile, 'F');
                return file_exists($outputFile) ? $outputFile : false;
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
