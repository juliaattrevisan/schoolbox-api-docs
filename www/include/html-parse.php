<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	Provides some generic functions for outputting and 		*
*	parsing HTML 											*
*   $Id: html-parse.php,v 1.5 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/

// Construct the html for a form select item
// $arrOptions is an array of label/value pairs of form ('nick'=>778, 'james'=>334)
function html_select( $select_name, $arrOptions, $selected_value) {
        $html = "<select name='$select_name'>";

        foreach( array_keys( $arrOptions) as $label) {
                $html .= "<option value='" . $arrOptions[ $label] . "'";
                if( $arrOptions[ $label] == $selected_value) {
                        $html .= ' selected';
                }
                $html .= ">$label</option>";
        }
	$html .= "</select>";
        return $html;
}


// Used to replace empty strings with 'null' for inserts/updates
function sqlNull( $strIn) {
        if( $strIn == '')  {
                return 'null';
        }
        else {
                return "'$strIn'";
        }
}

// Replace 'special' characters in string with HTML-encoded character version
// str_replace faster than preg_replace or equivalent, so this is used
// Does not work perfectly on subject new headings for some reason (but works on an exactly similar body) but comes close
function html_encode( $strIn) {
        $strIn = htmlentities( $strIn);                         // This base php function does most characters
        $strIn = str_replace( '\\\\', '&#92;', $strIn);         // To protect from \\ backreferences
        $strIn = str_replace( '/', '&#47;', $strIn);
        return str_replace( '$', '&#36;', $strIn);              // Dollars are important else they can cause backreferences
                                                               //  when used in regular expressions
}

// This function fixes problems with numbers however preserves html content for browser display
function num_encode( $strIn) {
        $strIn = str_replace( '\\\\', '&#92;', $strIn);         // To protect from \\ backreferences
//        $strIn = str_replace( '/', '&#47;', $strIn);
        return str_replace( '$', '&#36;', $strIn);              // Dollars are important else they can cause backreferences
}

function remove_script($str)
{
	//removes script tags to avoid css attacks and other nasties
	$str = str_replace("<script", "", $str);
	$str = str_replace("</script>", "", $str);
} 


function html_link($message){
	//Replace links with hyperlinks in HTML
		
	$message = preg_replace("/(?<!<a href=[\"'])((((http|https|ftp):\/\/)|(www\.))([\w\.]+)([,:;%#&\/?=\w+\.-]+))(?:\b|$)/is", 
	"<a href=\"/redir.php?url=$1\" target=\"_blank\">$1</a>", $message);
	
	//Replace emails addresses with hyperlinks to email send
		
	$message = preg_replace(
	"/([_a-zA-Z0-9\.]+\@(\[?)[_a-zA-Z0-9\-\.]+(\]?))\b/i",
	"<a href=\"/mail/send.php?replyto=$1\">$1</a>", $message);

	return $message;
}


	

?>
