<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\MobileApp;


use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class RegistryMapper
 *
 * @package Schoolbox\MobileApp
 * @method Collection|Registry[] findAll($options = [])
 * @method Collection|Registry[] findByColumn($columnName, $columnValue, $options = array())
 * @method Registry hydrate(array $row, $alias = '', $callback = null)
 */
class RegistryMapper extends Mapper
{

    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('mobile_app_registry');

        $this->hasModel(Registry::class);

        $this->joins('user_id', UserMapper::class);

        $this->uses('app_token',  self::TYPE_STRING, null, 'token');
        $this->uses('device_id',  self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('touched_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);

        $this->joins('app_id', ServiceMapper::class, 'id', null, null, 'service', 'service');
    }


    /**
     * TODO do not override findById
     *
     * @param int    $userId
     * @param string $token
     * @param string $provider
     * @return Registry
     * @throws Mapper\Exception
     */
    public function findById($userId, $token = null, $provider = null)
    {
        // mobile_app_registry.provider is a dead field:
        // look up the service's provider
        return $this->findOneByColumnList(array(
            'user_id'    => $userId,
            'app_token'  => $token,
            's.provider' => $provider,
        ));
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @throws \Schoolbox\Mapper\DefinitionException
     * @return string
     */
    public function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
            {$this->getJoinColumnString('user_id', 'u')},
            {$this->getJoinColumnString('service', 's')},
            {$this->getJoinMapper('service')->getJoinColumnString('cert', 'cert')}
        ";
    }


    /**
     * It seems we're going down this road...
     * To prevent cataclysmic backward-incompatibility, in the core, the FROM is absorbed into the SELECT... for now...
     *
     * @throws Mapper\DefinitionException
     * @return string
     */
    public function getFromString()
    {
        return "
            FROM {$this->table}
            LEFT JOIN {$this->getJoinSql('service', '', 's')}
            LEFT JOIN {$this->getJoinMapper('service')->getJoinSql('cert', 's', 'cert')}
            LEFT JOIN {$this->getJoinSql('user_id', '', 'u', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = new Collection();

        while ($r = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var Service $service */
            $service = $this->hydrateJoin($r, 'service', 's');

            if (!$service instanceof Service) {
                // if the query failed to hydrate a Service, do not return the registration
                // a registration without a service a) is invalid, and b) will explode.
                continue;
            }

            /** @var IStorage $cert */
            $cert = $this->getJoinMapper('service')->hydrateJoin($r, 'cert', 'cert');
            $service->setCertificate($cert);

            /** @var User $user */
            $user = $this->hydrateJoin($r, 'user_id', 'u');

            $reg = $this->hydrate($r);
            $reg->setUser($user);
            $reg->setService($service);

            $c->add($reg);
        }

        return $c;
    }


    /**
     * Deregister app registrations for the given user and provider.
     *
     * This is used for deprecating old providers when a registration arrives
     * for a new provider.
     *
     * @param User   $user
     * @param string $provider
     */
    public function deregisterForProvider(User $user, string $provider): void
    {
        // Which app IDs are registrations deregistered for?
        $serviceMapper = $this->getJoinMapper('service');
        $servicePb     = $serviceMapper->getPredicateBuilder()
                                       ->equals('provider', $provider);
        $appIds        = $serviceMapper->findScalarByPredicates('id', $servicePb);

        // Deregister for those app IDs and the given user
        $predicatePb = $this->getPredicateBuilder()
            ->in('app_id', $appIds)
            ->equals('user_id', $user->getId());
        $this->deleteByPredicates($predicatePb);
    }
}
