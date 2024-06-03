<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Paperly;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Alaress\Codec\ICodec;
use RuntimeException;
use Twig\Error\Error;
use Crell\ApiProblem\ApiProblem;

class Controller extends Base implements ICodec
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }

    /**
     * Get the hostname of Paperly endpoint with https enforced
     *
     * @return string
     */
    private function getPaperlyHostname(): string
    {
        $hostname = $this->getConfigLoader()->get(ConfigMapper::NAME_PAPERLY_API_HOSTNAME);
        $parsedUrl = parse_url($hostname);

        $scheme = $parsedUrl['scheme'] ?? null;
        $host = $parsedUrl['host'] ?? null;

        //Forcing Https on url
        return $scheme === null ?
            "https://$hostname" :
            ($scheme === 'http' ?
                "https://$host" :
                $hostname);
    }
    /**
     * Encode the path to the iframe by striping the hostname to be prepended by trusted Paperly hostname
     *
     * @param   mixed $rawValue
     * @return  array
     */
    public function encode(mixed $rawValue): array
    {
        $parsedUrl = parse_url($rawValue['url']);

        return array_map(
            'urlencode',
            [
                'title' => $rawValue['title'],
                'url' => base64_encode(
                    $parsedUrl['path']
                )
            ]
        );
    }
    /**
     * Decode the path and prepend with trusted Paperly hostname
     *
     * @param   mixed $encodedValue
     * @return  array
     */
    public function decode(mixed $encodedValue): array
    {
        $urlDecodedValue = array_map('urldecode', $encodedValue);
        return [
            'title' => $urlDecodedValue['title'],
            'url' => preg_replace(
                '/(?<!:)\/\//',
                '/',
                $this->getPaperlyHostname() . base64_decode($urlDecodedValue['url'])
            )
        ];
    }

    /**
     * Get Paperly payload from the API call
     *
     * @return array
     * @throws RuntimeException
     * @throws \JsonException
     */
    private function getPaperlyPayload(): array
    {

        $api = new Api(
            $this->getPaperlyHostname(),
            $this->getConfigLoader()->get(ConfigMapper::NAME_PAPERLY_API_KEY),
            $this->getUser()
        );

        /**
         * Currently the Paperly endpoint is called everytime function runs, might potentially hit rate limit
         */
        $paperlyPayload = $api->retrievePaperlyIframeView();

        return $paperlyPayload;
    }

    /**
     * Get a list of Paperly links to redirect to the matching iframes
     *
     * Returns data as JSON
     *
     * @api     GET /paperly/viewPaperly
     */
    public function getPaperlyLinks(): void
    {
        $paperlyLinks = [
            'features' => []
        ];

        try {
            $paperlyPayload = $this->getPaperlyPayload();
        } catch (RateLimitException $e) {
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus(429)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(429);
            return;
        } catch (RuntimeException $e) {
            $problem = (new ApiProblem('Paperly endpoint is not working.'))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        } catch (\Exception $e) {
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }
        foreach ($paperlyPayload['features'] as $feature) {
            $featureTitle = $feature['title'];
            $path = $this->encode($feature);
            $paperlyLinks['features'][][$featureTitle] =
                $this->routeGenerator
                    ->generate(
                        'paperly.getPaperlyViewIframe',
                        [
                            'pageTitle' => $path['title'],
                            'encodedUrl' => $path['url']
                        ]
                    );
        }

        $upcomingActions = [];
        foreach ($paperlyPayload['upcoming'] as $order => $upcoming) {
            $path = $this->encode($upcoming);
            $upcoming['url'] = $this->routeGenerator
                ->generate(
                    'paperly.getPaperlyViewIframe',
                    [
                        'pageTitle' => $path['title'],
                        'encodedUrl' => $path['url']
                    ]
                );
            $upcomingActions[$order] = $upcoming;
        }
        $paperlyLinks['upcoming'] = $upcomingActions;

        $this->response = $this->responseAsJson($paperlyLinks);
    }

    /**
     * Load an iframe for Paperly
     *
     * @param string $encodedUrl
     * @param string $pageTitle
     * @throws Error
     */
    public function getPaperlyViewIframe(string $encodedUrl, string $pageTitle): void
    {
        $path = $this->decode(
            [
                'title' => $pageTitle,
                'url' => $encodedUrl
            ]
        );

        $this->response = $this->responseFromTemplate('@paperly/iframe.twig', [
            'url' => $path['url']
        ], "Paperly " . $path['title']);
    }
}
