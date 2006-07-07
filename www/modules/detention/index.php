<?php
include("../../include/session.php");
include("../../include/include.php");
include("../../include/permission.php");
include("detperm.php");

switch (permType()) {
	case'4': header("Location: admin.php");
	default: header("Location: viewDet.php");
}

?>
