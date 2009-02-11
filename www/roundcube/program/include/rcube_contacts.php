<?php

/*
 +-----------------------------------------------------------------------+
 | program/include/rcube_contacts.php                                    |
 |                                                                       |
 | This file is part of the RoundCube Webmail client                     |
 | Copyright (C) 2006-2008, RoundCube Dev. - Switzerland                 |
 | Licensed under the GNU GPL                                            |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Interface to the local address book database                        |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+

 $Id: rcube_contacts.inc 328 2006-08-30 17:41:21Z thomasb $

*/


/**
 * Model class for the local address book database
 *
 * @package Addressbook
 */
class rcube_contacts
{
  var $db = null;
  var $db_name = '';
  //var $user_id = 0;
  var $filter = null;
  var $result = null;
  var $search_fields;
  var $search_string;
  var $table_cols = array('title', 'firstname', 'lastname');
  
  /** public properties */
  var $primary_key = 'id';
  var $readonly = false;
  var $list_page = 1;
  var $page_size = 10;
  var $ready = false;

  
  /**
   * Object constructor
   *
   * @param object  Instance of the rcube_db class
   * @param integer User-ID
   */
  function __construct($dbconn, $user)
  {
    $this->db = $dbconn;
    $this->db_name = get_table_name('contacts');
    $this->user_id = $user;
    $this->ready = $this->db && !$this->db->is_error();
  }


  /**
   * Set internal list page
   *
   * @param  number  Page number to list
   * @access public
   */
  function set_page($page)
  {
    $this->list_page = (int)$page;
  }


  /**
   * Set internal page size
   *
   * @param  number  Number of messages to display on one page
   * @access public
   */
  function set_pagesize($size)
  {
    $this->page_size = (int)$size;
  }


  /**
   * Save a search string for future listings
   *
   * @param  string SQL params to use in listing method
   */
  function set_search_set($filter)
  {
    $this->filter = $filter;
  }
  
  
  /**
   * Getter for saved search properties
   *
   * @return mixed Search properties used by this class
   */
  function get_search_set()
  {
    return $this->filter;
  }


  /**
   * Reset all saved results and search parameters
   */
  function reset()
  {
    $this->result = null;
    $this->filter = null;
    $this->search_fields = null;
    $this->search_string = null;
  }
  
  
  /**
   * Close connection to source
   * Called on script shutdown
   */
  function close(){}
  
  
  /**
   * List the current set of contact records
   *
   * @param  array  List of cols to show
   * @param  int    Only return this number of records, use negative values for tail
   * @return array  Indexed list of contact records, each a hash array
   */
  function list_records($cols=null, $subset=0)
  {
    // count contacts for this user
    $this->result = $this->count();
    $sql_result = NULL;

    // get contacts from DB
    if ($this->result->count)
    {
      $start_row = $subset < 0 ? $this->result->first + $this->page_size + $subset : $this->result->first;
      $length = $subset != 0 ? abs($subset) : $this->page_size;
	  
       $sql_result = $this->db->limitquery(
        "SELECT * FROM ".$this->db_name.
        " WHERE (group_id = 2 or group_id = 3)" . ($this->filter ? 
        " AND (".$this->filter.")" : "") .
        " ORDER BY lastname",
        $start_row,
        $length);
    }
    
    while ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result)))
    {
	  $sql_arr['name']      = formatName($sql_arr);
      $sql_arr['firstname'] = $sql_arr['firstname'];
      $sql_arr['surname']   = $sql_arr['lastname'];
      $sql_arr['email']     = formatEmail($sql_arr);
      $sql_arr['ID'] = $sql_arr[$this->primary_key];
      $this->result->add($sql_arr);
    }
    
    return $this->result;
  }


  /**
   * Search contacts
   *
   * @param array   List of fields to search in
   * @param string  Search value
   * @param boolean True if results are requested, False if count only
   * @return Indexed list of contact records and 'count' value
   */
  function search($fields, $value, $strict=false, $select=true)
  {
    if (!is_array($fields))
      $fields = array($fields);
      
    $add_where = array();
    foreach ($fields as $col)
    {
      if ($col == 'ID' || $col == $this->primary_key)
      {
        $ids = !is_array($value) ? split(',', $value) : $value;
        $add_where[] = $this->primary_key.' IN ('.join(',', $ids).')';
      }
      else if ($strict)
        $add_where[] = $this->db->quoteIdentifier($col).'='.$this->db->quote($value);
      else
        $add_where[] = $this->db->ilike($col, '%'.$value.'%');
    }
    
    if (!empty($add_where))
    {
      $this->set_search_set(join(' OR ', $add_where));
      if ($select)
        $this->list_records();
      else
        $this->result = $this->count();
    }
   
    return $this->result; 
  }


  /**
   * Count number of available contacts in database
   *
   * @return Result array with values for 'count' and 'first'
   */
  function count()
  {
    // count contacts for this user
    $sql_result = $this->db->query(
      "SELECT COUNT(id) AS rows
       FROM  " . $this->db_name . 
       " WHERE (group_id = 2 or group_id = 3)" . ($this->filter ? 
       " AND (".$this->filter.")" : "") .
       " ORDER BY lastname");

    $sql_arr = $this->db->fetch_assoc($sql_result);
    return new rcube_result_set($sql_arr['rows'], ($this->list_page-1) * $this->page_size);;
  }


  /**
   * Return the last result set
   *
   * @return Result array or NULL if nothing selected yet
   */
  function get_result($as_res=true)
  {
    return $this->result;
  }
  
  
  /**
   * Get a specific contact record
   *
   * @param mixed record identifier(s)
   * @return Result object with all record fields or False if not found
   */
  function get_record($id, $assoc=false)
  {
    // return cached result
    if ($this->result && ($first = $this->result->first()) && $first[$this->primary_key] == $id)
      return $assoc ? $first : $this->result;
      
    $this->db->query(
      "SELECT * FROM ".$this->db_name."
       WHERE  id=?".($this->filter ? "
       AND (".$this->filter.")" : ""),
      $id);

    if ($sql_arr = $this->db->fetch_assoc())
    {
	  // Translate results
      $sql_arr['name']      = formatName($sql_arr);
      $sql_arr['firstname'] = $sql_arr['firstname'];
      $sql_arr['surname']   = $sql_arr['lastname'];
      $sql_arr['email']     = formatEmail($sql_arr);
	
      $sql_arr['ID'] = $sql_arr[$this->primary_key];
      $this->result = new rcube_result_set(1);
      $this->result->add($sql_arr);
    }

    return $assoc && $sql_arr ? $sql_arr : $this->result;
  }

}
