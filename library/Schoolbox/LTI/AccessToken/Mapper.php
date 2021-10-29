<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\LTI\Provider\Mapper as ToolMapper;
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Mapper for oAuth access tokens for LTI.
 *
 * Whilst this currently covers LTI only, I can see a future where oAuth access
 * tokens are made available for all.
 *
 * @method Model findById($identifierValue)
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] createCollection()
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lti_access_token');
        $this->hasModel(Model::class);
        $this->uses('identifier', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->joins('lti_provider_id', ToolMapper::class, 'id', null, null, 'ltiTool', 'ltiTool');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('expires_at', self::TYPE_DATETIME);
        $this->uses('scopes', self::TYPE_JSON);
    }

    /**
     * Hydrate user+role and LTI tool along with the access token.
     *
     * @return string
     */
    public function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'user')},
                {$this->getJoinColumnString('user.role', 'userRole')},
                {$this->getJoinColumnString('ltiTool', 'ltiTool')}
        ";
    }

    /**
     * Hydrate user+role and LTI tool along with the access token.
     *
     * @return string
     */
    public function getFromString()
    {
        return "
            FROM       {$this->getTable()}
            LEFT  JOIN {$this->getJoinSql('user', '', 'user', true)}
            LEFT  JOIN {$this->getJoinSql('user.role', 'user', 'userRole', true)}
            INNER JOIN {$this->getJoinSql('ltiTool', '', 'ltiTool', true)}
        ";
    }

    /**
     * Hydrate user+role and LTI tool along with the access token.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     */
    public function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            // Hydrate model
            $model = $this->hydrate($row);

            // Hydrate user+role
            $user = $this->hydrateJoin($row, 'user', 'user');
            if ($user instanceof User) {
                $userRole = $this->hydrateJoin($row, 'user.role', 'userRole');
                if ($userRole instanceof Role) {
                    $user->setRole($userRole);
                }
                $model->setUser($user);
            }

            // Hydrate LTI tool
            $ltiTool = $this->hydrateJoin($row, 'ltiTool', 'ltiTool');
            if ($ltiTool instanceof ToolModel) {
                $model->setLtiTool($ltiTool);
            }
            $collection->add($model);
        }
        return $collection;
    }
}
