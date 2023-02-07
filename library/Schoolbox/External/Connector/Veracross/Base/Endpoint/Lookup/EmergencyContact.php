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
 * Grab all the Emergency Contacts everywhere
 */
class EmergencyContact
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
     * Generate a compiled list of parents indexed by their household_id
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

            $generator = $this->api->retrieve('GET', 'emergency_contacts', Profile::SCOPE);

            foreach ($generator as $page) {
                $valueLists = $lookupConverter->generate($page['value_lists']);

                /* Avengers Assemble!
                 */
                foreach ($page['data'] as $record) {
                    /* Overwrite with lookupped values
                     */
                     $this->lookup[$record['student_id']][] = $record;
                }
            }
        }

        return $this->lookup;
    }
}
