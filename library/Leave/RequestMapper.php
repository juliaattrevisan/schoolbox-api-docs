<?php

class Leave_RequestMapper extends Mapper {
    protected $userMapper;

    protected function __construct () {
        $this->setUserMapper( UserMapper::getInstance() );

        parent::__construct();
    }

    protected function mapping () {
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'user_id',
            'property'   => 'userId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'start',
            'property'   => 'start',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'finish',
            'property'   => 'finish',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'host_type',
            'property'   => 'hostType',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'host_name',
            'property'   => 'hostName',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'host_phone',
            'property'   => 'hostPhone',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'user_phone',
            'property'   => 'userPhone',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'description',
            'property'   => 'description',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'created',
            'property'   => 'created',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'status',
            'property'   => 'status',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'authorisation_datetime',
            'property'   => 'authorisationDatetime',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'authorisation_user_id',
            'property'   => 'authorisationUserId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        parent::mapping();
    }

    /**
     * Returns a collection of requests by user id
     *
     * @param $userId
     * @return Collection
     */
    public function findByUserId ($userId) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString('lr')},
              u.id AS 'u.id', u.username AS 'u.username', u.password AS 'u.password', u.group_id AS 'u.group_id',
              u.title AS 'u.title', u.firstname AS 'u.firstname', u.lastname AS 'u.lastname',
              u.email_pass AS 'u.email_pass',u.synergy_id AS 'u.synergy_id', u.campus AS 'u.campus', u.year AS 'u.year',
              u.house AS 'u.house', u.local_email AS 'u.local_email', u.alt_email AS 'u.alt_email',
              u.mail_handler AS 'u.mail_handler', u.hash AS 'u.hash', u.key AS 'u.key',
              u.eportfolio_enabled AS 'u.eportfolio_enabled',
              au.id AS 'au.id', au.username AS 'au.username', au.password AS 'au.password', au.group_id AS 'au.group_id',
              au.title AS 'au.title', au.firstname AS 'au.firstname', au.lastname AS 'au.lastname',
              au.email_pass AS 'au.email_pass',au.synergy_id AS 'au.synergy_id', au.campus AS 'au.campus', au.year AS 'au.year',
              au.house AS 'au.house', au.local_email AS 'au.local_email', au.alt_email AS 'au.alt_email',
              au.mail_handler AS 'au.mail_handler', au.hash AS 'au.hash', au.key AS 'au.key',
              au.eportfolio_enabled AS 'au.eportfolio_enabled'
            FROM `{$this->table}` lr
            LEFT JOIN `user` u ON u.id = lr.user_id
            LEFT JOIN `user` au ON au.id = lr.authorisation_user_id
            WHERE lr.user_id = :userId
        ");

        $statement->execute( array(
            'userId' => $userId
        ) );

        $requestList = $statement->fetchAll();

        $requestCollection = $this->hydrateList($requestList);

        return $requestCollection;
    }

    /**
     * Returns a collection of requests by house id
     *
     * @param $houseId
     * @return Collection
     */
    public function findByHouseId ($houseId) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString('lr')},
                {$this->getUserMapper()->getColumnString('u')},
                {$this->getUserMapper()->getColumnString('au')}
            FROM `{$this->table}` lr
            LEFT JOIN `user` u ON u.id = lr.user_id
            LEFT JOIN `user` au ON au.id = lr.authorisation_user_id
            WHERE u.house = :houseId
        ");

        $statement->execute( array(
            'houseId' => $houseId
        ) );

        $requestList = $statement->fetchAll();

        $requestCollection = $this->hydrateList($requestList);

        return $requestCollection;
    }

    public function findById ($id) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString('lr')},
                {$this->getUserMapper()->getColumnString('u')},
                {$this->getUserMapper()->getColumnString('au')}
            FROM `{$this->table}` lr
            LEFT JOIN `user` u ON u.id = lr.user_id
            LEFT JOIN `user` au ON au.id = lr.authorisation_user_id
            WHERE lr.id = :id
        ");

        $statement->execute( array(
            'id' => $id
        ) );

        $requestList = $statement->fetchAll();

        $requestCollection = $this->hydrateList($requestList);

        return $requestCollection[0];
    }

    public function findByDateRange ($start, $finish) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString('lr')},
                {$this->getUserMapper()->getColumnString('u')},
                {$this->getUserMapper()->getColumnString('au')}
            FROM `{$this->table}` lr
            LEFT JOIN `user` u ON u.id = lr.user_id
            LEFT JOIN `user` au ON au.id = lr.authorisation_user_id
            WHERE lr.start < :finish && lr.finish > :start
        ");

        $statement->execute( array(
            ':start' => $start,
            ':finish' => $finish,
        ) );

        $requestList = $statement->fetchAll();

        $requestCollection = $this->hydrateList($requestList);

        return $requestCollection;
    }

    public function hydrateList ($rowList, $alias = '', $callback = null) {
        if ( empty($alias) ) {
            $alias = 'lr';
        }

        if ( empty($callback) ) {
            $callback = function ($mapper, $request, $row) {
                /* Hydrate user */
                if ( !empty($row['u.id']) ) {
                    $request->setUser( $mapper->getUserMapper()->hydrate($row, 'u') );
                }

                /* Hydrate authorized user */
                if ( !empty($row['au.id']) ) {
                    $request->setAuthorisationUser( $mapper->getUserMapper()->hydrate($row, 'au') );
                }

                return $request;
            };
        }

        $requestList = parent::hydrateList($rowList, $alias, $callback);

        return $requestList;
    }

    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    public function getUserMapper()
    {
        return $this->userMapper;
    }
}
