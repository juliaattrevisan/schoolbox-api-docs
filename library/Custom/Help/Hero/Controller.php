<?php
/**
 * help.schoolbox.com.au
 *
 * Hero Report
 * Get a list of potential Heroes, and also a list of existing Heroes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\Hero;

use Alaress\Web\Controller\Base;
use Schoolbox\Error;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use function ini_set;

class Controller extends Base
{

    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * @var Specification\Mapper
     */
    private $courseSpecificationMapper;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * Controller constructor.
     *
     * @param Error\Mapper         $errorMapper
     * @param Specification\Mapper $courseSpecificationMapper
     * @param Folder\Mapper        $folderMapper
     * @param UserAccessMapper     $userAccessMapper
     */
    public function __construct(
        Error\Mapper $errorMapper,
        Specification\Mapper $courseSpecificationMapper,
        Folder\Mapper $folderMapper,
        UserAccessMapper $userAccessMapper
    ) {
        ini_set('max_execution_time', 300);
        $this->errorMapper = $errorMapper;
        $this->courseSpecificationMapper = $courseSpecificationMapper;
        $this->folderMapper = $folderMapper;
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * Hero Report
     *
     * All the people who have achieved Hero status
     *
     * @throws \DomainException
     * @throws \UnderflowException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getHero()
    {
        /* If the hardcoded course is not found, for some reason
         * Add an error code for this later if it becomes user-configurable
         * For now, we hardcode this manually; is reasonable to assume it exists
         */
        try {
            $courseSpecification = $this->courseSpecificationMapper->findById(Acquisition::HERO_COURSE_SPEC_ID);
        }
        catch (MapperException $e) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Acquisition + Aggregation
         */
        $start = microtime(true);

        $aggregatedData = (new Aggregation($this->folderMapper, $this->userAccessMapper))->aggregate(
            (new Acquisition($this->errorMapper->getDatabase()))->findInvolvement()
        );

        $end = microtime(true);

        /* Presentation
         */
        $this->getResponse()->content->set($this->buildHtmlOutput('@helpHero/hero.twig', [
            'course' => $courseSpecification,
            'duration' => number_format($end - $start, 2),
            'aggregatedData' => $aggregatedData
        ]));
    }
}
