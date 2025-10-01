<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\Pastoral;

use Tests\Support\ApiTester;
use Schoolbox\Pastoral\Access\Mapper;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * pastoral@patch.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api PastoralPatchCest
 *
 */
class PastoralPatchCest
{
    private User $userPrivate;
    private User $userPublic;
    private User $userDeny;
    private User $student;

    /**
     * @param ApiTester $i
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function run(ApiTester $i)
    {
        $i->preparePastoralActions();

        $users = $i->getUsers();

        $this->userPrivate = $i->createTestUser('private-' . time(), Role::STAFF, false);
        $this->userPublic = $i->createTestUser('public-' . time(), Role::STAFF, false);
        $this->userDeny = $i->createTestUser('deny-' . time(), Role::STAFF, false);

        /* Pastoral records get created with this user as the recipient
         * The foreach() below also tests a student creating the pastoral record for themselves
         */
        $this->student = $i->createTestUser('student-' . time(), Role::STUDENT, false);

        /** @var User $user */
        foreach ($users as $user) {
            $this->testPatch($i, $user);
        }
    }

    /**
     * @param ApiTester $i
     * @param User      $user
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function testPatch(ApiTester $i, User $user)
    {
        /* Yoink the first Pastoral Type that is in the lookup, somewhat arbitrarily
         */
        /** @var \Schoolbox\Pastoral\Type\Model $pastoralType */
        $pastoralType = $i->getPastoralTypes()->first();

        /* Yoink the first Pastoral Subtype that is in the Pastoral Type, somewhat arbitrarily
         */
        $pastoralSubtype = $pastoralType->getSubTypes()->first()?->getId();

        /* Set up a pastoral record that's mostly complete.
         * It fulfils the minimum required fields, and maybe a couple more.
         * This emulates the most averagest pastoral record in the wild to my best estimation.
         */
        $pastoral = [
            'data' => [
                'student_id' => $this->student->getId(),
                'heading' => 'Base record for PATCH mutations',
                'occurredOn' => \Alaress_Date_Format::now()->getLocalShort(),
                'typeId' => $pastoralType->getId(),
                'subtypeId' => $pastoralSubtype,
                'severityId' => $i->getPastoralSeverities()->first()->getId(),
                'role'=> [
                    /* These are hardcoded in the user interface: valid options are
                     *     - role-type-student
                     *     - role-type-parent
                     *     - an integer id
                     *         - this corresponds to "Administration" in sales data
                     *         - this corresponds to "Staff" in demo datums
                     *
                     */
                    1 => Mapper::ACCESS_PUBLIC,
                    'role-type-parent' => Mapper::ACCESS_PUBLIC,
                ],
                'group' => [
                    /* These are ids of Pastoral Groups which are user-definable.
                     * 1 corresponds to Year 9 Coordinators in the sales data
                     */
                    1 => Mapper::ACCESS_PUBLIC
                ],
                'user' => [
                    $this->userPrivate->getId() => Mapper::ACCESS_PRIVATE,
                    $this->userPublic->getId() => Mapper::ACCESS_PUBLIC,
                    $this->userDeny->getId() => Mapper::ACCESS_DENY,
                ],
                'public_attachments' => [
                    'store:723b6e31c2979b33deea852cdbbad9c4b0e00b96:piglet2.jpg'
                ],
                'private_attachments' => [
                    'store:69903e5d89e1574d92de8afd1634f472e3096b3c:scrabble2.jpg'
                ],
                'tags' => implode(',', [
                    $i->getPastoralTags()->first()->getId(),
                    $i->getPastoralTags()->last()->getId(),
                ]),
                'tagNames' => "test tag A,test tag B",
                'duplicateIds' => '25,26',
                'points' => 100.1,
                'body_public' => "Public body\nPublic body.",
                'body_private' => "Private body\nPrivate body.",
            ],
            'expectedResponseCode' => [
                400 => [$this->userDeny],
                200 => [$this->userPrivate, $this->userPublic],
            ],
            'isValid' => true,
        ];

        $i->comment("Testing as user {$user->getUsername()} [{$user->getId()}]");
        $i->switchUser($user);

        /* Create a pastoral record
         */
        $i->sendPost('/pastoral/record/insert', $pastoral['data']);

        /* Sequential dependency
         * These checks must occur after sendPost() executes, not before hand: you can't exit early
         *
         * A $user is one of four different user setups: some have permission to create
         * pastoral records and some don't. This simulates what happens when a student user
         * tries to create a pastoral record, etc.
         */
        if (!$user->getRole()->isStaff()) {
            $i->seeResponseCodeIs(401);
            return;
        }

        if (!$pastoral['isValid']) {
            $i->seeResponseCodeIs(400);
            return;
        }

        /* Successfully created the pastoral record
         */
        $i->seeResponseCodeIs(201);

        /* The following mutations occur on the same pastoral record, in sequence, to test
         * repeated mutability.
         */

        /* Mutate one field via the patch endpoint
         */

        /* Evaluate response
         */

        /* Mutate multiple fields via the patch endpoint
         */

        /* Evaluate response
         */

        /* Mutate no fields using a payload containing invalid nodes via the patch endpoint
         */

        /* Evaluate response
         */

        /* Mutate a mixture of valid and invalid nodes in a payload via the patch endpoint
         */

        /* Evaluate response
         */















