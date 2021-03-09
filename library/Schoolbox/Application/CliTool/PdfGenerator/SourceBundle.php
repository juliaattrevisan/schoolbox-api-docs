<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool\PdfGenerator;

/**
 * Bundle for various data relating to PDF generation.
 */
class SourceBundle
{
    /**
     * The name of the PDF file to concatenate the result of `$this->urls` into.
     *
     * @var string
     */
    private $filename;


    /**
     * A list of URLs to concatenate into a single PDF.
     *
     * @var array|string[]
     */
    private $urls;


    /**
     * A log message to output before generating a PDF from this set of URLs.
     *
     * @var string|null
     */
    private $message;


    /**
     * Constructor
     *
     * @param string         $filename
     * @param array|string[] $urls
     * @param string|null    $message
     */
    public function __construct(string $filename, array $urls, string $message = null)
    {
        $this->filename = $filename;
        $this->urls     = $urls;
        $this->message  = $message;
    }


    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }


    /**
     * @return array|string[]
     */
    public function getUrls()
    {
        return $this->urls;
    }


    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}
