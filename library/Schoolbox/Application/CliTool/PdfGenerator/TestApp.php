<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool\PdfGenerator;

use Alaress\Authorizer\Failure\Unauthenticated;
use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\GenericCollection;
use Schoolbox\User;
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
    $green{$file} {$yellow}[OPTION]... [PATH]...$white

DESCRIPTION
    A test application for generating an archive of PDFs from Schoolbox content.

    Each PATH will be added to the PDF; if no paths are provided, the PDF will
    contain only the user's dashboard.
    (PATHs may only be provided as relative to Schoolbox's base URL; any PATH
    which is not relative will raise an error.)

OPTIONS
$baseOptionHelp

EXAMPLES:
 * Write log to terminal, output to archive.zip
      $file 1> archive.zip
      $file -o archive.zip
 * Append log to export.log, output to archive.zip
      $file 2>> export.log 1> archive.zip
      $file -l export.log -o archive.zip
 * Output /, /calendar, /resources to archive.zip
      $file -l export.log -o archive.zip / /calendar /resources
";
        fwrite(STDOUT, $help);
    }


    /**
     * This tool requires that the authenticated user be a superuser.
     *
     * @param User|null $user
     *
     * @throws Unauthenticated if no user is authenticated
     * @throws Unauthorized    if user is not a superuser
     */
    protected function authorizeUser(?User $user): void
    {
        $message = 'This tool requires that a user be authenticated, and that they be a superuser';
        if (!$user) {
            throw new Unauthenticated($message);
        }
        if (!$user->isSuperuser()) {
            throw new Unauthorized($message);
        }
    }


    /**
     * @inheritdoc
     */
    protected function generatePdfSources(UriInterface $baseUrl): GenericCollection
    {
        return new GenericCollection(
            SourceBundle::class,
            [
                new SourceBundle(
                    'test.pdf',
                    [$baseUrl->setPath('/')->toString(),],
                    'Generating test.pdf'
                ),
            ]
        );
    }
}
