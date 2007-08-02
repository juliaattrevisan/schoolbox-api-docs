<?php
# File		: Module.php
# Written	: Nick Doyle, 1/7/2002
# Description	: Extension of the Template class to allow PHP code in templates 
#		  (which I'm calling 'modules', extension '.iphp' following the standard of templates having extension '.html')
#		  Note that if you have template variable-like strings in your php e.g. {CONTENT} this may get parsed/substituted,
#		  depending on how you're doing things

require_once( 'template.php');

class Module extends Template {

		# Override the pparse function to evaluate the parsed code as php instead
		# Evaulation is left till last and performed at print-out time, so the code is only sent to PHP to evaluate once
	function pparse( $target, $handle, $append = false) {
		eval( '?>' . $this->parse( $target, $handle, $append)); 
		return false;
	}

}

?>
