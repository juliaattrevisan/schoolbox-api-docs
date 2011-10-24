<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:45 PM
 * To change this template use File | Settings | File Templates.
 */

interface External_ClassInterface {
	public function getStudents ($classCode);

	public function getTeacherName ($classCode);
}
