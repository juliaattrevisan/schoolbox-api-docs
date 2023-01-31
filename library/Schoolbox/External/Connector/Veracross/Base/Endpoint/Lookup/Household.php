<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\ValueListConverter;

/**
 * Grab all the Parents from everywhere. This is the Household concept
 * in Veracross. Making individual requests "gimme parents of student x"
 * costs just over 1 second to execute, just to transfer the data.
 *
 * To dodge around the diabolical exchange rate we can index entire
 * families by their household id
 */
class Household
{
    /**
     * Remember the output we might have previously generated
     *
     * @var array|null
     */
    private ?array $household = null;


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
        if (null === $this->household) {
            $this->household = [];

            /* Rebuild lookups on each page
             */
            $lookupConverter = new ValueListConverter();

            $parentPayloadGenerator = $this->api->retrieve('GET', 'parents');

            foreach ($parentPayloadGenerator as $parentPage) {
                $parentLookups = $lookupConverter->generate($parentPage['value_lists']);

                /* Avengers Assemble!
                 */
                foreach ($parentPage['data'] as $parent) {
                    /* Overwrite with lookupped values
                     */
                    $compiledParent = $parent;
                    $compiledParent['name_prefix'] = $parentLookups['name_prefix'][$compiledParent['name_prefix']];
                    $compiledParent['name_suffix'] = $parentLookups['name_suffix'][$compiledParent['name_suffix']];

                    $this->household[$parent['household_id']][] = $compiledParent;
                }
            }
        }

        return $this->household;
    }
}
