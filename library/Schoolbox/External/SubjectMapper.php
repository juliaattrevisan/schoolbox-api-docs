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
    * Gets all subjects for the given student/staff.
    * This returns subjects only in SB
    *
    * @param User $user
    * @return SubjectCollection
    */
    public function findAllForUser(User $user): SubjectCollection
    {
        $subjects = $this->external_user->getSubjectList($user);

        // if no subjects, just return an empty collection
        if (empty($subjects)) {
            return new SubjectCollection();
        }

        $folderMapper = Schoolbox\Resource\Folder\Mapper::getInstance();
        $subjectCollection = new SubjectCollection();
        foreach ($subjects as $subject) {
            $folder = $folderMapper->findForCampusAndClass($subject['campusCode'], $subject['code']);
            if (!$folder || $subjectCollection->offsetExists($folder->getId())) {
                continue;
            }
            $subjectCollection->offsetset(
                $folder->getId(),
                [
                    'id' => $folder->getId(),
                    'matchedCode' => $folder->getMatchedCode(),
                    'name' => $folder->getName(),
                    'code' => $subject['code'],
                    'campusCode' => $subject['campusCode'],
                    'classType' => $subject['classType'],
                ]
            );
        }
        return $subjectCollection->sortByCallback(
            static function (array $a, array $b): int {
                $type = strnatcasecmp($a['classType'], $b['classType']);
                if ($type === 0) {
                    $name = strnatcasecmp($a['name'], $b['name']);
                    if ($name === 0) {
                        return strnatcasecmp($a['matchedCode'], $b['matchedCode']);
                    }
                    return $name;
                }
                return $type;
            }
        );
    }
}
