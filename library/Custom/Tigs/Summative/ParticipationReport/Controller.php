<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

use Alaress\Web;
use Aura\Di\Exception\ServiceNotFound;
use Custom\Tigs\Summative\Config;
use Schoolbox\User;
use Schoolbox\Filter\Filters;

class Controller extends Web\Controller\Base
{

    /**
     * Displays a student's participation report for current term
     *
     * @api   GET /modules/custom/tigs/summative/participation/user/{userId}
     * @level 18.1.0
     * @param User $user
     * @throws ServiceNotFound
     */
    public function getReport(User $user): void
    {
        $data   = [
            'user'      => $user,
            'isEnabled' => $this->getConfigLoader()->get(Config::_TIGS_PARTICIPATION_REPORT_ENABLED),
        ];
        $output = $this->buildHtmlOutput('@tigsParticipationReport/participation.twig', $data, "Participation Report");

        $this->getResponse()->content->set($output);
    }
}
