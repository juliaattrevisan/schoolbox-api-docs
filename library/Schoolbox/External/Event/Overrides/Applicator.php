<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Overrides;

/**
 * Applies a set of Overrides $overrides to a usual set of externalIds $usuals
 * and figureo ut who's left standing at the end
 *
 * This is a cheapish alternative to hydrating all the objects etc. where we
 * can just do it to arrays of scalars instead
 *
 * Note that the CLEAR_STAFF override mechanism is still in play here, which
 * is used specifically to clear staff members from the final manifest
 */
class Applicator
{
    /**
     * @var \Schoolbox\External\Profile\Staff\Mapper
     */
    private \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper;


    /**
     * Remember this set if we fetched it already
     *
     * @var array|null
     */
    private ?array $staffExternalIds = null;


    /**
     * Constructor.
     *
     * @param \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper
     */
    public function __construct(\Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper)
    {
        $this->staffProfileMapper = $staffProfileMapper;
    }

    /**
     * Apply $overrides to a set of usual guys $usuals,
     * to see who still remains. Everyone still surviving
     * is the event's $manifest
     *
     * @param array $usuals
     * @param array $overrides
     * @return array
     * @throws \PDOException
     */
    public function apply(array $usuals, array $overrides): array
    {
        $manifest = $usuals;

        foreach ($overrides as $override) {
            if (\Schoolbox\External\Event\Overrides\Model::SUBTRACT === $override['action']) {
                unset($manifest[$override['external_id']]);
            } elseif (\Schoolbox\External\Event\Overrides\Model::ADD === $override['action']) {
                $manifest[$override['external_id']] = $override['external_id'];
            } elseif (\Schoolbox\External\Event\Overrides\Model::CLEAR === $override['action']) {
                $manifest = [];
            } elseif (\Schoolbox\External\Event\Overrides\Model::CLEAR_STAFF === $override['action']) {
                /* If we haven't got this already, get it and remember it
                 * in case we needed it again and again and again
                 */
                if (null === $this->staffExternalIds) {
                    $this->staffExternalIds = $this->staffProfileMapper->findAllExternalIds();
                }

                /* Get a list of staff members,
                 * then do some array magic between $usualUsers
                 * and this list of staff ids, to exclude staff
                 * from $usualUsers
                 */
                $manifest = array_diff($manifest, $this->staffExternalIds);
            }
        }

        return $manifest;
    }
}
