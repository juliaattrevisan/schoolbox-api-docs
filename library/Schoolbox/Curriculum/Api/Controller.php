<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Curriculum\Node;
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;

/**
 * Controller for the curriculum API.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param Node\Mapper     $nodeMapper      Curriculum node mapper
     * @param ArticleResolver $articleResolver Article resolver: required in search for some reason?
     */
    public function __construct(
        private Node\Mapper $nodeMapper,
        private ArticleResolver $articleResolver
    ) {
    }

    public function getNode(Node\Model $node): void
    {

    }
}
