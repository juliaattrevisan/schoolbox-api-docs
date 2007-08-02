<?php
# File		: moduleTableEdit.php
# Written	: Nick Doyle, 9/12/2002
# Description	: module to edit database tables

require_once( 'common.php');
require_once( 'sql.php');

class ModuleTableEdit extends HelpDeskPage {
	var $_TPL_NAME = 'tableEdit.html';
	var $_tpl;
	var $_tableName;

		# Optional argument of user id
	function moduleTableEdit( $tableName) {
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME);
		$this->_tableName = $tableName;
	
		$this->_tpl->set_var( 'TABLE_NAME', $tableName);
		
		
	}


	function getContent() {
		if($this->_tableName){
			$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
			mysql_select_db( DB_NAME, $link);

			$sql = SQL_tableDump( $this->_tableName);
			$query = mysql_query( $sql, $link)
				or die( "moduldeTableEdit::getContent - couldn't execute query. SQL was : " . $sql);

			$this->_tpl->set_block( 'tplContent', 'rowData', 'blkRowData');
			$this->_tpl->set_block( 'tplContent', 'row', 'blkRow');

			$blnHeadingsSet = false;					# flag whether headings set yet or not
			$arrHeadings = array();



			while( $row = mysql_fetch_assoc( $query)) {
				$row = stripslashes_array($row);
                       	$this->_tpl->set_var( 'blkRowData', '');
                       	$this->_tpl->set_var( 'blkRowInput', '');

				$blnPKDone = false;
				foreach( array_keys( $row) as $key) {
					$value = $row[ $key];
                        	$this->_tpl->set_var( 'ROW_DATA', $value);
                        	$this->_tpl->set_var( 'ROW_NAME', $key);

					# If it's the PK just print
					if( $key != 'synergyID' and ( substr( $key, strlen( $key)-2, strlen( $key)) == 'ID') and !$blnPKDone) {
						$blnPKDone = true;
                        		$this->_tpl->set_var( 'blkRowInput', '');
                        		$this->_tpl->set_var( 'ROW_DATA_ID', $value);
					}
                       	$this->_tpl->parse( 'blkRowData', 'rowData', true);

					if( !$blnHeadingsSet) {
						array_push( $arrHeadings, $key);
					}
				}

				if( isset( $arrHeadings)) {
					$blnHeadingsSet = true;
				}

				$this->_tpl->parse( 'blkRow', 'row', true);
			}

			# set the headings and 'add new' row
			$this->_tpl->set_block( 'tplContent', 'headerData', 'blkHeaderData');
			$this->_tpl->set_block( 'tplContent', 'newData', 'blkNewData');
			$blnPKDone = false;
			foreach( $arrHeadings as $heading) {
                       	$this->_tpl->set_var( 'HEADER_DATA', $heading);
                       	$this->_tpl->parse( 'blkHeaderData', 'headerData', true);

					# If column ends in 'ID' hide input element (coulnd't get null block going)
				if(( substr( $heading, strlen( $heading)-2, strlen( $heading)) == 'ID') and !$blnPKDone) {
					$blnPKDone = true;
                       		$this->_tpl->set_var( 'NEW_BEFORE', '<!--');
                       		$this->_tpl->set_var( 'NEW_AFTER', '-->');
				}
				else {		# else set name of input element
                       		$this->_tpl->set_var( 'NEW_BEFORE', '');
                       		$this->_tpl->set_var( 'NEW_AFTER', '');
                       		$this->_tpl->set_var( 'NEW_DATA_NAME', TABLE_COLUMN_NAME_PREFIX . $heading);
				}
                       	$this->_tpl->parse( 'blkNewData', 'newData', true);
			}

			mysql_close( $link);

			$this->_tpl->set_var( 'TABLE_NAME', $_REQUEST[ 'tableName']);
		}
		else
		{
			$this->_tpl->set_block('tplContent', 'blockTable', 'lBlock');
			$this->_tpl->set_var( 'lBlock', '');
		}
		return $this->_tpl->parse( 'tplContenetParsed', tplContent);
	}


		# Add a new item
	function add( &$parms) {
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME, $link);

		$sql = SQL_tableAdd( $parms, $this->_tableName);
		mysql_query( $sql)
			or die( 'moduleTableEdit::add - horrible death, sql was : <br>' . $sql);

		mysql_close( $link);
	}

		# Delete an item
		# All the extra stuff is to enforce foreign keys. It's non-trivial.
	function delete( $id) {
		$idName = tableID( $this->_tableName);
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME, $link);

			# Get each sql/tablename pair, to check if that table uses the key
		$arrCheck = SQL_arrCheckForeignKeyExists( $id, $this->_tableName);
		foreach( $arrCheck as $tmp) {
			$sqlCurr = $tmp[ 'sql'];
			$tableNameCurr = $tmp[ 'tableName'];

			if( $sqlCurr != '') {		# Couldn't get array_filter to work properly so slight kludge
				$row = mysql_fetch_assoc( mysql_query( $sqlCurr));

					# If this table has a column named $idName
				if( array_key_exists( $idName, $row)) {
					$sql = SQL_checkForeignKey( $id, $tableNameCurr, $idName);
					$query = mysql_query( $sql);
					if( mysql_num_rows( $query) > 0) {
						return( "Error deleting row id '" . $id . "' from table '" . $this->_tableName . "'.<br>
							Foreign key is still in use according to SQL:<blockquote>" . $sql . "</blockquote>
							If you're aware of this and wish to continue please get a developer to do it manually.
							<br>The deletion has been cancelled.<br><br>
							<a href='javascript:history.go(-1)'>back</a>");
					}
				}
			}
		}

		$sql = SQL_tableDelete( $id, $this->_tableName);
		mysql_query( $sql, $link)
			or die( 'moduleTableEdit::delete - horrible death, sql was : <br>' . $sql);
		mysql_close( $link);

		return;
	}


		# Update an item
	function update( &$arrPArms) {
		$sql = SQL_equipmentUpdate( $this->_tableName, $arrPArms);
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		mysql_query( $sql);

		if( mysql_error() <> '') {
			die( 'Error accessing database to update equipment : ' . print_r( $details) . '<br><br' . mysql_error());
		}
	}

		# Check whethere or not to show the delete button, take appropriate action
	function showHideDeleteButton() {
		if( !$this->_blnShowDeleteButton) {
			$this->_tpl->set_block( 'tplContent', 'button_delete');
			$this->_tpl->set_var( 'button_delete', '');
			
		}
	}
}
