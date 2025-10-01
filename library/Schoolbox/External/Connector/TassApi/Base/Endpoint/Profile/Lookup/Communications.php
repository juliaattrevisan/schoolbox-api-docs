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
 * Class Communications
 *
 * Build a lookup of phone numbers indexed by family id
 * Phone numbers can appear in any format across two specific fields
 * so you have to go hunting for data and collating it cleanly
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup
 */
class Communications
{
    /**
     * This add_num is always used in a pseudo-mandatory way across the fleet.
     * As far as I can tell this is consistently used as a global catch-all
     * for communications across the tass platform
     *
     * Every other address block other than 1 is configurable by the school
     * in whatever haphazard manner tass allows
     */
    private const ADDNUM_CORRESPONDENCE = 1;


    /**
     * Decide which set of comm rules to obtain using these two consts as args.
     */
    private const CURRENT = 'current';
    private const FUTURE = 'future';


    /**
     * Phone constructor.
     *
     * $api uses the credentials for DSNCodec::MODULE_STUDENT_DETAILS
     *
     * @param IApi $api                  $api uses the credentials for DSNCodec::MODULE_STUDENT_DETAILS
     * @param int $addnumFather          The add_num field in the getCommunicationRulesDetails payload
     *                                   that refers to a father specifically, as determined by schools' instances
     * @param int $addnumMother          The add_num field in the getCommunicationRulesDetails payload
     *                                   that refers to a mother specifically, as determined by schools' instances
     * @param string $communicationTypes TASS communication rules type codes (in comma separated format) for all guardian relationships
     *                                   that can be associated with students
     */
    public function __construct(
        private IApi $api,
        private int $addnumFather,
        private int $addnumMother,
        private string $communicationTypes
    ) {
    }


    /**
     * Generate a set of comm rules for current parents
     *
     * @return array
     */
    public function generateCurrent(): array
    {
        return $this->generate(self::CURRENT);
    }


    /**
     * Generate a set of comm rules for future parents
     *
     * @return array
     */
    public function generateFuture(): array
    {
        return $this->generate(self::FUTURE);
    }


