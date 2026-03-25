-- Add IB Candidate Number field to the user table as per SA-21578
ALTER TABLE `user`
    ADD COLUMN `ib_candidate_number` varchar(255) DEFAULT NULL;
