<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Enrolment->parse()`.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Enrolment_Map extends External_Isams_Batch_Reader_Iterator_BaseMap
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
        if ($current->matches('//TeachingManager/Sets/Set')) {
            // This class
            return [
                'RecordType'      => 'Set',
                'ID'              => $id,
                'ClassYear'       => (string)$result->attributes()->YearId,
                'ClassID'         => (string)$result->attributes()->SubjectId,
                'ClassCode'       => "$result->SetCode",
            ];
        } elseif ($current->matches('//TeachingManager/SetLists/SetList')) {
            // Students for this class
            return [
                'RecordType'      => 'SetList',
                'ID'              => $id,
                'SetID'           => (string)$result->attributes()->SetId,
                'StudentID'       => (string)$result->attributes()->PupilId,
            ];
        } else {
            return [
                'RecordType'   => 'Unknown',
                'ID'           => $id,
            ];
        }
    }
}