    /**
     * @param string $type
     * @return array
     */
    private function generate(string $type): array
    {
        $stack = [];

        /* Use the api endpoint to get all the data, so we don't have to smash the api
         * all day. It'll be faster, and we won't incur rate limiting wrath from tass
         */
        $addresses = $this->api->retrieveDocument('getCommunicationRulesDetails', [
            'currentstatus' => $type,
            'commtype' => $this->communicationTypes
        ]);

        /* Inspect each element from the returned data. Family code is stored in
         * parent_code array element and we should index with that; parents in a family
         * might end up sharing phone numbers because tass
         */
        foreach ($addresses['commrules'] as $commRule) {
            /* Filter out address blocks we don't care about:
             *    * we need address block 1 (as per the presumed const, ADDNUM_CORRESPONDENCE)
             *    * we need addnum_father (as configured by schoolbox config)
             *    * we need addnum_mother (as configured by schoolbox config)
             */
            $addnumFather = $this->addnumFather;
            $addnumMother = $this->addnumMother;

            $addressBlocks = array_filter($commRule['addresses'], static function ($a) use ($addnumFather, $addnumMother) {
                return \in_array((int)$a['add_num'], [
                    self::ADDNUM_CORRESPONDENCE,
                    $addnumFather,
                    $addnumMother
                ], true);
            });

            /* Pre-sort by add_num, which is the address block number
             */
            usort($addressBlocks, static function ($a, $b) {
                $addnumA = (int)$a['add_num'];
                $addnumB = (int)$b['add_num'];

                if ($addnumA > $addnumB) {
                    return 1;
                }

                if ($addnumA < $addnumB) {
                    return -1;
                }

                return 0;
            });

            /* Now figure out how all these address blocks come together
             */
            foreach ($addressBlocks as $address) {
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
                 * the person designated by add_num: this field will tell you whether
                 * it's for the mother or father, but a school can configure its value to whatever
                 * they want; that means we need a config var so the school can tell us which data
                 * set corresponds to each add_num.
                 *
                 * When add_num is 1, it conveys a special meaning. Mothers and fathers are usually
                 * assigned to add_nums 4 and 5 but a school can choose whichever ones they want.
                 *
                 * A school might map this setup:
                 *
                 * add_num         meaning           schoolbox config var
                 * ---------------------------------------------------------------
                 *  1              Correspondence
                 *  4              Mother            external_tass_addnum_mother
                 *  5              Father            external_tass_addnum_father
                 * "Value X"       --                --
                 *
                 * "Value X"
                 * When the add_num is not one of the configured values, or specifically 1,
                 * then disregard it. These usually contain things like Emergency Contacts,
                 * Grandmothers, random associates and other non-parent information. These
                 * will have already been filtered out by the time you get here so you don't
                 * have to worry too much about it.
                 *
                 * For add_num 1, we use it for a nuclear family and it should apply in the same way
                 * it has always done (mobile_phone_1 for mother, mobile_phone_2 for father, etc.)
                 * using that numeric suffix assumption.
                 *
                 * Comm Rules
                 * -----------
                 * When this occurs for a split family, assume that the fields
                 * [mobile_phone_1, mobile_phone_2, email_1, email_2]
                 * actually belong to one person (e.g. a guy has two phone numbers/two email addresses)
                 * and should be assigned to the guy specified by add_num
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
                if ((int)$address['add_num'] === $this->addnumMother) {
                    $firstname = trim((string)$address['m_first_name']);

                    $stack[$address['parent_code']][Family::MOTHER_NODE] = [
                        'firstname' => $firstname,
                        'firstnamePreferred' => trim((new FirstMeaningfulValue())->find([
                            $address['m_preferred_name'],
                            $firstname
                        ])),
                        'surname' => trim((string)$address['m_surname']),
                        'mobile' => $mobilePhone1 ?: $mobilePhone2,
                        'home' => trim((string)$address['home_phone']),
                        'work' => trim((string)$address['business_phone']),
                        'email' => $email1 ?: $email2,
                        'relationship' => $relationshipM,
                        'validCommRule' => true
                    ];
                } elseif ((int)$address['add_num'] === $this->addnumFather) {
                    $firstname = trim((string)$address['f_first_name']);

                    $stack[$address['parent_code']][Family::FATHER_NODE] = [
                        'firstname' => $firstname,
                        'firstnamePreferred' => trim((new FirstMeaningfulValue())->find([
                            $address['f_preferred_name'],
                            $firstname
                        ])),
                        'surname' => trim((string)$address['f_surname']),
                        'mobile' => $mobilePhone1 ?: $mobilePhone2,
                        'home' => trim((string)$address['home_phone']),
                        'work' => trim((string)$address['business_phone']),
                        'email' => $email1 ?: $email2,
                        'relationship' => $relationshipF,
                        'validCommRule' => true
                    ];
                } elseif ((int)$address['add_num'] === self::ADDNUM_CORRESPONDENCE) {
                    /* Nukular family: fields are distributed among the guys in the fam
                     */
                    $firstname = trim((string)$address['m_first_name']);

                    $stack[$address['parent_code']][Family::MOTHER_NODE] = [
                        'firstname' => $firstname,
                        'firstnamePreferred' => trim((new FirstMeaningfulValue())->find([
                            $address['m_preferred_name'],
                            $firstname
                        ])),
                        'surname' => trim((string)$address['m_surname']),
                        'mobile' => $mobilePhone1,
                        'home' => trim((string)$address['home_phone']),
                        'work' => trim((string)$address['business_phone']),
                        'email' => $email1,
                        'relationship' => $relationshipM,
                        'validCommRule' => true
                    ];

                    $firstname = trim((string)$address['f_first_name']);

                    $stack[$address['parent_code']][Family::FATHER_NODE] = [
                        'firstname' => $firstname,
                        'firstnamePreferred' => trim((new FirstMeaningfulValue())->find([
                            $address['f_preferred_name'],
                            $firstname
                        ])),
                        'surname' => trim((string)$address['f_surname']),
                        'mobile' => $mobilePhone2,
                        'home' => trim((string)$address['home_phone']),
                        'work' => trim((string)$address['business_phone']),
                        'email' => $email2,
                        'relationship' => $relationshipF,
                        'validCommRule' => true
                    ];
                }

                /* Some other addnum that wasn't filtered out;
                 * ignore it and continue onwards
                 */
            }
        }

        return $stack;
    }
}
