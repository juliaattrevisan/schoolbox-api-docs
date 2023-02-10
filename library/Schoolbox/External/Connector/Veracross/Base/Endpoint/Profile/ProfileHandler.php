<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Chandler;
use Schoolbox\External\Profile\Mapper as BaseProfileMapper;
use Schoolbox\External\Profile\Model as BaseProfile;
use Schoolbox\External\Profile\Parent\Mapper as ParentProfileMapper;
use Schoolbox\External\Profile\Parent\Model as ParentProfile;
use Schoolbox\External\Profile\Staff\Mapper as StaffProfileMapper;
use Schoolbox\External\Profile\Staff\Model as StaffProfile;
use Schoolbox\External\Profile\Student\Mapper as StudentProfileMapper;
use Schoolbox\External\Profile\Student\Model as StudentProfile;
use Schoolbox\Mapper\Exception;


/**
 * Prototype for specially handlerising data going into sis_user_*
 *
 * There's a hypothesis that changing an indexed field causes
 * some kind of corruption to occur after a transaction is committed
 * leading to substantial performance impacts
 *
 * To avoid this we can use a raw query to only update sis_user_*.updated_at
 * if none of its other fields have changed, for example, thus avoiding
 * any alleged reindexing woes.
 *
 * Mappers always save every column regardless if they've been changed or
 * not so you can't just "do a diff" but you have to go back to the old
 * Handler ways from Handler days
 */
class ProfileHandler
{
    /**
     * Constructor, with optional logging
     *
     * @param BaseProfileMapper    $baseProfileMapper
     * @param StudentProfileMapper $studentProfileMapper
     * @param ParentProfileMapper  $parentProfileMapper
     * @param StaffProfileMapper   $staffProfileMapper
     * @param LoggerInterface|null $log
     */
    public function __construct(
        private BaseProfileMapper $baseProfileMapper,
        private StudentProfileMapper $studentProfileMapper,
        private ParentProfileMapper $parentProfileMapper,
        private StaffProfileMapper $staffProfileMapper,
        private ?LoggerInterface $log = null
    ) {
    }


    /**
     * Specially craft updates for the given profiles and mappers
     *
     * @param BaseProfile $newProfile
     * @param BaseProfile $oldProfile
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function saveBaseProfile(
        BaseProfile $newProfile,
        BaseProfile $oldProfile
    ): void {
        (new Chandler($this->baseProfileMapper, $this->log))
            ->save($newProfile, $oldProfile);
    }


    /**
     * Specially craft updates for the given profiles and mappers
     *
     * @param StudentProfile $newProfile
     * @param StudentProfile $oldProfile
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function saveStudentProfile(
        StudentProfile $newProfile,
        StudentProfile $oldProfile
    ): void {
        (new Chandler($this->studentProfileMapper, $this->log))
            ->save($newProfile, $oldProfile);
    }


    /**
     * Specially craft updates for the given profiles and mappers
     *
     * @param ParentProfile $newProfile
     * @param ParentProfile $oldProfile
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function saveParentProfile(
        ParentProfile $newProfile,
        ParentProfile $oldProfile
    ): void {
        (new Chandler($this->parentProfileMapper, $this->log))
            ->save($newProfile, $oldProfile);
    }


    /**
     * Specially craft updates for the given profiles and mappers
     *
     * @param StaffProfile $newProfile
     * @param StaffProfile $oldProfile
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function saveStaffProfile(
        StaffProfile $newProfile,
        StaffProfile $oldProfile
    ): void {
        (new Chandler($this->staffProfileMapper, $this->log))
            ->save($newProfile, $oldProfile);
    }
}
