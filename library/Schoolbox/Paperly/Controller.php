<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Paperly;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Iframe\Validator;
use Twig\Error\Error;
use Crell\ApiProblem\ApiProblem;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(private ErrorMapper $errorMapper)
    {
    }

    /**
     * Checking whether the iframe url is valid
     *
     * @param null|string $iframeUrl
     * @throws Exception
     * @throws Error
     */
    private function validatingIframeEndpoints(null|string $iframeUrl): void
    {
        //Checking if the target endpoint exists
        if (null === $iframeUrl) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        //Making sure the url is safe to load in the iframe from payload
        if (!(new Validator($this->getConfigLoader()->get('safe_iframe_source_list')))->isValid($iframeUrl)) {
            //TODO error message might not accurately telling the user it's missing the url in the safe list
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
    }

    /**
     * Get Paperly payload from the API call
     *
     * @return array
     * @throws \JsonException
     */
    private function getPaperlyPayload(): array
    {
        $api = new Api(
            $this->getConfigLoader()->get(ConfigMapper::NAME_PAPERLY_API_HOSTNAME),
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
        } catch (\RuntimeException $e) {
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
            $paperlyLinks['features'][][$featureTitle] =
                "/paperly/view/" . str_replace(' ', '-', strtolower($featureTitle));
        }

        $upcomingActions = [];
        foreach ($paperlyPayload['upcoming'] as $order => $upcoming) {
            $upcoming['url'] = "/paperly/action/$order";
            $upcomingActions[$order] = $upcoming;
        }
        $paperlyLinks['upcoming'] = $upcomingActions;

        $this->response = $this->responseAsJson($paperlyLinks);
    }

    /**
     * Load an iframe for Paperly feature
     *
     * @param string $feature
     * @throws Exception
     * @throws Error
     */
    public function getPaperlyViewIframe(string $feature): void
    {

        $iframeUrl = null;
        $paperlyPayload = $this->getPaperlyPayload();

        $featureTitle = ucwords(str_replace('-', ' ', $feature));

        foreach ($paperlyPayload['features'] as $feat) {
            if ($feat['title'] === $featureTitle) {
                $iframeUrl = $feat['url'];
                break; //Exit early if the feature matched
            }
        }

        $this->validatingIframeEndpoints($iframeUrl);

        $this->response = $this->responseFromTemplate('@paperly/iframe.twig', [
            'url' => $iframeUrl
        ], "Paperly " . $featureTitle);
    }

    /**
     * Load an iframe for Paperly action
     *
     * @param int $order
     * @throws Exception
     * @throws Error
     */
    public function getPaperlyActionIframe(int $order): void
    {
        $paperlyPayload = $this->getPaperlyPayload();

        $iframeUrl = $paperlyPayload['upcoming'][$order]['url'] ?? null;

        $this->validatingIframeEndpoints($iframeUrl);

        $this->response = $this->responseFromTemplate('@paperly/iframe.twig', [
            'url' => $iframeUrl
        ], 'Paperly Actions');
    }
}
