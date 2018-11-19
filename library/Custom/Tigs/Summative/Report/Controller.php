<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report;

use Alaress\Web;
use Aura\Di\Exception\ServiceNotFound;
use Custom\Tigs\Summative\Config;
use Schoolbox\User;

class Controller extends Web\Controller\Base
{
    /**
     * Displays a student's participation report for current term
     *
     * @api   GET /modules/custom/tigs/summative/user/{userId}
     * @level 18.1.0
     * @param User $user
     * @throws ServiceNotFound
     */
    public function getParticipationReport(User $user): void
    {
        $data = [
            'user'      => $user,
            'isEnabled' => $this->getConfigLoader()->get(Config::_TIGS_PARTICIPATION_REPORT_ENABLED),
        ];
        $this
            ->getResponse()->content
            ->set($this->buildHtmlOutput('@tigsSummativeReport/participation.twig', $data)
            ->setTitle("Participation Report"));
    }


    /**
     * Displays a student's subject report for specified folder and current term
     *
     * @api   GET /modules/custom/tigs/summative/user/{userId}/folder/{folderId}
     * @level 18.1.0
     * @param int $userId
     * @param int $folderId
     * @throws ServiceNotFound
     */
    public function getSubjectReport(int $userId, int $folderId): void
    {
        $this
            ->getResponse()->content
            ->set($this->buildHtmlOutput('@tigsSummativeReport/subject.twig', [
                'userId'   => $userId,
                'folderId' => $folderId,
            ])
            ->setTitle("Subject Report"));
    }
}
