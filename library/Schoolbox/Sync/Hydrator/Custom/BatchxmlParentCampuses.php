<?php
/**
 * BatchxmlParentCampuses
 *
 * A custom hydrator specifically for the Batchxml option in ISAMs.
 * This looks for a parent in the xml, finds their students, then figures out the campuses
 * This allows parents' accounts to be created so that later on we can build the user_relation bridge
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Sync\Hydrator\Custom;

use Monolog\Logger;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Config\Loader;
use Schoolbox\External\Connector\Isams\Base\Api;
use Schoolbox\External\Connector\Isams\Base\MockApi;
use Schoolbox\External\Normalise\User\Base as ExternalUser;
use Schoolbox\External\Normalise\User\Guardian as ExternalParent;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\Connector\Isams\Base\Importer\Batchxml;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\User as InternalUser;

class BatchxmlParentCampuses extends AbstractLoggableHydrator
{

    /**
     * @var Batchxml
     */
    private $batchxml;


    /**
     * @var CampusMapper
     */
    private $campusMapper;


    /**
     * BatchxmlParentCampuses constructor.
     */
    public function __construct()
    {
        $config = new Loader();

        $api = new Api($config->get('external_ups_provider_batchxml_host'), $config->get('external_ups_provider_batchxml_key'));

        $this->batchxml = new Batchxml($api);

        $this->campusMapper = CampusMapper::getInstance();
    }


    /**
     * Gets the publicly visible name of this hydrator.
     *
     * @return string
     */
    public function getName()
    {
        return 'Batch XML Parent Campuses for iSAMS';
    }


    /**
     * Gets the publicly visible description of this hydrator.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'This custom rule adds each parent to all the campuses to which their children belong';
    }


    /**
     * Hydrates an internal user,
     *
     * @param ExternalUser $input
     * @param InternalUser $previous
     *
     * @return InternalUser
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    public function hydrate(ExternalUser $input, InternalUser $previous)
    {
        if ($input instanceof ExternalParent) {

            /* List of campuses with which $previous should be involved
             */
            $newCampuses = [];

            // Don't attempt to parse campuses of children from $input.
            // Just get all this parent's children, and their campi.

            $parentId = $previous->getExternalId();

            $students = $this->batchxml->findStudentsOfParent($parentId);

            /* Remember all the unique campuses in which the parent is involved
             */
            /** @var Student $student */
            foreach ($students as $student) {
                foreach ($student->getCampus() as $studentCampus) {
                    try {

                        /* If we previously found this campus, don't worry - continue
                         */
                        if (isset($newCampuses[$studentCampus->getName()])) {
                            continue;
                        }

                        /* Otherwise stack it up
                         */
                        /** @var Campus $campus */
                        $campus = $this->campusMapper->findOneByColumn('name', $studentCampus->getName());

                        $newCampuses[$campus->getName()] = $campus;
                    } catch (Exception $e) {
                        $message = "Parent {$previous->getFullName()}: assignation of unknown campus {$studentCampus->getName()} found via {$student->getPreferredName()} {$student->getLastname()} {$student->getId()}";

                        $this->log(Logger::WARNING, $message, $input, $previous);
                    }
                }
            }

            /* Only assign new campuses when some were found so you can't force user accounts
             * into an illegal state which has no campuses at all
             */
            if (\count($newCampuses)) {
                $oldCampuses = $previous->getCampusCollection()->getPropertyValues('id');
                $newCampuses = new GenericCollection(Campus::class, array_values($newCampuses));

                $previous->setCampusCollection($newCampuses);

                $message = "Assigning {$newCampuses->count()} child campuses to parent {$previous->getId()} ({$previous->getExternalId()})";

                $context = [
                    'diff' => [
                        'old' => $oldCampuses,
                        'new' => $newCampuses->getPropertyValues('id'),
                    ]
                ];
            } else {
                $message = 'No child campuses found to assign to parent';
                $context = [];
            }

            $this->log(Logger::INFO, $message, $input, $previous, $context);
        }

        return $previous;
    }
}
