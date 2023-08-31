<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;
use Ramsey\Uuid\Uuid;
use Twig\Error\Error;

/**
 * The Greenhouse lets you experiment with twig templates and contents without
 * needing a backend dev to do it for you. You can also experiment with other
 * front-end stuff in an isolated manner
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * Load $templateName
     *
     * @param string $templateName
     * @throws Error
     */
    public function getGreenhouse(string $templateName): void
    {
        $uuid = (string)Uuid::uuid4();

        new \Alaress_Probe($uuid, strlen($uuid));

        $filters = $this->getFiltersArray();

        $this->response = $this->responseFromTemplate(
            "@greenhouse/$templateName.twig", [
                'filters' => $filters,
                'filtersTerminology' => $this->getTerminologyForFilters($filters)
            ]
        );
    }

    /**
     * @return array - Array of filter names indexed by group name.
     */
    private function getFiltersArray(): array
    {
        // Strategy:
        //  1. The set of available filters comes from the list in https://schoolbox.atlassian.net/browse/SA-7782.
        //  2. Add or do not add filters to the $filters array depending on whether applicable features are enabled.
        //  3. Add in any applicable terms from the Personalisation > Terminology page.

        $filters = [];

        if (true) {
            $filters['Assessment'] = [
                "Due Work",
                "Lesson Plan",
                "LTI", // Requested by Justin.
                "Project",
                "Quiz",
                "Task",
            ];
        }

        if (true) { // Toggle in Modules > Home (Enable Badges Module)
            $filters['Badges'] = null;
        }

        if (true) {
            $filters["Events"] = null;
        }

        if (true) {
            $filters["Files"] = [
                "Audio",
                "Document",
                "Image",
                "Other",
                "Video",
            ];
        }

        if (true) {
            $filters["Forums"] = null;
        }

        if (true) {
            $filters["Links"] = null;
        }

        if (true) {
            $filters["News"] = null;
        }

        if (true) {
            $filters["Pages / Folders"] = [
                "Course/Unit", // Permission setting for this maybe (check feature matrix used by marketing) See Schoolbox Plan in System Settings -> Site
                "Current Class",
                "Folder",
                "Group",
                "Homepage",
            ];
        }

        if (true) {
            $filters["Textboxes"] = null;
        }

        if (true) {
            $filters["Tiles"] = null;
        }

        if (true) {
            $filters["Users"] = [ // Show option if no users of that type exist?
                "Guest",
                "Parent",
                "Staff",
                "Student",
            ];
        }

        return $filters;
    }

    /**
     * @param array $filters
     * @return array
     */
    private function getTerminologyForFilters(array $filters): array
    {
        return [];
    }
}
