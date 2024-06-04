<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\SummativeReport\Payload\VictorianProgression;
use Twig\Error\Error;

/**
 * The Greenhouse lets you experiment with twig templates and contents without
 * needing a backend dev to do it for you. You can also experiment with other
 * front-end stuff in an isolated manner
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(private \Schoolbox\Assessment\Term\Mapper $termMapper)
    {
    }


    /**
     * Load $templateName
     *
     * @param string $templateName
     * @throws Error
     */
    public function getGreenhouse(string $templateName): void
    {
        $workTypeMapper = \Schoolbox\Learning\Assessment\WorkType\Mapper::getInstance();
        $termMapper = \Schoolbox\Assessment\Term\Mapper::getInstance();
        $userMapper = \Schoolbox\UserMapper::getInstance();

        $student = $userMapper->findById($this->requestQuery->get('userId'));

        /* Find the special Work Type by name for lack of a better way.
         * One day this will be configurable in Summative Report itself, at which point it will be conclusive.
         */
        try {
            $workType = $workTypeMapper->findOneByColumn('name', VictorianProgression\Payload::WORK_TYPE_NAME);
            $workTypeId = (int)$workType->getId();
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new \DomainException(sprintf(
                'Missing required worktype in admin: %s',
                VictorianProgression\Payload::WORK_TYPE_NAME
            ));
        }

        /* TODO find out how summative report handles multiple code(s)
         */
        $classCode = $this->requestQuery->get('code');

        $academicPeriod = $this->termMapper->findForDate(\Alaress_Date_Format::now());

        $this->response = $this->responseFromTemplate('@greenhouse/' . $templateName . '.twig', [
            'payload' => (new VictorianProgression\Payload($workTypeMapper, $termMapper, $workTypeId))->generate($student, $academicPeriod, $classCode),
            'audit' => (new VictorianProgression\Auditor($workTypeMapper))->audit()
        ]);
    }
}
