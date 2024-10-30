<?php

namespace Schoolbox\Paperly\Agent;

use Alaress\Database\Connector\IConnector;

class ViewSelector
{
    /** @param array $viewLookUp Approved default paperly view mapping*/
    private array $viewLookup = [
        'allClasses'            => 'uvPaperlyStudentsByClassCode',
        'getStudentsByClass'    => 'uvPaperlyStudentsByClassCode',
        'allStaffs'             => 'uvPaperlyAllStaff',
        'allStudents'           => 'uvPaperlyAllStudents',
        'getStudentsAvatar'     => 'uvPaperlyPhotos',
        'getStudentsMedical'    => 'uvPaperlyMedicalConditions',
        'getStudentsFromParent' => 'uvPaperlyAllStudents',
        'allParents'            => 'uvPaperlyParentDetails',
        'getStudentAbilities'   => 'uvPaperlyParentDetails',
        'getStudentConsents'    => 'uvPaperlyStudentConsents',
    ];

    public function __construct(
        private IConnector $externalDb,
    ) {
    }

    public function getView(string $method): string
    {
        if (!array_key_exists($method, $this->viewLookup)) {
            throw new \OutOfBoundsException('View does not exist');
        }

        $view = $this->viewLookup[$method];

        $sql = "SELECT count(TABLE_NAME)
	            FROM INFORMATION_SCHEMA.VIEWS
	            WHERE TABLE_NAME = ?";

        /* Custom views set by school should be appended by '_custom'
         */
        $customView = $view . "_custom";

        $params = [$customView];

        $rowCount = $this->externalDb->executeQuery($sql, $params)->fetchColumn();

        /* Use custom view if it exists otherwise fallback to use default view
         * which it is assumed to always exist
         */
        return $rowCount > 0 ? $customView : $view;
    }
}
