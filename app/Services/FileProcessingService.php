<?php
namespace App\Services;

use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class FileProcessingService
{
    public function extractText($filePath)
    {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $fullPath = storage_path("app/public/{$filePath}");

        // return $ext;
        if ($ext === 'pdf') {
            $parser = new Parser();
            $pdf = $parser->parseFile($fullPath);
            return $pdf->getText();
        } elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            return (new TesseractOCR($fullPath))->run();
        }

        return '';
    }
}
