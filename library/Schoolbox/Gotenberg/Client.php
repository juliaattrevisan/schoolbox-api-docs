<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Gotenberg;

use Gotenberg\Modules\Chromium;
use Gotenberg\Modules\PdfEngines;
use Http\Client\HttpClient;

/**
 * Wrapper around Gotenberg's API.
 */
class Client
{
    public function __construct(
        private HttpClient $httpClient,
        private Chromium $htmlToPdf,
        private PdfEngines $pdfEngine,
    ) {
    }

    public function urlToPdf(string $url)
    {
        return $this->htmlToPdf->url($url);
    }
}
