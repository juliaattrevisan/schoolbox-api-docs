<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Parent->parse()`.
 *
 * Converts results to the same format that
 * `External_UserInterface->getStudentUserAccountInfo()` returns.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Parent_Map extends External_Isams_Batch_Reader_Iterator_BaseMap
{
    /**
     * @return array
     */
    public function current()
    {
        $current = $this->getInnerIterator()->current();
        $result  = $this->stripClarkNotationFromArrayKeys($current['value']);

        // ID: the database's PK value, distinct from SchoolID
        $id = $current['attributes']['id'];
        if (array_key_exists('Address', $result)) {
            // //Contacts/Contact: build student's contacts out of this
            // iSAMS contains no home phone
            return [
                'RecordType'    => 'Contacts',
                'ID'            => $id,
                'name'          => $result['Forename'].' '.$result['Surname'],
                'home_phone'    => '',
                'work_phone'    => $result['Telephone'],
                'mobile_phone'  => $result['Mobile'],
                'email'         => $result['EmailAddress'],
                // TODO add address as separate key
                'address'       => $this->parseAddress($result['Address']),
                'students'      => $this->parseStudents($result['Pupils']),
            ];
        } else {
            return [
                'RecordType' => 'Unknown',
                'ID'         => $id,
            ];
        }
    }

    /**
     * Parses the addresses in an Address element into a Schoolbox format.
     *
     * @param  array $address
     * @return array
     */
    protected function parseAddress($address)
    {
        $address = $this->stripClarkNotationFromArrayKeys($address);
        return [
            'address'  => implode(', ', array_filter($address['AddressLines'])),
            'postcode' => $address['Postcode'],
            'suburb'   => $address['Town'],
        ];
    }

    /**
     * Parses the student list in a Pupils element into a list of student IDs.
     *
     * @param  array $students
     * @return array
     */
    protected function parseStudents($students)
    {
        $studentIds = [];
        foreach ($students as $student) {
            $values = $this->stripClarkNotationFromArrayKeys($student['value']);
            if (strtolower($values['Status']) === 'current') {
                $studentIds[] = $student['attributes']['id'];
            }
        }
        return $studentIds;
    }
}
