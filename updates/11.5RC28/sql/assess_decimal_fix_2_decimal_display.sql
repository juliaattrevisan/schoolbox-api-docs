

/* Marks are now floats rounded to 2 decimals
 */
 ALTER TABLE `assess_question`     CHANGE `marks` `marks` DECIMAL(15,2) DEFAULT '0.00' NOT NULL;
 ALTER TABLE `assess_response`     CHANGE `mark` `mark` DECIMAL(15,2) DEFAULT '0.00' NULL ;