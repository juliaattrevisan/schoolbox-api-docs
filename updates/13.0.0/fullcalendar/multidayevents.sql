/* Add startDate and endDate columns
 */
ALTER TABLE event ADD COLUMN startDate date AFTER note;
ALTER TABLE event ADD COLUMN endDate date AFTER startTime;

/* Derive default values of startDate and endDate
 */
UPDATE event SET startDate = `date`;
UPDATE event SET endDate = `date`;

/* Ditch the old 'date' column
 */
ALTER TABLE event DROP COLUMN `date`;