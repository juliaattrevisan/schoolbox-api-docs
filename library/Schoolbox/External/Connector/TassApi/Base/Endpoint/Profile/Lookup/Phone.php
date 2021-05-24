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
            'commtype' => 'gen'
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
                 * Look in each and use the first non-empty home phone number
                 *
                 * Work phone can't be determined from these fields, we can't tell which parent
                 * owns that number due to the nature of the data struct
                 *
                 * Note that '' !== null
                 *
                 * + Assumption: Parents share phone numbers
                 * Note also that there's no obvious and objective way to tell which
                 * number(s) belong to each parent. This could be a significant hazard
                 * for split families.
                 */
                $stack[$address['parent_code']][Family::MOTHER_NODE] = [
                    'firstname' => trim((string)$address['m_preferred_name']),
                    'surname' => trim((string)$address['m_surname']),
                    'mobile' => trim((string)$address['mobile_phone_1']),
                    'home' => trim((string)$address['home_phone']),
                    'work' => '',
                    'email' => trim((string)$address['email']),
                    'relationship' => trim((string)$address['m_description']),
                ];

                $stack[$address['parent_code']][Family::FATHER_NODE] = [
                    'firstname' => trim((string)$address['f_preferred_name']),
                    'surname' => trim((string)$address['f_surname']),
                    'mobile' => trim((string)$address['mobile_phone_2']),
                    'home' => trim((string)$address['home_phone']),
                    'work' => '',
                    'email' => trim((string)$address['email_2']),
                    'relationship' => trim((string)$address['f_description']),
                ];
            }
        }

        return $stack;
    }
}
