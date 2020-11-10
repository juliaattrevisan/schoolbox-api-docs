<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool\PdfGenerator;

use Zend\Uri\UriInterface;

/**
 * Test application for the CLI PDF generator.
 *
 * All this does is generate a PDF of the dashboard for the authenticated user.
 */
class TestApp extends App
{
    protected function printHelp(): void
    {
        $file           = $this->getToolFilename();
        $green          = self::COLOUR_GREEN;
        $white          = self::COLOUR_WHITE;
        $yellow         = self::COLOUR_YELLOW;
        $baseOptionHelp = $this->generateHelpMessageForBaseOptions();
        $help           = "{$white}NAME:
    CLI PDF Generation Test App

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    A proof-of-concept application for generating an archive of PDFs from
    Schoolbox content.

    This application simply generates a PDF of the dashboard for the
    authenticated user.

OPTIONS
$baseOptionHelp

EXAMPLES:
 * Write log to terminal, output to archive.zip
      $file 1> archive.zip
      $file -o archive.zip
 * Append log to export.log, output to archive.zip
      $file 2>> export.log 1> archive.zip
      $file -l export.log -o archive.zip
";
        fwrite(STDOUT, $help);
    }

    /**
     * @inheritdoc
     */
    protected function generatePdfSources(UriInterface $baseUrl): array
    {
        return [
            [
                'filename' => 'test.pdf',
                'message'  => 'Generating test.pdf',
                'urls'     => [
                    $baseUrl->setPath('/')->toString(),
                ],
            ],
        ];
    }
}
