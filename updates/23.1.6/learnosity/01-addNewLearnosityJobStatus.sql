# Add a new status: unprocessable, if for whatever reason there is bad data in
# Schoolbox's database preventing the quiz from being processed.
alter table assess
    modify learnosity_job_status enum ('pending', 'processing', 'completed', 'failed', 'unprocessable') null;
