<?php

namespace Schoolbox\DifferentiationProfile\FileParser;

use Smalot\PdfParser\Parser;

class PDFParser implements IParser
{
    /**
     * Constructor
     *
     * @param Parser $parser The PDF parser to use
     */
    public function __construct(
        private readonly Parser $parser = new Parser(),
    ) {
    }

    /**
     * @inheritdoc
     */
    public function flattenText(string $filePath): string
    {
        $pdf = $this->parser->parseFile($filePath);
        return $pdf->getText();
    }
}
