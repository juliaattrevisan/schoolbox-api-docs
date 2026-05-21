<?php

/**
 * IB registration session student translator test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Translator\Test;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Model as IBGroupModel;
use Schoolbox\IB\Group\Subject\Language\Model as GroupSubjectLanguageModel;
use Schoolbox\IB\Group\Subject\Model as IBGroupSubjectModel;
use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Helper;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\IB\Registrations\Sessions\Student\Translator\ModelToArray;
use Schoolbox\IB\Subject\Model as IBSubjectModel;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Nationality\Model as NationalityModel;
use Schoolbox\User;

class ModelToArrayTest extends TestCase
{
    public function testBuildsCompletePayload(): void
    {
        $payload = $this->createTranslator()(
            $this->buildStudent(),
            [
                'StudentBirthDate' => '2009-04-12',
                'StudentGender' => 'F',
            ],
            ' Example School '
        );

        $this->assertSame(123, $payload['userId']);
        $this->assertSame(456, $payload['registrationSessionStudentId']);
        $this->assertSame(RegistrationSessionStudentModel::STATUS_CONFIRMED, $payload['status']);
        $this->assertSame('Alex, Nguyen', $payload['userName']);
        $this->assertFalse($payload['hasMissingDetails']);
        $this->assertSame([], $payload['missingFields']);
        $this->assertSame(
            [
                'updateLanguages' => '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/123/languages',
                'updateConfirmation' => '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/123/confirmation',
                'renotify' => null,
            ],
            $payload['_routes']
        );
        $this->assertSame(
            [
                'HL' => [],
                'SL' => [],
                'Core' => [],
            ],
            $payload['ibGroupSubjects']
        );
    }

    public function testBuildsMissingFieldsForIncompletePayload(): void
    {
        $student = (new RegistrationSessionStudentModel())
            ->setUser(
                (new User())
                    ->setId(123)
                    ->setFirstname('')
                    ->setLastname('Nguyen')
                    ->setLocalEmail(false)
                    ->setAltEmail('')
                    ->setLanguageCollection(LanguageModel::createCollection())
                    ->setNationalityCollection(NationalityModel::createCollection())
            );

        $payload = $this->createTranslator()($student, [], '');

        $this->assertSame(', Nguyen', $payload['userName']);
        $this->assertTrue($payload['hasMissingDetails']);
        $this->assertSame(
            [
                'firstName',
                'dateOfBirth',
                'email',
                'gender',
                'language1',
                'nationality1',
                'schoolName',
            ],
            $payload['missingFields']
        );
    }

    public function testBuildsIbGroupSubjectPayload(): void
    {
        $frenchLanguage = (new GroupSubjectLanguageModel())
            ->setId(654)
            ->setLanguage((new LanguageModel())->setId(9)->setCode('FRA')->setName('French'))
            ->setClassCode('FRE-HL');

        $student = $this->buildStudent()
            ->setIbGroupSubjects(
                new GenericCollection(IBGroupSubjectModel::class, [
                    (new IBGroupSubjectModel())
                        ->setId(321)
                        ->setGroupId(7)
                        ->setGroup((new IBGroupModel())->setId(7)->setName('Language Acquisition'))
                        ->setSubject((new IBSubjectModel())->setId(8)->setName('French'))
                        ->setLevel('HL')
                        ->setLanguages(new GenericCollection(GroupSubjectLanguageModel::class, [$frenchLanguage])),
                    (new IBGroupSubjectModel())
                        ->setId(322)
                        ->setGroupId(8)
                        ->setGroup((new IBGroupModel())->setId(8)->setName('Core'))
                        ->setSubject((new IBSubjectModel())->setId(10)->setName('Theory of Knowledge (TOK)'))
                        ->setLevel(null)
                        ->setLanguages(new GenericCollection(GroupSubjectLanguageModel::class)),
                ])
            );

        $payload = $this->createTranslator()(
            $student,
            [
                'StudentBirthDate' => '2009-04-12',
                'StudentGender' => 'F',
            ],
            'Example School'
        );

        $this->assertSame(
            [
                'HL' => [
                    [
                        'id' => 321,
                        'groupId' => 7,
                        'subject' => 'French',
                        'level' => 'HL',
                        'languages' => [
                            [
                                'languageId' => 9,
                                'languageName' => 'French',
                                'classCode' => [
                                    [
                                        'code' => 'FRE-HL',
                                        'folder' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'SL' => [],
                'Core' => [
                    [
                        'id' => 322,
                        'groupId' => 8,
                        'subject' => 'Theory of Knowledge (TOK)',
                        'level' => 'Core',
                        'languages' => [],
                    ],
                ],
            ],
            $payload['ibGroupSubjects']
        );
    }

    public function testBuildsSessionLevelRenotifyRouteForUnconfirmedStudent(): void
    {
        $payload = $this->createTranslator()(
            $this->buildStudent()->setStatus(RegistrationSessionStudentModel::STATUS_UNCONFIRMED),
            [
                'StudentBirthDate' => '2009-04-12',
                'StudentGender' => 'F',
            ],
            'Example School'
        );

        $this->assertSame(
            '/adminv2/international-baccalaureate/registrations/sessions/12/candidates/renotify',
            $payload['_routes']['renotify']
        );
    }

    private function createTranslator(): ModelToArray
    {
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
                        '/adminv2/international-baccalaureate/registrations/sessions/%d/candidates/renotify',
                        $params['sessionId'],
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

        return new ModelToArray(new Helper(), $routeGenerator, $authorizer, new User());
    }

    private function buildStudent(): RegistrationSessionStudentModel
    {
        return (new RegistrationSessionStudentModel())
            ->setId(456)
            ->setStatus(RegistrationSessionStudentModel::STATUS_CONFIRMED)
            ->setRegistrationSession((new RegistrationSessionModel())->setId(12))
            ->setUser(
                (new User())
                    ->setId(123)
                    ->setFirstname('Alex')
                    ->setLastname('Nguyen')
                    ->setLocalEmail(false)
                    ->setAltEmail('alex.nguyen@example.edu')
                    ->setLanguageCollection(
                        new GenericCollection(LanguageModel::class, [
                            (new LanguageModel())->setCode('ENG')->setName('English'),
                            (new LanguageModel())->setCode('FRA')->setName('French'),
                        ])
                    )
                    ->setNationalityCollection(
                        new GenericCollection(NationalityModel::class, [
                            (new NationalityModel())->setCode('AUS')->setName('Australian'),
                            (new NationalityModel())->setCode('NZL')->setName('New Zealander'),
                        ])
                    )
            );
    }
}
