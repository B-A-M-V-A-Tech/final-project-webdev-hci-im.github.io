-- Run once in phpMyAdmin or: mysql -u root < setup_powerbi_user.sql
-- Creates a read-only account for Power BI Desktop (blank root password often fails in PBI)

CREATE USER IF NOT EXISTS 'powerbi'@'localhost' IDENTIFIED BY 'SipPulse_PBI_2026';
CREATE USER IF NOT EXISTS 'powerbi'@'127.0.0.1' IDENTIFIED BY 'SipPulse_PBI_2026';

GRANT SELECT ON sip_and_pulse_db.orders TO 'powerbi'@'localhost';
GRANT SELECT ON sip_and_pulse_db.sales_analytics_rows TO 'powerbi'@'localhost';
GRANT SELECT ON sip_and_pulse_db.menu_items TO 'powerbi'@'localhost';
GRANT SELECT ON sip_and_pulse_db.reviews TO 'powerbi'@'localhost';

GRANT SELECT ON sip_and_pulse_db.orders TO 'powerbi'@'127.0.0.1';
GRANT SELECT ON sip_and_pulse_db.sales_analytics_rows TO 'powerbi'@'127.0.0.1';
GRANT SELECT ON sip_and_pulse_db.menu_items TO 'powerbi'@'127.0.0.1';
GRANT SELECT ON sip_and_pulse_db.reviews TO 'powerbi'@'127.0.0.1';

FLUSH PRIVILEGES;
