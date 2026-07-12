<?php
/**
 * Best-effort text extraction from uploaded past questions.
 *
 * Tries the fast/high-quality path first (a real PDF text layer, or the
 * DOCX XML) and only falls back to OCR (Tesseract) for scanned images or
 * scanned PDFs. Requires the `tesseract` and `pdftotext`/`pdftoppm`
 * (poppler-utils) binaries on PATH - see README for install links. If
 * they're missing, every function here degrades to null instead of
 * crashing the upload; the paper still saves, it just won't have
 * searchable/AI-usable text until OCR tools are installed.
 */

declare(strict_types=1);

function shell_null_redirect(): string
{
    return stripos(PHP_OS, 'WIN') === 0 ? '2>NUL' : '2>/dev/null';
}

function extract_text_from_upload(string $absolutePath, string $mimeType): ?string
{
    return match (true) {
        $mimeType === 'application/pdf' => extract_text_from_pdf($absolutePath),
        str_starts_with($mimeType, 'image/') => extract_text_from_image($absolutePath),
        $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            => extract_text_from_docx($absolutePath),
        default => null,
    };
}

function extract_text_from_pdf(string $path): ?string
{
    $null = shell_null_redirect();

    // 1. Born-digital PDF - has a real text layer. Fast and accurate, try it first.
    $text = shell_exec('pdftotext ' . escapeshellarg($path) . " - {$null}");
    if (is_string($text) && mb_strlen(trim($text)) > 40) {
        return trim($text);
    }

    // 2. Probably a scanned PDF - rasterize each page, then OCR the images.
    $prefix = sys_get_temp_dir() . '/ocr_' . bin2hex(random_bytes(6));
    shell_exec('pdftoppm -png -r 200 ' . escapeshellarg($path) . ' ' . escapeshellarg($prefix) . " {$null}");

    $pages = glob($prefix . '*.png') ?: [];
    sort($pages);

    $combined = '';
    foreach ($pages as $page) {
        $combined .= extract_text_from_image($page) . "\n";
        @unlink($page);
    }

    $combined = trim($combined);
    return $combined !== '' ? $combined : null;
}

function extract_text_from_image(string $path): ?string
{
    $null   = shell_null_redirect();
    $output = shell_exec('tesseract ' . escapeshellarg($path) . " stdout {$null}");

    if (!is_string($output)) {
        return null;
    }

    $output = trim($output);
    return $output !== '' ? $output : null;
}

function extract_text_from_docx(string $path): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return null;
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false) {
        return null;
    }

    $text = preg_replace('/<w:p[ >]/', "\n", $xml) ?? $xml;
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = trim($text);

    return $text !== '' ? $text : null;
}

/**
 * Best-effort split of extracted paper text into individual numbered
 * questions (lines like "1.", "2)", "Question 3"). This is a heuristic,
 * not a guarantee - the admin review screen lets staff fix any
 * mis-split questions before the paper is published to students.
 *
 * @return array<int, array{question_number: string, content: string}>
 */
function split_into_questions(string $text): array
{
    $text = str_replace("\r\n", "\n", $text);

    $pattern = '/\n\s*(?:Question\s*)?(\d{1,2})[\.\)]\s+/i';
    $parts   = preg_split($pattern, "\n" . $text, -1, PREG_SPLIT_DELIM_CAPTURE);

    if ($parts === false || count($parts) < 3) {
        // No recognisable numbering found - keep the whole paper as one
        // item so the Solver still has something to work with; admin can
        // manually split it on the review screen.
        $trimmed = trim($text);
        return $trimmed === '' ? [] : [['question_number' => '1', 'content' => $trimmed]];
    }

    $items = [];
    // $parts[0] is text before the first match (usually header noise) - skip it.
    for ($i = 1; $i < count($parts); $i += 2) {
        $number  = trim($parts[$i]);
        $content = trim($parts[$i + 1] ?? '');
        if ($content !== '') {
            $items[] = ['question_number' => $number, 'content' => $content];
        }
    }

    return $items;
}
