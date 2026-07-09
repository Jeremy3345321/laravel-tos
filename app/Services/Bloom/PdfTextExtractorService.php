<?php

namespace App\Services\Bloom;

use Smalot\PdfParser\Parser;

/**
 * Extracts plain text from an uploaded lesson PDF.
 *
 * Requires: composer require smalot/pdfparser
 *
 * For scanned/image-only PDFs (no embedded text layer), this will
 * return an empty/near-empty string — in that case you'd need OCR
 * (e.g. the Tesseract + Poppler pipeline already used elsewhere in
 * the HR system) before this will work. Most typed lesson PDFs /
 * DepEd learning modules exported from Word will work fine as-is.
 */
class PdfTextExtractorService
{
    public function extractFromPath(string $absolutePath): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($absolutePath);

        $text = $pdf->getText();

        // Collapse excessive whitespace/newlines from PDF extraction noise
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Truncate lesson text to a safe size before sending to the LLM.
     * Keeps API cost/latency predictable for very long lesson PDFs.
     */
    public function truncateForPrompt(string $text, int $maxChars = 12000): string
    {
        if (strlen($text) <= $maxChars) {
            return $text;
        }

        return substr($text, 0, $maxChars) . "\n\n[...lesson content truncated for length...]";
    }
}
