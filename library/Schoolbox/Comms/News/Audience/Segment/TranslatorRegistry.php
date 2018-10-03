<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\GenericCollection;

class TranslatorRegistry implements ITranslate
{

    /**
     * @var GenericCollection|ITranslate[]
     */
    private $registry;


    /**
     * @param GenericCollection|ITranslate[] $strategies
     */
    public function __construct(GenericCollection $strategies)
    {
        $this->registry = $strategies;
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        $pieces = [];

        foreach ($this->registry as $strategy) {
            $pieces[] = "({$strategy->getTranslateSql($segmentIdsPlaceholder)})";
        }

        return \implode(' UNION ALL ', $pieces);
    }
}
