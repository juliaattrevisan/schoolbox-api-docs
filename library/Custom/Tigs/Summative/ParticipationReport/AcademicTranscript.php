<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

use Alaress\Authorizer\Authorizer;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\InvalidColumnException;
use External_ClassInterface;
use Schoolbox\UserMapper;
use function array_column;
use Custom\Tigs\Summative\StudentAttributes;
use Custom\Tigs\Summative\SubjectReport\Report;
use DomainException;
use External_UserInterface;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Assessment\Rubric\DataModel as RubricDataModel;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use UnderflowException;
use UnexpectedValueException;
use Aura\Router;
use Schoolbox\Resource\Textbox;

/**
 * Responsible for rendering the 'Academic Transcript' component on the Participation report page
 */
class AcademicTranscript
{

    /** @var IConfigLoader */
    private $config;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var External_ClassInterface */
    private $externalClass;

    /** @var CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var Assessment\MapperProxy */
    private $assessmentMapperProxy;

    /** @var RubricDataModel\DataSource */
    private $rubricMapper;

    /** @var UserMapper */
    private $userMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Textbox\Mapper */
    private $textBoxMapper;

    /** @var Access */
    private $access;

    /** @var IConnector */
    private $database;

    /** @var Authorizer */
    private $authorizer;

    /** @var Router\Router */
    private $router;


    /**
     * @param IConfigLoader          $config
     * @param Folder\Mapper          $folderMapper
     * @param External_UserInterface $externalUser
     * @param External_ClassInterface $externalClass
     * @param CourseImpl\Mapper      $courseImplMapper
     * @param Assessment\MapperProxy $assessmentMapperProxy
     * @param RubricDataModel\DataSource $rubricMapper
     * @param UserMapper              $userMapper
     * @param Assessment\Mapper       $assessmentMapper
     * @param Textbox\Mapper         $textBoxMapper
     * @param Access                 $access
     * @param IConnector             $database
     * @param Authorizer             $authorizer
     * @param Router\Router          $router
     */
    public function __construct(
        IConfigLoader $config,
        Folder\Mapper $folderMapper,
        External_UserInterface $externalUser,
        External_ClassInterface $externalClass,
        CourseImpl\Mapper $courseImplMapper,
        Assessment\MapperProxy $assessmentMapperProxy,
        RubricDataModel\DataSource $rubricMapper,
        UserMapper $userMapper,
        Assessment\Mapper $assessmentMapper,
        Textbox\Mapper $textBoxMapper,
        Access $access,
        IConnector $database,
        Authorizer $authorizer,
        Router\Router $router
    ) {
        $this->config                = $config;
        $this->folderMapper          = $folderMapper;
        $this->externalUser          = $externalUser;
        $this->externalClass         = $externalClass;
        $this->courseImplMapper      = $courseImplMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricMapper          = $rubricMapper;
        $this->userMapper            = $userMapper;
        $this->assessmentMapper      = $assessmentMapper;
        $this->textBoxMapper         = $textBoxMapper;
        $this->access                = $access;
        $this->database              = $database;
        $this->authorizer            = $authorizer;
        $this->router                = $router;
    }


    /**
     * @param User              $viewer
     * @param User              $student
     * @param StudentAttributes $attributes
     * @param array             $subjectList
     * @param string|null       $roleTypeOverride
     * @return array
     * @throws DefinitionException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     * @throws UnexpectedValueException
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @throws \Alaress\Database\Exception
     */
    public function toContext(User $viewer, User $student, StudentAttributes $attributes, array $subjectList, ?string $roleTypeOverride): array
    {
        $folders = $this->folderMapper->findByColumn('id', array_column($subjectList, 'folderId'))->indexBy('id');

        $data = [];
        foreach ($subjectList as $subject) {
            $folderId      = $subject['folderId'];
            $subjectReport = new Report(
                $this->config,
                $this->externalUser,
                $this->externalClass,
                $this->courseImplMapper,
                $this->assessmentMapperProxy,
                $this->rubricMapper,
                $this->userMapper,
                $this->assessmentMapper,
                $this->textBoxMapper,
                new Visibility($this->access),
                $this->database,
                $this->authorizer,
                $this->router,
                $viewer
            );

            $data[$folderId] = $subjectReport->getReport(
                $student,
                $folders[$folderId],
                $attributes,
                $roleTypeOverride
            );
        }
        return $data;
    }
}
