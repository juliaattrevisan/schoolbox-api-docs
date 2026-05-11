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
use Schoolbox\IB\Registrations\Sessions\Mapper as RegistrationSessionMapper;
use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
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

    private function buildController(
        RegistrationSessionMapper $registrationSessionMapper,
        RegistrationSessionStudentMapper $registrationSessionStudentMapper,
        \External_UserInterface $externalUser
    ): Controller {
        $controller = new Controller(
            $this->createStub(ConfigMapper::class),
            $this->createStub(IBGroupMapper::class),
            $this->createStub(IBGroupSubjectMapper::class),
            $this->createStub(GroupSubjectLanguageMapper::class),
            $this->createStub(LanguageMapper::class),
            $this->createStub(FolderCodeMapper::class),
            $registrationSessionMapper,
            $this->createStub(\External_ClassInterface::class),
            $registrationSessionStudentMapper,
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
