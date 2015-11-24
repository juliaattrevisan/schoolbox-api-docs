/*
SQLyog Ultimate v11.31 (64 bit)
MySQL - 5.5.43-0ubuntu0.14.04.1 : Database - schoolbox
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*Data for the table `ups_condition` */

LOCK TABLES `ups_condition` WRITE;

insert  into `ups_condition`(`id`,`rule_id`,`condition_type_id`,`arguments`) values (1,1,2,'[true]'),(2,3,1,'[\"StudentYearLevel\",[0,1,2,3,4,5,6],false]'),(3,4,1,'[\"NetworkLogin\",null,false]');

UNLOCK TABLES;

/*Data for the table `ups_effect` */

LOCK TABLES `ups_effect` WRITE;

insert  into `ups_effect`(`id`,`rule_id`,`user_property`,`effect_type_id`,`arguments`) values (1,1,'enabled',2,'[false]'),(30,2,'username',2,'[\"{:NetworkLogin}\"]'),(31,2,'title',2,'[null]'),(32,2,'firstname',2,'[\"{:StudentPreferred}\"]'),(33,2,'lastname',2,'[\"{:StudentSurname}\"]'),(34,2,'altEmail',2,'[\"{:StudentEmail}\"]'),(35,2,'localEmail',2,'[true]'),(36,2,'eportfolioEnable',2,'[true]'),(37,2,'year',2,'[\"{:StudentYearLevel}\"]'),(38,2,'externalId',2,'[\"{:StudentID}\"]'),(39,2,'superuser',2,'[false]'),(40,2,'password',2,'[\"password\"]'),(41,2,'defaultCampus',1,'[\"{:StudentCampus}\",{\"S\":1,\"J\":2}]'),(42,2,'schoolHouse',1,'[\"{:StudentHouse}\",{\"Da\":1,\"Fo\":2,\"Oa\":3,\"Th\":4}]'),(44,3,'role',2,'[3]'),(45,4,'username',2,'[\"{:StudentPreferred|lc|first}{:StudentSurname|alpha|lc}\"]');

UNLOCK TABLES;

/*Data for the table `ups_rule` */

LOCK TABLES `ups_rule` WRITE;

insert  into `ups_rule`(`id`,`role_type`,`enabled`,`sequence`) values (1,'student',1,1),(2,'student',1,2),(3,'student',1,3),(4,'student',1,4);

UNLOCK TABLES;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
