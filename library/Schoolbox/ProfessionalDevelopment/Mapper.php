<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * Store this so I don't need to make db schema etc
     *
     * @var array
     */
    private $states = [
        'Please select',
        'New South Wales',
        'Victoria',
        'Queensland',
        'Northern Territory',
        'Western Australia',
        'South Australia',
        'Canberra'
    ];

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('user_professionaldevelopment');
        $this->hasModel(Model::class);

        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
    }


    /**
     * @inheritdoc
     *
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getSelectString()
    {
        $userMapper = $this->getJoinMapper('user');

        return "
            SELECT
                {$this->getColumnString()},
                {$userMapper->getColumnString('u')}
        ";
    }


    /**
     * @inheritdoc
     *
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getFromString()
    {
        return "
            FROM `{$this->table}`
            LEFT JOIN {$this->getJoinSql('user', '', 'u')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $userMapper = $this->getJoinMapper('user');

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        $pd = $this->hydrate($row);
        $pd->setUser($userMapper->hydrate($row, 'u'));

        return $pd;
    }


    /**
     * Get the hardcoded list of states
     *
     * @return array
     */
    public function getStates()
    {
        return $this->states;
    }
}