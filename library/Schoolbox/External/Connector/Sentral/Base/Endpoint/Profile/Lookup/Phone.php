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
 * Class Phone
 *
 * Finds mobile phone numbers specifically
 *
 * FIXME Tell Sydney James?
 *  Phone data cannot not be sideloaded alongside student-contacts
 */
class Phone
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
     * Compile a lookup of enrolment
     *
     * @return $this
     * @throws UnexpectedValueException
     */
    public function compile(): self
    {
        $this->stack = [];

        /* Always process the first page
         */
        $nextUrl = $this->processRecords('enrolments/person-phone?limit=200');

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

        /* Some mobiles might not be intended for contact
         * If canContact != 1, then ignore it
         */
        foreach ($data['data'] as $record) {
            if ((bool)$record['attributes']['canContact']) {
                $personId = $record['relationships']['owner']['data']['id'];

                $type = trim(strtolower($record['attributes']['typeName'] ?? ''));

                /* Only remember one of these numbers
                 */
                if (!isset($this->stack[$personId][$type])) {
                    $this->stack[$personId][$type] = trim(implode(' ', [
                        trim((string)$record['attributes']['extension']),
                        trim((string)$record['attributes']['number']),
                    ]));
                }
            }
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
