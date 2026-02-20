-- Parking sessions table for automatic IN/OUT tracking
CREATE TABLE IF NOT EXISTS `parking_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `in_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `out_time` datetime DEFAULT NULL,
  `guard_id` int(11) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vehicle_id_idx` (`vehicle_id`),
  CONSTRAINT `parking_sessions_vehicle_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: backfill / example insert
-- INSERT INTO `parking_sessions` (`vehicle_id`, `in_time`) VALUES (19, NOW());
