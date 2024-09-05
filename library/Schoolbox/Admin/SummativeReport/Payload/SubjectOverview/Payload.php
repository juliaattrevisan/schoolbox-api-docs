<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\SubjectOverview;

use Alaress\Database\Statement\Query\IQuery;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Textbox;

class Payload
{
    private \Alaress\Database\Statement\Query\Base|IQuery $textboxStatement;


    public function __construct( private FolderMapper $folderMapper)
    {
        /* Only prepare the textbox statement once
         */
        $this->textboxStatement = $this->folderMapper->getDatabase()->prepareQuery(
        // Notes:
        //  * A similar query exists in library/Schoolbox/Search/Api/Database/ResultType/Textbox/IndexBuilder.php.
        //  * A similar query exists in \Schoolbox\Assessment\Course\Plan\Controller.php.
        //  * The displayed heading of a textbox component on a homepage will be:
        //     * The component name if non-empty,
        //     * otherwise the textbox heading if non-empty,
        //     * otherwise Component_Homepage_Controller::DEFAULT_COMPONENT_HEADING.
        //    (see library/Schoolbox/Resource/Textbox/Component/Homepage/Controller.php)
        //    So that is the heading we must use for the search index.
            "SELECT
    COALESCE(
            IF(cci_componentname.value = '', NULL, cci_componentname.value),
            IF(tb.heading = '', NULL, tb.heading),
            :homepageDefaultComponentHeading
    ) AS componentHeading,
    cci_textbox.component_instance_id AS componentID,
    tb.textBoxID AS textboxID,
    tb.body AS textboxBody ,
    tb.updated_at AS updated_at
FROM component_definition cd
         JOIN component_instance ci ON (ci.component_definition_id = cd.id AND ci.module = 'homepage')
         JOIN folder f ON f.id = ci.identifier
         JOIN component_config_definition ccd_textbox ON (
    ccd_textbox.component_definition_id = cd.id AND
    ccd_textbox.key = :homepageComponentKey
    )
         LEFT JOIN component_config_instance cci_textbox ON (
    cci_textbox.component_config_definition_id = ccd_textbox.id AND
    cci_textbox.component_instance_id = ci.id
    )
         LEFT JOIN textBox tb ON (
    CAST(tb.textBoxID AS NCHAR)=cci_textbox.value AND
    tb.deleted_at IS NULL
    )
         JOIN component_config_definition ccd_componentname ON (
    ccd_componentname.component_definition_id = cd.id AND
    ccd_componentname.key = :componentConfigComponentNameKey
    )
         LEFT JOIN component_config_instance cci_componentname ON (
    cci_componentname.component_config_definition_id = ccd_componentname.id AND
    cci_componentname.component_instance_id = ci.id
    )
WHERE cd.`class` = 'Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller'
  AND f.deleted_at IS NULL
  AND f.id = :folderId
and COALESCE(
        IF(cci_componentname.value = '', NULL, cci_componentname.value),
        IF(tb.heading = '', NULL, tb.heading),
        :homepageDefaultComponentHeading
    )  = :componentHeading
ORDER BY tb.updated_at ASC"
        );
    }
    public function generate(array $folderId, string $searchTerm): array
    {
        $courses = []; // temp storage of courses and their textbox.
        $homepages = []; // temp storage of homepages, and their course textboxes
        // find the collection of courses imported onto the specified pages
        $query = "select course_specification.folder_id, folder.name, course_implementation.folder_id
                    from course_specification
                        join course_implementation on course_specification.id = course_implementation.course_specification_id
                        join folder on course_specification.folder_id = folder.id
                    where 
                        course_implementation.deleted_at is null AND
                        course_implementation.folder_id in (:folderIDs);"; // finding all courses that have been imported onto the specified class pages.
        // find any textboxes that match the search term
        $this->textboxStatement->execute([
            'homepageDefaultComponentHeading' => Textbox\Component\Homepage\Controller::DEFAULT_COMPONENT_HEADING,
            'homepageComponentKey' => 'textbox',
            'componentConfigComponentNameKey' => 'componentName',
            'folderId' => $folderId
        ]);
        // sort the textboxes by course title naturally
        // return the textboxes grouped by the folder Id
    }
}
