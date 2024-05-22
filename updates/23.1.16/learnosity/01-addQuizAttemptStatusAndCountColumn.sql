# Add status and count columns to quiz attempts,
# analogous to those added for quizzes themselves in SA-17023.
alter table assess_attempt
    add learnosity_status enum ('pending', 'processing', 'completed', 'failed', 'unprocessable') null after learnosity_reference,
    add learnosity_poll_count tinyint unsigned null after learnosity_status;
