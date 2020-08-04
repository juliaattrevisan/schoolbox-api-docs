<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;

use RuntimeException;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\IApi;

/**
 * Class Phone
 *
 * Build a lookup of phone numbers indexed by family id
 * Phone numbers can appear in any format across two specific fields
 * so you have to go hunting for data and collating it cleanly
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup
 */
class Phone
{

    /**
     * $api uses the credentials for DSNCodec::MODULE_STUDENT_DETAILS
     *
     * @var IApi
     */
    private $api;


    /**
     * Phone constructor.
     *
     * $api uses the credentials for DSNCodec::MODULE_STUDENT_DETAILS
     *
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }


    /**
     * @return array
     * @throws RuntimeException
     */
    public function generate(): array
    {
        $stack = [];

        /* Use the api endpoint to get all the data, so we don't have to smash the api
         * all day. It'll be faster, and we won't incur rate limiting wrath from tass
         */
        $addresses = $this->api->retrieveDocument('getCommunicationRulesDetails', [
            'currentstatus' => 'current',
            'commtype' => 'lw'
        ]);

        /* Inspect each element from the returned data. Family code is stored in
         * parent_code array element and we should index with that; parents in a family
         * might end up sharing phone numbers because tass
         */
        foreach ($addresses['commrules'] as $commRule) {
            foreach ($commRule['addresses'] as $address) {

                /* Phone numbers could exist in one or more of the following fields
                 * mobile_phone_1, mobile_phone_2, business_phone, home_phone
                 *
                 * Look in each and use the first non-empty mobile number
                 * Look in each and use the first non-empty work and home phone number
                 *
                 * Note that '' !== null
                 */
                $mobile = array_filter([$address['mobile_phone_1'], $address['mobile_phone_2']])[0] ?? '';
                $home = array_filter([$address['home_phone']])[0] ?? '';
                $work = array_filter([$address['business_phone']])[0] ?? '';

                /* Stack it up for quick reference later
                 */
                $stack[$address['parent_code']] = [
                    'mobile' => trim((string)$mobile),
                    'home' => trim((string)$home),
                    'work' => trim((string)$work)
                ];
            }
        }

        return $stack;
    }
}


