<?php

//this function leverages off the existing imap functions in php
//it provides extra features in terms of locating and identifying particular
//parts of an email. given the recursive nature of email this is often
//a complicated process.  hopefully this file will help
include($_SERVER['DOCUMENT_ROOT']."/mail/email.php");

class mail_parser
{

        function mail_parser($inbox, $msgid)
        {
		//check email exists in mailbox
		if($msgid > 0 && $msgid <= imap_num_msg($inbox))
		{
			$this->header = imap_header($inbox, $msgid);
			$this->structure = imap_fetchstructure($inbox, $msgid);
		
			$this->inbox = $inbox;
			$this->msgid = $msgid;

			$this->parseStructure();
        	}
		else
			error(46);	

	}

	function parseStructure()
	{	
		if($this->structure->type == 1) //if multipart
			$this->email = new email("", &$this->structure);
		else
			$this->email = new email(1, &$this->structure);
	}

        function getBody($prefferred)
        {
		//gets preffered email body content
		
		$pidList = array();
		
		if($prefferred == "HTML")
		{
			$pid = $this->searchParts(0, "HTML", &$this->email, &$pidList);
			if(sizeof($pidList) < 1)
				$pid = $this->searchParts(0, "PLAIN", &$this->email, &$pidList);
			else
				$type = "HTML";
		}
		else
		{
			$pid = $this->searchParts(0, "PLAIN", &$this->email, &$pidList);
			if(sizeof($pidList) < 1)
			{
				$pid = $this->searchParts(0, "HTML", &$this->email, &$pidList);
				$type = "HTML";
			}	
		}		
	
		$body = imap_fetchbody($this->inbox, $this->msgid, $pidList[0]);

		//if we retrieved a html body format appropriately
		if($type == "HTML")
		{
			$this->decodeMail($this->getEncoding($pidList[0]), &$body);
			//get rid of html/head/body tags
			$body = preg_replace(array('/<html[^>]*>.*<body[^>]*>/si','/<\/body>*<\/html>/si'), array('',''), $body);
			$body = scrubHTML($body);
 			//if inline images are to be included
 			//if($inlineimage)
 			$body = preg_replace('/\"cid:([^\"]*)/i', "\"download.php?msgid=".trim($this->header->Msgno)."&cid=$1", $body);
			return $body;
		}
		else if($prefferred == "TEXT_ONLY") //used to output text as text
		{
			return scubTEXT(stripslashes(trim($body)));
		}	
		else //outputs a text based email formatted in html
		{
			return html_link(nl2br(scrubTEXT(stripslashes(trim($body)))));
		}

        }

        function searchParts($type, $subtype, &$part, &$resultList)
        {
			if($part->type == $type && strtolower($part->subtype) == strtolower($subtype))
				$resultList[] = $part->pid;
			else
			{
				if(is_array($part->subparts))
				{
					foreach($part->subparts as $subpart)
					{
						//first check that we not going into another message
						if($subpart->type != TYPEMESSAGE)
						{
							$this->searchParts($type, $subtype, &$subpart, &$resultList);
						}	
					}
				}
			}
	}

	function getEncoding($pid)
	{
		return $this->variableSearch($pid, "encoding", &$this->email);
	}

	function variableSearch($pid, $variable, &$part)
	{

		//this function gets the requested variables from the pid specified
		//used for getting encoding, filenames and other information about email parts

		if($part->pid == $pid)
		{
			if(is_array($variable))
			{
				foreach($variable as $varname)
				{
					//this is a dirty little hack to make arrays work aswell.
					$varstring = '$var = $part->'.$varname.';';
					eval($varstring);
					$temp[$varname] = $var;
				}
				return $temp;
			} 
			else	
				return $part->$variable;
		}
		else
		{
			if(is_array($part->subparts))
			{
				foreach($part->subparts as $subpart)
				{
					$result = $this->variableSearch($pid, $variable, &$subpart);
					if($result !== FALSE)
						return $result;
				}
			}
			return FALSE;
		}	
	
	}

	function decodeMail($encoding, &$body)
	{
		switch($encoding){
			case 0: //7BIT
                        break;

                                case 1: //8BIT
                                        $body = imap_8bit($body);
                                break;

                                case 2: //BINARY

                                case 3: //BASE64
                                        $body = imap_base64($body);
                                break;

                                case 4: //QOUTED_PRINTABLE
                                        $body = quoted_printable_decode($body);
                                break;

                                case 5: //OTHER
                        }
	}
	
	function decodeHeader($text)
	{
		//decode encoded subject headings (not common but occasionally)
		$decoded = "";

		$elements = imap_mime_header_decode($text);
		foreach($elements as $component)
			$decoded .= $component->text;
		return $decoded;
	}  

	function convertCIDtoPID($cid)
	{
		//basically seaches structure for id and returns the corresponding pid
		//this function is designed for inline images

		$pid = $this->attributeSearch(&$this->email, "id", $cid);
		
		if($pid)
			return $pid;
		else
		{
			//ok this cid aint recorded so lets look for other attributes that are know to identify
			list($first, $second) = explode("@", $cid);
			
			//look is first is a filename
			$pid = $this->attributeSearch(&$this->email, "parameters[0]->value", $first);
		
			if($pid)
				return $pid;
		}
		
		//failed 
		return FALSE;
			

	} 

	function attributeSearch(&$part, $attribute, $match)
	{
		//search through email looking for matches between attribute and match
		//this function is case insensitive and finds substrings
		//this is because email headers are notoriously unstandard so any match is a good match
		//returns the pid of the first part with the matching attribute
		

		//this is a dirty little hack to make arrays work aswell.. ;p you know it.
		$varstring = '$var = $part->'.$attribute.';';
		eval($varstring);

		//print $var ." == ". $match."<br><br>";
		if(stristr(strtolower($var),strtolower($match)) !== FALSE)
			return $part->pid;
		else
		{ 
			if(is_array($part->subparts))
			{
				foreach($part->subparts as $subpart)
				{
					$result = $this->attributeSearch(&$subpart, $attribute, $match);
					if($result !== FALSE)
						return $result;
				}
			}
			return FALSE;
		}
	}

	function getAttachmentList()
	{
		//search the email structure for attachments that are not inline

		$attachList = array();

		$this->attachmentSearch($this->email, &$attachList);

		return $attachList;			

	}

	function attachmentSearch(&$part, &$attachList)
	{
		if(strtolower($part->disposition) == "attachment" && strtolower($part->dparameters[0]->attribute) == "filename")
		{
			$temp['pid'] = $part->pid;
			$temp['name'] = $part->dparameters[0]->value;
			$temp['size'] = $part->bytes;
			$attachList[] = $temp;
		}
		else
		{
			if(is_array($part->subparts))
			{
				foreach($part->subparts as $subpart)
				{
					$result = $this->attachmentSearch(&$subpart, &$attachList);
				}
			}
		}			
	}

}


?>
