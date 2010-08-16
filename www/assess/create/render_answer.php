<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

$answer_file = "assess/answer/{$this->arg[0]}/create.html";

if(is_readable("../templates/core/$answer_file")) {
    $this->setFile(TEMPLATE_content, $answer_file);
    $this->template->pparse('finaloutput', TEMPLATE_content);
}

exit;

?>
