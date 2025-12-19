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
 * Grab all the Parents from everywhere. Making individual requests "gimme parents of student x"
 * costs just over 1 second to execute, just to transfer the data.
 *
 * To dodge around the diabolical exchange rate we can index entire
 * parents indexed by their ID
 */
class Parents
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

            $generator = $this->api->retrieve('GET', 'parents', Profile::SCOPE);

            foreach ($generator as $page) {
                $valueLists = $lookupConverter->generate($page['value_lists']);

                /* Avengers Assemble!
                 */
                foreach ($page['data'] as $record) {
                    /* Overwrite with lookupped values
                     */
                    $compiledRecord = $record;
                    $compiledRecord['name_prefix'] = $valueLists['name_prefix'][$compiledRecord['name_prefix']];
                    $compiledRecord['name_suffix'] = $valueLists['name_suffix'][$compiledRecord['name_suffix']];

                    $this->lookup[$record['id']] = $compiledRecord;
                }
            }
        }

        return $this->lookup;
    }
}
