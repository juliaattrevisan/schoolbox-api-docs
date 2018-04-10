<?php
/**
 * help.schoolbox.com.au
 *
 * Hero Report
 * Get a list of potential Heroes, and also a list of existing Heroes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\Hero;

use Alaress\Web\Controller\Base;
use Schoolbox\Error;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

ini_set('max_execution_time', 300);

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
     * @var UserMapper
     */
    private $userMapper;

    /**
     * Controller constructor.
     *
     * @param Error\Mapper                   $errorMapper
     * @param Specification\Mapper           $courseSpecificationMapper
     * @param UserMapper                     $userMapper
     */
    public function __construct(
        Error\Mapper $errorMapper,
        Specification\Mapper $courseSpecificationMapper,
        UserMapper $userMapper
    ) {
        $this->errorMapper = $errorMapper;
        $this->courseSpecificationMapper = $courseSpecificationMapper;
        $this->userMapper = $userMapper;
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

        $aggregatedData = (new Aggregation())->aggregate(
            (new Acquisition($this->userMapper))->findInvolvement()
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