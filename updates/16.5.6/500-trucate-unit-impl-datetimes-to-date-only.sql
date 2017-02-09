-- Previously, a Unit Implementation's open and due dates were stored as DATETIME.
-- Logically, they can never over-lap dates, and should always start at 00:00:00 and end at 23:59:59.
-- We now rely on a custom hydrator to enforce this, and store just the date component in the DB
ALTER TABLE course_unit_implementation
  MODIFY COLUMN open_date DATE,
  MODIFY COLUMN due_date DATE;
