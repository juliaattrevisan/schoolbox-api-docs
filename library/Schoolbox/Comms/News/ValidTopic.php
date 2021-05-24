<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Alaress\Specification\Leaf;
use Schoolbox\Mapper\Exception;

use function strtolower;

class ValidTopic extends Leaf
{
    /** @var Topic\Mapper */
    private $topicMapper;


    /**
     * @param Topic\Mapper $topicMapper
     */
    public function __construct(Topic\Mapper $topicMapper)
    {
        $this->topicMapper = $topicMapper;
    }


    /**
     * @inheritDoc
     * @param null|string $candidate
     */
    public function isSatisfiedBy($candidate): bool
    {
        if ($candidate === null || $candidate === '' || strtolower($candidate) === Mapper::TOPIC_ALL_SLUG) {
            return true;
        }
        try {
            return (bool)$this->topicMapper->findOneByColumn('slug', $candidate);
        } catch (Exception $e) {
            // noop
        }
        return false;
    }
}
