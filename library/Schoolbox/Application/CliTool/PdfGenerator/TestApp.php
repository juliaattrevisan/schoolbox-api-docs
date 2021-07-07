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
use Laminas\Uri\UriInterface;

/**
 * Test application for the CLI PDF generator.
 *
 * All this does is generate a PDF of any URLs passed in the command: defaults
 * to only including the dashboard for the authenticated user.
 */
class TestApp extends App
{
    /**
     * Option for URLs to include within the generated PDF.
     */
    private const OPT_SHORT_URL = 'u:';
    private const DEFAULT_URLS = ['/'];

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return array_merge(parent::getShortOpts(), [self::OPT_SHORT_URL]);
    }

    /**
     * @inheritdoc
     */
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
    A test application for generating an archive of PDFs from Schoolbox content.

OPTIONS
$baseOptionHelp

    $yellow-u=URL$white
        A URL to include within the PDF. This option may be provided multiple
        times, but only URLs relative to the Schoolbox instance's base path will
        be accepted.
        Defaults to only including the user's dashboard.

EXAMPLES:
 * Write log to terminal, output to archive.zip
      $file 1> archive.zip
      $file -o archive.zip
 * Append log to export.log, output to archive.zip
      $file 2>> export.log 1> archive.zip
      $file -l export.log -o archive.zip
 * Output /, /calendar, /resources to archive.zip
      $file -l export.log -o archive.zip -u / -u /calendar -u /resources
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
        $urls = $this->getOpt(self::OPT_SHORT_URL);
        if (!isset($urls)) {
            $urls = self::DEFAULT_URLS;
        }
        if (!is_array($urls)) {
            $urls = [$urls];
        }

        $outputUrls = [];
        foreach ($urls as $url) {
            $outputUrls[] = $baseUrl->setPath($url)->toString();
        }

        return new GenericCollection(
            SourceBundle::class,
            [
                new SourceBundle('test.pdf', $outputUrls, 'Generating test.pdf'),
            ]
        );
    }
}
