<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\L10n\RosettaStone;

class Controller extends Base
{

    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * @var RosettaStone
     */
    private $rosettaStone;


    /**
     * Controller constructor.
     *
     * @param ErrorMapper  $errorMapper
     * @param RosettaStone $rosettaStone
     */
    public function __construct(ErrorMapper $errorMapper, RosettaStone $rosettaStone)
    {
        $this->errorMapper = $errorMapper;
        $this->rosettaStone = $rosettaStone;
    }


    /**
     * Display the calendar itself
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @api   GET /learning/assessment/calendar
     * @level 19.1
     */
    public function getReport(): void
    {
        $pageTitle = sprintf('%s Calendar', $this->rosettaStone->translate('assessment', false, true));

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessmentCalendar/index.twig', [
        ])->setTitle($pageTitle));
    }
}
