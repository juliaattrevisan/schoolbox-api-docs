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
        $this->uses('css', self::TYPE_STRING);
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
        $vieweeCampusList = $viewee->getCampusCollection()->indexBy('id')->keys();

        if (count($vieweeCampusList) < 1) {
            throw new MapperException('User has no campus, and therefore has no profile');
        }

        $viewerCampusList = $viewer->getCampusCollection()->indexBy('id')->keys();

        if (count($viewerCampusList) < 1) {
            throw new MapperException('Session user has no campus');
        }

        $query = "
                SELECT DISTINCT {$this->getColumnString()}
                FROM profile_button
                INNER JOIN profile_button_campus_availability ca ON profile_button.id = ca.profile_button_id
                INNER JOIN profile_button_role_availability ra ON profile_button.id = ra.profile_button_id
                INNER JOIN profile_button_campus_visibility cv ON profile_button.id = cv.profile_button_id
                INNER JOIN profile_button_role_visibility rv ON profile_button.id = rv.profile_button_id
                WHERE ca.campus_id IN " . $this->getPredicateBuilder()->getArrayPlaceholders($vieweeCampusList, 'vieweecampus') . "
                AND ra.role_id = :availabilityRoleId
                AND cv.campus_id IN " . $this->getPredicateBuilder()->getArrayPlaceholders($viewerCampusList, 'viewercampus') . "
                AND rv.role_id = :visibilityRoleId
                AND profile_button.deleted_at IS NULL
                ORDER BY sort_order ASC
        ";

        $vieweeParams = $this->getPredicateBuilder()->getArrayParameters($vieweeCampusList, 'vieweecampus');
        $viewerParams = $this->getPredicateBuilder()->getArrayParameters($viewerCampusList, 'viewercampus');

        $params = array_merge($vieweeParams, $viewerParams, [
            'availabilityRoleId' => $viewee->getRole()->getId(),
            'visibilityRoleId' => $viewer->getRole()->getId()
        ]);

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        $buttons = $this->hydrateList($statement);

        $processedButtons = new Collection();

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
            $vieweeExternalId = empty($viewee->getExternalId()) ? '' : $viewee->getExternalId();
            $viewerExternalId = empty($viewer->getExternalId()) ? '' : $viewer->getExternalId();

            $array_replace = [
                \urlencode((string)$viewee->getId()),
                \urlencode($vieweeExternalId),
                \urlencode($viewee->getUsername()),
                \urlencode($viewee->getFirstname()),
                \urlencode($viewee->getLastname()),
                \urlencode($vieweeEmail),
                \urlencode((string)$viewer->getId()),
                \urlencode($viewerExternalId),
                \urlencode($viewer->getUsername()),
                \urlencode($viewer->getFirstname()),
                \urlencode($viewer->getLastname()),
                \urlencode($viewerEmail)
            ];

            $processedButton = clone $button;
            $processedButton->setUrl(str_ireplace($array_find, $array_replace, $button->getUrl()));
            $processedButtons->add($processedButton);
        }

        return $processedButtons;
    }
}
