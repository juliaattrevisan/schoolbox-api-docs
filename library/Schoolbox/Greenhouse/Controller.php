<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;
use Ramsey\Uuid\Uuid;
use Schoolbox\L10n\Mapper as L10nMapper;
use Twig\Error\Error;

/**
 * The Greenhouse lets you experiment with twig templates and contents without
 * needing a backend dev to do it for you. You can also experiment with other
 * front-end stuff in an isolated manner
 */
class Controller extends Base
{
    /**
     * @var L10nMapper
     */
    protected static $l10nMapper;

    /**
     * Lets build this thing!
     */
    public function __construct()
    {
        self::$l10nMapper = L10nMapper::getInstance();
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

//        new \Alaress_Probe($uuid, strlen($uuid));

        $filtersRaw = $this->getFiltersRawArray();
        $filtersExpanded = $this->expandFiltersRawArray($filtersRaw);

//var_dump(
//    json_encode(
//        [
//            'filtersRaw' => $filtersRaw,
//            'filters' => $filtersExpanded,
//            'filtersTerminology' => $this->getTerminologyForFilters($filtersRaw)
//        ]
//    )
//);
//die;

        $this->response = $this->responseFromTemplate(
            "@greenhouse/$templateName.twig", [
                'filters' => $filtersExpanded,
                'filtersTerminology' => $this->getTerminologyForFilters($filtersRaw)
            ]
        );
    }

    /**
     * @return array - Array of filter names indexed by group name.
     */
    private function getFiltersRawArray(): array
    {
        // The hard-coded filters list returned by this function comes from https://schoolbox.atlassian.net/browse/SA-7782.

        // TODO: All the feature toggles relevant to filters are used in conf/modules/Learning.php.

        $filtersRaw = [];

        if (true) {
            $filtersRaw['Assessment'] = [
                "Due Work" => null,
                "Lesson Plan" => null,
                "LTI" => null, // Requested by Justin.
                "Project" => null,
                "Quiz" => null,
                "Task" => null,
            ];
        }

        if (true) { // Toggle in Modules > Home (Enable Badges Module)
            $filtersRaw['Badges'] = null;
        }

        if (true) {
            $filtersRaw["Events"] = null;
        }

        if (true) {
            $filtersRaw["Files"] = [
                "Audio" => null,
                "Document" => null,
                "Image" => null,
                "Other" => null,
                "Video" => null,
            ];
        }

        if (true) {
            $filtersRaw["Forums"] = null;
        }

        if (true) {
            $filtersRaw["Links"] = null;
        }

        if (true) {
            $filtersRaw["News"] = null;
        }

        if (true) {
            $filtersRaw["Pages / Folders"] = [
                "Course/Unit" => null, // Permission setting for this maybe (check feature matrix used by marketing) See Schoolbox Plan in System Settings -> Site
                "Current Class" => null,
                "Folder" => null,
                "Group" => null,
                "Homepage" => null,
            ];
        }

        if (true) {
            $filtersRaw["Textboxes"] = null;
        }

        if (true) {
            $filtersRaw["Tiles"] = null;
        }

        if (true) {
            $filtersRaw["Users"] = [ // Show option if no users of that type exist?
                "Guest" => null,
                "Parent" => null,
                "Staff" => null,
                "Student" => null,
            ];
        }

        return $filtersRaw;
    }

    /**
     * @param array $filtersRaw
     * @return array
     *  - Example: [
     *       'Due Work' => [
     *           'singular'       => 'assignment',
     *           'plural'         => 'assignments',
     *           'properSingular' => 'Assignment',
     *           'properPlural'   => 'Assignments'
     *       ],
     *       ...
     *    ]
     *
     */
    private function getTerminologyForFilters(array $filtersRaw): array
    {
        $terminologyBySingularForm = [];

        foreach ($filtersRaw as $name => $moreFilters) {
            if (is_array($moreFilters)) {
                $terminologyBySingularForm = array_merge(
                    $terminologyBySingularForm,
                    $this->getTerminologyForFilters($moreFilters)
                );
            } else {
                $terminology = self::getTerminologyMatchingSingularForm(strtolower($name));

                if ($terminology !== null) {
                    $terminologyBySingularForm[$name] = $terminology;
                }
            }
        }

        return $terminologyBySingularForm;
    }

    /**
     * @param array $filtersRaw
     * @return array
     */
    private function expandFiltersRawArray(array $filtersRaw): array
    {
        $filtersExpanded = [];

        foreach ($filtersRaw as $filterName => $moreFiltersRaw) {
            $terminology = self::getTerminologyMatchingSingularForm(strtolower($filterName));
            $filtersExpanded[] = [
                "title" => $filterName,
                "value" => ($terminology === null ? null : $terminology['term']),
                "subTypes" => is_array($moreFiltersRaw) ? $this->expandFiltersRawArray($moreFiltersRaw) : []
            ];
        }

        return $filtersExpanded;
    }

    /**
     * @param string $name
     * @return array|null
     */
    private static function getTerminologyMatchingSingularForm($name): array|null
    {
        static $terminologyBySingularForm = null;

        if ($terminologyBySingularForm === null) {
            $terminologyBySingularForm = [];

            foreach (self::$l10nMapper->findAll()->sortNaturally('title') as $termObj) {
                $singularForm = strtolower($termObj->getSingularForm());
                $terminologyBySingularForm[$singularForm] = array_merge(
                    $termObj->allForms(),
                    ['term' => $termObj->getTerm()]
                );
            }
        }

        return (array_key_exists($name, $terminologyBySingularForm)) ? $terminologyBySingularForm[$name] : null;
    }
}
