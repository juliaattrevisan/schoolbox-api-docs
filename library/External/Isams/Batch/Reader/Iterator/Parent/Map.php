<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Student->parse()`.
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
        // At this stage I still can't tell if I have:
        // //CurrentPupils/Pupil or //CurrentPupilsHealth/Pupil
        // I don't know where I came from.
        $current = $this->getInnerIterator()->current();
        $result  = $this->stripClarkNotationFromArrayKeys($current['value']);
//        foreach ($current['value'] as $key => $value) {
//            $result[$this->stripClarkNotation($key)] = $value;
//        }

        // ID: the database's PK value, distinct from SchoolID
        $id = $current['attributes']['id'];
        // Don't share CanShare = 0
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
                'students'      => $this->parsePupils($result['Pupils']),
            ];
        } else {
            return [
                'RecordType' => 'Unknown',
                'ID'         => $id,
            ];
        }
    }

    protected function parseAddress($address)
    {
        // Get addresses out of this contact.
        $address = $this->stripClarkNotationFromArrayKeys($address);
        return [
            'address'  => implode(', ', array_filter($address['AddressLines'])),
            'postcode' => $address['Postcode'],
            'suburb'   => $address['Town'],
        ];
    }

    protected function parsePupils($pupils)
    {
        $studentIds = [];
        foreach ($pupils as $pupil) {
            $values = $this->stripClarkNotationFromArrayKeys($pupil['value']);
            if (strtolower($values['Status']) === 'current') {
                $studentIds[] = $pupil['attributes']['id'];
            }
        }
        return $studentIds;
    }
}
