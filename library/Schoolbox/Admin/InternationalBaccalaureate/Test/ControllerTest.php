<?php

/**
 * International Baccalaureate admin controller test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate\Test;

use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Response\Factory as ResponseFactory;
use Alaress\Web\Request\Values;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\InternationalBaccalaureate\Controller;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Mapper as IBGroupMapper;
use Schoolbox\IB\Group\Subject\Language\Mapper as GroupSubjectLanguageMapper;
use Schoolbox\IB\Group\Subject\Mapper as IBGroupSubjectMapper;
use Schoolbox\IB\Group\Subject\Model as IBGroupSubjectModel;
use Schoolbox\IB\Registrations\Sessions\Mapper as RegistrationSessionMapper;
use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Mapper as RegistrationSessionStudentSubjectLanguageMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Model as RegistrationSessionStudentSubjectLanguageModel;
use Schoolbox\IConfigLoader;
use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Nationality\Model as NationalityModel;
use Schoolbox\Resource\Folder\Code\Mapper as FolderCodeMapper;
use Schoolbox\User;
use Twig\Environment;

class ControllerTest extends TestCase
{
    public function testRegistrationSessionStudentsReturnsOnlyGroupedStudents(): void
    {
        $registrationSessionMapper = $this->createStub(RegistrationSessionMapper::class);
        $registrationSessionMapper
            ->method('findById')
            ->willReturn(new RegistrationSessionModel());

        $registrationSessionStudentMapper = $this->createStub(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->method('findStudentsForSession')
            ->willReturn(new Collection([
                $this->buildStudent(456, 123, RegistrationSessionStudentModel::STATUS_CONFIRMED, 'Alex', 'Nguyen', 'S123'),
                $this->buildStudent(789, 234, RegistrationSessionStudentModel::STATUS_FAILED, 'Sam', 'Patel', 'S234'),
            ]));

        $externalUser = $this->createStub(\External_UserInterface::class);
        $externalUser
            ->method('getStudent')
            ->willReturnMap([
                ['S123', ['StudentBirthDate' => '2009-04-12', 'StudentGender' => 'F']],
                ['S234', ['StudentBirthDate' => '2008-09-24', 'StudentGender' => 'M']],
            ]);

        $controller = $this->buildController(
            $registrationSessionMapper,
            $registrationSessionStudentMapper,
            $externalUser
        );

        $controller->getRegistrationSessionStudents(12);

        $response = $controller->getResponse();
        $this->assertNotNull($response);

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('students', $payload);
        $this->assertArrayNotHasKey('studentsByStatus', $payload);
        $this->assertArrayHasKey('statuses', $payload);
        $this->assertSame(
            [
                [
                    'userId' => 123,
                    'registrationSessionStudentId' => 456,
                    'status' => RegistrationSessionStudentModel::STATUS_CONFIRMED,
                    'userName' => 'Alex, Nguyen',
                    'hasMissingDetails' => false,
                    'missingFields' => [],
                    'ibGroupSubjects' => [
                        'HL' => [],
                        'SL' => [],
                        'Core' => [],
                    ],
                ],
            ],
            $payload['students'][RegistrationSessionStudentModel::STATUS_CONFIRMED]
        );
        $this->assertSame(
            [
                [
                    'userId' => 234,
                    'registrationSessionStudentId' => 789,
                    'status' => RegistrationSessionStudentModel::STATUS_FAILED,
                    'userName' => 'Sam, Patel',
                    'hasMissingDetails' => false,
                    'missingFields' => [],
                    'ibGroupSubjects' => [
                        'HL' => [],
                        'SL' => [],
                        'Core' => [],
                    ],
                ],
            ],
            $payload['students'][RegistrationSessionStudentModel::STATUS_FAILED]
        );
    }

    public function testUpdateRegistrationSessionStudentSubjectLanguagesSavesOverrides(): void
    {
        $registrationSessionStudent = (new RegistrationSessionStudentModel())->setId(456);

        $registrationSessionStudentMapper = $this->createStub(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->method('findOneByColumnList')
            ->with([
                'id' => 456,
                'registration_session_id' => 12,
            ])
            ->willReturn($registrationSessionStudent);

        $groupSubjectMapper = $this->createStub(IBGroupSubjectMapper::class);
        $groupSubjectMapper
            ->method('findByColumn')
            ->with('id', [123, 124, 125])
            ->willReturn(new GenericCollection(IBGroupSubjectModel::class, [
                (new IBGroupSubjectModel())->setId(123),
                (new IBGroupSubjectModel())->setId(124),
                (new IBGroupSubjectModel())->setId(125),
            ]));

        $languageMapper = $this->createStub(LanguageMapper::class);
        $languageMapper
            ->method('findByColumn')
            ->with('id', [45, 46])
            ->willReturn(new GenericCollection(LanguageModel::class, [
                (new LanguageModel())->setId(45),
                (new LanguageModel())->setId(46),
            ]));

        $subjectLanguageMapper = $this->createMock(RegistrationSessionStudentSubjectLanguageMapper::class);
        $subjectLanguageMapper
            ->method('createModel')
            ->willReturnCallback(fn() => new RegistrationSessionStudentSubjectLanguageModel());
        $subjectLanguageMapper
            ->expects($this->once())
            ->method('deleteByColumn')
            ->with('registration_session_student_id', 456);
        $subjectLanguageMapper
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (array $models): bool {
                $this->assertCount(3, $models);
                $this->assertSame(456, $models[0]->getRegistrationSessionStudent()?->getId());
                $this->assertSame(123, $models[0]->getGroupSubject()?->getId());
                $this->assertSame(45, $models[0]->getLanguage()?->getId());
                $this->assertSame(124, $models[1]->getGroupSubject()?->getId());
                $this->assertSame(45, $models[1]->getLanguage()?->getId());
                $this->assertSame(125, $models[2]->getGroupSubject()?->getId());
                $this->assertSame(46, $models[2]->getLanguage()?->getId());

                return true;
            }));

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class),
            $groupSubjectMapper,
            $languageMapper,
            $subjectLanguageMapper
        );
        $controller->setRequestPost(new Values([
            'subjects' => [
                ['groupSubjectId' => 123, 'languageId' => 45],
                ['groupSubjectId' => 124, 'languageId' => 45],
                ['groupSubjectId' => 125, 'languageId' => 46],
            ],
        ]));

        $controller->updateRegistrationSessionStudentSubjectLanguages(12, 456);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame([
            ['groupSubjectId' => 123, 'languageId' => 45],
            ['groupSubjectId' => 124, 'languageId' => 45],
            ['groupSubjectId' => 125, 'languageId' => 46],
        ], $payload['subjects']);
    }

    public function testUpdateRegistrationSessionStudentSubjectLanguagesRejectsInvalidPayload(): void
    {
        $subjectLanguageMapper = $this->createMock(RegistrationSessionStudentSubjectLanguageMapper::class);
        $subjectLanguageMapper
            ->expects($this->never())
            ->method('deleteByColumn');
        $subjectLanguageMapper
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $this->createStub(RegistrationSessionStudentMapper::class),
            $this->createStub(\External_UserInterface::class),
            subjectLanguageMapper: $subjectLanguageMapper
        );
        $controller->setRequestPost(new Values([
            'subjects' => [
                ['groupSubjectId' => 123],
            ],
        ]));

        $controller->updateRegistrationSessionStudentSubjectLanguages(12, 456);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(400, $response->getStatusCode());
    }

    private function buildController(
        RegistrationSessionMapper $registrationSessionMapper,
        RegistrationSessionStudentMapper $registrationSessionStudentMapper,
        \External_UserInterface $externalUser,
        ?IBGroupSubjectMapper $groupSubjectMapper = null,
        ?LanguageMapper $languageMapper = null,
        ?RegistrationSessionStudentSubjectLanguageMapper $subjectLanguageMapper = null
    ): Controller {
        $controller = new Controller(
            $this->createStub(ConfigMapper::class),
            $this->createStub(IBGroupMapper::class),
            $groupSubjectMapper ?? $this->createStub(IBGroupSubjectMapper::class),
            $this->createStub(GroupSubjectLanguageMapper::class),
            $languageMapper ?? $this->createStub(LanguageMapper::class),
            $this->createStub(FolderCodeMapper::class),
            $registrationSessionMapper,
            $this->createStub(\External_ClassInterface::class),
            $registrationSessionStudentMapper,
            $subjectLanguageMapper ?? $this->createStub(RegistrationSessionStudentSubjectLanguageMapper::class),
            $externalUser,
            $this->createStub(FieldStrategyFactory::class),
            $this->createStub(Rebuilder::class),
            $this->createStub(CacheInterface::class)
        );

        $httpFactory = new HttpFactory();
        $controller->setResponseFactory(
            new ResponseFactory(
                $httpFactory,
                $httpFactory,
                $this->createStub(Environment::class)
            )
        );
        $controller->setDi($this->buildContainer());
        $controller->setUser(new User());

        return $controller;
    }

    private function buildContainer(): ContainerInterface
    {
        $configLoader = $this->createStub(IConfigLoader::class);
        $configLoader
            ->method('get')
            ->with(ConfigMapper::NAME_IB_SCHOOL_NAME)
            ->willReturn('Example School');

        return new class ($configLoader) implements ContainerInterface {
            public function __construct(private IConfigLoader $configLoader)
            {
            }

            public function get(string $id): mixed
            {
                if ($id === 'config') {
                    return $this->configLoader;
                }

                throw new \RuntimeException(sprintf('Unknown container id "%s"', $id));
            }

            public function has(string $id): bool
            {
                return $id === 'config';
            }
        };
    }

    private function buildStudent(
        int $id,
        int $userId,
        int $status,
        string $firstName,
        string $lastName,
        string $externalId
    ): RegistrationSessionStudentModel {
        return (new RegistrationSessionStudentModel())
            ->setId($id)
            ->setStatus($status)
            ->setUser(
                (new User())
                    ->setId($userId)
                    ->setExternalId($externalId)
                    ->setFirstname($firstName)
                    ->setLastname($lastName)
                    ->setLocalEmail(false)
                    ->setAltEmail(sprintf('%s.%s@example.edu', strtolower($firstName), strtolower($lastName)))
                    ->setLanguageCollection(
                        new GenericCollection(LanguageModel::class, [
                            (new LanguageModel())->setCode('ENG')->setName('English'),
                        ])
                    )
                    ->setNationalityCollection(
                        new GenericCollection(NationalityModel::class, [
                            (new NationalityModel())->setCode('AUS')->setName('Australian'),
                        ])
                    )
            );
    }
}
