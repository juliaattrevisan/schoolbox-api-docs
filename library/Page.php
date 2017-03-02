<?php

require_once(WEB_root.'/include/stdfuncs.php');

class Page {

	function __construct($error = false) {
		$this->templateRoot = Cfg::get('template_dir');
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

		if(is_file(WEB_root.'/'.$page . '.php') && $execute)
			include(WEB_root.'/'.$page . '.php');
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
		$this->template->setVar($var, $value);
	}

	function setUnknowns($unknowns) {
		$this->template->setUnknowns($unknowns);
	}

	function setBlock($parent, $handle, $name = '') {
		$this->template->setBlock($parent, $handle, $name);
	}

	function parse($target, $handle, $append = false) {
		$this->template->parse($target, $handle, $append);
	}

	function setFile($handle, $file) {
		return $this->template->setFile($handle, $file);
	}

        function dropDown($handle, $data, $selected='', $mapping='', $template=TEMPLATE_content) {
                $this->setBlock($template, $handle, $handle.'_block');
		$this->setVar($handle.'_block', '');
		if(is_array($data)) {
			foreach($data as $row) {
				foreach($row as $key => $value) {
					$mapping[$key]?$key=$mapping[$key]:'';
					$this->setVar($key, Scrub::text($value));
					if($value == $selected)
						$this->setVar($key.'_SELECTED', 'selected="selected"');
					else
						$this->setVar($key.'_SELECTED', '');
				}
				$this->parse($handle.'_block', $handle, true);
			}
		}
		else
			$this->setVar($handle.'_block', '');

	}

	function outputPage() {
		$this->template->pparse('finaloutput', TEMPLATE_body);
	}
}