//        foreach ($this->getCreateTestData($i) as $postData) {
//
//
//            // TODO: check role permission
//            if (!$operator->getRole()->isStaff()) {
//                $i->seeResponseCodeIs(401);
//                continue;
//            }
//
//            if (!$postData['isValid']) {
//                $i->seeResponseCodeIs(400);
//                continue;
//            }
//
//            $i->seeResponseCodeIs(201);
//            $response = $i->grabDataFromResponseByJsonPath('$[0].id');
//            $i->seeResponseIsValidOnJsonSchemaString($this->getSchema());
//            $studentResponse = $i->grabDataFromResponseByJsonPath('$[0].student');
//
//
//            $recordId   = $response[0];
//            $recordPath = "/pastoral/student/{$this->student->getId()}/record/{$recordId}";
//
//            // get user by userId
//            $i->sendGet($recordPath, []);
//            $i->seeResponseCodeIs(200);
//
//            foreach ($postData['expectedResponseCode'] as $code => $users) {
//                foreach ($users as $user) {
//                    $i->switchUser($user);
//                    $i->sendGet($recordPath, []);
//                    $i->seeResponseCodeIs($code);
//                }
//            }
//
//            // turn off severity
//            $i->changeDBConfig('pastoral_severity', 0);
//            $i->sendPost('/pastoral/record/insert', $postData['data']);
//
//            $i->seeResponseCodeIs(201);
//            $i->seeResponseIsValidOnJsonSchemaString($this->getSchema());
//            $i->restoreDBConfig();
//
//            // turn on action
//            $i->changeDBConfig('pastoral_action_enable', 1);
//            $i->sendPost('/pastoral/record/insert', $postData['data']);
//
//            $i->seeResponseCodeIs(201);
//            $i->seeResponseIsValidOnJsonSchemaString($this->getSchema());
//            $i->restoreDBConfig();
//
//            // turn off tag creation
//            $i->changeDBConfig('pastoral_create_tag_enable', 0);
//            $postData['data']['tagNames'] = "newTag" . time();
//            $i->sendPost('/pastoral/record/insert', $postData['data']);
//            unset($postData['data']['tagNames']);
//
//            $i->seeResponseCodeIs(400);
//            $i->restoreDBConfig();
//
//            // try invalid points
//            $postData['data']['points'] = 1000000000;
//            $i->sendPost('/pastoral/record/insert', $postData['data']);
//            $i->seeResponseCodeIs(400);
//            $postData['data']['points'] = 100;
//
//            // check pastoral_student_access config
//            if (isset($postData['data']['role'])) {
//                $i->changeDBConfig('pastoral_student_access', 0);
//                $postData['data']['role']['role-type-student'] = Mapper::ACCESS_PUBLIC;
//                $i->sendPost('/pastoral/record/insert', $postData['data']);
//                $i->seeResponseCodeIs(400);
//
//                $i->changeDBConfig('pastoral_student_access', 1);
//                $postData['data']['role']['role-type-student'] = Mapper::ACCESS_PUBLIC;
//                $i->sendPost('/pastoral/record/insert', $postData['data']);
//                $i->seeResponseCodeIs(201);
//                $i->restoreDBConfig();
//            }
//        }
    }

