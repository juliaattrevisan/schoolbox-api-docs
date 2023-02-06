<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Gotenberg;

use Gotenberg\Modules\Chromium;
use Gotenberg\Modules\PdfEngines;
use GuzzleHttp\Cookie\CookieJar;
use Http\Client\HttpClient;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\Realtime\Pipe\PipeManager;

/**
 * Wrapper around Gotenberg's API.
 */
class Client
{
    public function __construct(
        private HttpClient $httpClient,
        private Chromium $htmlToPdf,
        private PdfEngines $pdfEngine,
        private string $apiKey,
    ) {
        // Pre-configure Chromium and PDF engines
    }

    public function htmlToPdf(?string $sessionHash): Chromium
    {
        if (isset($sessionHash)) {
            return $this->htmlToPdf->extraHttpHeaders([
                'Cookie' => sprintf('%s=%s', PipeManager::PHP_SESSION_PREFIX, $sessionHash)
            ]);
        }
        return $this->htmlToPdf->extraHttpHeaders([]);
    }

    public function merge(): PdfEngines
    {
        return $this->pdfEngine;
    }

    /**
     * Send a request created with a Gotenberg client.
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws ClientExceptionInterface if for some reason it couldn't be sent
     */
    public function send(RequestInterface $request): ResponseInterface
    {
        // Add webhook API key here:
        // note that this uses the old header instead of the new one which
        // `\Gotenberg\MultipartFormDataModule::webhook` adds.
        if ($this->apiKey) {
            $request = $request->withHeader('Gotenberg-Webhookurl-SBAuth', $this->apiKey);
        }
        return $this->httpClient->sendRequest($request);
    }
}
