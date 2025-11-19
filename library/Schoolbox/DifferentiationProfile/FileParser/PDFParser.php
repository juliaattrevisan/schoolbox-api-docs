<?php

namespace Schoolbox\DifferentiationProfile\FileParser;

use Schoolbox\Resource\Storage\FilesystemProxy;

class PDFParser implements IParser
{
    public function __construct(
        private \Smalot\PdfParser\Parser $parser = new \Smalot\PdfParser\Parser(),
    ) {
    }

    public function flattenText(string $filePath): string
    {
        $pdf = $this->parser->parseFile($filePath);
        return $pdf->getText();
    }
}
