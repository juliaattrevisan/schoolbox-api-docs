<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;

use RuntimeException;
use Schoolbox\External\Connector\FirstMeaningfulValue;
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
    private IApi $api;


    /**
     * The address_description field in the getCommunicationRulesDetails payload
     * that refers to a father specifically, as determined by schools' instances
     *
     * @var string
     */
    private string $fatherAddressDescription;


    /**
     * The address_description field in the getCommunicationRulesDetails payload
     * that refers to a mother specifically, as determined by schools' instances
     *
     * @var string
     */
    private string $motherAddressDescription;


    /**
     * TASS communication rules type codes (in comma separated format) for all guardian relationships
     * that can be associated with students
     *
     * @var string
     */
    private string $communicationTypes;


    /**
     * Phone constructor.
     *
     * $api uses the credentials for DSNCodec::MODULE_STUDENT_DETAILS
     *
     * @param IApi   $api
     * @param string $fatherAddressDescription
     * @param string $motherAddressDescription
     * @param string $communicationTypes
     */
    public function __construct(IApi $api, string $fatherAddressDescription, string $motherAddressDescription, string $communicationTypes)
    {
        $this->api = $api;
        $this->fatherAddressDescription = $fatherAddressDescription;
        $this->motherAddressDescription = $motherAddressDescription;
        $this->communicationTypes = $communicationTypes;
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
            'commtype' => 'tkco'
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
                 * Note that '' !== null
                 */

                /* There are actually three sets of data in this payload
                 *
                 * Two data sets ars described by prefixes m_ and f_
                 *
                 * m_ are fields about the mother, specifically
                 * f_ are fields about the father, specifically
                 *
                 * The third data set doesn't have prefixes. These are fields that correspond to
                 * the person designated by address_description: this field will tell you whether
                 * it's for the mother or father, but a school can configure its value to whatever
                 * they want; that means we need a config var so the school can tell us which data
                 * set corresponds to each address_description.
                 *
                 * When address_description doesn't match one of the schoolbox-configured values
                 * it conveys a special meaning. A school might map this setup:
                 *
                 * address_description   meaning   schoolbox config var
                 * ---------------------------------------------------------------
                 * "big mama"            Mother    tass_mother_address_description
                 * "papa joel"           Father    tass_father_address_description
                 * "Value X"             --        --
                 *
                 * "Value X"
                 * When the address_description is not one of the configured values, then assume
                 * it's for a nuclear family and it should apply to both in the same way it has
                 * always done (mobile_phone_1 for mother, mobile_phone_2 for father, etc.) using that
                 * numeric suffix assumption.
                 *
                 *
                 * Comm Rules
                 * -----------
                 * When this occurs for a split family, assume that the fields
                 * [mobile_phone_1, mobile_phone_2, email_1, email_2]
                 * actually belong to one person (e.g. a guy has two phone numbers/two email addresses)
                 * and should be assigned to the guy specified by address_description
                 *
                 * For a nuclear family,
                 * [mobile_phone_1, mobile_phone_2, email_1, email_2]
                 * are actually shared/distributed among the family so continue with the previous assumption
                 * ("convention") that mobile_phone_1 and email_1 belonged to mother and mobile_phone_2 and email_2
                 * belonged to father, and carry on like we used to do
                 */

                /* Set up some of the data fields
                 */
                $mobilePhone1 = trim((string)$address['mobile_phone_1']);
                $mobilePhone2 = trim((string)$address['mobile_phone_2']);

                /* Annoyingly not following the convention
                 */
                $email1 = trim((string)$address['email']);
                $email2 = trim((string)$address['email_2']);

                /* Relationship is actually a two-part tuple:
                 *
                 * A "relationship" value like we all know, such as a mother or a father
                 * and a "relationship type" which is typically "Biological" or "Guardian"
                 * so you can tell who's a biological mum and who is a stepmum
                 *
                 * We can combine this representation
                 */
                $relationshipSuffix = trim((string)$address['relationship']);

                $relationshipM = sprintf(
                    '%s %s',
                    trim((string)$address['m_description']),
                    $relationshipSuffix ? "({$relationshipSuffix})" : ''
                );

                $relationshipF = sprintf(
                    '%s %s',
                    trim((string)$address['f_description']),
                    $relationshipSuffix ? "({$relationshipSuffix})" : ''
                );

                /* Split fam: non-prefixed fields are for a specific guy
                 */
                if ($address['address_description'] === $this->motherAddressDescription) {
                    $stack[$address['parent_code']][Family::MOTHER_NODE] = [
                        'firstname' => trim((new FirstMeaningfulValue())->find([
                            $address['m_preferred_name'],
                            $address['m_first_name']
                        ])),
                        'surname' => trim((string)$address['m_surname']),
                        'mobile' => $mobilePhone1 ?: $mobilePhone2,
                        'home' => trim((string)$address['home_phone']),
                        'work' => '',
                        'email' => $email1 ?: $email2,
                        'relationship' => $relationshipM,
                    ];
                } elseif ($address['address_description'] === $this->fatherAddressDescription) {
                    $stack[$address['parent_code']][Family::FATHER_NODE] = [
                        'firstname' => trim((new FirstMeaningfulValue())->find([
                            $address['f_preferred_name'],
                            $address['f_first_name']
                        ])),
                        'surname' => trim((string)$address['f_surname']),
                        'mobile' => $mobilePhone1 ?: $mobilePhone2,
                        'home' => trim((string)$address['home_phone']),
                        'work' => '',
                        'email' => $email1 ?: $email2,
                        'relationship' => $relationshipF,
                    ];
                } else {
                    /* Nukular family: fields are distributed among the guys in the fam
                     */
                    $stack[$address['parent_code']][Family::MOTHER_NODE] = [
                        'firstname' => trim((new FirstMeaningfulValue())->find([
                            $address['m_preferred_name'],
                            $address['m_first_name']
                        ])),
                        'surname' => trim((string)$address['m_surname']),
                        'mobile' => $mobilePhone1,
                        'home' => trim((string)$address['home_phone']),
                        'work' => '',
                        'email' => $email1,
                        'relationship' => $relationshipM,
                    ];

                    $stack[$address['parent_code']][Family::FATHER_NODE] = [
                        'firstname' => trim((new FirstMeaningfulValue())->find([
                            $address['f_preferred_name'],
                            $address['f_first_name']
                        ])),
                        'surname' => trim((string)$address['f_surname']),
                        'mobile' => $mobilePhone2,
                        'home' => trim((string)$address['home_phone']),
                        'work' => '',
                        'email' => $email2,
                        'relationship' => $relationshipF,
                    ];
                }
            }
        }

        return $stack;
    }
}
