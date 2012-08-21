<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class GizaUser extends Generic_Object {

    // convenience method to encapsulate chaining
    /**
     * @return String
     */
    public function getGroupType() {
        return $this->get('group')->get('group_type');
    }

    /**
     * @param int $service
     * @param int $access
     * @return GizaCollection
     */
    public function getSubjects($service = HOMEPAGE_SERVICE, $access = READ_ACCESS) {
        $args = array(
            'service' => $service,
            'access'  => $access,
        );
        return $this->getField('subjects', $args);
    }

    public function getGroups($service = HOMEPAGE_SERVICE, $access = READ_ACCESS) {
        $args = array(
            'service' => $service,
            'access'  => $access,
        );
        return $this->getField('groups', $args);
    }

    public function getName($format = '') {

        // Check for custom format
        if (empty($format)){
            $format = Config::get('name_format');
        }

        /* Replace tags */
        preg_match_all('|{\w+}|', $format, $tags);

        if (empty($tags)) {
            return 'Unknown User';
        }

        foreach ($tags[0] as $tag) {
            $key = trim($tag, '{}');

            // use safe method for testing
            if (is_string($this->__get($key))) {
                $value = $this->get($key);
            }
            else {
                $value = '';
            }

            $format = str_replace($tag, $value, $format);
        }

        if (trim($format)) {
            return $format;
        }
        else {
            return 'Unknown User';
        }
    }

    public function __toString() {
        return $this->getName();
    }
}
