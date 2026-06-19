ALTER TABLE resource_booking
    ADD quantity int NOT NULL DEFAULT 1 AFTER notes;
