# PHP Stan

This readme outlines the statistical analysis tool. 

## The Current Setup.

PHP stan does stastical analysis on the CLI command. Concourse the CI server runs the tool based on every new Pull Request in Jira. The tool does statistical analysis on the following folders:
* conf
* dav
* library
* tools

The settings for what is run over and autoloaded for analysis can be found [here](../phpstan.neon).

The script that is executed by the CI pipeline is [here](../tools/ci/test-phpstan-pr.sh).

Logs for each phpstan run can be found in [here](https://ci.schoolbox.com.au/) and are reported back on jira checks.

## Running PHP stan manually.

To run PHP stan manually there is a usefull script [here](../phpstan_run.sh).

Otherwise you can run the same command that is in the script or you can use the script and pipe it into a log file. eg. ../phpstan_run.sh > stan_run.log

By piping the log into a file you can use less to search through the file for string matches for errors you are looking for. However if there are not many errors you can simply print it out to screen.

After running the tool you will have a log that looks similar to this:

	Line   tools/mq/schedule-safe-restart.php                                                                                   
	
	------------------------------------------------------------------------------------- 
	96     Function interrogate not found.                                                                                      
	167    Function interrogate not found.                                                                                      
	196    Function extractEvent not found while trying to analyse it - autoloading is probably not configured properly.        
	227    Function verifyFrameTrigger not found while trying to analyse it - autoloading is probably not configured properly.  
	232    Function frame2trigger not found.                                                                                    
	285    Function interrogate not found while trying to analyse it - autoloading is probably not configured properly.         
	322    Function extractEvent not foud.                                                                                     
	343    Function verifyFrameTrigger not found.                                                                               
	369    Function frame2trigger not found while trying to analyse it - autoloading is probably not configured properly.       

It will state which file that it found the issue, what has been detected as a problem and what line it was on.

Note: Classes that are created within the file that is being scanned will not link properly when doing the analysis and you will get false failures. Strongly recomend to ensure all classes are defined in sepperate files.

## Debuging a failed PHP stan run.

If the run fails run the command manually using ./vendor/bin/phpstan analyze --debug

The debug flag will change the output to state each file that is run and you can see which is the last file to be scanned to be able to work out which file caused the faillure in analysis.