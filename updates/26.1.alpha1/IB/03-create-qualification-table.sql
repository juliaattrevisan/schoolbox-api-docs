-- Create lu_ibis_qualification table for IB Qualifications
-- This table will store qualification information as per SA-21623

CREATE TABLE `lu_ibis_qualification` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `system_purpose` text DEFAULT NULL,
    `sequence` int(11) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `deleted_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_deleted_at` (`deleted_at`),
    KEY `idx_sequence` (`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Insert initial qualification data that was previously hardcoded
INSERT INTO `lu_ibis_qualification` (`id`, `name`, `system_purpose`, `sequence`) VALUES
(1, 'DP Year 1', 'ANTICIPATED', 1),
(2, 'DP Year 2', 'DIPLOMA', 2),
(3, 'VCE', 'COURSE', 3);