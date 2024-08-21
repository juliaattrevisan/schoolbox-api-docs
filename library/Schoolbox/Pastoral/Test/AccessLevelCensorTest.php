<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Test;

use PHPUnit\Framework\TestCase;
use Alaress_Date_Format;
use Schoolbox\Campus;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\Test\MockConnector;
use Schoolbox\User;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachment;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Pastoral\AccessLevelCensor::class)]
class AccessLevelCensorTest extends TestCase
{
    /** @var Pastoral\AccessLevelCensor */
    private $accessLevelCensor;


    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        $pastoralGroupMemberMapper = Pastoral\Group\Member\Mapper::getInstance();
        $pastoralGroupMemberMapper->setDatabase(new MockConnector($this));
        $this->accessLevelCensor = new Pastoral\AccessLevelCensor($pastoralGroupMemberMapper);
    }

    // ======================== Tests ======================== //
    /**
     * Tests filtering the record, and related records
     *
     * @param string              $recordType
     * @param Pastoral\Model      $originalRecord
     * @param Pastoral\Model|null $expectedFilteredRecord
     * @param User                $viewer
     * @throws \Schoolbox\Mapper\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRelatedRecordCollection')]
    public function testFilterRecordsForUser(
        string $recordType,
        Pastoral\Model $originalRecord,
        ?Pastoral\Model $expectedFilteredRecord,
        User $viewer
    ): void {
        // Apply the filtering logic multiple times to check for idempotence
        for ($i = 0; $i < 10; $i++) {
            $actualFilteredRecord = $this->accessLevelCensor->filterRecordForUser($originalRecord, $viewer);
            if ($expectedFilteredRecord === null) {
                $this->assertNull($actualFilteredRecord);
                continue;
            }

            // Compare the record itself
            $diff = Pastoral\Mapper::getInstance()->diff($expectedFilteredRecord, $actualFilteredRecord);
            $this->assertEmpty($diff, "{$recordType} {$viewer->getUsername()} pastoral record: " . count($diff));

            // Compare related records
            $diffRecords = $expectedFilteredRecord->getRelatedRecords()->diff(
                $actualFilteredRecord->getRelatedRecords(),
                function ($a, $b) {
                    return empty(Pastoral\Mapper::getInstance()->diff($a, $b));
                }
            );
            $this->assertEmpty($diffRecords, "{$recordType} {$viewer->getUsername()} related records: " . $diffRecords->count());
        }
    }

    // ======================== Providers ======================== //

    /**
     * Data provider
     * @return array
     */
    public function providerRelatedRecordCollection(): array
    {
        // (string $recordType, Pastoral\Model $originalRecord, ?Pastoral\Model $expectedFilteredRecord, User $viewer)
        $data = [];

        // owner
        $owner = (new User())->setId($this->guid++)->setUsername('owner')->setRole((new Role())->setType(Role::STAFF));
        $owner->setAccessList(UserAccess::createCollection())->setPastoralGroupIds([]);

        // no Access
        $noAccessUser = (new User())->setId($this->guid++)->setUsername('no_access');
        $noAccessUser->setAccessList(UserAccess::createCollection())->setPastoralGroupIds([]);

        // moderator
        $pastoralModerator = (new User())->setId($this->guid++)->setUsername('moderator');
        $pastoralModPerm   = (new UserAccess())->setAccessLevel(UserAccess::ACCESS_FULL)->setService(UserAccess::SERVICE_PASTORAL)->setUser($pastoralModerator);
        $accessList        = UserAccess::createCollection();
        $accessList->add($pastoralModPerm);
        $pastoralModerator->setAccessList($accessList)->setPastoralGroupIds([]);

        // prepare records without access
        foreach ($this->buildRelatedRecordCollection($owner) as $records) {
            $data[] = ['No access', $records['original'], null, $noAccessUser];
            $data[] = ['No access', $records['original'], $records['moderator'], $pastoralModerator];
            $data[] = ['No access', $records['original'], $records['owner'], $owner];
        }

        // prepare records with private/public access
        $testAccesses = $this->generateAccessData();
        // check each pastoral access
        foreach ($testAccesses as $testAccess) {
            foreach ($this->buildRelatedRecordCollection($owner, $testAccess['pastoralAccess']) as $records) {
                $data[] = [$testAccess['record'], $records['original'], null, $noAccessUser];
                $data[] = [$testAccess['record'], $records['original'], $records['moderator'], $pastoralModerator];
                $data[] = [$testAccess['record'], $records['original'], $records['owner'], $owner];
                $data[] = [
                    $testAccess['record'],
                    $records['original'],
                    $testAccess['privateAccess'] ? $records['private'] : $records['public'],
                    $testAccess['user'],
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
    private function buildRelatedRecordCollection(User $owner, ?PastoralAccess $pastoralAccess = null): array
    {
        $studentCampus = Campus::createCollection()->add((new Campus())->setId(1));
        $recordA       = $this->generateRecord('A', $owner, $pastoralAccess);
        $recordB       = $this->generateRecord('B', $owner, $pastoralAccess);
        $recordC       = $this->generateRecord('C', $owner, $pastoralAccess);
        $recordD       = $this->generateRecord('D', $owner, $pastoralAccess);
        $recordAPublic = $this->filterPrivate($recordA);
        $recordBPublic = $this->filterPrivate($recordB);
        $recordCPublic = $this->filterPrivate($recordC);
        $recordDPublic = $this->filterPrivate($recordD);

        // no owner record
        $ownerX        = (new User())->setId($this->guid++)->setRole((new Role())->setType(Role::STAFF));
        $studentX      = (new User())->setId($this->guid++)->setRole((new Role())->setType(Role::STUDENT))->setCampusCollection($studentCampus);
        $recordX       = (new Pastoral\Model())
            ->setHeading('X')
            ->setId(9)
            ->setOwner($ownerX)
            ->setModifiedBy($ownerX)
            ->setStudent($studentX)
            ->setType((new Pastoral\Type\Model())->setId(1)->setName('type'))
            ->setExternalId(null);

        $recordAForOwner = clone $recordA;
        $recordA->setRelatedRecords(Pastoral\Model::createCollection()->add($recordB)->add($recordC)->add($recordD)->add($recordX));
        $recordAForOwner->setRelatedRecords(Pastoral\Model::createCollection()->add($recordB)->add($recordC)->add($recordD));
        $recordAPublic->setRelatedRecords(Pastoral\Model::createCollection()->add($recordBPublic)->add($recordCPublic)->add($recordDPublic));

        $recordBForOwner = clone $recordB;
        $recordB->setRelatedRecords(Pastoral\Model::createCollection()->add($recordA)->add($recordC)->add($recordD)->add($recordX));
        $recordBForOwner->setRelatedRecords(Pastoral\Model::createCollection()->add($recordA)->add($recordC)->add($recordD));
        $recordBPublic->setRelatedRecords(Pastoral\Model::createCollection()->add($recordAPublic)->add($recordCPublic)->add($recordDPublic));

        $recordCForOwner = clone $recordC;
        $recordC->setRelatedRecords(Pastoral\Model::createCollection()->add($recordA)->add($recordB)->add($recordD)->add($recordX));
        $recordCForOwner->setRelatedRecords(Pastoral\Model::createCollection()->add($recordA)->add($recordB)->add($recordD));
        $recordCPublic->setRelatedRecords(Pastoral\Model::createCollection()->add($recordAPublic)->add($recordBPublic)->add($recordDPublic));

        $recordDForOwner = clone $recordD;
        $recordD->setRelatedRecords(Pastoral\Model::createCollection()->add($recordA)->add($recordB)->add($recordC)->add($recordX));
        $recordDForOwner->setRelatedRecords(Pastoral\Model::createCollection()->add($recordA)->add($recordB)->add($recordC));
        $recordDPublic->setRelatedRecords(Pastoral\Model::createCollection()->add($recordAPublic)->add($recordBPublic)->add($recordCPublic));

        return [
            [
                'original'  => $recordA,
                'moderator' => $recordA,
                'owner'     => $recordAForOwner,
                'private'   => $recordAForOwner,
                'public'    => $recordAPublic,
            ],
            [
                'original'  => $recordB,
                'moderator' => $recordB,
                'owner'     => $recordBForOwner,
                'private'   => $recordBForOwner,
                'public'    => $recordBPublic,
            ],
            [
                'original'  => $recordC,
                'moderator' => $recordC,
                'owner'     => $recordCForOwner,
                'private'   => $recordCForOwner,
                'public'    => $recordCPublic,
            ],
            [
                'original'  => $recordD,
                'moderator' => $recordD,
                'owner'     => $recordDForOwner,
                'private'   => $recordDForOwner,
                'public'    => $recordDPublic,
            ],
        ];
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
        $studentCampus = Campus::createCollection()->add((new Campus())->setId(1));

        $student = (new User())->setId($this->guid++)->setRole((new Role())->setType(Role::STUDENT))->setCampusCollection($studentCampus);
        $record  = (new Pastoral\Model())
            ->setHeading($name)
            ->setId($this->guid++)
            ->setOwner($owner)
            ->setType((new Pastoral\Type\Model())->setId(1)->setName('type'))
            ->setExternalId(null)
            ->setModifiedBy($owner)
            ->setStudent($student)
            ->setBodyPrivate('Private Body')
            ->setBodyPublic('Public Body')
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
        ));

        // set pastoral access
        if ($pastoralAccess) {
            $pastoralAccessCollection = PastoralAccessMapper::getInstance()->createCollection();
            $pastoralAccessCollection->add($pastoralAccess);
            $record->setAccess($pastoralAccessCollection);
        }

        return $record;
    }

    /**
     * Filter out private comments and attachments from the given record, to generate expected public record
     * @param Pastoral\Model $record
     * @return Pastoral\Model
     */
    private function filterPrivate(Pastoral\Model $record): Pastoral\Model
    {
        $publicRecord = clone $record;

        $publicComments = PastoralComment::createCollection();
        foreach ($record->getComments() as $comment) {
            if ($comment->isPublic()) {
                $publicComments->add($comment);
            }
        }
        $publicRecord->setComments($publicComments);

        $publicAttachments = PastoralAttachment::createCollection();
        foreach ($record->getAttachments() as $attachment) {
            if ($attachment->isPublic()) {
                $publicAttachments->add($attachment);
            }
        }
        $publicRecord->setAttachments($publicAttachments);
        $publicRecord->setBodyPrivate(null);

        return $publicRecord;
    }

    /**
     * Returns access data with expected results
     *
     * @return array
     */
    private function generateAccessData()
    {
        $studentCampus     = Campus::createCollection()->add((new Campus())->setId(1));
        $noStudentCampus   = Campus::createCollection()->add((new Campus())->setId(2));
        $privateAccessUser = (new User())->setId($this->guid++)->setUsername('private_user')->setRole((new Role())->setType(Role::STAFF))->setCampusCollection($noStudentCampus);
        $privateAccessUser->setAccessList(UserAccess::createCollection())->setPastoralGroupIds([]);

        $publicAccessUser = (new User())->setId($this->guid++)->setUsername('public_user')->setRole((new Role())->setType(Role::STAFF))->setCampusCollection($noStudentCampus);
        $publicAccessUser->setAccessList(UserAccess::createCollection())->setPastoralGroupIds([]);

        $privateAccessRole     = (new Role())->setId($this->guid++)->setType(Role::STAFF);
        $privateAccessRoleUser = (new User())->setId($this->guid++)->setUsername('private_role')->setRole($privateAccessRole)->setCampusCollection($noStudentCampus);
        $privateAccessRoleUser->setAccessList(UserAccess::createCollection())->setPastoralGroupIds([]);

        $publicAccessRole     = (new Role())->setId($this->guid++)->setType(Role::STAFF);
        $publicAccessRoleUser = (new User())->setId($this->guid++)->setUsername('public_role')->setRole($publicAccessRole)->setCampusCollection($noStudentCampus);
        $publicAccessRoleUser->setAccessList(UserAccess::createCollection())->setPastoralGroupIds([]);

        $privateAccessGroupUser = (new User())->setId($this->guid++)->setUsername('private_group')->setRole((new Role())->setType(Role::STAFF))->setCampusCollection($noStudentCampus);
        $privateAccessGroup     = (new Pastoral\Group\Model())->setId($this->guid++);
        $privateAccessGroupUser->setAccessList(UserAccess::createCollection())
                               ->setPastoralGroupIds([$privateAccessGroup->getId()])->setCampusCollection($noStudentCampus);

        $publicAccessGroupUser = (new User())->setId(107)->setUsername('public_group')->setRole((new Role())->setType(Role::STAFF))->setCampusCollection($noStudentCampus);
        $publicAccessGroup     = (new Pastoral\Group\Model())->setId($this->guid++);
        $publicAccessGroupUser->setAccessList(UserAccess::createCollection())
                              ->setPastoralGroupIds([$publicAccessGroup->getId()])->setCampusCollection($noStudentCampus);

        $publicAccessRoleCampusUser = (new User())->setId($this->guid++)->setUsername('public_role')->setRole($publicAccessRole)->setCampusCollection($studentCampus);
        $publicAccessRoleCampusUser->setAccessList(UserAccess::createCollection())->setPastoralGroupIds([]);

        return [
            [
                'record'         => 'Private user access',
                'user'           => $privateAccessUser,
                'pastoralAccess' => (new PastoralAccess())->setUser($privateAccessUser)->setAccess(PastoralAccessMapper::ACCESS_PRIVATE),
                'privateAccess'  => true,
            ],
            [
                'record'         => 'Public user access',
                'user'           => $publicAccessUser,
                'pastoralAccess' => (new PastoralAccess())->setUser($publicAccessUser)->setAccess(PastoralAccessMapper::ACCESS_PUBLIC),
                'privateAccess'  => false,
            ],
            [
                'record'         => 'Private role access',
                'user'           => $privateAccessRoleUser,
                'pastoralAccess' => (new PastoralAccess())->setRole($privateAccessRole)->setAccess(PastoralAccessMapper::ACCESS_PRIVATE),
                'privateAccess'  => true,
            ],
            [
                'record'         => 'Public role access',
                'user'           => $publicAccessRoleUser,
                'pastoralAccess' => (new PastoralAccess())->setRole($publicAccessRole)->setAccess(PastoralAccessMapper::ACCESS_PUBLIC),
                'privateAccess'  => false,
            ],
            [
                'record'         => 'Private group access',
                'user'           => $privateAccessGroupUser,
                'pastoralAccess' => (new PastoralAccess())->setGroup($privateAccessGroup)->setAccess(PastoralAccessMapper::ACCESS_PRIVATE),
                'privateAccess'  => true,
            ],
            [
                'record'         => 'Public group access',
                'user'           => $publicAccessGroupUser,
                'pastoralAccess' => (new PastoralAccess())->setGroup($publicAccessGroup)->setAccess(PastoralAccessMapper::ACCESS_PUBLIC),
                'privateAccess'  => false,
            ],
            [
                'record'         => 'Public role access with campus',
                'user'           => $publicAccessRoleCampusUser,
                'pastoralAccess' => (new PastoralAccess())->setRole($publicAccessRole)->setAccess(PastoralAccessMapper::ACCESS_PUBLIC)->setMatchCampus(true),
                'privateAccess'  => false,
            ],
        ];
    }
}
