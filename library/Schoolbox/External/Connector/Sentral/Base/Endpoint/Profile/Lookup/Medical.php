<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Lookup;

use Schoolbox\External\Connector\Sentral\Base\Endpoint\Api;
use UnexpectedValueException;

/**
 * Class Medical
 *
 * Download a precompile a medical summary for everyone
 * Return it as a lookup struct without extra fluff
 *
 * FIXME Tell Sydney James?
 *  Medical data cannot not be sideloaded alongside student-contacts
 */
class Medical
{

    /**
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 1000;


    /**
     * @var Api
     */
    private $api;


    /**
     * A list of everyone's condition names
     *
     * @var null|array
     */
    private $stack;


    /**
     * Medical constructor.
     *
     * @param Api $api
     */
    public function __construct(Api $api)
    {
        $this->api = $api;
    }


    /**
     * Compile a lookup of medical data
     *
     * @return $this
     * @throws UnexpectedValueException
     */
    public function compile(): self
    {
        $this->stack = [];

        /* Always process the first page
         */
        $nextUrl = $this->processRecords('enrolments/medical-condition');

        /* If there are more pages, grab them too
         */
        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $nextUrl = $this->processRecords($nextUrl);
        }

        return $this;
    }


    /**
     * Process a page of data
     *
     * @param string $endpoint
     * @return string|null
     * @throws UnexpectedValueException
     */
    private function processRecords(string $endpoint): ?string
    {
        $data = $this->api->retrieveDocument($endpoint);

        foreach ($data['data'] as $medicalRecord) {
            $personId = $medicalRecord['relationships']['person']['data']['id'];

            $this->stack[$personId][] = $medicalRecord['attributes']['name'];
        }

        return $data['links']['next'] ?? null;
    }


    /**
     * @return array|null
     */
    public function getRecords(): ?array
    {
        return $this->stack;
    }
}
