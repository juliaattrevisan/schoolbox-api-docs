# Add a job attempt count column:
# if a job fails more than a given number of times, mark it unprocessable
alter table assess add learnosity_job_attempt_count tinyint unsigned null after learnosity_job_status;
