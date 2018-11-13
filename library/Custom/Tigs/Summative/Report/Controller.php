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
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use External_UserInterface;
use Schoolbox\Assessment\Term;

class Controller extends Web\Controller\Base
{

    /** @var External_UserInterface */
    private $externalUser;

    /** @var Term\Mapper */
    private $termMapper;


    /**
     * @param External_UserInterface $externalUser
     * @param Term\Mapper            $termMapper
     */
    public function __construct(External_UserInterface $externalUser, Term\Mapper $termMapper)
    {
        $this->externalUser = $externalUser;
        $this->termMapper   = $termMapper;
    }


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
        $data   = [
            'user'      => $user,
            'isEnabled' => $this->getConfigLoader()->get(Config::_TIGS_PARTICIPATION_REPORT_ENABLED),
        ];
        $output = $this->buildHtmlOutput('@tigsSummativeReport/participation.twig', $data, "Participation Report");

        $this->getResponse()->content->set($output);
    }


    /**
     * Displays a student's subject report for specified folder and current term
     *
     * @api   GET /modules/custom/tigs/summative/user/{userId}/folder/{folderId}?term=[1,2,3,4]
     * @level 18.1.0
     * @param User         $user
     * @param Folder\Model $folder
     * @throws ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function getSubjectReport(User $user, Folder\Model $folder): void
    {
        $ibFlagOn = (bool)$this->externalUser->getStudent($user->getExternalId())['StudentIBFlag'];

        $isHsc = !$ibFlagOn || $user->getYear() <= 10;
        $isIb  = $ibFlagOn || $user->getYear() <= 10;

        // Hardcoded term dates for now
        $firstDayTerm1Relative = '1 January';
        $firstDayTerm2Relative = '1 April';
        $firstDayTerm3Relative = '1 July';
        $firstDayTerm4Relative = '1 October';

        $term = $this->getRequest()->query->get('term');
        if (!$term || !preg_match('/^[1234]$/', $term)) {
            // Current Term + Semester
            $now           = \Alaress_Date_Format::now();
            $firstDayTerm1 = new \Alaress_Date_Format(\strtotime($firstDayTerm1Relative), \Alaress_Date_Format::TIMESTAMP);
            $firstDayTerm2 = new \Alaress_Date_Format(\strtotime($firstDayTerm2Relative), \Alaress_Date_Format::TIMESTAMP);
            $firstDayTerm3 = new \Alaress_Date_Format(\strtotime($firstDayTerm3Relative), \Alaress_Date_Format::TIMESTAMP);
            $firstDayTerm4 = new \Alaress_Date_Format(\strtotime($firstDayTerm4Relative), \Alaress_Date_Format::TIMESTAMP);
            if ($now->gte($firstDayTerm1) && $now->lt($firstDayTerm2)) {
                $term = 1;
            } elseif ($now->lt($firstDayTerm3)) {
                $term = 2;
            } elseif ($now->lt($firstDayTerm4)) {
                $term = 3;
            } else {
                $term = 4;
            }
            $semTitle = $this->termMapper->findForDate($now)->getTitle();

            // Vagrant + TIGS current share the same term title scheme, eg 'Semester 2, 2018'
            if (!\preg_match('/Semester (\d)/', $semTitle, $matches)) {
                throw new \UnexpectedValueException("Unable to recognise a semester");
            }
            $sbSem = (int)$matches[1];
        } else {
            $term = (int)$term;
            // Simulated Term + Semester
            switch ($term) {
                case 1:
                case 2:
                    $sbSem = 1;
                    break;
                case 3:
                case 4:
                    $sbSem = 2;
                    break;
                default:
                    throw new \UnexpectedValueException("Unable to recognise a term");
            }
        }

        // Apply SB to Curriculum mapping
        if (4 === $term && (int)$user->getYear() === 11) {
            // Year 11, Term 4 => Sem1 Yr 12 Curriculum
            $curriculumYearLevel = 12;
            $curriculumSemester  = 1;
        } elseif (2 === $term && (int)$user->getYear() === 12) {
            // Year 12, Term 2 => Sem2 Yr 12 Curriculum
            $curriculumYearLevel = 12;
            $curriculumSemester  = 2;
        } else {
            // Normal Case
            $curriculumYearLevel = $user->getYear();
            $curriculumSemester  = $sbSem;
        }

        $data   = [
            'user'                => $user,
            'folder'              => $folder,
            'isHsc'               => $isHsc,
            'isIb'                => $isIb,
            'sbSem'               => $sbSem,
            'term'                => $term,
            'curriculumYearLevel' => $curriculumYearLevel,
            'curriculumSemester'  => $curriculumSemester,
        ];
        $output = $this->buildHtmlOutput('@tigsSummativeReport/subject.twig', $data, "Subject Report");

        $this->getResponse()->content->set($output);
    }
}
