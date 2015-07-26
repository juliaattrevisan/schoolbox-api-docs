<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Classes->parse()`.
 *
 * Converts results to the same format that
 * `External_UserInterface->getStudentUserAccountInfo()` returns.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Classes_Map extends External_Isams_Batch_Reader_Iterator_BaseMap
{
    /**
     * @return array
     */
    public function current()
    {
        /** @var Alaress\Xml\State $current */
        $current = $this->getInnerIterator()->current();
        $result  = new SimpleXMLElement($current->getReader()->readOuterXml());
        $id      = (string)$result->attributes()->id;
        if ($current->matches('//TeachingManager/Departments/Department/Subjects/Subject')) {
            return [
                'RecordType'   => 'Subject',
                'ID'           => $id,
                'ClassName'    => "$result->Name",
                'ClassCode'    => "$result->Code",
            ];
        } elseif ($current->matches('//TeachingManager/Sets/Set')) {
            return [
                'RecordType'      => 'Set',
                'ID'              => $id,
                'ClassYear'       => (string)$result->attributes()->YearId,
                'ClassID'         => (string)$result->attributes()->SubjectId,
                'ClassCode'       => "$result->SetCode",
                'ClassTeacherIds' => $this->parseTeachers($result->Teachers[0]),
            ];
        } else {
            return [
                'RecordType'   => 'Unknown',
                'ID'           => $id,
            ];
        }
    }

    /**
     * Parses the teachers in an Teacher element.
     *
     * @param  SimpleXMLElement $teachers
     * @return array
     */
    protected function parseTeachers($teachers)
    {
        $ids = [];
        if ($teachers instanceof SimpleXMLElement) {
            foreach ($teachers->children() as $teacher) {
                $ids[] = (string)$teacher->attributes()->StaffId;
            }
        }
        return $ids;
    }

}
