<?php
/**
 * This script remaps existing deprecated components to their new counterparts, then deletes the deprecated components
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// use dryrun for test, true means don't actually touch the database yet
define('DEPRECATE_DRYRUN', false);
define('DEPRECATE_VERBOSE', false);

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$db = new DeprecatedModel();

// set where the fields will be mapped to
$mapping = array(
    // replace smalls with bigs
    'Small Links'      => 'Links',
    'Small Files'      => 'Files',
    'Small RSS Feed'   => 'RSS Feed',
    'Small Calendar'   => 'Calendar',

    'Image Simple'     => 'Image',

    'Small Due Work'   => 'Due Work',
    'Small Assignment' => 'Due Work',
    'Small Homework'   => 'Due Work',
    'Assignments'      => 'Due Work',
    'Homework'         => 'Due Work',
);

if(DEPRECATE_VERBOSE) print_r($mapping);

// indicate which NEW components must be unique per homepage
$limits = array( 'Links', 'Files', 'Calendar', 'Due Work' );

if(DEPRECATE_VERBOSE) print_r($limits);

$existing = array();

foreach($mapping as $old => $new) {

    // preserve the IDs of the existing items for each "new" component
    if(!isset($existing[$new])) {
        $query = "SELECT homepageComponentLocationID AS hcl_id
    FROM homepage_comp_location hcl
    LEFT JOIN homepage_comp hc ON hcl.component = hc.id
    WHERE hc.name = '$new'";

        if(DEPRECATE_DRYRUN) echo "[ ] $query\n\n";

        $ids = $db->db->getAll($query);

        foreach($ids as $k => $v) {
            $existing[$new][] = $v['hcl_id'];
        }
    }

    // migrate all the items from old to new
    $query =
"UPDATE homepage_comp_location hcl
    LEFT JOIN homepage_comp hc ON hcl.component = hc.id
    SET hcl.component = (
        SELECT id FROM homepage_comp WHERE `name` = '$new'
    )
    WHERE `name` = '$old'";

    if(DEPRECATE_DRYRUN)
        echo "[ ] $query\n\n";
    else
        $db->db->query($query);

}

if(DEPRECATE_VERBOSE) print_r($existing);

// iterate through the preservation list and if limited, remove any duplicates
foreach($existing as $component => $hcl_id) {
    if(!in_array($component, $limits))  {
        if(DEPRECATE_DRYRUN) echo "[-] Skipping $component.\n";
        continue;
    }

    // if the limit is exceeded, remove all but those on the preserve list
    $query = "SELECT cid AS category_id, c.name AS category_name, component AS component_id, hc.name AS component_name, COUNT(cid) AS num
    FROM homepage_comp_location hcl
    LEFT JOIN homepage_comp hc ON hcl.component = hc.id
    LEFT JOIN category c ON cid = c.id
    WHERE hc.name = '$component'
    AND c.name IS NOT NULL
    GROUP BY cid";

    $result = $db->db->getAll($query);

    if(DEPRECATE_DRYRUN) echo "[ ] Testing $component:\n    $query\n\n";
    if(DEPRECATE_VERBOSE) print_r($result);

    foreach($result as $category) {
        if($category['num'] > 1) {
            if(DEPRECATE_DRYRUN) echo "[!] More than 1 instance of {$category['component_name']} in {$category['category_name']}. Removing all unpreserved instances.\n\n";;
            if(DEPRECATE_VERBOSE) print_r($existing[$category['component_name']]);

            $preserve = implode("', '", $existing[$category['component_name']]);

            $query = "DELETE FROM homepage_comp_location
    WHERE cid = '{$category['category_id']}'
    AND component = '{$category['component_id']}'
    AND homepageComponentLocationID NOT IN ('$preserve')";

            if(DEPRECATE_DRYRUN)
                echo "[ ] $query\n\n";
            else
                $db->db->query($query);

        }
    }
}

// finally, it's time to delete the old components
$impl = implode("', '",array_keys($mapping));
$query = "DELETE FROM homepage_comp
    WHERE `name` IN ('$impl')";

if(DEPRECATE_DRYRUN)
    echo "[ ] $query\n\n";
else
    $db->db->query($query);

?>