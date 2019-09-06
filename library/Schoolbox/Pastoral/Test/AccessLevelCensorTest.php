<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Pastoral\Test;

use PHPUnit\Framework\TestCase;
use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachment;

/**
 * @covers \Schoolbox\Pastoral\AccessLevelCensor
 * @codeCoverageIgnore
 */
class AccessLevelCensorTest extends TestCase
{

    /** @var Pastoral\AccessLevelCensor */
    private $accessLevelCensor;


    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->accessLevelCensor = new Pastoral\AccessLevelCensor();
    }

    // ======================== Tests ======================== //

    /**
     * Tests filtering the record, and related records
     *
     * @dataProvider providerRelatedRecordCollection
     * @param string         $recordType
     * @param Pastoral\Model $record
     * @param User           $viewer
     * @param int|null       $expectedRelatedCount
     * @param int|null       $expectedCommentCount
     * @param int|null       $expectedAttachmentCount
     */
    public function test_filterRecordsForUser(
        string $recordType,
        Pastoral\Model $record,
        User $viewer,
        ?int $expectedRelatedCount,
        ?int $expectedCommentCount,
        ?int $expectedAttachmentCount
    ): void {
        // Apply the filtering logic multiple times to check for idempotence
        for ($i = 0; $i < 10; $i++) {
            $actual = $this->accessLevelCensor->filterRecordForUser($record, $viewer);
            if ($expectedRelatedCount === null) {
                $this->assertNull($actual);
                continue;
            }
            $this->assertEquals($expectedRelatedCount, $actual->getRelatedRecords()->count(), "{$recordType} {$viewer->getUsername()} related records");
            if ($expectedCommentCount === null) {
                $this->assertEmpty($actual->getComments());
            } else {
                $this->assertEquals($expectedCommentCount, $actual->getComments()->count(), "{$recordType} {$viewer->getUsername()} comments");
            }
            if ($expectedAttachmentCount === null) {
                $this->assertEmpty($actual->getAttachments());
            } else {
                $this->assertEquals($expectedAttachmentCount, $actual->getAttachments()->count(), "{$recordType} {$viewer->getUsername()} attachments");
            }
        }
    }

    // ======================== Providers ======================== //

    /**
     * Data provider
     * @return array
     */
    public function providerRelatedRecordCollection(): array
    {
        // (Pastoral\Model $record, User $user, bool $isNull, ?int $expectedRelatedCount)
        $data = [];

        // owner
        $owner = (new User())->setId($this->guid++)->setUsername('owner')->setRole((new Role())->setType(Role::STAFF));
        $owner->setAccessList(UserAccess::createCollection())->setPastoralGroupsMemberships(PastoralGroupMember::createCollection());

        // no Access
        $noAccessUser = (new User())->setId($this->guid++)->setUsername('no_access');
        $noAccessUser->setAccessList(UserAccess::createCollection())->setPastoralGroupsMemberships(PastoralGroupMember::createCollection());

        // moderator
        $pastoralModerator = (new User())->setId($this->guid++)->setUsername('moderator');
        $pastoralModPerm   = (new UserAccess())->setAccessLevel(UserAccess::ACCESS_FULL)->setService(UserAccess::SERVICE_PASTORAL)->setUser($pastoralModerator);
        $accessList        = UserAccess::createCollection();
        $accessList->add($pastoralModPerm);
        $pastoralModerator->setAccessList($accessList)->setPastoralGroupsMemberships(PastoralGroupMember::createCollection());

        // prepare records without access
        foreach ($this->buildRelatedRecordCollection($owner) as $record) {
            $data[] = ['No access', $record, $noAccessUser, null, null, null];
            $data[] = ['No access', $record, $pastoralModerator, 4, 2, 2];
            $data[] = ['No access', $record, $owner, 3, 2, 2];
        }

        // prepare records with private/public access
        $testAccesses = $this->generateAccessData();
        // check each pastoral access
        foreach ($testAccesses as $testAccess) {
            foreach ($this->buildRelatedRecordCollection($owner, $testAccess['pastoralAccess']) as $record) {
                $data[] = [$testAccess['record'], $record, $noAccessUser, null, null, null];
                $data[] = [$testAccess['record'], $record, $pastoralModerator, 4, 2, 2];
                $data[] = [$testAccess['record'], $record, $owner, 3, 2, 2];
                $data[] = [
                    $testAccess['record'],
                    $record,
                    $testAccess['user'],
                    $testAccess['expectedRelatedCount'],
                    $testAccess['expectedCommentCount'],
                    $testAccess['expectedAttachmentCount'],
                ];
            }
        }

        return $data;
    }


    // ======================== Helpers ======================== //


    /**
     * Returns a set of 4 related pastoral records, for 4 students.
     *
     * @param User                $owner
     * @param PastoralAccess|null $pastoralAccess
     * @return GenericCollection|Pastoral\Model[]
     */
    private function buildRelatedRecordCollection(User $owner, ?PastoralAccess $pastoralAccess = null): GenericCollection
    {
        $recordA = $this->generateRecord('A', $owner, $pastoralAccess);
        $recordB = $this->generateRecord('B', $owner, $pastoralAccess);
        $recordC = $this->generateRecord('C', $owner, $pastoralAccess);
        $recordD = $this->generateRecord('D', $owner, $pastoralAccess);

        // no owner record
        $ownerX   = (new User())->setId($this->guid++)->setRole((new Role())->setType(Role::STAFF));
        $studentX = (new User())->setId($this->guid++)->setRole((new Role())->setType(Role::STUDENT));
        $recordX  = (new Pastoral\Model())->setHeading('X')->setId(9)->setOwner($ownerX)->setStudent($studentX);

        $recordA->getRelatedRecords()->add($recordB)->add($recordC)->add($recordD)->add($recordX);
        $recordB->getRelatedRecords()->add($recordA)->add($recordC)->add($recordD)->add($recordX);
        $recordC->getRelatedRecords()->add($recordA)->add($recordB)->add($recordD)->add($recordX);
        $recordD->getRelatedRecords()->add($recordA)->add($recordB)->add($recordC)->add($recordX);

        return Pastoral\Model::createCollection()->add($recordA)->add($recordB)->add($recordC)->add($recordD);
    }


    /**
     * @var int random guid
     */
    private $guid = 100;

    /**
     * Returns a pastoral record with comments and attachments
     *
     * @param string              $name
     * @param User                $owner
     * @param PastoralAccess|null $pastoralAccess
     * @return Pastoral\Model
     */
    private function generateRecord(string $name, User $owner, ?PastoralAccess $pastoralAccess = null): Pastoral\Model
    {
        $student = (new User())->setId($this->guid++)->setRole((new Role())->setType(Role::STUDENT));
        $record  = (new Pastoral\Model())->setHeading($name)->setId($this->guid++)->setOwner($owner)->setStudent($student)
                                         ->setCreatedAt((new Alaress_Date_Format())::now())
                                         ->setUpdatedAt((new Alaress_Date_Format())::now());
        $record->setComments(
            PastoralComment::createCollection()->add(
                (new PastoralComment())->setPrivate(true)->setBody('Private Comment')
                                       ->setCreatedAt((new Alaress_Date_Format())::now())
                                       ->setUpdatedAt((new Alaress_Date_Format())::now())
                                       ->setPastoral($record)
                                       ->setOwner($owner)
            )->add(
                (new PastoralComment())->setPrivate(false)->setBody('Public Comment')
                                       ->setCreatedAt((new Alaress_Date_Format())::now())
                                       ->setUpdatedAt((new Alaress_Date_Format())::now())
                                       ->setPastoral($record)
                                       ->setOwner($owner)
            )
        );

        $record->setAttachments(PastoralAttachment::createCollection()->add(
            (new PastoralAttachment())->setPrivate(true)->setPastoral($record)
        )->add(
            (new PastoralAttachment())->setPrivate(false)->setPastoral($record)
        )
        );

        // set pastoral access
        if ($pastoralAccess) {
            $pastoralAccessCollection = PastoralAccessMapper::getInstance()->createCollection();
            $pastoralAccessCollection->add($pastoralAccess);
            $record->setAccess($pastoralAccessCollection);
        }

        return $record;
    }

    /**
     * Returns access data with expected results
     *
     * @return array
     */
    private function generateAccessData()
    {
        $privateAccessUser = (new User())->setId($this->guid++)->setUsername('private_user')->setRole((new Role())->setType(Role::STAFF));
        $privateAccessUser->setAccessList(UserAccess::createCollection())->setPastoralGroupsMemberships(PastoralGroupMember::createCollection());

        $publicAccessUser = (new User())->setId($this->guid++)->setUsername('public_user')->setRole((new Role())->setType(Role::STAFF));
        $publicAccessUser->setAccessList(UserAccess::createCollection())->setPastoralGroupsMemberships(PastoralGroupMember::createCollection());

        $privateAccessRole     = (new Role())->setId($this->guid++)->setType(Role::STAFF);
        $privateAccessRoleUser = (new User())->setId($this->guid++)->setUsername('private_role')->setRole($privateAccessRole);
        $privateAccessRoleUser->setAccessList(UserAccess::createCollection())->setPastoralGroupsMemberships(PastoralGroupMember::createCollection());

        $publicAccessRole     = (new Role())->setId($this->guid++)->setType(Role::STAFF);
        $publicAccessRoleUser = (new User())->setId($this->guid++)->setUsername('public_role')->setRole($publicAccessRole);
        $publicAccessRoleUser->setAccessList(UserAccess::createCollection())->setPastoralGroupsMemberships(PastoralGroupMember::createCollection());

        $privateAccessGroupUser = (new User())->setId($this->guid++)->setUsername('private_group')->setRole((new Role())->setType(Role::STAFF));
        $privateAccessGroup     = (new PastoralGroup())->setId($this->guid++);
        $privateAccessGroupUser->setAccessList(UserAccess::createCollection())
                               ->setPastoralGroupsMemberships(
                                   PastoralGroupMember::createCollection()->add((new PastoralGroupMember())->setUser($privateAccessGroupUser)->setGroup($privateAccessGroup))
                               );

        $publicAccessGroupUser = (new User())->setId(107)->setUsername('public_group')->setRole((new Role())->setType(Role::STAFF));
        $publicAccessGroup     = (new PastoralGroup())->setId($this->guid++);
        $publicAccessGroupUser->setAccessList(UserAccess::createCollection())
                              ->setPastoralGroupsMemberships(
                                  PastoralGroupMember::createCollection()->add((new PastoralGroupMember())->setUser($publicAccessGroupUser)->setGroup($publicAccessGroup))
                              );

        return [
            [
                'record'                  => 'Private user access',
                'user'                    => $privateAccessUser,
                'pastoralAccess'          => (new PastoralAccess())->setUser($privateAccessUser)->setAccess(PastoralAccessMapper::ACCESS_PRIVATE),
                'expectedRelatedCount'    => 3,
                'expectedCommentCount'    => 2,
                'expectedAttachmentCount' => 2,
            ],
            [
                'record'                  => 'Public user access',
                'user'                    => $publicAccessUser,
                'pastoralAccess'          => (new PastoralAccess())->setUser($publicAccessUser)->setAccess(PastoralAccessMapper::ACCESS_PUBLIC),
                'expectedRelatedCount'    => 3,
                'expectedCommentCount'    => 1,
                'expectedAttachmentCount' => 1,
            ],
            [
                'record'                  => 'Private role access',
                'user'                    => $privateAccessRoleUser,
                'pastoralAccess'          => (new PastoralAccess())->setRole($privateAccessRole)->setAccess(PastoralAccessMapper::ACCESS_PRIVATE),
                'expectedRelatedCount'    => 3,
                'expectedCommentCount'    => 2,
                'expectedAttachmentCount' => 2,
            ],
            [
                'record'                  => 'Public role access',
                'user'                    => $publicAccessRoleUser,
                'pastoralAccess'          => (new PastoralAccess())->setRole($publicAccessRole)->setAccess(PastoralAccessMapper::ACCESS_PUBLIC),
                'expectedRelatedCount'    => 3,
                'expectedCommentCount'    => 1,
                'expectedAttachmentCount' => 1,
            ],
            [
                'record'                  => 'Private group access',
                'user'                    => $privateAccessGroupUser,
                'pastoralAccess'          => (new PastoralAccess())->setGroup($privateAccessGroup)->setAccess(PastoralAccessMapper::ACCESS_PRIVATE),
                'expectedRelatedCount'    => 3,
                'expectedCommentCount'    => 2,
                'expectedAttachmentCount' => 2,
            ],
            [
                'record'                  => 'Public group access',
                'user'                    => $publicAccessGroupUser,
                'pastoralAccess'          => (new PastoralAccess())->setGroup($publicAccessGroup)->setAccess(PastoralAccessMapper::ACCESS_PUBLIC),
                'expectedRelatedCount'    => 3,
                'expectedCommentCount'    => 1,
                'expectedAttachmentCount' => 1,
            ],
        ];

    }
}
