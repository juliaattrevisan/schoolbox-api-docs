<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Paperly;

use Alaress\Web\Controller\Base;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Config\ConfigMapper;
use RuntimeException;
use Twig\Error\Error;
use Crell\ApiProblem\ApiProblem;
use Aura\Router\Exception\RouteNotFound;
use Alaress\Codec\Json\Base64;

class Controller extends Base
{
    private Base64 $base64Codec;
    private const PAPERLY_API_CACHE_DEFAULT_TTL = 600;
    private const PAPERLY_API_CACHE_KEY = 'paperly_dashboard_user_%s';
    private const PAPERLY_ICON_CLASSES = [
        'parent teacher interview' => 'icon-paperly-pti',
        'excursions' => 'icon-paperly-excursions',
        'forms' => 'icon-paperly-forms',
        'details' => 'icon-paperly-details',
    ];


    /**
     * Controller constructor.
     */
    public function __construct(
        private CacheInterface $paperlyCache
    ) {
        $this->base64Codec = new Base64();
    }

    /**
     * Get the hostname of Paperly endpoint with https enforced
     *
     * @return string
     */
    private function getPaperlyHostname(): string
    {
        $hostname = $this->getConfigLoader()->get(ConfigMapper::NAME_PAPERLY_API_HOSTNAME);

        //Early return if config not set
        if ($hostname == null) {
            return '';
        }

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
     * Prepare routes for paperly iframe
     *
     * @param   array $paperlyLink
     * @return  string
     * @throws  RouteNotFound
     */
    private function preparePaperlyRoute($paperlyLink): string
    {
        $parsedUrl = parse_url($paperlyLink['url']);

        $link = $this->base64Codec->encode(
            [
                'pageTitle' => $paperlyLink['title'],
                'url' => $parsedUrl['path'] . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '')
            ]
        );

        return $this->routeGenerator
            ->generate(
                'paperly.getPaperlyViewIframe',
                [
                    'encodedUrl' => urlencode($link)
                ]
            );
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
        $cacheKey = sprintf(
            self::PAPERLY_API_CACHE_KEY,
            $this->getUser()->getExternalId()
        );

        $paperlyPayload = $this->paperlyCache->get($cacheKey);

        if ($paperlyPayload === null) {
            $api = new Api(
                $this->getPaperlyHostname(),
                $this->getConfigLoader()->get(ConfigMapper::NAME_PAPERLY_API_KEY),
                $this->getUser()
            );

            $paperlyPayload = $api->retrievePaperlyIframeView();

            //If we successfully acquired the payload, save it.
            if ($paperlyPayload !== null) {
                $this->paperlyCache->set(
                    $cacheKey,
                    $paperlyPayload,
                    self::PAPERLY_API_CACHE_DEFAULT_TTL
                );
            }
        }

        return $paperlyPayload;
    }

    /**
     * Get a list of Paperly links to redirect to the matching iframes
     *
     * Returns data as JSON
     *
     * @api     GET /paperly/viewPaperly
     * @throws  RouteNotFound
     */
    public function getPaperlyLinks(): void
    {
//        $this->response = $this->responseAsJson($this->getPaperlyMockResponse());
//        return;

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
            $paperlyLinks['features'][] = [
                'url' => $this->preparePaperlyRoute($feature),
                'title' => $featureTitle,
                'icon' => self::PAPERLY_ICON_CLASSES[strtolower($featureTitle)] ?? ''
            ];
        }

        $upcomingActions = [];
        foreach ($paperlyPayload['upcoming'] as $order => $upcoming) {
            $upcoming['url'] = $this->preparePaperlyRoute($upcoming);
            $upcomingActions[$order] = $upcoming;
        }
        $paperlyLinks['upcoming'] = $upcomingActions;

        $this->response = $this->responseAsJson($paperlyLinks);
    }

    /**
     * Load an iframe for Paperly
     *
     * @param string $encodedUrl
     * @throws Error
     */
    public function getPaperlyViewIframe(string $encodedUrl): void
    {
        $path = $this->base64Codec->decode(urldecode($encodedUrl));

        $this->response = $this->responseFromTemplate('@paperly/iframe.twig', [
            //Removing trailing forward slash on hostname
            'url' => preg_replace(
                '/(?<!:)\/\//',
                '/',
                $this->getPaperlyHostname() . $path['url']
            )
        ], "Paperly " . $path['pageTitle']);
    }

//    /**
//     * Creating a mock response for testing the payload for front end
//     *
//     * @return array[]
//     */
//    private function getPaperlyMockResponse(): array
//    {
//        return [
//            "features" => [
//                [
//                    "title" => "Excursions",
//                    "url"   => "https://schoolboxtest.paperlyapp.com/excursion",
//                ],
//                [
//                    "title" => "Forms",
//                    "url"   => "https://schoolboxtest.paperlyapp.com/form-builder",
//                ],
//                [
//                    "title" => "Parent Teacher Interview",
//                    "url"   => "https://schoolboxtest.paperlyapp.com/parent-teacher-interview/event",
//                ],
//            ],
//            "upcoming" => [
//                [
//                    "title" => "Calendar Event Requests",
//                    "url"   => "https://schoolboxtest.paperlyapp.com/form/calendar-event-requests",
//                    "due"   => "2025-07-11T03:32:00.000000Z",
//                ],
//                [
//                    "title" => "Application for Staff Professional Development",
//                    "url"   => "https://schoolboxtest.paperlyapp.com/form/application-for-staff-professional-development",
//                    "due"   => "2025-07-11T03:42:00.000000Z",
//                ],
//                [
//                    "title" => "Maintenance Request",
//                    "url"   => "https://schoolboxtest.paperlyapp.com/form/maintenance-request",
//                ],
//                [
//                    "title"  => "Application for Staff Professional Development",
//                    "url"    => "https://schoolboxtest.paperlyapp.com/form-builder/submitted/8",
//                    "status" => "Pending Approval",
//                    "due"   => "2025-07-11T03:42:00.000000Z",
//                ],
//                [
//                    "title"  => "Application for Staff Professional Development",
//                    "url"    => "https://schoolboxtest.paperlyapp.com/form-builder/submitted/8",
//                    "status" => "Pending Approval",
//                ],
//            ],
//
//        ];
//    }
}
