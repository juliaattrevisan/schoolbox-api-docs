<?php

namespace Schoolbox\Paperly\Agent;

use Alaress\Database\Connector\IConnector;

class ViewSelector
{
    public const PAPERLY_VIEW_ALL_CLASSES = 'uvPaperlyStudentsByClassCode';
    public const PAPERLY_VIEW_ALL_STAFF = 'uvPaperlyAllStaff';
    public const PAPERLY_VIEW_ALL_STUDENTS = 'uvPaperlyAllStudents';
    public const PAPERLY_VIEW_ALL_PARENTS = 'uvPaperlyParentDetails';
    public const PAPERLY_VIEW_STUDENT_AVATAR = 'uvPaperlyPhotos';
    public const PAPERLY_VIEW_STUDENT_MEDICAL = 'uvPaperlyMedicalConditions';
    public const PAPERLY_VIEW_STUDENT_CONSENT = 'uvPaperlyStudentConsents';
    public const PAPERLY_VIEW_STUDENT_ABILITIES = 'uvPaperlyStudentAbilities';
    public const PAPERLY_VIEW_STUDENT_FROM_PARENT = 'uvPaperlyAllStudents';


    public function __construct(
        private IConnector $externalDb,
    ) {
    }

    public function getView(string $view): string
    {

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
