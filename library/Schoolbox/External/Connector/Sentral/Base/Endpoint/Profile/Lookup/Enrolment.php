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
 * Class Enrolment
 *
 * This happens to contain start/end dates for students,
 * in addition to their school year level and boarding status
 * Also tells you whether they're an active enrolment in the school
 *
 * FIXME Tell Sydney James?
 *  Enrolment data cannot not be sideloaded alongside student-contacts
 */
class Enrolment
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
        $nextUrl = $this->processRecords('enrolments/enrolment?limit=200');

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

        foreach ($data['data'] as $record) {
            $studentId = $record['relationships']['student']['data']['id'];

            /* TODO: There's a link to a House here
             */
            $this->stack[$studentId] = [
                'startDate' => $record['attributes']['startDate'],
                'endDate' => $record['attributes']['endDate'],
                'year' => $record['attributes']['schoolYear'],
                'isBoarding' => $record['attributes']['isBoarding'],
                'isActive' => $record['attributes']['isActive'],
            ];
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
