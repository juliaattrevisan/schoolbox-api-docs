<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Api\Usage;

use Alaress\Database\Connector\IConnector;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Curriculum\Node;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Badge\Translator\Links as BadgeLinksTranslator;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Badge as Badge;
use Schoolbox\Resource\Folder;

/**
 * Controller for the curriculum usage API.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param IConnector                       $database             Database required for building cursor object
     * @param Folder\Mapper                    $folderMapper         Folder mapper
     * @param CourseSpec\Mapper                $courseSpecMapper     Course specification mapper
     * @param UnitSpec\Mapper                  $unitSpecMapper       Unit specification mapper
     * @param Assessment\DataModel\MapperProxy $assessmentDataSource Data source for assessment
     * @param Counter                          $counter              Counter for node usages
     */
    public function __construct(
        private IConnector $database,
        private ConfigMapper $configMapper,
        private Folder\Mapper $folderMapper,
        private CourseSpec\Mapper $courseSpecMapper,
        private UnitSpec\Mapper $unitSpecMapper,
        private Assessment\DataModel\MapperProxy $assessmentDataSource,
        private Counter $counter,
        private Badge\Mapper $badgeMapper
    ) {
    }

    /**
     * Endpoint for fetching a curriculum node's usage.
     *
     * @api   GET /api/curriculum/usage/{id}
     * @since 23.0
     *
     * @param Node\Model $node
     * @return void
     */
    public function getNodeUsage(Node\Model $node): void
    {
        // Fetch usage
        $encodedCursor = $this->requestQuery->get('cursor');
        $limit         = $this->requestQuery->getInt('limit', Cursor::LIMIT_DEFAULT);
        $cursor        = new Cursor($this->database, $this->configMapper, $node, $this->counter, $limit, null);
        $data          = $cursor->retrieveResults($encodedCursor);

        // I can't create this in the module because a user object is required,
        // and the user doesn't reliably exist in the module.
        $folderTranslator = new Folder\Translator\ModelToArray\LinkOnly(1);
        $courseTranslator = new CourseSpec\Translator\ModelToArray(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $folderTranslator,
        );
        $unitTranslator = new UnitSpec\Translator\ModelToArray(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $folderTranslator,
            $courseTranslator,
        );
        $assessmentTranslator = new Translator\Assessment\ModelToArray(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $unitTranslator,
        );
        $prefixGenerator = new PrefixGenerator($this->getConfigLoader());
        $badgeTranslator = new \Schoolbox\Learning\Badge\Translator\ModelToArray(
            new \Schoolbox\User\Translator\Author\ModelToArray(),
            new \Schoolbox\Resource\Storage\Translator\ModelToArray($prefixGenerator),
            new ScrubAdapter(),
            new BadgeLinksTranslator($this->routeGenerator, $this->authorizer, $this->getUser(), $prefixGenerator)
        );

        $hydrator = new Hydrator(
            $this->folderMapper,
            $folderTranslator,
            $this->courseSpecMapper,
            $courseTranslator,
            $this->unitSpecMapper,
            $unitTranslator,
            $this->assessmentDataSource,
            $assessmentTranslator,
            $this->badgeMapper,
            $badgeTranslator
        );

        $this->response = $this->responseAsJson([
            'data'     => $hydrator->hydrateResults($data),
            'metadata' => [
                'count' => $cursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $encodedCursor ?? $cursor->current($data),
                    'next'    => $cursor->next($data),
                ],
            ],
        ]);
    }
}
