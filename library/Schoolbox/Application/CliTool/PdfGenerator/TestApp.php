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
//                    $baseUrl->setPath('/homepage/22')->toString(),
                ],
            ]
        ];
    }
}
