<?php

//email object class
//this class represents a email part. 
//it is annoningly recursive.. 

class email
{
	var $pid; 
	var $type;
	var $encoding;
	var $subtype;
	var $disposition;
	var $dparameters;
	var $parameters;
	var $subparts;

	function email($pid, &$part)
	{
		$this->pid = $pid;
		$this->id = $part->id;
		$this->type = $part->type;
		$this->encoding = $part->encoding;
		$this->subtype = $part->subtype;
		$this->bytes = $part->bytes;
		$this->disposition = $part->disposition;
		$this->dparameters = $part->dparameters;
		$this->parameters = $part->parameters;
	
		$this->setSubParts(&$part);
	}
	
	function setSubParts($part)
	{
		for($i = 0; $part->parts[$i] ; $i++)
		{
			
			//stupid multipart messages root starts at the second level so we need to take that into consideration

			if($this->type == TYPEMESSAGE) //message encaps like rfc822 are not counted as a level ??
				$subpart = new email($this->pid, &$part->parts[$i]);
			else if($this->pid)
				$subpart = new email($this->pid.".".($i+1), &$part->parts[$i]);
			else
				$subpart = new email(($i+1), &$part->parts[$i]);
			$this->subparts[$i] = $subpart;
		} 
	}	
}
?>