//    /**
//     * @param ApiTester $i
//     * @return iterable
//     */
//    private function getCreateTestData(ApiTester $i): iterable
//    {
//        /** @var \Schoolbox\Pastoral\Type\Model $pastoralType */
//        $pastoralType = $i->getPastoralTypes()->first();
//
//        // least required
//        yield [
//            'data'                 => [
//                'student_id' => $this->student->getId(),
//                'heading'    => 'Cest least ' . time(),
//                'typeId'     => $pastoralType->getId(),
//                //        'subtypeId' => $record->getSubType()?->getId();
//                'subtypeId'  => $pastoralType->getSubTypes()->first()?->getId(),
//                // TODO: config
//                'severityId' => $i->getPastoralSeverities()->first()->getId(),
//            ],
//            'expectedResponseCode' => [
//                400 => [$this->userDeny, $this->userPrivate, $this->userPublic],
//            ],
//            'isValid'              => true,
//        ];
//
//        // set null to nullable
//        yield [
//            'data'                 => [
//                'student_id'          => $this->student->getId(),
//                'duplicateIds'        => null,
//                'heading'             => 'Cest nullable ' . time(),
//                'occurredOn'          => null,
//                'typeId'              => $pastoralType->getId(),
//                'subtypeId'           => $pastoralType->getSubTypes()->first()?->getId(),
//                // TODO: config
//                'severityId'          => $i->getPastoralSeverities()->first()->getId(),
//                'public_attachments'  => null,
//                'private_attachments' => null,
//                'actions'             => [1, 2],
//                'tags'                => null,
//                'points'              => null,
//            ],
//            'body_public'          => null,
//            'body_private'         => null,
//            'expectedResponseCode' => [
//                400 => [$this->userDeny, $this->userPrivate, $this->userPublic],
//            ],
//            'isValid'              => true,
//        ];
//
//        yield [
//            'data'                 => [
//                'student_id'          => $this->student->getId(),
//                //                'heading'             => 'Cest normal ' . time(),
//                'heading'             => '
//01234567890123456789012345678901234567890123456789
//01234567890123456789012345678901234567890123456789
//01234567890123456789012345678901234567890123456789
//                ',
//                'occurredOn'          => \Alaress_Date_Format::now()->getLocalShort(),
//                'typeId'              => $pastoralType->getId(),
//                //        'subtypeId' => $record->getSubType()?->getId();
//                'subtypeId'           => $pastoralType->getSubTypes()->first()?->getId(),
//                // TODO: config
//                'severityId'          => $i->getPastoralSeverities()->first()->getId(),
//                // TODO: permission
//                'role'                => [
//                    1                  => Mapper::ACCESS_PUBLIC,
//                    'role-type-parent' => Mapper::ACCESS_PUBLIC,
//                ],
//                'group'               => [1 => Mapper::ACCESS_PUBLIC],
//                'user'                => [
//                    $this->userPrivate->getId() => Mapper::ACCESS_PRIVATE,
//                    $this->userPublic->getId()  => Mapper::ACCESS_PUBLIC,
//                    $this->userDeny->getId()    => Mapper::ACCESS_DENY,
//                ],
//                'public_attachments'  => ['store:723b6e31c2979b33deea852cdbbad9c4b0e00b96:piglet2.jpg'],
//                'private_attachments' => ['store:69903e5d89e1574d92de8afd1634f472e3096b3c:scrabble2.jpg'],
//                'tags'                => implode(',', [
//                    $i->getPastoralTags()->first()->getId(),
//                    $i->getPastoralTags()->last()->getId(),
//                ]),
//                'tagNames'            => "test tag A,test tag B",
//                'duplicateIds'        => '25,26',
//                'points'              => 100.1,
//                'body_public'         => "Public body\nPublic body.",
//                'body_private'        => "Private body\nPrivate body.",
//            ],
//            'expectedResponseCode' => [
//                400 => [$this->userDeny],
//                200 => [$this->userPrivate, $this->userPublic],
//            ],
//            'isValid'              => true,
//        ];
//    }

//    /**
//     * @return false|string
//     */
//    private function getSchema()
//    {
//        $schema = [
//            'type'       => 'array',
//            'properties' => [
//                'id'         => [
//                    'type' => 'integer',
//                ],
//                'externalId' => [
//                    'type'     => 'string',
//                    'nullable' => true,
//                ],
//                'student'    => [
//                    'type' => 'object',
//                ],
//                'heading'    => [
//                    'type' => 'string',
//                ],
//                'type'       => [
//                    'type' => 'object',
//                ],
//                '_links'     => [
//                    'type' => 'object',
//                ],
//            ],
//        ];
//        return json_encode($schema);
//    }
}
