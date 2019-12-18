<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport;

use Alaress\Database\Connector\IConnector;
use Alaress\Web;
use Aura\Di\Exception\ServiceNotFound;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\StudentAttributes;
use External_ClassInterface;
use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Filter\Filters;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\Resource\Textbox;
use Schoolbox\User;
use External_UserInterface;
use Schoolbox\UserMapper;
use function array_merge;

class Controller extends Web\Controller\Base
{

    /** @var External_UserInterface */
    private $externalUser;

    /** @var External_ClassInterface */
    private $externalClass;

    /** @var CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var Assessment\MapperProxy */
    private $assessmentMapperProxy;

    /** @var RubricDataSource */
    private $rubricMapper;

    /** @var UserMapper */
    private $userMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Textbox\Mapper */
    private $textBoxMapper;

    /** @var Visibility */
    private $visibility;

    /** @var IConnector */
    private $database;


    /**
     * @param External_UserInterface  $externalUser
     * @param External_ClassInterface $externalClass
     * @param CourseImpl\Mapper       $courseImplMapper
     * @param Assessment\MapperProxy  $assessmentMapperProxy
     * @param RubricDataSource        $rubricMapper
     * @param UserMapper              $userMapper
     * @param Assessment\Mapper       $assessmentMapper
     * @param Textbox\Mapper          $textboxMapper
     * @param Visibility              $visibility
     * @param IConnector              $database
     */
    public function __construct(
        External_UserInterface $externalUser,
        External_ClassInterface $externalClass,
        CourseImpl\Mapper $courseImplMapper,
        Assessment\MapperProxy $assessmentMapperProxy,
        RubricDataSource $rubricMapper,
        UserMapper $userMapper,
        Assessment\Mapper $assessmentMapper,
        Textbox\Mapper $textboxMapper,
        Visibility $visibility,
        IConnector $database
    ) {
        $this->externalUser          = $externalUser;
        $this->externalClass         = $externalClass;
        $this->courseImplMapper      = $courseImplMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricMapper          = $rubricMapper;
        $this->userMapper            = $userMapper;
        $this->assessmentMapper      = $assessmentMapper;
        $this->textBoxMapper         = $textboxMapper;
        $this->visibility            = $visibility;
        $this->database              = $database;
    }


    /**
     * Displays a student's subject report for specified folder and current term
     *
     * @api   GET /modules/custom/tigs/summative/subject/user/{userId}/folder/{folderId}{/reportDate}
     *            Where {reportDate} is anything recognised by strtotime()
     * @since 18.1.0
     * @param User         $user
     * @param Folder\Model $folder
     * @param string|null  $reportDate
     * @throws ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \UnderflowException
     * @throws \Alaress\Database\Exception
     */
    public function getReport(User $user, Folder\Model $folder, ?string $reportDate): void
    {
        $attributes       = new StudentAttributes(
            $this->getConfigLoader(),
            $user,
            $this->externalUser->getStudent($user->getExternalId())['StudentIBFlag'],
            $reportDate
        );
        $roleTypeOverride = $this->request->query->get('role-type');

        $report = new Report(
            $this->getConfigLoader(),
            $this->externalUser,
            $this->externalClass,
            $this->courseImplMapper,
            $this->assessmentMapperProxy,
            $this->rubricMapper,
            $this->userMapper,
            $this->assessmentMapper,
            $this->textBoxMapper,
            $this->visibility,
            $this->database,
            $this->getAuthorizer(),
            $this->getRouter(),
            $this->getUser()
        );

        $subjectData = $report->getReport($user, $folder, $attributes, $roleTypeOverride);

        $data = array_merge($subjectData, [
                'user'               => $user,
                'attributes'         => $attributes,
                'dataAttributeError' => Config::ERROR_DATA_ATTRIBUTE,
                'showErrors'         => ($roleTypeOverride ?? $this->getUser()->getRole()->getType()) === Role::STAFF,
                'isEnabled'          => $this->getConfigLoader()->get(Config::_TIGS_SUBJECT_REPORT_ENABLED),
            ]
        );

        $output = $this->buildHtmlOutput('@tigsSubjectReport/subject.twig', $data, "Subject Report");

        $this->getResponse()->content->set($output);
    }
}
