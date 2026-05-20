<?php

/**
 * International Baccalaureate admin controller test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate\Test;

use Alaress\Authorizer\Authorizer;
use Alaress\Database\Connector\IConnector;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Response\Factory as ResponseFactory;
use Alaress\Web\Request\Values;
use Aura\Router\Generator;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\InternationalBaccalaureate\Controller;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Collection;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Mapper as IBGroupMapper;
use Schoolbox\IB\Group\Subject\Language\Mapper as GroupSubjectLanguageMapper;
use Schoolbox\IB\Group\Subject\Mapper as IBGroupSubjectMapper;
use Schoolbox\IB\Group\Subject\Model as IBGroupSubjectModel;
use Schoolbox\IB\Registrations\Sessions\Mapper as RegistrationSessionMapper;
use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Helper as RegistrationSessionStudentHelper;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Mapper as RegistrationSessionStudentSubjectLanguageMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Model as RegistrationSessionStudentSubjectLanguageModel;
use Schoolbox\IB\Registrations\Sessions\Student\Translator\ModelToArray as RegistrationSessionStudentModelToArray;
use Schoolbox\IConfigLoader;
use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Mapper as BaseMapper;
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
                    '_routes' => [
                        'updateLanguages' => '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/123/languages',
                        'updateConfirmation' => '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/123/confirmation',
                        'renotify' => null,
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
                    '_routes' => [
                        'updateLanguages' => '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/234/languages',
                        'updateConfirmation' => '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/234/confirmation',
                        'renotify' => null,
                    ],
                ],
            ],
            $payload['students'][RegistrationSessionStudentModel::STATUS_FAILED]
        );
    }

    public function testRegistrationSessionStudentsIncludesRenotifyRouteForUnconfirmedStudents(): void
    {
        $registrationSessionMapper = $this->createStub(RegistrationSessionMapper::class);
        $registrationSessionMapper
            ->method('findById')
            ->willReturn(new RegistrationSessionModel());

        $registrationSessionStudentMapper = $this->createStub(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->method('findStudentsForSession')
            ->willReturn(new Collection([
                $this->buildStudent(456, 123, RegistrationSessionStudentModel::STATUS_UNCONFIRMED, 'Alex', 'Nguyen', 'S123'),
            ]));

        $externalUser = $this->createStub(\External_UserInterface::class);
        $externalUser
            ->method('getStudent')
            ->willReturn(['StudentBirthDate' => '2009-04-12', 'StudentGender' => 'F']);

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
        $this->assertSame(
            '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/123/renotify',
            $payload['students'][RegistrationSessionStudentModel::STATUS_UNCONFIRMED][0]['_routes']['renotify']
        );
    }

    public function testRenotifyUnconfirmedTouchesStudentAndPropagatesUnconfirmedNotification(): void
    {
        $registrationSessionStudent = $this->buildStudent(
            456,
            234,
            RegistrationSessionStudentModel::STATUS_UNCONFIRMED,
            'Sam',
            'Patel',
            'S234'
        );

        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findOneByColumnList')
            ->with([
                'user_id' => 234,
                'registration_session_id' => 12,
            ])
            ->willReturn($registrationSessionStudent);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('propagate')
            ->with(
                $this->isInstanceOf(User::class),
                BaseMapper::METHOD_UPDATE,
                $registrationSessionStudent,
                [
                    'status' => [
                        RegistrationSessionStudentModel::STATUS_UNCONFIRMED,
                        RegistrationSessionStudentModel::STATUS_PREPARATION,
                    ],
                ]
            );

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class)
        );

        $controller->renotifyUnconfirmed(12, 234);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame('Student renotified.', $payload['message']);
    }

    public function testSessionCompletePreparationTransitionsSelectedPreparationStudents(): void
    {
        $studentOne = $this->buildStudent(
            456,
            123,
            RegistrationSessionStudentModel::STATUS_PREPARATION,
            'Alex',
            'Nguyen',
            'S123'
        );
        $studentTwo = $this->buildStudent(
            789,
            234,
            RegistrationSessionStudentModel::STATUS_PREPARATION,
            'Sam',
            'Patel',
            'S234'
        );
        $studentThree = $this->buildStudent(
            890,
            345,
            RegistrationSessionStudentModel::STATUS_PREPARATION,
            'Jordan',
            'Smith',
            'S345'
        );

        $database = $this->createMock(IConnector::class);
        $database
            ->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);
        $database
            ->expects($this->once())
            ->method('commit');
        $database
            ->expects($this->never())
            ->method('rollBack');

        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->method('getDatabase')
            ->willReturn($database);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findStudentsForSession')
            ->with(12)
            ->willReturn(new Collection([$studentOne, $studentTwo, $studentThree]));

        $savedUserIds = [];
        $registrationSessionStudentMapper
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->callback(function (RegistrationSessionStudentModel $student) use (&$savedUserIds): bool {
                $savedUserIds[] = $student->getUser()?->getId();
                $this->assertSame(RegistrationSessionStudentModel::STATUS_UNCONFIRMED, $student->getStatus());

                return true;
            }));

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class)
        );
        $controller->setRequestPost(new Values([
            'studentIds' => [123, '234', 234],
        ]));

        $controller->sessionCompletePreparation((new RegistrationSessionModel())->setId(12));

        $this->assertSame([123, 234], $savedUserIds);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame(2, $payload['transitionedCount']);
        $this->assertSame(RegistrationSessionStudentModel::STATUS_PREPARATION, $studentThree->getStatus());
    }

    public function testSessionCompletePreparationRejectsInvalidStudentIds(): void
    {
        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('getDatabase');
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('findStudentsForSession');
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class)
        );
        $controller->setRequestPost(new Values([
            'studentIds' => [],
        ]));

        $controller->sessionCompletePreparation((new RegistrationSessionModel())->setId(12));

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(400, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(400, $payload['status']);
        $this->assertSame('Student IDs are required.', $payload['title']);
    }

    public function testRenotifyUnconfirmedReturns404WhenCandidateCannotBeFound(): void
    {
        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findOneByColumnList')
            ->with([
                'user_id' => 234,
                'registration_session_id' => 12,
            ])
            ->willThrowException(new \Schoolbox\Mapper\Exception\NotFound());
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('save');
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('propagate');

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class)
        );

        $controller->renotifyUnconfirmed(12, 234);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertFalse($payload['success']);
        $this->assertSame('Candidate not found for this user in the registration session.', $payload['message']);
    }

    public function testRenotifyUnconfirmedReturns409WhenCandidateIsNotUnconfirmed(): void
    {
        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findOneByColumnList')
            ->willReturn($this->buildStudent(
                456,
                234,
                RegistrationSessionStudentModel::STATUS_CONFIRMED,
                'Sam',
                'Patel',
                'S234'
            ));
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('save');
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('propagate');

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class)
        );

        $controller->renotifyUnconfirmed(12, 234);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(409, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertFalse($payload['success']);
        $this->assertSame('Candidate is not awaiting confirmation.', $payload['message']);
    }

    public function testSessionCompletePreparationRejectsStudentThatCannotBeFound(): void
    {
        $database = $this->createMock(IConnector::class);
        $database
            ->expects($this->never())
            ->method('beginTransaction');
        $database
            ->expects($this->never())
            ->method('commit');
        $database
            ->expects($this->never())
            ->method('rollBack');

        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->method('getDatabase')
            ->willReturn($database);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findStudentsForSession')
            ->with(12)
            ->willReturn(new Collection([
                $this->buildStudent(
                    456,
                    123,
                    RegistrationSessionStudentModel::STATUS_PREPARATION,
                    'Alex',
                    'Nguyen',
                    'S123'
                ),
            ]));
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class)
        );
        $controller->setRequestPost(new Values([
            'studentIds' => [999],
        ]));

        $controller->sessionCompletePreparation((new RegistrationSessionModel())->setId(12));

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(400, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(400, $payload['status']);
        $this->assertSame(
            'One or more selected students could not be found in this registration session.',
            $payload['title']
        );
    }

    public function testSessionCompletePreparationRejectsStudentThatIsNotInPreparation(): void
    {
        $database = $this->createMock(IConnector::class);
        $database
            ->expects($this->never())
            ->method('beginTransaction');
        $database
            ->expects($this->never())
            ->method('commit');
        $database
            ->expects($this->never())
            ->method('rollBack');

        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->method('getDatabase')
            ->willReturn($database);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findStudentsForSession')
            ->with(12)
            ->willReturn(new Collection([
                $this->buildStudent(
                    456,
                    123,
                    RegistrationSessionStudentModel::STATUS_CONFIRMED,
                    'Alex',
                    'Nguyen',
                    'S123'
                ),
            ]));
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class)
        );
        $controller->setRequestPost(new Values([
            'studentIds' => [123],
        ]));

        $controller->sessionCompletePreparation((new RegistrationSessionModel())->setId(12));

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(400, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(400, $payload['status']);
        $this->assertSame(
            'One or more selected student IDs belong to candidates who are already further down the process.',
            $payload['title']
        );
    }

    public function testUpdateRegistrationSessionStudentSubjectLanguagesSavesOverrides(): void
    {
        $registrationSessionStudent = (new RegistrationSessionStudentModel())
            ->setId(456)
            ->setUser((new User())->setId(234));

        $registrationSessionStudentMapper = $this->createStub(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->method('findOneByColumnList')
            ->with([
                'user_id' => 234,
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
        $database = $this->createMock(IConnector::class);
        $database
            ->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);
        $database
            ->expects($this->once())
            ->method('commit');
        $database
            ->expects($this->never())
            ->method('rollBack');
        $subjectLanguageMapper
            ->method('getDatabase')
            ->willReturn($database);
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
        $controller->setRequest(
            (new HttpFactory())
                ->createServerRequest('PUT', '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/234/languages')
                ->withParsedBody([
                    'subjects' => [
                        ['groupSubjectId' => 123, 'languageId' => 45],
                        ['groupSubjectId' => 124, 'languageId' => 45],
                        ['groupSubjectId' => 125, 'languageId' => 46],
                    ],
                ])
        );

        $controller->updateRegistrationSessionStudentSubjectLanguages(12, 234);

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

    public function testUpdateRegistrationSessionStudentSubjectLanguagesPersistsDynamicCandidateBeforeSavingOverrides(): void
    {
        $dynamicRegistrationSessionStudent = (new RegistrationSessionStudentModel())
            ->setRegistrationSession((new RegistrationSessionModel())->setId(12))
            ->setUser((new User())->setId(234))
            ->setStatus(RegistrationSessionStudentModel::STATUS_PREPARATION);
        $persistedRegistrationSessionStudent = (new RegistrationSessionStudentModel())
            ->setId(456)
            ->setRegistrationSession((new RegistrationSessionModel())->setId(12))
            ->setUser((new User())->setId(234))
            ->setStatus(RegistrationSessionStudentModel::STATUS_PREPARATION);

        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $findRegistrationSessionStudentCallCount = 0;
        $registrationSessionStudentMapper
            ->expects($this->exactly(2))
            ->method('findOneByColumnList')
            ->with([
                'user_id' => 234,
                'registration_session_id' => 12,
            ])
            ->willReturnCallback(function () use (&$findRegistrationSessionStudentCallCount, $persistedRegistrationSessionStudent) {
                if ($findRegistrationSessionStudentCallCount++ === 0) {
                    throw new \Schoolbox\Mapper\Exception\NotFound();
                }

                return $persistedRegistrationSessionStudent;
            });
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findStudentsForSession')
            ->with(12)
            ->willReturn(new Collection([$dynamicRegistrationSessionStudent]));
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (RegistrationSessionStudentModel $student): bool {
                $this->assertNull($student->getId());
                $this->assertSame(12, $student->getRegistrationSession()?->getId());
                $this->assertSame(234, $student->getUser()?->getId());
                $this->assertSame(RegistrationSessionStudentModel::STATUS_PREPARATION, $student->getStatus());

                return true;
            }))
            ->willReturn([456]);

        $groupSubjectMapper = $this->createStub(IBGroupSubjectMapper::class);
        $groupSubjectMapper
            ->method('findByColumn')
            ->with('id', [123])
            ->willReturn(new GenericCollection(IBGroupSubjectModel::class, [
                (new IBGroupSubjectModel())->setId(123),
            ]));

        $languageMapper = $this->createStub(LanguageMapper::class);
        $languageMapper
            ->method('findByColumn')
            ->with('id', [45])
            ->willReturn(new GenericCollection(LanguageModel::class, [
                (new LanguageModel())->setId(45),
            ]));

        $subjectLanguageMapper = $this->createMock(RegistrationSessionStudentSubjectLanguageMapper::class);
        $database = $this->createMock(IConnector::class);
        $database
            ->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);
        $database
            ->expects($this->once())
            ->method('commit');
        $database
            ->expects($this->never())
            ->method('rollBack');
        $subjectLanguageMapper
            ->method('getDatabase')
            ->willReturn($database);
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
                $this->assertCount(1, $models);
                $this->assertSame(456, $models[0]->getRegistrationSessionStudent()?->getId());
                $this->assertSame(123, $models[0]->getGroupSubject()?->getId());
                $this->assertSame(45, $models[0]->getLanguage()?->getId());

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
        $controller->setRequest(
            (new HttpFactory())
                ->createServerRequest('PUT', '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/234/languages')
                ->withParsedBody([
                    'subjects' => [
                        ['groupSubjectId' => 123, 'languageId' => 45],
                    ],
                ])
        );

        $controller->updateRegistrationSessionStudentSubjectLanguages(12, 234);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame([
            ['groupSubjectId' => 123, 'languageId' => 45],
        ], $payload['subjects']);
    }

    public function testUpdateRegistrationSessionStudentSubjectLanguagesReturns404WhenCandidateCannotBePersisted(): void
    {
        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findOneByColumnList')
            ->with([
                'user_id' => 234,
                'registration_session_id' => 12,
            ])
            ->willThrowException(new \Schoolbox\Mapper\Exception\NotFound());
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findStudentsForSession')
            ->with(12)
            ->willReturn(new Collection());
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('save');

        $subjectLanguageMapper = $this->createMock(RegistrationSessionStudentSubjectLanguageMapper::class);
        $subjectLanguageMapper
            ->expects($this->never())
            ->method('deleteByColumn');
        $subjectLanguageMapper
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class),
            $this->createStub(IBGroupSubjectMapper::class),
            $this->createStub(LanguageMapper::class),
            $subjectLanguageMapper
        );
        $controller->setRequest(
            (new HttpFactory())
                ->createServerRequest('PUT', '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/234/languages')
                ->withParsedBody([
                    'subjects' => [
                        ['groupSubjectId' => 123, 'languageId' => 45],
                    ],
                ])
        );

        $controller->updateRegistrationSessionStudentSubjectLanguages(12, 234);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertFalse($payload['success']);
        $this->assertSame('Candidate not found for this user in the registration session.', $payload['message']);
    }

    public function testUpdateRegistrationSessionStudentSubjectLanguagesRollsBackOnWriteFailure(): void
    {
        $registrationSessionStudent = (new RegistrationSessionStudentModel())
            ->setId(456)
            ->setUser((new User())->setId(234));

        $registrationSessionStudentMapper = $this->createStub(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->method('findOneByColumnList')
            ->willReturn($registrationSessionStudent);

        $groupSubjectMapper = $this->createStub(IBGroupSubjectMapper::class);
        $groupSubjectMapper
            ->method('findByColumn')
            ->with('id', [123])
            ->willReturn(new GenericCollection(IBGroupSubjectModel::class, [
                (new IBGroupSubjectModel())->setId(123),
            ]));

        $languageMapper = $this->createStub(LanguageMapper::class);
        $languageMapper
            ->method('findByColumn')
            ->with('id', [45])
            ->willReturn(new GenericCollection(LanguageModel::class, [
                (new LanguageModel())->setId(45),
            ]));

        $subjectLanguageMapper = $this->createMock(RegistrationSessionStudentSubjectLanguageMapper::class);
        $database = $this->createMock(IConnector::class);
        $database
            ->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);
        $database
            ->expects($this->never())
            ->method('commit');
        $database
            ->expects($this->once())
            ->method('rollBack');
        $subjectLanguageMapper
            ->method('getDatabase')
            ->willReturn($database);
        $subjectLanguageMapper
            ->method('createModel')
            ->willReturnCallback(fn() => new RegistrationSessionStudentSubjectLanguageModel());
        $subjectLanguageMapper
            ->expects($this->once())
            ->method('deleteByColumn')
            ->with('registration_session_student_id', 456)
            ->willThrowException(new \Exception('Delete failed'));
        $subjectLanguageMapper
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class),
            $groupSubjectMapper,
            $languageMapper,
            $subjectLanguageMapper
        );
        $controller->setRequest(
            (new HttpFactory())
                ->createServerRequest('PUT', '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/234/languages')
                ->withParsedBody([
                    'subjects' => [
                        ['groupSubjectId' => 123, 'languageId' => 45],
                    ],
                ])
        );

        $controller->updateRegistrationSessionStudentSubjectLanguages(12, 234);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(500, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertFalse($payload['success']);
        $this->assertSame('Something has gone wrong. Please try again later.', $payload['message']);
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
        $controller->setRequest(
            (new HttpFactory())
                ->createServerRequest('PUT', '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/123/languages')
                ->withParsedBody([
                    'subjects' => [
                        ['groupSubjectId' => 123],
                    ],
                ])
        );

        $controller->updateRegistrationSessionStudentSubjectLanguages(12, 123);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUpdateRegistrationSessionStudentConfirmationSavesSectionState(): void
    {
        $registrationSessionStudent = (new RegistrationSessionStudentModel())
            ->setId(456)
            ->setUser((new User())->setId(234));

        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findOneByColumnList')
            ->with([
                'user_id' => 234,
                'registration_session_id' => 12,
            ])
            ->willReturn($registrationSessionStudent);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (RegistrationSessionStudentModel $student): bool {
                $this->assertTrue($student->getCandidateConfirmed());
                $this->assertInstanceOf(
                    \Alaress_Date_Format::class,
                    $student->getCandidateConfirmedAt()
                );
                $this->assertSame(999, $student->getCandidateConfirmedBy()?->getId());

                return true;
            }));

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class),
            currentUser: (new User())
                ->setId(999)
                ->setFirstname('Ibis')
                ->setLastname('Coordinator')
                ->setNameFormat($this->buildConfigLoader()->get(ConfigMapper::NAME_NAME_FORMAT))
        );
        $controller->setRequest(
            (new HttpFactory())
                ->createServerRequest(
                    'PUT',
                    '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/234/confirmation'
                )
                ->withParsedBody([
                    'section' => 'candidate',
                    'confirmed' => true,
                ])
        );

        $controller->updateRegistrationSessionStudentConfirmation(12, 234);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame('candidate', $payload['section']);
        $this->assertTrue($payload['confirmed']);
        $this->assertIsString($payload['updatedAt']);
        $this->assertSame([
            'id' => 999,
            'name' => 'Ibis Coordinator',
        ], $payload['updatedBy']);
    }

    public function testUpdateRegistrationSessionStudentConfirmationSavesSubjectSectionState(): void
    {
        $registrationSessionStudent = (new RegistrationSessionStudentModel())
            ->setId(456)
            ->setUser((new User())->setId(234));

        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('findOneByColumnList')
            ->with([
                'user_id' => 234,
                'registration_session_id' => 12,
            ])
            ->willReturn($registrationSessionStudent);
        $registrationSessionStudentMapper
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (RegistrationSessionStudentModel $student): bool {
                $this->assertFalse($student->getSubjectConfirmed());
                $this->assertInstanceOf(
                    \Alaress_Date_Format::class,
                    $student->getSubjectConfirmedAt()
                );
                $this->assertSame(999, $student->getSubjectConfirmedBy()?->getId());
                $this->assertFalse($student->getCandidateConfirmed());
                $this->assertNull($student->getCandidateConfirmedAt());
                $this->assertNull($student->getCandidateConfirmedBy());

                return true;
            }));

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class),
            currentUser: (new User())
                ->setId(999)
                ->setFirstname('Ibis')
                ->setLastname('Coordinator')
                ->setNameFormat($this->buildConfigLoader()->get(ConfigMapper::NAME_NAME_FORMAT))
        );
        $controller->setRequest(
            (new HttpFactory())
                ->createServerRequest(
                    'PUT',
                    '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/234/confirmation'
                )
                ->withParsedBody([
                    'section' => 'subject',
                    'confirmed' => false,
                ])
        );

        $controller->updateRegistrationSessionStudentConfirmation(12, 234);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame('subject', $payload['section']);
        $this->assertFalse($payload['confirmed']);
        $this->assertIsString($payload['updatedAt']);
        $this->assertSame([
            'id' => 999,
            'name' => 'Ibis Coordinator',
        ], $payload['updatedBy']);
    }

    public function testUpdateRegistrationSessionStudentConfirmationRejectsUnknownSection(): void
    {
        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('findOneByColumnList');
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class)
        );
        $controller->setRequest(
            (new HttpFactory())
                ->createServerRequest(
                    'PUT',
                    '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/234/confirmation'
                )
                ->withParsedBody([
                    'section' => 'unknown',
                    'confirmed' => true,
                ])
        );

        $controller->updateRegistrationSessionStudentConfirmation(12, 234);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(400, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(400, $payload['status']);
        $this->assertSame('Invalid confirmation request data.', $payload['title']);
    }

    public function testUpdateRegistrationSessionStudentConfirmationRejectsNonBooleanConfirmed(): void
    {
        $registrationSessionStudentMapper = $this->createMock(RegistrationSessionStudentMapper::class);
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('findOneByColumnList');
        $registrationSessionStudentMapper
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildController(
            $this->createStub(RegistrationSessionMapper::class),
            $registrationSessionStudentMapper,
            $this->createStub(\External_UserInterface::class)
        );
        $controller->setRequest(
            (new HttpFactory())
                ->createServerRequest(
                    'PUT',
                    '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/234/confirmation'
                )
                ->withParsedBody([
                    'section' => 'candidate',
                    'confirmed' => 'true',
                ])
        );

        $controller->updateRegistrationSessionStudentConfirmation(12, 234);

        $response = $controller->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(400, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(400, $payload['status']);
        $this->assertSame('Invalid confirmation request data.', $payload['title']);
    }

    private function buildController(
        RegistrationSessionMapper $registrationSessionMapper,
        RegistrationSessionStudentMapper $registrationSessionStudentMapper,
        \External_UserInterface $externalUser,
        ?IBGroupSubjectMapper $groupSubjectMapper = null,
        ?LanguageMapper $languageMapper = null,
        ?RegistrationSessionStudentSubjectLanguageMapper $subjectLanguageMapper = null,
        ?User $currentUser = null
    ): Controller {
        $registrationSessionStudentHelper = new RegistrationSessionStudentHelper();
        $routeGenerator = $this->createStub(Generator::class);
        $routeGenerator
            ->method('generate')
            ->willReturnCallback(static function (string $route, array $params): string {
                if ($route === 'internationalBaccalaureate.updateRegistrationSessionStudentConfirmation') {
                    return sprintf(
                        '/adminv2/international-baccalaureate/registrations/sessions/%d/candidates/%d/confirmation',
                        $params['sessionId'],
                        $params['studentId']
                    );
                }

                if ($route === 'internationalBaccalaureate.renotifyUnconfirmed') {
                    return sprintf(
                        '/adminv2/international-baccalaureate/registrations/sessions/%d/candidates/%d/renotify',
                        $params['sessionId'],
                        $params['studentId']
                    );
                }

                TestCase::assertSame('internationalBaccalaureate.updateRegistrationSessionStudentSubjectLanguages', $route);

                return sprintf(
                    '/adminv2/international-baccalaureate/registrations/sessions/%d/candidates/%d/languages',
                    $params['sessionId'],
                    $params['studentId']
                );
            });
        $authorizer = $this->createStub(Authorizer::class);
        $authorizer
            ->method('isPathAuthorizedForUser')
            ->willReturn(true);
        $currentUser ??= new User();

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
            new RegistrationSessionStudentModelToArray(
                $registrationSessionStudentHelper,
                $routeGenerator,
                $authorizer,
                $currentUser,
            ),
            $registrationSessionStudentHelper,
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
        $controller->setRequest($httpFactory->createServerRequest('GET', '/')->withParsedBody([]));
        $controller->setDi($this->buildContainer());
        $controller->setUser($currentUser);

        return $controller;
    }

    private function buildContainer(): ContainerInterface
    {
        $configLoader = $this->buildConfigLoader();

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

    private function buildConfigLoader(): IConfigLoader
    {
        return new ArrayLoader([
            ConfigMapper::NAME_IB_SCHOOL_NAME => 'Example School',
            ConfigMapper::NAME_NAME_FORMAT => '{title} {firstname} {lastname}',
        ]);
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
            ->setRegistrationSession((new RegistrationSessionModel())->setId(12))
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
