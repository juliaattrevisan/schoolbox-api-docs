<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/conf/conf.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/../include/template.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/stdfuncs.php');

class Page {
		
	function Page($error = false) {
		$this->templateRoot = TEMPLATE_dir;
		$this->template = new Template($this->templateRoot);
	}

	function loadContent($pageFile, $execute=TRUE) {
		$this->pageName = $pageFile;
	
		//max dir depth is 2 so anything after that must be arguments being passed to the script
		if( !preg_match("/^[a-zA-Z0-9\/\-\_]+/", $pageFile) ) {
			$this->pageNotFound();
		}

		$path = explode('/', $pageFile);
		$page = $pageFile;
		if(count($path) > 1) { 
			$page = $path[0];
			$this->arg = array_slice($path,1);
		}
		$page = rtrim($page, '/');
		
		if(is_file($_SERVER['DOCUMENT_ROOT'].'/'.$page . '.php') && $execute)
			include($_SERVER['DOCUMENT_ROOT'].'/'.$page . '.php');
		else if(is_file($this->templateRoot .'/'. $page . '.html')) 
			$this->setFile(TEMPLATE_content, $page . '.html');
		else
			$this->pageNotFound();

	}
	

	function pageNotFound() {
		header("HTTP/1.0 404 Not Found");
		$this->setFile(TEMPLATE_content, '404.html');
		$this->outputPage();
		exit;
	}
	
	function setVar($var, $value='') {
		$this->template->set_var($var, $value);
	}

	function setBlock($parent, $handle, $name) {
		$this->template->set_block($parent, $handle, $name);
	}
	
	function parse($target, $handle, $append = false) {
		$this->template->parse($target, $handle, $append);
	}
	
	function setFile($handle, $file) {
		$this->template->set_file($handle, $file);
	}
	
	function dropDown($handle, $data, $selected='', $template = TEMPLATE_content) {
		$this->setBlock($template, $handle, $handle.'_block');
		
		foreach($data as $row) {
			foreach($row as $key => $value) {
				$this->setVar($key, $value);
				if($value == $selected)
					$this->setVar($key.'_SELECTED', 'selected="selected"');
				else
					$this->setVar($key.'_SELECTED', '');
			}
			$this->parse($handle.'_block', $handle, true); 
		}
	}

	function outputPage() {		
		$this->template->pparse('finaloutput', TEMPLATE_body);
	}
}


?>