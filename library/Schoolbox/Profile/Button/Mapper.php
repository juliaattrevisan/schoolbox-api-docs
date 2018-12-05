<?php
declare(strict_types=1);

namespace Schoolbox\Profile\Button;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Profile\Button\Model;
use Schoolbox\Collection;
use Schoolbox\User;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping(): void
    {
        $this->hasTable('profile_button');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('sort_order', self::TYPE_INTEGER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('text', self::TYPE_STRING);
        $this->uses('icon', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('target', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @param User $viewee
     * @param User $viewer
     * @return Collection
     * @throws MapperException
     */
    public function findButtonsForUser(User $viewee, User $viewer): Collection
    {
        $campusList = array();

        foreach ($viewee->getCampusCollection() as $campus) {
            $campusList[] = $campus->getId();
        }

        if (count($campusList) < 1) {
            throw new MapperException('User has no campus, and therefore has no profile');
        }

        $query = "
                SELECT DISTINCT {$this->getColumnString()}
                FROM profile_button
                INNER JOIN profile_button_campus ON profile_button.id = profile_button_campus.profile_button_id
                INNER JOIN profile_button_role ON profile_button.id = profile_button_role.profile_button_id
                WHERE profile_button_campus.campus_id IN " . $this->getPredicateBuilder()->getArrayPlaceholders($campusList, 'campus') . "
                AND profile_button_role.role_id = :roleId    
                AND profile_button.deleted_at IS NULL
                ORDER BY sort_order ASC
        ";

        $params = $this->getPredicateBuilder()->getArrayParameters($campusList, 'campus');
        $params['roleId'] = $viewee->getRole()->getId();

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        $buttons = $this->hydrateList($statement);

        /** @var Model $button */
        foreach ($buttons as $button) {
            // Primitive tokenization for URLs, allows injecting user IDs and possibly other variables

            $array_find = [
                '[profileId]',
                '[profileExternalId]',
                '[profileUsername]',
                '[profileFirstName]',
                '[profileLastName]',
                '[profileEmail]',
                '[id]',
                '[externalId]',
                '[username]',
                '[firstName]',
                '[lastName]',
                '[email]',
            ];

            $vieweeEmail = empty($viewee->getEmail()) ? '' : $viewee->getEmail();
            $viewerEmail = empty($viewer->getEmail()) ? '' : $viewer->getEmail();

            $array_replace = [
                \urlencode((string)$viewee->getId()),
                \urlencode($viewee->getExternalId()),
                \urlencode($viewee->getUsername()),
                \urlencode($viewee->getFirstname()),
                \urlencode($viewee->getLastname()),
                \urlencode($vieweeEmail),
                \urlencode((string)$viewer->getId()),
                \urlencode($viewer->getExternalId()),
                \urlencode($viewer->getUsername()),
                \urlencode($viewer->getFirstname()),
                \urlencode($viewer->getLastname()),
                \urlencode($viewerEmail)
            ];

            $button->setUrl(str_ireplace($array_find, $array_replace, $button->getUrl()));
        }

        return $buttons;
    }
}
