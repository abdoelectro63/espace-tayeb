<?php

namespace App\Services\Shipping;

use Illuminate\Contracts\Filesystem\Filesystem;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

class ShippingInvoiceFileReader
{
    public function textFromStoragePath(Filesystem $disk, string $path): string
    {
        if (! $disk->exists($path)) {
            return '';
        }

        $mime = $disk->mimeType($path);

        if ($this->isPdf($mime, $path)) {
            return $this->textFromPdfAbsolutePath($disk->path($path));
        }

        return $disk->get($path);
    }

    private function isPdf(string $mime, string $path): bool
    {
        if (in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            return true;
        }

        return str_ends_with(strtolower($path), '.pdf');
    }

    /**
     * Uses Poppler pdftotext when available (better for invoices), then smalot only if needed — smalot
     * loads whole PDF structures in PHP and can exhaust default memory (e.g. 128M) on large files.
     *
     * @throws RuntimeException
     */
    private function textFromPdfAbsolutePath(string $absolutePath): string
    {
        $size = (int) (@filesize($absolutePath) ?: 0);
        $maxSmalot = max(0, (int) config('services.pdf.smalot_max_bytes', 2 * 1024 * 1024));

        $pdftotext = $this->tryPdftotext($absolutePath);

        if ($this->pdftotextAloneIsEnough($pdftotext)) {
            return $pdftotext;
        }

        $smalot = ($size <= $maxSmalot || $maxSmalot === 0)
            ? $this->trySmalot($absolutePath)
            : '';

        if ($pdftotext === '' && $smalot === '') {
            return '';
        }

        if ($pdftotext === '') {
            return $smalot;
        }

        if ($smalot === '') {
            return $pdftotext;
        }

        return $this->pickRicherInvoiceText($pdftotext, $smalot);
    }

    /**
     * If Poppler already extracted invoice-like text, skip smalot entirely (saves RAM and time).
     */
    private function pdftotextAloneIsEnough(string $pdftotext): bool
    {
        if ($pdftotext === '') {
            return false;
        }

        $t = mb_strtolower($pdftotext);
        if (str_contains($t, 'cl-')) {
            return true;
        }

        if (strlen($pdftotext) >= 400) {
            return true;
        }

        return $this->invoiceTextQualityScore($pdftotext) >= 80;
    }

    private function invoiceTextQualityScore(string $text): int
    {
        $t = mb_strtolower($text);

        return substr_count($t, 'cl-') * 500
            + substr_count($t, ' dh') * 50
            + substr_count($t, 'livré') * 30
            + (int) (strlen($text) / 10);
    }

    private function tryPdftotext(string $absolutePath): string
    {
        $binary = $this->resolvePdftotextBinary();
        if ($binary === null) {
            return '';
        }

        try {
            $process = new Process(array_merge(
                [$binary, '-layout', '-nopgbrk'],
                [$absolutePath, '-']
            ));
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                return '';
            }

            $text = $process->getOutput();
            if ($text !== '' && ! mb_check_encoding($text, 'UTF-8')) {
                $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
                if ($converted !== false) {
                    $text = $converted;
                }
            }

            return $text;
        } catch (\Throwable) {
            return '';
        }
    }

    private function resolvePdftotextBinary(): ?string
    {
        $configured = config('services.pdf.pdftotext_binary');
        if (is_string($configured) && $configured !== '') {
            if (@is_executable($configured) || (PHP_OS_FAMILY === 'Windows' && is_file($configured))) {
                return $configured;
            }
        }

        $finder = PHP_OS_FAMILY === 'Windows'
            ? new Process(['where', 'pdftotext'])
            : new Process(['which', 'pdftotext']);
        $finder->run();
        if (! $finder->isSuccessful()) {
            return null;
        }

        $line = trim((string) strtok($finder->getOutput(), "\r\n"));

        return $line !== '' ? $line : null;
    }

    /**
     * Prefer text that looks more like a Vitips / shipping invoice (CL-, DH, Livré).
     */
    private function pickRicherInvoiceText(string $a, string $b): string
    {
        return $this->invoiceTextQualityScore($a) >= $this->invoiceTextQualityScore($b) ? $a : $b;
    }

    private function trySmalot(string $absolutePath): string
    {
        if (! class_exists(Parser::class)) {
            $this->ensurePdfParserLoaded();
        }

        if (! class_exists(Parser::class)) {
            return '';
        }

        $prevMemory = ini_get('memory_limit');
        $bump = config('services.pdf.smalot_memory_limit', '512M');
        if (is_string($bump) && $bump !== '') {
            @ini_set('memory_limit', $bump);
        }

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($absolutePath);
            $text = $pdf->getText();
            unset($pdf, $parser);

            return $text;
        } catch (\Throwable) {
            return '';
        } finally {
            if ($prevMemory !== false) {
                @ini_set('memory_limit', (string) $prevMemory);
            }
        }
    }

    /**
     * Ensures Composer autoload and (if needed) the Parser class file are loaded.
     * Fixes environments where optimized autoload is stale after adding the package.
     */
    private function ensurePdfParserLoaded(): void
    {
        $autoload = base_path('vendor/autoload.php');
        if (is_file($autoload)) {
            require_once $autoload;
        }

        if (class_exists(Parser::class)) {
            return;
        }

        $parserFile = base_path('vendor/smalot/pdfparser/src/Smalot/PdfParser/Parser.php');
        if (is_file($parserFile)) {
            require_once $parserFile;
        }
    }
}
