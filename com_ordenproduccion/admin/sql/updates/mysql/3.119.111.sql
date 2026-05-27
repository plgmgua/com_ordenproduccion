-- com_ordenproduccion 3.119.111-STABLE: User session audit log (Control de Ventas → User Audit, super user only).

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_user_session_audit` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL DEFAULT 0,
    `session_id` varchar(128) NOT NULL DEFAULT '',
    `ip_address` varchar(64) NOT NULL DEFAULT '',
    `user_agent` varchar(512) NOT NULL DEFAULT '',
    `browser` varchar(128) NOT NULL DEFAULT '',
    `platform` varchar(128) NOT NULL DEFAULT '',
    `device_type` varchar(32) NOT NULL DEFAULT 'unknown' COMMENT 'desktop|mobile|tablet|bot|unknown',
    `accept_language` varchar(255) NOT NULL DEFAULT '',
    `request_uri` varchar(512) NOT NULL DEFAULT '',
    `view_name` varchar(64) NOT NULL DEFAULT '',
    `task_name` varchar(128) NOT NULL DEFAULT '',
    `referer` varchar(512) NOT NULL DEFAULT '',
    `meta` mediumtext COMMENT 'JSON: username, groups, headers, session info, etc.',
    `first_seen` datetime NOT NULL,
    `last_seen` datetime NOT NULL,
    `hit_count` int unsigned NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_user_last_seen` (`user_id`, `last_seen`),
    KEY `idx_session_id` (`session_id`),
    KEY `idx_ip_last_seen` (`ip_address`, `last_seen`),
    KEY `idx_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User session / device audit for com_ordenproduccion (super user tab)';
