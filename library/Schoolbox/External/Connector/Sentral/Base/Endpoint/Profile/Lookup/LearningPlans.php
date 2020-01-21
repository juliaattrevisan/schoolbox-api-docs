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
 * Class LearningPlans
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Lookup
 */
class LearningPlans
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
     * A list of available learning plans (among others)
     * These seem synonymous with Individual Learning Plans
     * The learning-related ones seem to show in either the parent or student portals
     *
     * All the others are things like risk management, emergency care, etc. and they
     * don't show up in either portal (I don't know if this is configurable)
     *
     * FIXME Ask Sydney James about plan usage and how to identify ILP
     *
     * @var array
     */
    private $availablePlans;


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
        /* Set up available plans
         */
        $this->compileAvailablePlans();

        $this->stack = [];

        /* Always process the first page
         */
        $nextUrl = $this->processRecords('plans/student-plan?limit=200');

        /* If there are more pages, grab them too
         */
        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $nextUrl = $this->processRecords($nextUrl);
        }

        return $this;
    }


    /**
     * Compile a list of valid learning plans per person, I guess
     *
     * @param string $endpoint
     * @return string|null
     * @throws UnexpectedValueException
     */
    private function processRecords(string $endpoint): ?string
    {
        $this->stack = [];

        $data = $this->api->retrieveDocument($endpoint);

        foreach ($data['data'] as $record) {
            $planTypeId = $record['relationships']['type']['data']['id'];

            /* Only collate plans that are learning-related
             * see processAvailablePlans()
             */
            if (isset($this->availablePlans[$planTypeId])) {
                $studentId = $record['relationships']['student']['data']['id'];

                /* ILP is a flag so we only need to know that plans exist, not what they are
                 */
                $this->stack[$studentId] = true;
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


    /**
     * Get a list of available learning-related plans
     *
     * @return $this
     * @throws UnexpectedValueException
     */
    private function compileAvailablePlans(): self
    {
        $this->availablePlans = [];

        /* Always process the first page
         */
        $nextUrl = $this->processAvailablePlans('plans/student-plan-type?limit=200');

        /* If there are more pages, grab them too
         */
        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $nextUrl = $this->processAvailablePlans($nextUrl);
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
    private function processAvailablePlans(string $endpoint): ?string
    {
        $data = $this->api->retrieveDocument($endpoint);

        /* Only collate plans that are learning-related
         * The definition of that is assumed to be the visibility in either
         * student or parent portals according to the keys 'showInStudentPortal'
         * and 'showInParentPortal' respectively
         */
        foreach ($data['data'] as $record) {
            if ((bool)$record['attributes']['showInParentPortal'] || (bool)$record['attributes']['showInStudentPortal']) {
                $planTypeId = $record['id'];

                $this->availablePlans[$planTypeId] = $record['attributes']['displayName'];
            }
        }

        return $data['links']['next'] ?? null;
    }
}
