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
use Custom\Tigs\Summative\StudentAttributes;
use Schoolbox\User;
use Schoolbox\Filter\Filters;
use External_UserInterface;
use Schoolbox\UserMapper;
use InvalidArgumentException;
use External_User;

class Controller extends Web\Controller\Base
{

    /** @var External_UserInterface|External_User */
    private $externalUser;

    /** @var UserMapper */
    private $userMapper;


    /**
     * @param External_UserInterface $externalUser
     * @param UserMapper             $userMapper
     */
    public function __construct(External_UserInterface $externalUser, UserMapper $userMapper)
    {
        $this->externalUser = $externalUser;
        $this->userMapper   = $userMapper;
    }


    /**
     * Displays a student's participation report for current term
     *
     * @api   GET /modules/custom/tigs/summative/participation/user/{userId}
     * @level 18.1.0
     * @param User $user
     * @throws ServiceNotFound
     * @throws InvalidArgumentException
     */
    public function getReport(User $user): void
    {
        $externalStudent = $this->externalUser->getStudent($user->getExternalId());

        $attributes = new StudentAttributes(
            $user,
            $externalStudent['StudentIBFlag'],
            $this->getRequest()->query->get('now')
        );

        // This is just to enable testing on our TIGS test dataset, which uses the schoolbox connector.
        // The client (TIGS) uses the Synergetic connector.
        // The correct solution would be to implement the yearLevel and tutor/mentor relationships in the
        // Schoolbox connector and populate the TIGS test dataset, but there's problems with their implementation,
        // and we don't have time to fix them now. So we'll just hack in some dummy data for now.
        if ('schoolbox' === $this->getConfigLoader()->get('external_type')) {
            $externalIdDean   = ($user->getId() % 2) ? '483' : ''; // Trevor Reese
            $externalIdMentor = ($user->getId() % 2) ? '' : '8';   // Celia Glenn
        } else {
            $externalIdDean   = $externalStudent['YearLevelCoordID'];
            $externalIdMentor = $externalStudent['TeacherTutorID'];
        }

        $deanAndMentor = $this->userMapper->findByColumn('synergy_id', [$externalIdDean, $externalIdMentor]);
        $dean          = $mentor = null;
        foreach ($deanAndMentor as $deanOrMentor) {
            // Can't break, as Dean and Mentor may be same user
            if ($deanOrMentor->getExternalId() === $externalIdDean) {
                $dean = $deanOrMentor;
            }
            if ($deanOrMentor->getExternalId() === $externalIdMentor) {
                $mentor = $deanOrMentor;
            }
        }

        $data   = [
            'user'       => $user,
            'isEnabled'  => $this->getConfigLoader()->get(Config::_TIGS_PARTICIPATION_REPORT_ENABLED),
            'attributes' => $attributes,
            'dean'       => $dean,
            'mentor'     => $mentor,

        ];
        $output = $this->buildHtmlOutput('@tigsParticipationReport/participation.twig', $data, "Participation Report");

        $this->getResponse()->content->set($output);
    }
}
