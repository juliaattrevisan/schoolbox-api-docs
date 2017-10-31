<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Comment;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /** @var bool $notifier Kludge to allow notifier to access pastoral records */
    private $notifier = false;

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_comment');
        $this->hasModel(Model::class);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('pastoral_id', PastoralMapper::class, 'id', null, null, 'pastoral', 'pastoral');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_code', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('private', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('owner', 'o')},
                        {$this->getJoinColumnString('pastoral', 'p')}
            FROM        {$this->getTable()} 
            LEFT JOIN   {$this->getJoinSql('owner', '', 'o')}
            LEFT JOIN   {$this->getJoinSql('pastoral', '', 'p')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // Return pastoral records fully hydrated by default

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var User $owner */
            $owner = $this->hydrateJoin($row, 'owner', 'o');
            $model->setOwner($owner);

            /** @var Pastoral $pastoral */
            $pastoral = $this->hydrateJoin($row, 'pastoral', 'p');

            $model->setPastoral($pastoral);

            // If the notifier is using the mapper this should be all it needs

            if ($this->notifier) {
                $model->setBody(null);
                $collection->add($model);
                continue;
            }

            // If there is no user in the session this call is coming from the notifier

            if (isset($this->sessionData['user'])) {
                $user = $this->getSessionUser();
            } else {
                $collection->add($model);
                continue;
            }

            $pastoralModerator = false;

            if ($user instanceof User) {
                $pastoralModerator = $this->getSessionUser()
                        ->getAccessList()
                        ->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0;
            }

            if ($pastoralModerator ||                                  // Pastoral moderators
                $model->getOwner()->getId() == $user->getId()) {       // The records owner
                $addRecord = true;                                     // Can see the comment always
            } else {
                // Work out access level use has to pastoral record

                $accessLevel = PastoralAccessMapper::ACCESS_NONE;

                foreach ($pastoral->getAccess() as $access) {
                    $accessLevelCurrent = $access->getAccessLevel($user);
                    if ($accessLevelCurrent >= $accessLevel) {
                        $accessLevel = $accessLevelCurrent;
                    }

                    if ($accessLevel >= PastoralAccessMapper::ACCESS_PRIVATE) {
                        break;
                    }
                }

                if ($model->getPrivate()) {
                    $addRecord = $accessLevel >= PastoralAccessMapper::ACCESS_PRIVATE;
                } else {
                    $addRecord = $accessLevel >= PastoralAccessMapper::ACCESS_PUBLIC;
                }
            }

            if ($addRecord) {
                $collection->add($model);
            }
        }

        return $collection;
    }
}
