<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile\Profile;

/**
 * Grab all the Relationships everywhere. This is a list of all the people
 * who are related in some way to a student; these are not just parents,
 * but also family members, friends, and other associates.
 *
 * Households limit it to what we know as "parents", whose relationship
 * to a student must be obtained from this mega list
 */
class Relationship
{
    /**
     * Remember the output we might have previously generated
     *
     * @var array|null
     */
    private ?array $lookup = null;


    /**
     * Constructor
     *
     * @param IApi $api
     */
    public function __construct(private IApi $api)
    {
    }


    /**
     * Generate a compiled list of records, indexed by an identifier
     *
     * @return array
     */
    public function generate(): array
    {
        /* If we generated this lookup previously, just recycle it
         */
        if (null === $this->lookup) {
            $this->lookup = [];

            /* Rebuild lookups on each page
             */
            $lookupConverter = new ValueListConverter();

            /* We only want the actual legal custody people (parents)
             * We don't need random associates
             */
            $generator = $this->api->retrieve('GET', 'relationships', Profile::SCOPE, [
                'parent_portal_access' => true
            ]);

            foreach ($generator as $page) {
                $valueLists = $lookupConverter->generate($page['value_lists']);

                /* Avengers Assemble!
                 */
                foreach ($page['data'] as $record) {
                    /* Overwrite with lookupped values
                     *   - id is the primary identifier for the relationship
                     *   - person_id is the student's id
                     *   - related_person_id is this person's external identifier equivalent
                     */
                    $compiledRecord = $record;
                    $compiledRecord['relationship'] = $valueLists['relationship'][$compiledRecord['relationship']];

                    $this->lookup[$record['person_id']][$record['related_person_id']] = $compiledRecord;
                }
            }
        }

        return $this->lookup;
    }
}
