<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Presidio;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Schoolbox\Url\Uri;

/**
 * Client for Presidio anonymisation service.
 */
class Client
{
    /**
     * URL for analysis of text, relative to `$this->analysisUrl`.
     *
     * Text must be passed through the analysis service before it is able to be
     * anonymised: this analysis is passed to the anonymisation service.
     */
    private const ANALYSIS_ENDPOINT = '/analyze';

    /**
     * URL for anonymisation of text, relative to `$this->anonymisationUrl`.
     */
    private const ANONYMISATION_ENDPIONT = '/anonymize';
    /**
     * URL for de-anonymisation of text, relative to `$this->anonymisationUrl`.
     */
    private const DEANONYMISATION_ENDPIONT = '/deanonymize';

    /**
     * Constructor
     *
     * @param HttpClient $httpClient       Used for making HTTP requests.
     * @param string     $analysisUrl      URL at which Presidio's analysis service is hosted.
     * @param string     $anonymisationUrl URL at which Presidio's anonymisation service is hosted.
     */
    public function __construct(
        private HttpClient $httpClient,
        private string $analysisUrl,
        private string $anonymisationUrl,
    ) {
    }

    /**
     * Analyse `$text`, detecting parts thereof which could be anonymised.
     *
     * @param string $text Text to analyse.
     * @return array An array payload which may be passed to `$this->anonymise()`.
     * @throws ClientExceptionInterface if the request goes wrong
     */
    public function analyse(string $text): array
    {
        $analysisUrl      = new Uri($this->analysisUrl);
        $analysisUrl      = $analysisUrl->withPath(rtrim($analysisUrl->getPath(), '/') . self::ANALYSIS_ENDPOINT);
        // TODO figure out how to determine language:
        //      config? hardcode? define from user session?
        $analysisResponse = $this->httpClient->request(
            'POST',
            $analysisUrl,
            [
                RequestOptions::JSON => [
                    'text'                    => $text,
                    'language'                => 'en',
                    'return_decision_process' => true,
                ],
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json'
                ],
            ],
        );

        return json_decode($analysisResponse->getBody()->getContents(), true);
    }

    /**
     * Anonymise `$text`.
     *
     * @param string                 $text          Text to anonymise.
     * @param array                  $analyseResult The analysis of `$text`, from `$this->analyse()`.
     * @param Anonymiser\IAnonymiser $anonymiser    The anonymiser to use. Currently, only a default is used.
     * @return array{text: string, items: array} text: anonymised text
     *                                           items: a list of what was anonymised, which could be passed to
     *                                           `$this->deanonymise()` if the `encrypt` anonymisation strategy was used
     *                                           to anonymise.
     * @throws ClientExceptionInterface if the request goes wrong
     */
    public function anonymise(string $text, array $analyseResult, Anonymiser\IAnonymiser $anonymiser): array
    {
        $anonymisationUrl      = new Uri($this->anonymisationUrl);
        $anonymisationUrl      = $anonymisationUrl->withPath(rtrim($anonymisationUrl->getPath(), '/') . self::ANONYMISATION_ENDPIONT);
        $anonymisationResponse = $this->httpClient->request(
            'POST',
            $anonymisationUrl,
            [
                RequestOptions::JSON => [
                    'text'             => $text,
                    'analyzer_results' => $analyseResult,
                    'anonymizers'      => [
                        'DEFAULT' => $anonymiser->buildPayload(),
                    ]
                ],
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json'
                ],
            ],
        );

        return json_decode($anonymisationResponse->getBody()->getContents(), true);
    }

    /**
     * De-anonymises `$text`, according to `$anonymiseResult`.
     *
     * @param string             $text            Text to de-anonymise, from the `text` key of `$this->anonymise()`.
     * @param array              $anonymiseResult Result of the anonymisation process, from the `items` key of
     *                                            `$this->anonymise()`.
     * @param Anonymiser\Decrypt $anonymiser      The decrypt anonymiser to use: must use the same key as the
     *                                            `Anonymiser\Encrypt` instance used in `$this->anonymise()`.
     * @return array{text: string, items: array} text: de-anonymised text
     *                                           items: a list of what was de-anonymised
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deanonymise(string $text, array $anonymiseResult, Anonymiser\Decrypt $decrypt): array
    {
        $deanonymisationUrl      = new Uri($this->anonymisationUrl);
        $deanonymisationUrl      = $deanonymisationUrl->withPath(rtrim($deanonymisationUrl->getPath(), '/') . self::DEANONYMISATION_ENDPIONT);
        $deanonymisationResponse = $this->httpClient->request(
            'POST',
            $deanonymisationUrl,
            [
                RequestOptions::JSON => [
                    'text'               => $text,
                    'anonymizer_results' => $anonymiseResult,
                    'deanonymizers'      => [
                        'DEFAULT' => $decrypt->buildPayload(),
                    ]
                ],
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json'
                ],
            ],
        );

        return json_decode($deanonymisationResponse->getBody()->getContents(), true);
    }
}
