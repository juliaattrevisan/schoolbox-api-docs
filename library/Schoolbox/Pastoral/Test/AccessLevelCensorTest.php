<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Pastoral\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;

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
     * @param Pastoral\Model      $record
     * @param User                $user
     * @param Pastoral\Model|null $expected
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_noAccess(Pastoral\Model $record, User $user, bool $isNull, ?int $expectedRelatedCount): void
    {
        // Apply the filtering logic multiple times to check for idempotence
        for($i = 0; $i < 10; $i++){
            $actual = $this->accessLevelCensor->filterRecordForUser($record, $user);
            $isNull ? $this->assertNull($actual) : $this->assertNotNull($actual);
            if(!$isNull){
                $this->assertEquals($expectedRelatedCount, $actual->getRelatedRecords()->count());
            }
        }
    }

    /**
     * Tests a user with pastoral mod perm
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_pastoralModPerm(): void
    {


    }

    /**
     * Tests a user who owns the pastoral record
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_recordOwner(): void
    {

    }

    /**
     * Tests a user who has been granted private access via a user access rule
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_privateAccessUser(): void
    {

    }

    /**
     * Tests a user who has been granted private access via a role access rule
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_privateAccessRole(): void
    {

    }

    /**
     * Tests a user who has been granted private access via a pastoralGroup access rule
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_privateAccessPastoralGroup(): void
    {

    }


    /**
     * Tests a user who has been granted private access via a user access rule
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_publicAccessUser(): void
    {

    }

    /**
     * Tests a user who has been granted private access via a role access rule
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_publicAccessRole(): void
    {

    }

    /**
     * Tests a user who has been granted private access via a pastoralGroup access rule
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_publicAccessPastoralGroup(): void
    {

    }


    /**
     * Tests a user who has been granted private access via a user access rule
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_denyAccessUser(): void
    {

    }

    /**
     * Tests a user who has been granted private access via a role access rule
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_denyAccessRole(): void
    {

    }

    /**
     * Tests a user who has been granted private access via a pastoralGroup access rule
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_denyAccessPastoralGroup(): void
    {

    }



    /**
     * Tests a user who has been granted a mixture of DENY + PRIVATE access
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function test_filterRecordsForUser_mixedAccess(): void
    {

    }

    // TODO test comments, attachments, etc

    // ======================== Providers ======================== //

    public function providerRelatedRecordCollection(): array
    {
        // (Pastoral\Model $record, User $user, bool $isNull, ?int $expectedRelatedCount)
        $data = [];

        // no Access
        $noAccessUser = (new User())->setId(100)->setAccessList(UserAccess::createCollection());
        foreach ($this->buildRelatedRecordCollection() as $record) {
            $data[] = [$record, $noAccessUser, true, null];
        }
        return $data;
//
//
//        $pastoralModerator = new User();
//        $pastoralModPerm = (new UserAccess())->setAccessLevel(UserAccess::ACCESS_WRITE)->setService(UserAccess::SERVICE_PASTORAL)->setUser($pastoralModerator);
//        $accessList = UserAccessMapper::getInstance()->createCollection();
//        $accessList->add($pastoralModPerm);
//        $pastoralModerator->setAccessList($accessList);
//
//        $filtered = (new Pastoral\AccessLevelCensor())->filterRecordsForUser($records, $pastoralModerator);
//        $this->assertEquals($filtered->count(), 4);
    }


    // ======================== Helpers ======================== //


    /**
     * Returns a set of 4 related pastoral records, for students with ids 1-4.
     *
     * Does not contain any access records
     *
     * @return GenericCollection|Pastoral\Model[]
     */
    private function buildRelatedRecordCollection(): GenericCollection
    {
        $recordA = (new Pastoral\Model())->setOwner((new User())->setId(100));
        $recordB = (new Pastoral\Model())->setOwner((new User())->setId(100));
        $recordC = (new Pastoral\Model())->setOwner((new User())->setId(100));
        $recordD = (new Pastoral\Model())->setOwner((new User())->setId(100));

        $recordA->getRelatedRecords()->add($recordB)->add($recordC)->add($recordD);
        $recordB->getRelatedRecords()->add($recordA)->add($recordC)->add($recordD);
        $recordC->getRelatedRecords()->add($recordA)->add($recordB)->add($recordD);
        $recordD->getRelatedRecords()->add($recordA)->add($recordB)->add($recordC);

        return Pastoral\Model::createCollection()->add($recordA)->add($recordB)->add($recordC)->add($recordD);
    }
}
