<?php

/**
 * Mapper for subjects stored within external database.
 *
 * While subjects will be stored in an external database, they will have a
 * corresponding Category object within Schoolbox.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External;

use Schoolbox;
use Schoolbox\User;

class SubjectMapper extends Schoolbox\External\Mapper
{

    /**
    * Defines mapping for this mapper.
    *
    * @return void
    */
    protected function mapping()
    {
        //
        // has an external table, not a Schoolbox one.
        // Field mappings may not be completely appropriate.
        $this->hasModel(Subject::class);
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'code',
            'property'   => 'code',
            'type'       => self::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'name',
            'property'   => 'name',
            'type'       => self::TYPE_STRING,
        ));
    }


    /**
    * Retrieves subject by Schoolbox ID.
    *
    * @throws Schoolbox\Mapper\Exception if no instance of model is found
    * @param  int $id
    * @return Subject
    */
    public function findById($id)
    {
        $statement = $this->database->prepare('
			SELECT c.id, c.name, GROUP_CONCAT(co.code) AS code
			FROM folder c
			LEFT JOIN folder_code co ON co.folder_id = c.id AND co.deleted_at IS NULL
			WHERE co.id=?
		');
        $statement->execute(array($id));
        $row = $statement->fetch();
        if (!empty($row)) {
            throw new Schoolbox\Mapper\Exception\NotFound();
        }
        return $this->hydrate($row);
    }


    /**
    * Gets all subjects for the given student.
    *
    * @param User $user
    * @return SubjectCollection
    */
    public function findAllForStudent(User $user)
    {
        $subjects = $this->external_user->getSubjectList($user);

        // if no subjects, just return an empty collection
        if ($subjects === false) {
            return new SubjectCollection();
        }

        $category_statement = $this->database->prepare('
			SELECT c.id, GROUP_CONCAT(co.code) AS code
			FROM folder c
			LEFT JOIN folder_code co ON co.folder_id = c.id AND co.deleted_at IS NULL
			WHERE (co.code=? OR co.code=?)
		');
        $add_id_map = function ($subject) use ($category_statement) {
            $category_statement->execute([
                $subject['code'],
                $subject['campusCode'] . "/" . $subject['code']
            ]);
            if ($row = $category_statement->fetch()) {
                $subject['id']   = $row['id'];
//              $subject['code'] = $row['code'];
            }
            return $subject;
        };
        $id_filter = function ($subject) {
            return isset($subject['id']);
        };
        return new SubjectCollection(
            array_filter(array_map($add_id_map, $subjects), $id_filter)
        );
    }
}
