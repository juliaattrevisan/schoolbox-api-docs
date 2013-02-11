<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;

class UserMapper extends Mapper {

    protected function mapping () {

        $this->hasTable('user');
        $this->hasModel('\Schoolbox\User');

        $this->hasMap(array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => Mapper::TYPE_INTEGER,
                'identifier' => true
            ));

        $this->hasMap(array(
                'column'     => 'username',
                'property'   => 'username',
                'type'       => Mapper::TYPE_STRING,
            ));

        $this->hasMap(array(
                'column'     => 'password',
                'property'   => 'password',
                'type'       => Mapper::TYPE_STRING,
            ));


        $this->hasMap(array(
                'column'     => 'title',
                'property'   => 'title',
                'type'       => Mapper::TYPE_STRING,
            ));

        $this->hasMap(array(
                'column'     => 'firstname',
                'property'   => 'firstname',
                'type'       => Mapper::TYPE_STRING,
            ));

        $this->hasMap(array(
                'column'     => 'lastname',
                'property'   => 'lastname',
                'type'       => Mapper::TYPE_STRING,
            ));


        $this->hasMap(array(
                'column'     => 'synergy_id',
                'property'   => 'externalId',
                'type'       => Mapper::TYPE_INTEGER,
            ));

        $this->hasMap(array(
                'column'     => 'year',
                'property'   => 'year',
                'type'       => Mapper::TYPE_INTEGER,
            ));

        $this->hasMap(array(
                'column'     => 'house',
                'property'   => 'schoolHouse',
                'type'       => Mapper::TYPE_INTEGER,
            ));

        $this->hasMap(array(
                'column' => 'residential_house_id',
                'property' => 'residentialHouse',
                'type' => Mapper::TYPE_INTEGER,
            ));


        $this->hasMap(array(
                'column'     => 'local_email',
                'property'   => 'localEmail',
                'type'       => Mapper::TYPE_BOOLEAN,
            ));

        $this->hasMap(array(
                'column'     => 'alt_email',
                'property'   => 'altEmail',
                'type'       => Mapper::TYPE_STRING,
            ));

        $this->hasMap(array(
                'column'     => 'mail_handler',
                'property'   => 'useInternalMail',
                'type'       => Mapper::TYPE_INTEGER,
            ));

        $this->hasMap(array(
                'column'     => 'hash',
                'property'   => 'hash',
                'type'       => Mapper::TYPE_STRING,
            ));

        $this->hasMap(array(
                'column'     => 'key',
                'property'   => 'key',
                'type'       => Mapper::TYPE_STRING,
            ));

        $this->hasMap(array(
                'column'     => 'eportfolio_enabled',
                'property'   => 'eportfolioEnable',
                'type'       => Mapper::TYPE_STRING,
            ));

        parent::mapping();
    }

    public function findById($id) {

        $user = false;
        $roleMapper = new RoleMapper;
        $campusMapper = new CampusMapper;

        $statement = $this->database->prepare( "
            SELECT {$this->getColumnString('u')}, IF(su.id, 1, 0) as `u.superuser`,
                    {$roleMapper->getColumnString('g')},
                    {$campusMapper->getColumnString('c')}, uc.defaultCampus as `uc.defaultCampus`
                    FROM `user` u
            LEFT JOIN `groups` g ON group_id = g.`id`
            LEFT JOIN userCampus uc ON uc.`uid`=u.id
            LEFT JOIN superusers su ON su.id = u.id
            INNER JOIN luCampus c ON c.id = uc.`campus`
            WHERE u.id = ?
            " );

        $statement->execute(array($id));

        foreach( $statement as $row ) {
            if(!is_object($user)){
                $user = $this->hydrate($row, 'u');
                $user->setSuperUser($row['u.superuser'] == 1);

                $user->setRole($roleMapper->hydrate($row, 'g'));
            }

            $campus = $campusMapper->hydrate($row, 'c');

            $user->getCampusCollection()->add($campus);

            if($row['uc.defaultCampus'] == 1) {
                $user->setDefaultCampus($campus);
            }



        }
        return $user;
    }

}
