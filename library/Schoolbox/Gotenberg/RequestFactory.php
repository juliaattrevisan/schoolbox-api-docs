<?php
/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Gotenberg;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Pipe\PipeManager;
use TheCodingMachine\Gotenberg;

/**
 * Factory for Gotenberg request objects.
 */
class RequestFactory
{
    /**
     * Config loader
     */
    private IConfigLoader $config;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Builds a URL request object.
     *
     * @param string      $url         The URL to generate a PDF for.
     * @param string|null $sessionHash If not null: set this as the session
     *                                 cookie value, thus generating the PDF for
     *                                 the user which is authenticated with this
     *                                 hash.
     * @return Gotenberg\URLRequest
     */
    public function buildUrlRequest(string $url, ?string $sessionHash): Gotenberg\URLRequest
    {
        $request = new Gotenberg\URLRequest($url);
        $request->addWebhookURLHTTPHeader(
            'SBAuth',
            $this->config->get(ConfigMapper::NAME_GOTENBERG_API_KEY)
        );
        if (isset($sessionHash)) {
            $request->addRemoteURLHTTPHeader(
                'Cookie',
                sprintf('%s=%s', PipeManager::PHP_SESSION_PREFIX, $sessionHash)
            );
        }
        return $request;
    }
}
