<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Source;

use Alaress\Html\ToText as Html2Text;
use InvalidArgumentException;
use Schoolbox\Curriculum\LearningArea\Mapper as AreaMapper;
use Schoolbox\Curriculum\LearningLevel\Mapper as LevelMapper;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\Curriculum\Source\AustralianCurriculum\Browser;
use Schoolbox\Curriculum\Source\AustralianCurriculum\SparqlClient;

/**
 * Factory for creating browsers out of a curriculum source model.
 *
 * The browser knows how to get learning levels and learning areas out of a
 * source, and also how to get outcomes out of the source for a level and area.
 */
class BrowserFactory
{
    /**
     * Constructor
     *
     * @param NodeMapper  $nodeMapper
     * @param AreaMapper  $areaMapper
     * @param LevelMapper $levelMapper
     */
    public function __construct(
        private NodeMapper $nodeMapper,
        private AreaMapper $areaMapper,
        private LevelMapper $levelMapper,
    ) {
    }

    /**
     * @param Model $source
     *
     * @return BrowserInterface
     */
    public function build(Model $source): BrowserInterface
    {
        $browserClass = $source->getClass() . '\\Browser';
        return match (ltrim($browserClass, '\\')) {
            Browser::class => new Browser(
                $source,
                $this->nodeMapper,
                $this->areaMapper,
                $this->levelMapper,
                new SparqlClient(SparqlClient::API_URI),
                new Html2Text(),
            ),
            default        => throw new InvalidArgumentException("Class \"$browserClass\" does not exist"),
        };
    }
}
