<?php

declare(strict_types=1);

namespace Schoolbox\Profile\Button;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Profile\Button\Model;
use Schoolbox\Collection;
use Schoolbox\User;

/**
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 */
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
        $this->uses('roletype', self::TYPE_STRING);
        $this->uses('type', self::TYPE_STRING);
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
        /* If the viewer is a Parent, and the Viewee is a Student,
         * and the viewer is *not* the parent of the student,
         * return an empty set: families are prohibited from seeing another family's buttons
         * (we don't have a "parents of student" impliit meaning in Profile Buttons)
         */
        if ($viewee->getRole()->isStudent() && $viewer->getRole()->isParent() && !$viewer->isParentOf($viewee)) {
            return new Collection();
        }

        /* Otherwise, continue based upon campus and roles as per the ui
         */
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
            WHERE ((ca.campus_id IN " . $this->getPredicateBuilder()->getArrayPlaceholders($vieweeCampusList, 'vieweecampus') . "
            AND ra.role_id = :availabilityRoleId
            AND cv.campus_id IN " . $this->getPredicateBuilder()->getArrayPlaceholders($viewerCampusList, 'viewercampus') . "
            AND rv.role_id = :visibilityRoleId)
            OR (self_enabled = 1 AND :vieweeId = :viewerId))
            AND profile_button.deleted_at IS NULL
            AND profile_button.roletype = :roletype
            ORDER BY sort_order ASC
        ";

        $vieweeParams = $this->getPredicateBuilder()->getArrayParameters($vieweeCampusList, 'vieweecampus');
        $viewerParams = $this->getPredicateBuilder()->getArrayParameters($viewerCampusList, 'viewercampus');

        $params = array_merge($vieweeParams, $viewerParams, [
            'availabilityRoleId' => $viewee->getRole()->getId(),
            'visibilityRoleId' => $viewer->getRole()->getId(),
            'roletype' => $viewee->getRole()->getType(),
            'vieweeId' => $viewee->getId(),
            'viewerId' => $viewer->getId()
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
                '[they]',
                '[They]',
                '[them]',
                '[Them]',
                '[theirs]',
                '[Theirs]',
            ];

            $vieweeEmail = empty($viewee->getEmail()) ? '' : $viewee->getEmail();
            $viewerEmail = empty($viewer->getEmail()) ? '' : $viewer->getEmail();
            $vieweeExternalId = empty($viewee->getExternalId()) ? '' : $viewee->getExternalId();
            $viewerExternalId = empty($viewer->getExternalId()) ? '' : $viewer->getExternalId();
            $vieweeLastName = empty($viewee->getLastName()) ? '' : $viewee->getLastName();
            $viewerLastName = empty($viewer->getLastName()) ? '' : $viewer->getLastName();
            $vieweeFirstName = empty($viewee->getFirstName()) ? '' : $viewee->getFirstName();
            $viewerFirstName = empty($viewer->getFirstName()) ? '' : $viewer->getFirstName();
            $subjectivePronoun = $viewee->getPronoun()?->getSubjective() ?: User\Pronoun\Model::SUBJECTIVE_DEFAULT;
            $subjectivePronounProper = ucfirst($viewee->getPronoun()?->getSubjective() ?: User\Pronoun\Model::SUBJECTIVE_DEFAULT);
            $objectivePronoun = $viewee->getPronoun()?->getObjective() ?: User\Pronoun\Model::OBJECTIVE_DEFAULT;
            $objectivePronounProper = ucfirst($viewee->getPronoun()?->getObjective() ?: User\Pronoun\Model::OBJECTIVE_DEFAULT);
            $possessivePronoun = $viewee->getPronoun()?->getPossessive() ?: User\Pronoun\Model::POSSESSIVE_DEFAULT;
            $possessivePronounProper = ucfirst($viewee->getPronoun()?->getPossessive() ?: User\Pronoun\Model::POSSESSIVE_DEFAULT);

            $array_replace = [
                \urlencode((string)$viewee->getId()),
                \urlencode($vieweeExternalId),
                \urlencode($viewee->getUsername()),
                \urlencode($vieweeFirstName),
                \urlencode($vieweeLastName),
                \urlencode($vieweeEmail),
                \urlencode((string)$viewer->getId()),
                \urlencode($viewerExternalId),
                \urlencode($viewer->getUsername()),
                \urlencode($viewerFirstName),
                \urlencode($viewerLastName),
                \urlencode($viewerEmail),
                \urlencode($subjectivePronoun),
                \urlencode($subjectivePronounProper),
                \urlencode($objectivePronoun),
                \urlencode($objectivePronounProper),
                \urlencode($possessivePronoun),
                \urlencode($possessivePronounProper),
            ];

            $processedButton = clone $button;
            $processedButton->setUrl(str_ireplace($array_find, $array_replace, $button->getUrl()));
            $processedButtons->add($processedButton);
        }

        return $processedButtons;
    }
}
