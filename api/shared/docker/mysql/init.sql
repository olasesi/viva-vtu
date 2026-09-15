-- Create separate databases for each microservice
CREATE DATABASE IF NOT EXISTS `viva_vtu_auth`;
CREATE DATABASE IF NOT EXISTS `viva_vtu_billing`;
CREATE DATABASE IF NOT EXISTS `viva_vtu_analytics`;

-- Dedicated databases used by the automated test suites (never seed these)
CREATE DATABASE IF NOT EXISTS `viva_vtu_billing_test`;
CREATE DATABASE IF NOT EXISTS `viva_vtu_auth_test`;
