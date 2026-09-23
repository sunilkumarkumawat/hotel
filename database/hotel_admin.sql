-- =============================================================================
-- Hotel Admin — database dump (MySQL / MariaDB)
--
-- Generated from database/migrations by scripts/dump-mysql.php, so it always
-- matches the app. Import this ONLY for a fresh start; if you already have the
-- app running, `php artisan migrate --seed` is the right way to pick up new
-- tables without losing your data.
--
-- phpMyAdmin:   create the `hotel_admin` database -> Import -> choose this file
-- Command line: mysql -u root hotel_admin < database/hotel_admin.sql
--
-- Sign in with: admin / password
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- -----------------------------------------------------------------------------
-- countries
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `countries`;
create table `countries` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(100) not null, `iso2` char(2) null, `phonecode` varchar(20) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

INSERT INTO `countries` (`id`, `name`, `iso2`, `phonecode`, `created_at`, `updated_at`) VALUES
  (1, 'India', 'IN', '91', '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- states
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `states`;
create table `states` (`id` bigint unsigned not null auto_increment primary key, `country_id` bigint unsigned not null, `name` varchar(100) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `states` add index `states_country_id_index`(`country_id`);

INSERT INTO `states` (`id`, `country_id`, `name`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Rajasthan', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Maharashtra', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Delhi', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Gujarat', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 'Karnataka', '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- cities
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `cities`;
create table `cities` (`id` bigint unsigned not null auto_increment primary key, `state_id` bigint unsigned not null, `name` varchar(60) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `cities` add index `cities_state_id_index`(`state_id`);

INSERT INTO `cities` (`id`, `state_id`, `name`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Jaipur', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Jodhpur', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Udaipur', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Kota', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 'Ajmer', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 2, 'Mumbai', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (7, 2, 'Pune', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (8, 2, 'Nagpur', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (9, 2, 'Nashik', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (10, 3, 'New Delhi', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (11, 3, 'Dwarka', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (12, 3, 'Rohini', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (13, 4, 'Ahmedabad', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (14, 4, 'Surat', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (15, 4, 'Vadodara', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (16, 5, 'Bengaluru', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (17, 5, 'Mysuru', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (18, 5, 'Mangaluru', '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- branches
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `branches`;
create table `branches` (`id` bigint unsigned not null auto_increment primary key, `branch_code` varchar(255) not null, `branch_name` varchar(255) not null, `director_administrator` varchar(255) null, `mobile_number` varchar(20) null, `email` varchar(255) null, `address` varchar(255) null, `country_id` bigint unsigned null, `state_id` bigint unsigned null, `city_id` bigint unsigned null, `pin_code` varchar(12) null, `expert_name` varchar(255) null, `business_type` varchar(255) null, `status` tinyint not null default '1' comment '1 = active, 0 = inactive', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `branches` add unique `branches_branch_code_unique`(`branch_code`);

alter table `branches` add `legal_name` varchar(255) null after `branch_name`;

alter table `branches` add `gst_no` varchar(20) null after `email`;

alter table `branches` add `sac_code` varchar(12) null after `gst_no`;

alter table `branches` add `logo` varchar(255) null after `sac_code`;

alter table `branches` add `reg_card_terms` text null after `logo`;

INSERT INTO `branches` (`id`, `branch_code`, `branch_name`, `director_administrator`, `mobile_number`, `email`, `address`, `country_id`, `state_id`, `city_id`, `pin_code`, `expert_name`, `business_type`, `status`, `created_at`, `updated_at`, `legal_name`, `gst_no`, `sac_code`, `logo`, `reg_card_terms`) VALUES
  (1, 'HO', 'Head Office', 'Administrator', '9876543210', 'admin@example.com', 'Main Road', 1, 1, 1, '302001', NULL, 'Hotel', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL, NULL, NULL, NULL);

-- -----------------------------------------------------------------------------
-- role
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `role`;
create table `role` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) null, `description` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `role` add index `role_branch_id_index`(`branch_id`);

INSERT INTO `role` (`id`, `branch_id`, `name`, `description`, `created_at`, `updated_at`) VALUES
  (1, NULL, 'Administrator', 'Full access to every screen, including users, roles and branches.', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Manager', 'Day-to-day operations, no access to roles or branches.', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Staff', 'Limited access — give them exactly what they need.', '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- users
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
create table `users` (`user_id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `role_id` bigint unsigned not null, `name` varchar(255) null, `username` varchar(255) not null, `mobile` varchar(20) not null, `password` varchar(255) not null, `address` varchar(255) null, `pincode` varchar(12) null, `city` varchar(255) null, `state` varchar(255) null, `country` varchar(255) null, `dob` date null, `gender` enum('male', 'female', 'transgender', 'group') null, `image` varchar(255) null, `status` tinyint not null default '1', `is_verified` tinyint not null default '0', `email_verified_at` timestamp null, `remember_token` varchar(100) null, `created_at` timestamp null, `updated_at` timestamp null, `deleted_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `users` add index `users_branch_id_index`(`branch_id`);

alter table `users` add index `users_role_id_index`(`role_id`);

alter table `users` add unique `users_username_unique`(`username`);

INSERT INTO `users` (`user_id`, `branch_id`, `role_id`, `name`, `username`, `mobile`, `password`, `address`, `pincode`, `city`, `state`, `country`, `dob`, `gender`, `image`, `status`, `is_verified`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
  (1, 1, 1, 'Administrator', 'admin', '9876543210', '$2y$12$oadWO8cam3gzNEylqYr1BuIP9WqVu3dWXmv1dWx3sAsSSg2DuzzxC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL),
  (2, 1, 3, 'Meena Devi', 'meena', '9876500011', '$2y$12$goyimB1tqSIblvKOTuT0IOIx9xR6kCHTOEteSTOxQDBrPyiIjTb52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, '2026-09-09 22:07:40', '2026-09-09 22:07:40', NULL),
  (3, 1, 3, 'Ramesh Yadav', 'ramesh', '9876500012', '$2y$12$/miyCG8kMqRz1jmfOiHcFu8c5jjXUTNDntLDFi/iF0sGKCEt3y9ay', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, '2026-09-09 22:07:40', '2026-09-09 22:07:40', NULL);

-- -----------------------------------------------------------------------------
-- password_reset_tokens
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `password_reset_tokens`;
create table `password_reset_tokens` (`email` varchar(255) not null, `token` varchar(255) not null, `created_at` timestamp null, primary key (`email`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

-- -----------------------------------------------------------------------------
-- sessions
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `sessions`;
create table `sessions` (`id` varchar(255) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` text null, `payload` longtext not null, `last_activity` int not null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `sessions` add index `sessions_user_id_index`(`user_id`);

alter table `sessions` add index `sessions_last_activity_index`(`last_activity`);

-- -----------------------------------------------------------------------------
-- module
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `module`;
create table `module` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `url` varchar(255) null, `icon` varchar(255) null, `sort` tinyint not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

INSERT INTO `module` (`id`, `name`, `url`, `icon`, `sort`, `created_at`, `updated_at`) VALUES
  (1, 'Dashboard', NULL, 'home', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 'Reservation', NULL, 'calendar', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 'Front Office', NULL, 'desktop', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 'House Keeping', NULL, 'layers', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 'Petty Cash', NULL, 'wallet', 5, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 'Accounting', NULL, 'file', 6, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (7, 'Masters', NULL, 'cog', 7, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (8, 'Administration', NULL, 'shield', 8, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (9, 'Point Of Sale', NULL, 'bag', 9, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (10, 'Reports', NULL, 'chart', 10, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (11, 'Pool', NULL, 'globe', 11, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (12, 'Banquet Hall', NULL, 'grid', 12, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (13, 'Car & Parking', NULL, 'package', 13, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (14, 'Rate Management', NULL, 'trending-up', 14, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (15, 'Compliance', NULL, 'shield', 15, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (16, 'Guest CRM', NULL, 'star', 16, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (17, 'Store', NULL, 'package', 17, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (18, 'Shift & Audit', NULL, 'shield', 18, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- submodule
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `submodule`;
create table `submodule` (`id` bigint unsigned not null auto_increment primary key, `module_id` bigint unsigned not null, `name` varchar(255) not null, `url` varchar(255) null, `sort` tinyint not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `submodule` add index `submodule_module_id_index`(`module_id`);

INSERT INTO `submodule` (`id`, `module_id`, `name`, `url`, `sort`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Dashboard', '/', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 2, 'New Reservation', 'reservation/new-reservation', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 2, 'Reservation Booking Details', 'reservation/booking-details', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 2, 'Reservation Status View', 'reservation/status-view', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 2, 'Reservation Calendar New', 'reservation/calendar-new', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 2, 'Cancel Reservation List', 'reservation/cancel-list', 5, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (7, 2, 'Reservation Calendar', 'reservation/calendar', 6, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (8, 2, 'Advanced Deposit', 'reservation/advance-deposit', 7, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (9, 2, 'Reservation Calendar Monthly', 'reservation/calendar-monthly', 8, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (10, 2, 'Return / Paidup', 'reservation/return-paidup', 9, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (11, 2, 'No Show Room Report', 'reservation/no-show-report', 10, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (12, 2, 'Booking Sheet Accounts', 'reservation/booking-sheet-accounts', 11, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (13, 3, 'Pre Reg Card', 'front-office/pre-reg-card', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (14, 3, 'Check In Guest', 'front-office/check-in-guest', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (15, 3, 'Direct Check In Guest', 'front-office/direct-check-in-guest', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (16, 3, 'Check In Guest Details', 'front-office/check-in-guest-details', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (17, 3, 'Pax Checkin', 'front-office/pax-checkin', 5, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (18, 3, 'Cancel Booking Details', 'front-office/cancel-booking-details', 6, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (19, 3, 'Room Calendar', 'front-office/room-calendar', 7, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (20, 3, 'Linked/UnLinked Report', 'front-office/linked-unlinked-report', 8, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (21, 3, 'Check Out Guest', 'front-office/check-out-guest', 9, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (22, 3, 'Calendar', 'front-office/calendar', 10, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (23, 3, 'Guest Checkout Date Extend', 'front-office/guest-checkout-date-extend', 11, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (24, 3, 'Paidup (Refund) Amount', 'front-office/paidup-refund-amount', 12, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (25, 3, 'Post Room/Mic Charges', 'front-office/post-room-mic-charges', 13, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (26, 3, 'Check Out Details', 'front-office/check-out-details', 14, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (27, 3, 'Advance Deposit/Room-Transfer', 'front-office/advance-deposit-room-transfer', 15, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (28, 3, 'Booking Linked/Unlinked', 'front-office/booking-linked-unlinked', 16, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (29, 3, 'Settlement', 'front-office/settlement', 17, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (30, 3, 'Room Wise Services', 'front-office/room-wise-services', 18, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (31, 3, 'Customer Details', 'front-office/customer-details', 19, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (32, 4, 'Housekeeping Board', 'house-keeping/board', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (33, 4, 'House Keeping Status', 'house-keeping/status', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (34, 4, 'Issue', 'house-keeping/issue', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (35, 4, 'Received', 'house-keeping/received', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (36, 4, 'Room Blocked', 'house-keeping/room-blocked', 5, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (37, 4, 'Work Order', 'house-keeping/work-order', 6, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (38, 4, 'Lost And Found Detail', 'house-keeping/lost-and-found-detail', 7, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (39, 5, 'Payment Expense', 'petty-cash/payment-expense', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (40, 5, 'Receipt', 'petty-cash/receipt', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (41, 5, 'Expense Summary Report', 'petty-cash/expense-summary-report', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (42, 5, 'Expense Head Master', 'masters/expense-head', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (43, 5, 'Receive Head Master', 'masters/receive-head', 5, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (44, 5, 'Payment Approval', 'petty-cash/payment-approval', 6, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (45, 6, 'Ledger', 'accounting/ledger', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (46, 6, 'Group', 'accounting/group', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (47, 6, 'Vendor Payment', 'accounting/vendor-payment', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (48, 6, 'Customer Receipt', 'accounting/customer-receipt', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (49, 6, 'Day Book', 'accounting/day-book', 5, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (50, 6, 'Cash Book', 'accounting/cash-book', 6, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (51, 6, 'Bank Book', 'accounting/bank-book', 7, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (52, 6, 'Payment Voucher', 'accounting/payment-voucher', 8, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (53, 6, 'Receipt Voucher', 'accounting/receipt-voucher', 9, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (54, 6, 'Contra Voucher', 'accounting/contra-voucher', 10, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (55, 6, 'Ledger Statement', 'accounting/ledger-statement', 11, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (56, 6, 'Journal', 'accounting/journal', 12, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (57, 6, 'Trial Balance', 'accounting/trial-balance', 13, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (58, 6, 'Profit & Loss', 'accounting/profit-loss', 14, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (59, 6, 'Upload E-Invoice', 'accounting/upload-e-invoice', 15, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (60, 6, 'Show E-Invoice', 'accounting/show-e-invoice', 16, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (61, 6, 'All Receipt', 'accounting/all-receipt', 17, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (62, 7, 'All Masters', 'masters', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (63, 7, 'Room Category', 'masters/room-category', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (64, 7, 'Room Type', 'masters/room-type', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (65, 7, 'Plan Type', 'masters/plan-type', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (66, 7, 'Room', 'masters/room', 5, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (67, 7, 'Tax', 'masters/tax', 6, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (68, 7, 'Service', 'masters/service', 7, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (69, 7, 'Company', 'masters/company', 8, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (70, 7, 'Booked By', 'masters/booked-by', 9, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (71, 7, 'Business Market', 'masters/business-market', 10, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (72, 7, 'Visit Purpose', 'masters/visit-purpose', 11, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (73, 7, 'Pick and Drop', 'masters/pick-drop', 12, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (74, 7, 'Billing Instruction', 'masters/billing-instruction', 13, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (75, 7, 'Pay Mode', 'masters/pay-mode', 14, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (76, 8, 'Users', 'users', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (77, 8, 'Role', 'role', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (78, 8, 'Branch', 'viewBranch', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (79, 8, 'Modules', 'modules', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (80, 8, 'Notification Settings', 'notification-settings', 5, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (81, 9, 'POS Dashboard', 'point-of-sale/dashboard', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (82, 9, 'POS', 'point-of-sale/pos', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (83, 9, 'Table Reservations', 'point-of-sale/table-reservations', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (84, 9, 'Kitchen Display System', 'point-of-sale/kitchen-display', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (85, 9, 'Room Service Orders', 'point-of-sale/room-service-orders', 5, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (86, 9, 'POS Reports', 'point-of-sale/reports', 6, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (87, 9, 'Setup', 'point-of-sale/setup', 7, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (88, 9, 'Outlets', 'point-of-sale/setup/outlets', 8, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (89, 9, 'Tables', 'point-of-sale/setup/tables', 9, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (90, 9, 'Item Category', 'point-of-sale/setup/item-category', 10, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (91, 9, 'Items', 'point-of-sale/setup/items', 11, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (92, 9, 'Rate Plan', 'point-of-sale/setup/rate-plan', 12, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (93, 9, 'Department', 'point-of-sale/setup/department', 13, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (94, 9, 'KOT Printing Setup', 'point-of-sale/setup/kot-printing', 14, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (95, 9, 'Slots', 'point-of-sale/setup/slots', 15, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (96, 9, 'Stewards', 'point-of-sale/setup/stewards', 16, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (97, 9, 'NC Types', 'point-of-sale/setup/nc-types', 17, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (98, 10, 'Reports', 'reports', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (99, 11, 'Pool Bookings', 'pool/bookings', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (100, 11, 'Pool Calendar', 'pool/calendar', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (101, 11, 'Pools', 'pool/setup', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (102, 12, 'Hall Bookings', 'hall/bookings', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (103, 12, 'Hall Calendar', 'hall/calendar', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (104, 12, 'Halls', 'hall/setup', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (105, 13, 'Parking', 'car/parking', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (106, 13, 'Pickup & Drop', 'car/trips', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (107, 13, 'Parking Slots', 'car/parking-slots', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (108, 13, 'Vehicles', 'car/vehicles', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (109, 3, 'Night Audit', 'front-office/night-audit', 20, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (110, 14, 'Rate Calendar', 'rates/calendar', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (111, 14, 'Rate Plans', 'rates/plans', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (112, 14, 'Rate Grid', 'rates/rules', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (113, 14, 'Seasons', 'rates/seasons', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (114, 15, 'Police Register', 'compliance/police-register', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (115, 15, 'Form C', 'compliance/form-c', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (116, 15, 'GST Returns', 'compliance/gst-returns', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (117, 15, 'Tally Export', 'compliance/tally-export', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (118, 16, 'Guests', 'crm/guests', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (119, 16, 'Feedback', 'crm/feedback', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (120, 16, 'Birthdays', 'crm/occasions', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (121, 17, 'Stock', 'store/stock', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (122, 17, 'Items', 'store/items', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (123, 17, 'Purchase Orders', 'store/purchase-orders', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (124, 17, 'Goods Receipts', 'store/grn', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (125, 17, 'Issues', 'store/issues', 5, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (126, 17, 'Wastage', 'store/wastage', 6, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (127, 17, 'Stock Adjustments', 'store/adjustments', 7, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (128, 17, 'Recipes', 'store/recipes', 8, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (129, 17, 'Store Categories', 'store/categories', 9, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (130, 18, 'My Shift', 'shift/my-shift', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (131, 18, 'Shift Reports', 'shift/reports', 2, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (132, 18, 'Audit Trail', 'audit/trail', 3, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (133, 18, 'Sign-in History', 'audit/logins', 4, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- user_permission
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `user_permission`;
create table `user_permission` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `branch_id` bigint unsigned null, `module_id` text null, `submodule_id` text null, `permissions` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `user_permission` add unique `user_permission_user_id_branch_id_unique`(`user_id`, `branch_id`);

alter table `user_permission` add index `user_permission_user_id_index`(`user_id`);

alter table `user_permission` add index `user_permission_branch_id_index`(`branch_id`);

INSERT INTO `user_permission` (`id`, `user_id`, `branch_id`, `module_id`, `submodule_id`, `permissions`, `created_at`, `updated_at`) VALUES
  (1, 1, 1, '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,96,97,98,99,100,101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,119,120,121,122,123,124,125,126,127,128,129,130,131,132,133', '{"1":{"view":1,"add":1,"edit":1,"delete":1},"2":{"view":1,"add":1,"edit":1,"delete":1},"3":{"view":1,"add":1,"edit":1,"delete":1},"4":{"view":1,"add":1,"edit":1,"delete":1},"5":{"view":1,"add":1,"edit":1,"delete":1},"6":{"view":1,"add":1,"edit":1,"delete":1},"7":{"view":1,"add":1,"edit":1,"delete":1},"8":{"view":1,"add":1,"edit":1,"delete":1},"9":{"view":1,"add":1,"edit":1,"delete":1},"10":{"view":1,"add":1,"edit":1,"delete":1},"11":{"view":1,"add":1,"edit":1,"delete":1},"12":{"view":1,"add":1,"edit":1,"delete":1},"13":{"view":1,"add":1,"edit":1,"delete":1},"14":{"view":1,"add":1,"edit":1,"delete":1},"15":{"view":1,"add":1,"edit":1,"delete":1},"16":{"view":1,"add":1,"edit":1,"delete":1},"17":{"view":1,"add":1,"edit":1,"delete":1},"18":{"view":1,"add":1,"edit":1,"delete":1},"19":{"view":1,"add":1,"edit":1,"delete":1},"20":{"view":1,"add":1,"edit":1,"delete":1},"21":{"view":1,"add":1,"edit":1,"delete":1},"22":{"view":1,"add":1,"edit":1,"delete":1},"23":{"view":1,"add":1,"edit":1,"delete":1},"24":{"view":1,"add":1,"edit":1,"delete":1},"25":{"view":1,"add":1,"edit":1,"delete":1},"26":{"view":1,"add":1,"edit":1,"delete":1},"27":{"view":1,"add":1,"edit":1,"delete":1},"28":{"view":1,"add":1,"edit":1,"delete":1},"29":{"view":1,"add":1,"edit":1,"delete":1},"30":{"view":1,"add":1,"edit":1,"delete":1},"31":{"view":1,"add":1,"edit":1,"delete":1},"32":{"view":1,"add":1,"edit":1,"delete":1},"33":{"view":1,"add":1,"edit":1,"delete":1},"34":{"view":1,"add":1,"edit":1,"delete":1},"35":{"view":1,"add":1,"edit":1,"delete":1},"36":{"view":1,"add":1,"edit":1,"delete":1},"37":{"view":1,"add":1,"edit":1,"delete":1},"38":{"view":1,"add":1,"edit":1,"delete":1},"39":{"view":1,"add":1,"edit":1,"delete":1},"40":{"view":1,"add":1,"edit":1,"delete":1},"41":{"view":1,"add":1,"edit":1,"delete":1},"42":{"view":1,"add":1,"edit":1,"delete":1},"43":{"view":1,"add":1,"edit":1,"delete":1},"44":{"view":1,"add":1,"edit":1,"delete":1},"45":{"view":1,"add":1,"edit":1,"delete":1},"46":{"view":1,"add":1,"edit":1,"delete":1},"47":{"view":1,"add":1,"edit":1,"delete":1},"48":{"view":1,"add":1,"edit":1,"delete":1},"49":{"view":1,"add":1,"edit":1,"delete":1},"50":{"view":1,"add":1,"edit":1,"delete":1},"51":{"view":1,"add":1,"edit":1,"delete":1},"52":{"view":1,"add":1,"edit":1,"delete":1},"53":{"view":1,"add":1,"edit":1,"delete":1},"54":{"view":1,"add":1,"edit":1,"delete":1},"55":{"view":1,"add":1,"edit":1,"delete":1},"56":{"view":1,"add":1,"edit":1,"delete":1},"57":{"view":1,"add":1,"edit":1,"delete":1},"58":{"view":1,"add":1,"edit":1,"delete":1},"59":{"view":1,"add":1,"edit":1,"delete":1},"60":{"view":1,"add":1,"edit":1,"delete":1},"61":{"view":1,"add":1,"edit":1,"delete":1},"62":{"view":1,"add":1,"edit":1,"delete":1},"63":{"view":1,"add":1,"edit":1,"delete":1},"64":{"view":1,"add":1,"edit":1,"delete":1},"65":{"view":1,"add":1,"edit":1,"delete":1},"66":{"view":1,"add":1,"edit":1,"delete":1},"67":{"view":1,"add":1,"edit":1,"delete":1},"68":{"view":1,"add":1,"edit":1,"delete":1},"69":{"view":1,"add":1,"edit":1,"delete":1},"70":{"view":1,"add":1,"edit":1,"delete":1},"71":{"view":1,"add":1,"edit":1,"delete":1},"72":{"view":1,"add":1,"edit":1,"delete":1},"73":{"view":1,"add":1,"edit":1,"delete":1},"74":{"view":1,"add":1,"edit":1,"delete":1},"75":{"view":1,"add":1,"edit":1,"delete":1},"76":{"view":1,"add":1,"edit":1,"delete":1},"77":{"view":1,"add":1,"edit":1,"delete":1},"78":{"view":1,"add":1,"edit":1,"delete":1},"79":{"view":1,"add":1,"edit":1,"delete":1},"80":{"view":1,"add":1,"edit":1,"delete":1},"81":{"view":1,"add":1,"edit":1,"delete":1},"82":{"view":1,"add":1,"edit":1,"delete":1},"83":{"view":1,"add":1,"edit":1,"delete":1},"84":{"view":1,"add":1,"edit":1,"delete":1},"85":{"view":1,"add":1,"edit":1,"delete":1},"86":{"view":1,"add":1,"edit":1,"delete":1},"87":{"view":1,"add":1,"edit":1,"delete":1},"88":{"view":1,"add":1,"edit":1,"delete":1},"89":{"view":1,"add":1,"edit":1,"delete":1},"90":{"view":1,"add":1,"edit":1,"delete":1},"91":{"view":1,"add":1,"edit":1,"delete":1},"92":{"view":1,"add":1,"edit":1,"delete":1},"93":{"view":1,"add":1,"edit":1,"delete":1},"94":{"view":1,"add":1,"edit":1,"delete":1},"95":{"view":1,"add":1,"edit":1,"delete":1},"96":{"view":1,"add":1,"edit":1,"delete":1},"97":{"view":1,"add":1,"edit":1,"delete":1},"98":{"view":1,"add":1,"edit":1,"delete":1},"99":{"view":1,"add":1,"edit":1,"delete":1},"100":{"view":1,"add":1,"edit":1,"delete":1},"101":{"view":1,"add":1,"edit":1,"delete":1},"102":{"view":1,"add":1,"edit":1,"delete":1},"103":{"view":1,"add":1,"edit":1,"delete":1},"104":{"view":1,"add":1,"edit":1,"delete":1},"105":{"view":1,"add":1,"edit":1,"delete":1},"106":{"view":1,"add":1,"edit":1,"delete":1},"107":{"view":1,"add":1,"edit":1,"delete":1},"108":{"view":1,"add":1,"edit":1,"delete":1},"109":{"view":1,"add":1,"edit":1,"delete":1},"110":{"view":1,"add":1,"edit":1,"delete":1},"111":{"view":1,"add":1,"edit":1,"delete":1},"112":{"view":1,"add":1,"edit":1,"delete":1},"113":{"view":1,"add":1,"edit":1,"delete":1},"114":{"view":1,"add":1,"edit":1,"delete":1},"115":{"view":1,"add":1,"edit":1,"delete":1},"116":{"view":1,"add":1,"edit":1,"delete":1},"117":{"view":1,"add":1,"edit":1,"delete":1},"118":{"view":1,"add":1,"edit":1,"delete":1},"119":{"view":1,"add":1,"edit":1,"delete":1},"120":{"view":1,"add":1,"edit":1,"delete":1},"121":{"view":1,"add":1,"edit":1,"delete":1},"122":{"view":1,"add":1,"edit":1,"delete":1},"123":{"view":1,"add":1,"edit":1,"delete":1},"124":{"view":1,"add":1,"edit":1,"delete":1},"125":{"view":1,"add":1,"edit":1,"delete":1},"126":{"view":1,"add":1,"edit":1,"delete":1},"127":{"view":1,"add":1,"edit":1,"delete":1},"128":{"view":1,"add":1,"edit":1,"delete":1},"129":{"view":1,"add":1,"edit":1,"delete":1},"130":{"view":1,"add":1,"edit":1,"delete":1},"131":{"view":1,"add":1,"edit":1,"delete":1},"132":{"view":1,"add":1,"edit":1,"delete":1},"133":{"view":1,"add":1,"edit":1,"delete":1}}', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 2, 1, '3,4', '32,33,19', '{"32":{"view":1,"edit":1},"33":{"view":1,"edit":1},"19":{"view":1,"edit":1}}', '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 3, 1, '3,4', '32,33,19', '{"32":{"view":1,"edit":1},"33":{"view":1,"edit":1},"19":{"view":1,"edit":1}}', '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- business_market
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `business_market`;
create table `business_market` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `charge` decimal(12, 2) not null default '0', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `business_market` add index `business_market_branch_id_index`(`branch_id`);

INSERT INTO `business_market` (`id`, `branch_id`, `name`, `charge`, `remark`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Corporate', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Leisure', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Government', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'OTA', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 'Walk In', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 1, 'Wedding', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- visit_purpose
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `visit_purpose`;
create table `visit_purpose` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `charge` decimal(12, 2) not null default '0', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `visit_purpose` add index `visit_purpose_branch_id_index`(`branch_id`);

INSERT INTO `visit_purpose` (`id`, `branch_id`, `name`, `charge`, `remark`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Business', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Holiday', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Wedding', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Conference', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 'Medical', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 1, 'Transit', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- pick_drop
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pick_drop`;
create table `pick_drop` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `charge` decimal(12, 2) not null default '0', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pick_drop` add index `pick_drop_branch_id_index`(`branch_id`);

INSERT INTO `pick_drop` (`id`, `branch_id`, `name`, `charge`, `remark`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Airport Pickup', 800, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Airport Drop', 800, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Railway Station Pickup', 350, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Railway Station Drop', 350, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 'Both Ways', 1500, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- billing_instruction
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `billing_instruction`;
create table `billing_instruction` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `charge` decimal(12, 2) not null default '0', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `billing_instruction` add index `billing_instruction_branch_id_index`(`branch_id`);

INSERT INTO `billing_instruction` (`id`, `branch_id`, `name`, `charge`, `remark`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Guest pays all', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Company pays room only', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Company pays all', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Room and tax only', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- expense_head
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `expense_head`;
create table `expense_head` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `charge` decimal(12, 2) not null default '0', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `expense_head` add index `expense_head_branch_id_index`(`branch_id`);

INSERT INTO `expense_head` (`id`, `branch_id`, `name`, `charge`, `remark`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Fuel', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Stationery', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Repairs & Maintenance', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Staff Welfare', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 'Conveyance', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 1, 'Miscellaneous', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- receive_head
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `receive_head`;
create table `receive_head` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `charge` decimal(12, 2) not null default '0', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `receive_head` add index `receive_head_branch_id_index`(`branch_id`);

INSERT INTO `receive_head` (`id`, `branch_id`, `name`, `charge`, `remark`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Scrap Sale', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Deposit Returned', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Miscellaneous Income', 0, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- pay_mode
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pay_mode`;
create table `pay_mode` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `type` enum('cash', 'bank', 'card', 'upi', 'cheque', 'other') not null default 'cash', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pay_mode` add index `pay_mode_branch_id_index`(`branch_id`);

INSERT INTO `pay_mode` (`id`, `branch_id`, `name`, `type`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Cash', 'cash', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'UPI', 'upi', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Credit Card', 'card', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Debit Card', 'card', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 'Bank Transfer', 'bank', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 1, 'Cheque', 'cheque', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- tax_master
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tax_master`;
create table `tax_master` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `percent` decimal(6, 2) not null default '0', `is_default` tinyint not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `tax_master` add index `tax_master_branch_id_index`(`branch_id`);

INSERT INTO `tax_master` (`id`, `branch_id`, `name`, `percent`, `is_default`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'GST 0%', 0, 0, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'GST 5%', 5, 0, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'GST 12%', 12, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'GST 18%', 18, 0, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 'GST 28%', 28, 0, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- room_category
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `room_category`;
create table `room_category` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(20) null, `sort` tinyint not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `room_category` add index `room_category_branch_id_index`(`branch_id`);

INSERT INTO `room_category` (`id`, `branch_id`, `name`, `code`, `sort`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Standard', 'STD', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Deluxe', 'DLX', 2, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Suite', 'STE', 3, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Cottage', 'CTG', 4, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- room_type
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `room_type`;
create table `room_type` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `room_category_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(20) null, `base_rent` decimal(12, 2) not null default '0', `max_adult` tinyint unsigned not null default '2', `max_child` tinyint unsigned not null default '1', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `room_type` add index `room_type_branch_id_index`(`branch_id`);

alter table `room_type` add index `room_type_room_category_id_index`(`room_category_id`);

INSERT INTO `room_type` (`id`, `branch_id`, `room_category_id`, `name`, `code`, `base_rent`, `max_adult`, `max_child`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 1, 'Standard Single', NULL, 1800, 1, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 1, 'Standard Double', NULL, 2400, 2, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 2, 'Deluxe Double', NULL, 3600, 2, 2, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 2, 'Deluxe Triple', NULL, 4500, 3, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 3, 'Executive Suite', NULL, 7800, 2, 2, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 1, 4, 'Family Cottage', NULL, 9500, 4, 2, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- plan_type
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `plan_type`;
create table `plan_type` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(20) null, `charge` decimal(12, 2) not null default '0', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `plan_type` add index `plan_type_branch_id_index`(`branch_id`);

INSERT INTO `plan_type` (`id`, `branch_id`, `name`, `code`, `charge`, `remark`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'European Plan', 'EP', 0, 'Room only', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Continental Plan', 'CP', 400, 'Room + breakfast', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Modified American Plan', 'MAP', 900, 'Room + breakfast + one meal', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'American Plan', 'AP', 1400, 'Room + all meals', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- rooms
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rooms`;
create table `rooms` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `room_category_id` bigint unsigned null, `room_type_id` bigint unsigned null, `room_no` varchar(30) not null, `floor` varchar(30) null, `base_rent` decimal(12, 2) not null default '0', `max_pax` tinyint unsigned not null default '2', `housekeeping_status` enum('clean', 'dirty', 'inspected', 'out_of_order') not null default 'clean', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `rooms` add unique `rooms_branch_id_room_no_unique`(`branch_id`, `room_no`);

alter table `rooms` add index `rooms_branch_id_index`(`branch_id`);

alter table `rooms` add index `rooms_room_category_id_index`(`room_category_id`);

alter table `rooms` add index `rooms_room_type_id_index`(`room_type_id`);

alter table `rooms` modify `housekeeping_status` enum('clean', 'dirty', 'inspected', 'touch_up', 'out_of_order') not null default 'clean';

alter table `rooms` add `housekeeper_id` bigint unsigned null after `housekeeping_status`;

alter table `rooms` add `housekeeping_remark` varchar(255) null after `housekeeper_id`;

INSERT INTO `rooms` (`id`, `branch_id`, `room_category_id`, `room_type_id`, `room_no`, `floor`, `base_rent`, `max_pax`, `housekeeping_status`, `status`, `created_at`, `updated_at`, `housekeeper_id`, `housekeeping_remark`) VALUES
  (1, 1, 1, 1, '101', 'First', 1800, 2, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (2, 1, 1, 1, '102', 'First', 1800, 2, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (3, 1, 1, 1, '103', 'First', 1800, 2, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (4, 1, 1, 2, '104', 'First', 2400, 3, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (5, 1, 1, 2, '105', 'First', 2400, 3, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (6, 1, 1, 2, '106', 'First', 2400, 3, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (7, 1, 2, 3, '201', 'Second', 3600, 4, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (8, 1, 2, 3, '202', 'Second', 3600, 4, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (9, 1, 2, 3, '203', 'Second', 3600, 4, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (10, 1, 2, 3, '204', 'Second', 3600, 4, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (11, 1, 2, 4, '205', 'Second', 4500, 4, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (12, 1, 2, 4, '206', 'Second', 4500, 4, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (13, 1, 3, 5, '301', 'Third', 7800, 4, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (14, 1, 3, 5, '302', 'Third', 7800, 4, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (15, 1, 4, 6, 'C1', 'Ground', 9500, 6, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL),
  (16, 1, 4, 6, 'C2', 'Ground', 9500, 6, 'clean', 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39', NULL, NULL);

-- -----------------------------------------------------------------------------
-- services
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `services`;
create table `services` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `tax_master_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(30) null, `price` decimal(12, 2) not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `services` add index `services_branch_id_index`(`branch_id`);

alter table `services` add index `services_tax_master_id_index`(`tax_master_id`);

INSERT INTO `services` (`id`, `branch_id`, `tax_master_id`, `name`, `code`, `price`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 4, 'Extra Bed', 'EXB', 800, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 4, 'Laundry', 'LND', 250, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 2, 'Airport Transfer', 'ATR', 1200, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 2, 'Breakfast (extra pax)', 'BFT', 350, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 4, 'Late Checkout', 'LCO', 1000, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 1, 4, 'Conference Hall (per hour)', 'CNF', 2500, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- companies
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `companies`;
create table `companies` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `gst_no` varchar(20) null, `contact_person` varchar(255) null, `mobile` varchar(20) null, `email` varchar(255) null, `address` varchar(255) null, `country_id` bigint unsigned null, `state_id` bigint unsigned null, `city_id` bigint unsigned null, `zip_code` varchar(12) null, `credit_limit` decimal(12, 2) not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `companies` add index `companies_branch_id_index`(`branch_id`);

-- -----------------------------------------------------------------------------
-- booked_by
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `booked_by`;
create table `booked_by` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `mobile` varchar(20) null, `email` varchar(255) null, `commission_percent` decimal(6, 2) not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `booked_by` add index `booked_by_branch_id_index`(`branch_id`);

INSERT INTO `booked_by` (`id`, `branch_id`, `name`, `mobile`, `email`, `commission_percent`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Walk In', NULL, NULL, 0, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Front Desk', NULL, NULL, 0, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Website', NULL, NULL, 0, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'MakeMyTrip', NULL, NULL, 15, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 'Booking.com', NULL, NULL, 15, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 1, 'Goibibo', NULL, NULL, 12, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (7, 1, 'Travel Agent', NULL, NULL, 10, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- guests
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `guests`;
create table `guests` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `title` varchar(10) null, `first_name` varchar(255) not null, `last_name` varchar(255) null, `email` varchar(255) null, `email2` varchar(255) null, `mobile` varchar(20) null, `mobile2` varchar(20) null, `address` varchar(255) null, `dob` date null, `gender` enum('male', 'female', 'other') null, `country_id` bigint unsigned null, `state_id` bigint unsigned null, `city_id` bigint unsigned null, `zip_code` varchar(12) null, `id_type` varchar(40) null, `id_number` varchar(60) null, `company_id` bigint unsigned null, `is_blacklisted` tinyint not null default '0', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `guests` add index `guests_branch_id_index`(`branch_id`);

alter table `guests` add index `guests_mobile_index`(`mobile`);

-- -----------------------------------------------------------------------------
-- reservations
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `reservations`;
create table `reservations` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `reservation_no` varchar(40) not null, `reservation_date` date not null, `guest_id` bigint unsigned null, `title` varchar(10) null, `first_name` varchar(255) not null, `last_name` varchar(255) null, `email` varchar(255) null, `email2` varchar(255) null, `mobile` varchar(20) null, `mobile2` varchar(20) null, `address` varchar(255) null, `dob` date null, `gender` enum('male', 'female', 'other') null, `country_id` bigint unsigned null, `state_id` bigint unsigned null, `city_id` bigint unsigned null, `zip_code` varchar(12) null, `reservation_type` enum('confirm', 'tentative', 'waiting', 'group') not null default 'confirm', `pick_drop_id` bigint unsigned null, `visit_purpose_id` bigint unsigned null, `arrival_from` varchar(255) null, `departure_to` varchar(255) null, `transport_mode` varchar(255) null, `confirm_voucher_no` varchar(60) null, `booked_by_id` bigint unsigned null, `business_market_id` bigint unsigned null, `company_id` bigint unsigned null, `company_gst_no` varchar(20) null, `emp_id` bigint unsigned null, `billing_instruction_id` bigint unsigned null, `pay_mode_id` bigint unsigned null, `remark` text null, `special_remark` text null, `room_total` decimal(12, 2) not null default '0', `service_total` decimal(12, 2) not null default '0', `discount_total` decimal(12, 2) not null default '0', `tax_total` decimal(12, 2) not null default '0', `net_amount` decimal(12, 2) not null default '0', `advance_paid` decimal(12, 2) not null default '0', `status` enum('confirmed', 'tentative', 'cancelled', 'checked_in', 'checked_out', 'no_show') not null default 'confirmed', `cancelled_on` date null, `cancel_reason` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `reservations` add unique `reservations_branch_id_reservation_no_unique`(`branch_id`, `reservation_no`);

alter table `reservations` add index `reservations_branch_id_index`(`branch_id`);

alter table `reservations` add index `reservations_reservation_no_index`(`reservation_no`);

alter table `reservations` add index `reservations_guest_id_index`(`guest_id`);

alter table `reservations` add index `reservations_status_index`(`status`);

-- -----------------------------------------------------------------------------
-- reservation_rooms
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `reservation_rooms`;
create table `reservation_rooms` (`id` bigint unsigned not null auto_increment primary key, `reservation_id` bigint unsigned not null, `arrival_date` date not null, `arrival_time` time null, `checkout_date` date not null, `checkout_time` time null, `guest_type` enum('adv_booking', 'walk_in', 'complimentary', 'house_use', 'group') not null default 'adv_booking', `room_category_id` bigint unsigned null, `room_type_id` bigint unsigned null, `plan_type_id` bigint unsigned null, `room_id` bigint unsigned null, `room_no` varchar(30) null, `no_of_days` smallint unsigned not null default '1', `no_of_rooms` smallint unsigned not null default '1', `tax_type` enum('exclusive', 'inclusive') not null default 'exclusive', `room_rent` decimal(12, 2) not null default '0', `discount` decimal(12, 2) not null default '0', `male` tinyint unsigned not null default '1', `female` tinyint unsigned not null default '0', `child` tinyint unsigned not null default '0', `amount` decimal(12, 2) not null default '0', `tax_percent` decimal(6, 2) not null default '0', `tax_amount` decimal(12, 2) not null default '0', `net_amount` decimal(12, 2) not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `reservation_rooms` add index `reservation_rooms_reservation_id_index`(`reservation_id`);

alter table `reservation_rooms` add index `reservation_rooms_room_id_index`(`room_id`);

alter table `reservation_rooms` add `plan_charge` decimal(12, 2) not null default '0' after `discount`;

-- -----------------------------------------------------------------------------
-- reservation_services
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `reservation_services`;
create table `reservation_services` (`id` bigint unsigned not null auto_increment primary key, `reservation_id` bigint unsigned not null, `service_id` bigint unsigned null, `service_name` varchar(255) not null, `tax_type` enum('exclusive', 'inclusive') not null default 'exclusive', `qty` decimal(10, 2) not null default '1', `price` decimal(12, 2) not null default '0', `tax_percent` decimal(6, 2) not null default '0', `tax_amount` decimal(12, 2) not null default '0', `amount` decimal(12, 2) not null default '0', `total_amount` decimal(12, 2) not null default '0', `remark` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `reservation_services` add index `reservation_services_reservation_id_index`(`reservation_id`);

alter table `reservation_services` add `posted_at` timestamp null after `remark`;

-- -----------------------------------------------------------------------------
-- advance_deposits
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `advance_deposits`;
create table `advance_deposits` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `reservation_id` bigint unsigned null, `deposit_date` date not null, `pay_mode_id` bigint unsigned null, `amount` decimal(12, 2) not null default '0', `reference_no` varchar(60) null, `type` enum('deposit', 'refund') not null default 'deposit', `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `advance_deposits` add index `advance_deposits_branch_id_index`(`branch_id`);

alter table `advance_deposits` add index `advance_deposits_reservation_id_index`(`reservation_id`);

alter table `advance_deposits` add `pay_type` varchar(40) null after `pay_mode_id`;

alter table `advance_deposits` add `card_type` varchar(40) null after `pay_type`;

alter table `advance_deposits` add `card_name` varchar(255) null after `card_type`;

alter table `advance_deposits` add `card_last4` varchar(4) null after `card_name`;

alter table `advance_deposits` add `pan_no` varchar(10) null after `card_last4`;

-- -----------------------------------------------------------------------------
-- check_ins
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `check_ins`;
create table `check_ins` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `reservation_id` bigint unsigned null, `reservation_room_id` bigint unsigned null, `guest_id` bigint unsigned null, `room_id` bigint unsigned null, `folio_no` varchar(40) not null, `guest_name` varchar(255) not null, `mobile` varchar(20) null, `checkin_date` date not null, `checkin_time` time null, `expected_checkout_date` date not null, `expected_checkout_time` time null, `actual_checkout_date` date null, `actual_checkout_time` time null, `plan_type_id` bigint unsigned null, `room_rent` decimal(12, 2) not null default '0', `discount` decimal(12, 2) not null default '0', `tax_type` enum('exclusive', 'inclusive') not null default 'exclusive', `male` tinyint unsigned not null default '1', `female` tinyint unsigned not null default '0', `child` tinyint unsigned not null default '0', `status` enum('in_house', 'checked_out', 'cancelled') not null default 'in_house', `is_direct` tinyint not null default '0', `remark` text null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `check_ins` add index `check_ins_branch_id_index`(`branch_id`);

alter table `check_ins` add index `check_ins_reservation_id_index`(`reservation_id`);

alter table `check_ins` add index `check_ins_guest_id_index`(`guest_id`);

alter table `check_ins` add index `check_ins_room_id_index`(`room_id`);

alter table `check_ins` add index `check_ins_folio_no_index`(`folio_no`);

alter table `check_ins` add index `check_ins_status_index`(`status`);

alter table `check_ins` add `plan_charge` decimal(12, 2) not null default '0' after `discount`;

-- -----------------------------------------------------------------------------
-- check_in_pax
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `check_in_pax`;
create table `check_in_pax` (`id` bigint unsigned not null auto_increment primary key, `check_in_id` bigint unsigned not null, `name` varchar(255) not null, `age` tinyint unsigned null, `gender` enum('male', 'female', 'other') null, `relation` varchar(40) null, `id_type` varchar(40) null, `id_number` varchar(60) null, `photo` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `check_in_pax` add index `check_in_pax_check_in_id_index`(`check_in_id`);

-- -----------------------------------------------------------------------------
-- folio_charges
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `folio_charges`;
create table `folio_charges` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `check_in_id` bigint unsigned not null, `charge_date` date not null, `charge_type` enum('room', 'service', 'misc', 'discount', 'tax') not null default 'service', `service_id` bigint unsigned null, `particulars` varchar(255) not null, `qty` decimal(10, 2) not null default '1', `price` decimal(12, 2) not null default '0', `tax_percent` decimal(6, 2) not null default '0', `tax_amount` decimal(12, 2) not null default '0', `amount` decimal(12, 2) not null default '0', `total_amount` decimal(12, 2) not null default '0', `is_settled` tinyint not null default '0', `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `folio_charges` add index `folio_charges_branch_id_index`(`branch_id`);

alter table `folio_charges` add index `folio_charges_check_in_id_index`(`check_in_id`);

alter table `folio_charges` add `reservation_service_id` bigint unsigned null after `service_id`;

-- -----------------------------------------------------------------------------
-- room_transfers
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `room_transfers`;
create table `room_transfers` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `check_in_id` bigint unsigned not null, `from_room_id` bigint unsigned null, `to_room_id` bigint unsigned null, `transfer_date` date not null, `new_room_rent` decimal(12, 2) not null default '0', `reason` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `room_transfers` add index `room_transfers_branch_id_index`(`branch_id`);

alter table `room_transfers` add index `room_transfers_check_in_id_index`(`check_in_id`);

-- -----------------------------------------------------------------------------
-- bills
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `bills`;
create table `bills` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `check_in_id` bigint unsigned null, `guest_id` bigint unsigned null, `bill_no` varchar(40) not null, `group_no` varchar(40) null, `bill_date` date not null, `room_total` decimal(12, 2) not null default '0', `service_total` decimal(12, 2) not null default '0', `discount_total` decimal(12, 2) not null default '0', `tax_total` decimal(12, 2) not null default '0', `net_amount` decimal(12, 2) not null default '0', `paid_amount` decimal(12, 2) not null default '0', `balance_amount` decimal(12, 2) not null default '0', `status` enum('open', 'settled', 'partial', 'cancelled') not null default 'open', `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `bills` add unique `bills_branch_id_bill_no_unique`(`branch_id`, `bill_no`);

alter table `bills` add index `bills_branch_id_index`(`branch_id`);

alter table `bills` add index `bills_check_in_id_index`(`check_in_id`);

alter table `bills` add index `bills_bill_no_index`(`bill_no`);

alter table `bills` add index `bills_group_no_index`(`group_no`);

alter table `bills` add index `bills_status_index`(`status`);

alter table `bills` add `advance_amount` decimal(12, 2) not null default '0' after `discount_total`;

alter table `bills` add `refund_amount` decimal(12, 2) not null default '0' after `paid_amount`;

alter table `bills` add `billing_instruction_id` bigint unsigned null after `status`;

alter table `bills` add `remark` varchar(255) null after `billing_instruction_id`;

-- -----------------------------------------------------------------------------
-- settlements
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `settlements`;
create table `settlements` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `bill_id` bigint unsigned null, `check_in_id` bigint unsigned null, `settle_date` date not null, `pay_mode_id` bigint unsigned null, `amount` decimal(12, 2) not null default '0', `reference_no` varchar(60) null, `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `settlements` add index `settlements_branch_id_index`(`branch_id`);

alter table `settlements` add index `settlements_bill_id_index`(`bill_id`);

alter table `settlements` add index `settlements_check_in_id_index`(`check_in_id`);

alter table `settlements` add `pay_type` varchar(40) null after `pay_mode_id`;

alter table `settlements` add `card_type` varchar(40) null after `pay_type`;

alter table `settlements` add `card_name` varchar(255) null after `card_type`;

alter table `settlements` add `card_last4` varchar(4) null after `card_name`;

alter table `settlements` add `pan_no` varchar(10) null after `card_last4`;

-- -----------------------------------------------------------------------------
-- hk_items
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hk_items`;
create table `hk_items` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `unit` varchar(20) not null default 'pcs', `opening_qty` decimal(12, 2) not null default '0', `current_qty` decimal(12, 2) not null default '0', `reorder_level` decimal(12, 2) not null default '0', `std_rate` decimal(12, 2) not null default '0', `exp_rate` decimal(12, 2) not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `hk_items` add index `hk_items_branch_id_index`(`branch_id`);

INSERT INTO `hk_items` (`id`, `branch_id`, `name`, `unit`, `opening_qty`, `current_qty`, `reorder_level`, `std_rate`, `exp_rate`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Bed Sheet', 'pcs', 0, 0, 0, 12, 20, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Pillow Cover', 'pcs', 0, 0, 0, 5, 9, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Bath Towel', 'pcs', 0, 0, 0, 10, 16, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Hand Towel', 'pcs', 0, 0, 0, 6, 10, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 'Face Towel', 'pcs', 0, 0, 0, 4, 7, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 1, 'Bath Mat', 'pcs', 0, 0, 0, 8, 14, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (7, 1, 'Duvet Cover', 'pcs', 0, 0, 0, 25, 40, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (8, 1, 'Table Cloth', 'pcs', 0, 0, 0, 15, 25, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (9, 1, 'Curtain', 'pcs', 0, 0, 0, 60, 95, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (10, 1, 'Staff Uniform', 'set', 0, 0, 0, 30, 50, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- vendors
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `vendors`;
create table `vendors` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `mobile` varchar(20) null, `email` varchar(255) null, `address` varchar(255) null, `gst_no` varchar(20) null, `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `vendors` add index `vendors_branch_id_index`(`branch_id`);

INSERT INTO `vendors` (`id`, `branch_id`, `name`, `mobile`, `email`, `address`, `gst_no`, `remark`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Shree Laundry Service', '9876500021', NULL, NULL, NULL, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Pali Dry Cleaners', '9876500022', NULL, NULL, NULL, NULL, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- hk_status_logs
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hk_status_logs`;
create table `hk_status_logs` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `room_id` bigint unsigned not null, `log_date` date not null, `status` enum('clean', 'dirty', 'inspected', 'out_of_order') not null default 'dirty', `attended_by` bigint unsigned null, `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `hk_status_logs` add index `hk_status_logs_branch_id_index`(`branch_id`);

alter table `hk_status_logs` add index `hk_status_logs_room_id_index`(`room_id`);

-- -----------------------------------------------------------------------------
-- hk_issues
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hk_issues`;
create table `hk_issues` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `issue_no` varchar(40) not null, `vendor_id` bigint unsigned null, `issue_date` date not null, `total_qty` decimal(12, 2) not null default '0', `total_amount` decimal(12, 2) not null default '0', `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `hk_issues` add unique `hk_issues_branch_id_issue_no_unique`(`branch_id`, `issue_no`);

alter table `hk_issues` add index `hk_issues_branch_id_index`(`branch_id`);

alter table `hk_issues` add index `hk_issues_vendor_id_index`(`vendor_id`);

-- -----------------------------------------------------------------------------
-- hk_issue_items
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hk_issue_items`;
create table `hk_issue_items` (`id` bigint unsigned not null auto_increment primary key, `hk_issue_id` bigint unsigned not null, `hk_item_id` bigint unsigned not null, `prev_qty` decimal(12, 2) not null default '0', `std_qty` decimal(12, 2) not null default '0', `exp_qty` decimal(12, 2) not null default '0', `rewash_qty` decimal(12, 2) not null default '0', `std_rate` decimal(12, 2) not null default '0', `exp_rate` decimal(12, 2) not null default '0', `amount` decimal(12, 2) not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `hk_issue_items` add index `hk_issue_items_hk_issue_id_index`(`hk_issue_id`);

alter table `hk_issue_items` add index `hk_issue_items_hk_item_id_index`(`hk_item_id`);

-- -----------------------------------------------------------------------------
-- hk_receipts
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hk_receipts`;
create table `hk_receipts` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `receipt_no` varchar(40) not null, `vendor_id` bigint unsigned null, `receive_date` date not null, `total_qty` decimal(12, 2) not null default '0', `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `hk_receipts` add unique `hk_receipts_branch_id_receipt_no_unique`(`branch_id`, `receipt_no`);

alter table `hk_receipts` add index `hk_receipts_branch_id_index`(`branch_id`);

alter table `hk_receipts` add index `hk_receipts_vendor_id_index`(`vendor_id`);

-- -----------------------------------------------------------------------------
-- hk_receipt_items
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hk_receipt_items`;
create table `hk_receipt_items` (`id` bigint unsigned not null auto_increment primary key, `hk_receipt_id` bigint unsigned not null, `hk_item_id` bigint unsigned not null, `pending_qty` decimal(12, 2) not null default '0', `received_qty` decimal(12, 2) not null default '0', `damaged_qty` decimal(12, 2) not null default '0', `missing_qty` decimal(12, 2) not null default '0', `remark` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `hk_receipt_items` add index `hk_receipt_items_hk_receipt_id_index`(`hk_receipt_id`);

alter table `hk_receipt_items` add index `hk_receipt_items_hk_item_id_index`(`hk_item_id`);

-- -----------------------------------------------------------------------------
-- room_blocks
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `room_blocks`;
create table `room_blocks` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `room_id` bigint unsigned not null, `from_date` date not null, `to_date` date not null, `reason` varchar(255) null, `status` enum('blocked', 'released') not null default 'blocked', `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `room_blocks` add index `room_blocks_branch_id_index`(`branch_id`);

alter table `room_blocks` add index `room_blocks_room_id_index`(`room_id`);

alter table `room_blocks` add index `room_blocks_status_index`(`status`);

alter table `room_blocks` add `block_type` enum('management', 'maintenance') not null default 'management' after `reason`;

-- -----------------------------------------------------------------------------
-- work_orders
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `work_orders`;
create table `work_orders` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `order_no` varchar(40) not null, `room_id` bigint unsigned null, `category` varchar(40) not null default 'other', `title` varchar(255) not null, `description` text null, `priority` enum('low', 'normal', 'high', 'urgent') not null default 'normal', `assigned_to` bigint unsigned null, `start_date` date null, `start_time` time null, `end_date` date null, `due_date` date null, `completed_on` date null, `room_block_id` bigint unsigned null, `status` enum('open', 'in_progress', 'done', 'cancelled') not null default 'open', `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `work_orders` add unique `work_orders_branch_id_order_no_unique`(`branch_id`, `order_no`);

alter table `work_orders` add index `work_orders_branch_id_index`(`branch_id`);

alter table `work_orders` add index `work_orders_room_id_index`(`room_id`);

alter table `work_orders` add index `work_orders_status_index`(`status`);

-- -----------------------------------------------------------------------------
-- lost_and_founds
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `lost_and_founds`;
create table `lost_and_founds` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `room_id` bigint unsigned null, `item_name` varchar(255) not null, `found_date` date not null, `found_by` varchar(255) null, `guest_name` varchar(255) null, `guest_mobile` varchar(20) null, `status` enum('stored', 'returned', 'disposed') not null default 'stored', `returned_on` date null, `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `lost_and_founds` add index `lost_and_founds_branch_id_index`(`branch_id`);

alter table `lost_and_founds` add index `lost_and_founds_status_index`(`status`);

-- -----------------------------------------------------------------------------
-- petty_cash_payments
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `petty_cash_payments`;
create table `petty_cash_payments` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `voucher_no` varchar(40) not null, `voucher_date` date not null, `expense_head_id` bigint unsigned null, `pay_mode_id` bigint unsigned null, `paid_to` varchar(255) null, `amount` decimal(12, 2) not null default '0', `bill_no` varchar(60) null, `remark` varchar(255) null, `approval_status` enum('pending', 'approved', 'rejected') not null default 'pending', `approved_by` bigint unsigned null, `approved_at` timestamp null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `petty_cash_payments` add index `petty_cash_payments_branch_id_index`(`branch_id`);

alter table `petty_cash_payments` add index `petty_cash_payments_voucher_no_index`(`voucher_no`);

alter table `petty_cash_payments` add index `petty_cash_payments_approval_status_index`(`approval_status`);

-- -----------------------------------------------------------------------------
-- petty_cash_receipts
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `petty_cash_receipts`;
create table `petty_cash_receipts` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `voucher_no` varchar(40) not null, `voucher_date` date not null, `receive_head_id` bigint unsigned null, `pay_mode_id` bigint unsigned null, `received_from` varchar(255) null, `amount` decimal(12, 2) not null default '0', `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `petty_cash_receipts` add index `petty_cash_receipts_branch_id_index`(`branch_id`);

alter table `petty_cash_receipts` add index `petty_cash_receipts_voucher_no_index`(`voucher_no`);

-- -----------------------------------------------------------------------------
-- account_groups
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `account_groups`;
create table `account_groups` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `parent_id` bigint unsigned null, `name` varchar(255) not null, `nature` enum('asset', 'liability', 'income', 'expense') not null default 'asset', `is_system` tinyint not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `account_groups` add index `account_groups_branch_id_index`(`branch_id`);

-- -----------------------------------------------------------------------------
-- ledgers
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `ledgers`;
create table `ledgers` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `account_group_id` bigint unsigned null, `name` varchar(255) not null, `opening_balance` decimal(14, 2) not null default '0', `balance_type` enum('dr', 'cr') not null default 'dr', `gst_no` varchar(20) null, `mobile` varchar(20) null, `email` varchar(255) null, `address` varchar(255) null, `is_system` tinyint not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `ledgers` add index `ledgers_branch_id_index`(`branch_id`);

alter table `ledgers` add index `ledgers_account_group_id_index`(`account_group_id`);

-- -----------------------------------------------------------------------------
-- vouchers
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `vouchers`;
create table `vouchers` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `voucher_type` enum('payment', 'receipt', 'contra', 'journal', 'sales', 'purchase') not null default 'journal', `voucher_no` varchar(40) not null, `voucher_date` date not null, `reference_no` varchar(60) null, `amount` decimal(14, 2) not null default '0', `narration` text null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `vouchers` add unique `vouchers_branch_id_voucher_type_voucher_no_unique`(`branch_id`, `voucher_type`, `voucher_no`);

alter table `vouchers` add index `vouchers_branch_id_index`(`branch_id`);

alter table `vouchers` add index `vouchers_voucher_type_index`(`voucher_type`);

alter table `vouchers` add index `vouchers_voucher_no_index`(`voucher_no`);

alter table `vouchers` add index `vouchers_voucher_date_index`(`voucher_date`);

-- -----------------------------------------------------------------------------
-- voucher_entries
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `voucher_entries`;
create table `voucher_entries` (`id` bigint unsigned not null auto_increment primary key, `voucher_id` bigint unsigned not null, `ledger_id` bigint unsigned not null, `debit` decimal(14, 2) not null default '0', `credit` decimal(14, 2) not null default '0', `narration` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `voucher_entries` add index `voucher_entries_voucher_id_index`(`voucher_id`);

alter table `voucher_entries` add index `voucher_entries_ledger_id_index`(`ledger_id`);

-- -----------------------------------------------------------------------------
-- e_invoices
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `e_invoices`;
create table `e_invoices` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `bill_id` bigint unsigned null, `bill_no` varchar(40) null, `irn` varchar(100) null, `ack_no` varchar(60) null, `ack_date` date null, `qr_code` text null, `status` enum('pending', 'uploaded', 'failed', 'cancelled') not null default 'pending', `response` text null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `e_invoices` add index `e_invoices_branch_id_index`(`branch_id`);

alter table `e_invoices` add index `e_invoices_bill_id_index`(`bill_id`);

alter table `e_invoices` add index `e_invoices_status_index`(`status`);

-- -----------------------------------------------------------------------------
-- pax_checkouts
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pax_checkouts`;
create table `pax_checkouts` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `check_in_id` bigint unsigned not null, `checkout_date` date not null, `pax` tinyint unsigned not null default '1', `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pax_checkouts` add index `pax_checkouts_branch_id_index`(`branch_id`);

alter table `pax_checkouts` add index `pax_checkouts_check_in_id_index`(`check_in_id`);

-- -----------------------------------------------------------------------------
-- cache
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `cache`;
create table `cache` (`key` varchar(255) not null, `value` mediumtext not null, `expiration` int not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

-- -----------------------------------------------------------------------------
-- cache_locks
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `cache_locks`;
create table `cache_locks` (`key` varchar(255) not null, `owner` varchar(255) not null, `expiration` int not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

-- -----------------------------------------------------------------------------
-- jobs
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `jobs`;
create table `jobs` (`id` bigint unsigned not null auto_increment primary key, `queue` varchar(255) not null, `payload` longtext not null, `attempts` tinyint unsigned not null, `reserved_at` int unsigned null, `available_at` int unsigned not null, `created_at` int unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `jobs` add index `jobs_queue_index`(`queue`);

-- -----------------------------------------------------------------------------
-- job_batches
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `job_batches`;
create table `job_batches` (`id` varchar(255) not null, `name` varchar(255) not null, `total_jobs` int not null, `pending_jobs` int not null, `failed_jobs` int not null, `failed_job_ids` longtext not null, `options` mediumtext null, `cancelled_at` int null, `created_at` int not null, `finished_at` int null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

-- -----------------------------------------------------------------------------
-- failed_jobs
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `failed_jobs`;
create table `failed_jobs` (`id` bigint unsigned not null auto_increment primary key, `uuid` varchar(255) not null, `connection` text not null, `queue` text not null, `payload` longtext not null, `exception` longtext not null, `failed_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `failed_jobs` add unique `failed_jobs_uuid_unique`(`uuid`);

-- -----------------------------------------------------------------------------
-- outlets
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `outlets`;
create table `outlets` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(20) null, `kind` enum('restaurant', 'room_service', 'bar', 'banquet', 'other') not null default 'restaurant', `address1` varchar(255) null, `address2` varchar(255) null, `address3` varchar(255) null, `phone1` varchar(30) null, `phone2` varchar(30) null, `website` varchar(255) null, `email` varchar(255) null, `gst_no` varchar(20) null, `cin_no` varchar(30) null, `pan_no` varchar(20) null, `sac_code` varchar(20) null, `start_time` time null, `end_time` time null, `bill_series` varchar(20) null, `bill_start_no` int unsigned not null default '1', `is_retail` tinyint not null default '0', `tax_inclusive` tinyint not null default '0', `discount_after_tax` tinyint not null default '0', `allow_open_item` tinyint not null default '0', `pos_dine_in` tinyint not null default '1', `pos_room_service` tinyint not null default '0', `pos_delivery` tinyint not null default '0', `pos_take_away` tinyint not null default '0', `split_liquor_bill` tinyint not null default '0', `diff_liquor_series` tinyint not null default '0', `liquor_bill_series` varchar(20) null, `auto_settle` tinyint not null default '0', `show_order_notification` tinyint not null default '0', `show_last_orders` tinyint not null default '0', `page_width` smallint unsigned not null default '80', `print_margin` smallint unsigned not null default '6', `print_header` varchar(255) null, `tax_invoice_name` varchar(255) null, `header_font` varchar(40) null, `header_font_size` smallint unsigned not null default '0', `header_font_bold` tinyint not null default '1', `print_footer` varchar(255) null, `guest_signature_print` tinyint not null default '1', `logo_path` varchar(255) null, `status` tinyint not null default '1', `deleted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `outlets` add index `outlets_branch_id_index`(`branch_id`);

INSERT INTO `outlets` (`id`, `branch_id`, `name`, `code`, `kind`, `status`, `bill_series`, `bill_start_no`, `page_width`, `print_margin`, `header_font`, `header_font_bold`, `guest_signature_print`, `pos_dine_in`, `pos_room_service`, `pos_take_away`, `split_liquor_bill`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Restaurant', 'RST', 'restaurant', 1, 'RST', 1, 80, 6, 'Arial', 1, 1, 1, 0, 1, 0, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Room Service', 'RMS', 'room_service', 1, 'RMS', 1, 80, 6, 'Arial', 1, 1, 0, 1, 0, 0, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Bar', 'BAR', 'bar', 1, 'BAR', 1, 80, 6, 'Arial', 1, 1, 1, 0, 0, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Banquet', 'BQT', 'banquet', 1, 'BQT', 1, 80, 6, 'Arial', 1, 1, 1, 0, 0, 0, '2026-09-09 22:07:39', '2026-09-09 22:07:39');
-- -----------------------------------------------------------------------------
-- outlet_user
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `outlet_user`;
create table `outlet_user` (`id` bigint unsigned not null auto_increment primary key, `outlet_id` bigint unsigned not null, `user_id` bigint unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `outlet_user` add unique `outlet_user_outlet_id_user_id_unique`(`outlet_id`, `user_id`);

alter table `outlet_user` add index `outlet_user_outlet_id_index`(`outlet_id`);

alter table `outlet_user` add index `outlet_user_user_id_index`(`user_id`);

-- -----------------------------------------------------------------------------
-- pos_table_groups
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_table_groups`;
create table `pos_table_groups` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `outlet_id` bigint unsigned not null, `name` varchar(255) not null, `kind` enum('apartment', 'room', 'table', 'villa') not null default 'table', `sort` smallint unsigned not null default '0', `status` tinyint not null default '1', `deleted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_table_groups` add index `pos_table_groups_branch_id_index`(`branch_id`);

alter table `pos_table_groups` add index `pos_table_groups_outlet_id_index`(`outlet_id`);
INSERT INTO `pos_table_groups` (`id`, `branch_id`, `outlet_id`, `name`, `kind`, `sort`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES
  (1, 1, 1, 'Non AC', 'table', 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- pos_tables
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_tables`;
create table `pos_tables` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `outlet_id` bigint unsigned not null, `pos_table_group_id` bigint unsigned not null, `name` varchar(60) not null, `capacity` smallint unsigned not null default '0', `sort` smallint unsigned not null default '0', `status` tinyint not null default '1', `deleted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_tables` add index `pos_tables_branch_id_index`(`branch_id`);

alter table `pos_tables` add index `pos_tables_outlet_id_index`(`outlet_id`);

alter table `pos_tables` add index `pos_tables_pos_table_group_id_index`(`pos_table_group_id`);
INSERT INTO `pos_tables` (`id`, `branch_id`, `outlet_id`, `pos_table_group_id`, `name`, `capacity`, `sort`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES
  (1, 1, 1, 1, '1', 4, 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 1, 1, '2', 4, 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 1, 1, '3', 6, 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 1, 1, '4', 2, 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- pos_menu_categories
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_menu_categories`;
create table `pos_menu_categories` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `outlet_id` bigint unsigned null, `name` varchar(255) not null, `type` enum('category', 'sub_category') not null default 'category', `parent_id` bigint unsigned null, `sort` smallint unsigned not null default '0', `status` tinyint not null default '1', `deleted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_menu_categories` add index `pos_menu_categories_branch_id_index`(`branch_id`);

alter table `pos_menu_categories` add index `pos_menu_categories_outlet_id_index`(`outlet_id`);

alter table `pos_menu_categories` add index `pos_menu_categories_parent_id_index`(`parent_id`);

INSERT INTO `pos_menu_categories` (`id`, `branch_id`, `outlet_id`, `name`, `type`, `parent_id`, `sort`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES
  (1, 1, NULL, 'Starters', 'category', NULL, 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, NULL, 'Main Course', 'category', NULL, 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, NULL, 'Indian Breads', 'category', NULL, 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, NULL, 'Beverages', 'category', NULL, 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, NULL, 'Desserts', 'category', NULL, 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39');
-- -----------------------------------------------------------------------------
-- pos_menu_items
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_menu_items`;
create table `pos_menu_items` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `pos_menu_category_id` bigint unsigned null, `pos_department_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(30) null, `price` decimal(12, 2) not null default '0', `tax_master_id` bigint unsigned null, `is_veg` tinyint not null default '1', `sort` smallint unsigned not null default '0', `status` tinyint not null default '1', `deleted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_menu_items` add index `pos_menu_items_branch_id_index`(`branch_id`);

alter table `pos_menu_items` add index `pos_menu_items_pos_menu_category_id_index`(`pos_menu_category_id`);

alter table `pos_menu_items` add index `pos_menu_items_pos_department_id_index`(`pos_department_id`);
-- -----------------------------------------------------------------------------
-- pos_rate_plans
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_rate_plans`;
create table `pos_rate_plans` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `status` tinyint not null default '1', `deleted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_rate_plans` add index `pos_rate_plans_branch_id_index`(`branch_id`);

-- -----------------------------------------------------------------------------
-- pos_rate_plan_outlet
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_rate_plan_outlet`;
create table `pos_rate_plan_outlet` (`id` bigint unsigned not null auto_increment primary key, `pos_rate_plan_id` bigint unsigned not null, `outlet_id` bigint unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_rate_plan_outlet` add unique `pos_rate_plan_outlet_pos_rate_plan_id_outlet_id_unique`(`pos_rate_plan_id`, `outlet_id`);

alter table `pos_rate_plan_outlet` add index `pos_rate_plan_outlet_pos_rate_plan_id_index`(`pos_rate_plan_id`);

alter table `pos_rate_plan_outlet` add index `pos_rate_plan_outlet_outlet_id_index`(`outlet_id`);

-- -----------------------------------------------------------------------------
-- pos_menu_item_prices
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_menu_item_prices`;
create table `pos_menu_item_prices` (`id` bigint unsigned not null auto_increment primary key, `pos_menu_item_id` bigint unsigned not null, `pos_rate_plan_id` bigint unsigned not null, `price` decimal(12, 2) not null default '0') default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_menu_item_prices` add unique `pos_menu_item_prices_pos_menu_item_id_pos_rate_plan_id_unique`(`pos_menu_item_id`, `pos_rate_plan_id`);

alter table `pos_menu_item_prices` add index `pos_menu_item_prices_pos_menu_item_id_index`(`pos_menu_item_id`);

alter table `pos_menu_item_prices` add index `pos_menu_item_prices_pos_rate_plan_id_index`(`pos_rate_plan_id`);

-- -----------------------------------------------------------------------------
-- pos_departments
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_departments`;
create table `pos_departments` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `status` tinyint not null default '1', `deleted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_departments` add index `pos_departments_branch_id_index`(`branch_id`);
INSERT INTO `pos_departments` (`id`, `branch_id`, `name`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Kitchen', 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'Bar', 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Room Service', 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Bakery', 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- pos_stewards
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_stewards`;
create table `pos_stewards` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `phone` varchar(30) null, `status` tinyint not null default '1', `deleted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_stewards` add index `pos_stewards_branch_id_index`(`branch_id`);

-- -----------------------------------------------------------------------------
-- pos_nc_types
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_nc_types`;
create table `pos_nc_types` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `requires_department` tinyint not null default '0', `status` tinyint not null default '1', `deleted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_nc_types` add index `pos_nc_types_branch_id_index`(`branch_id`);
INSERT INTO `pos_nc_types` (`id`, `branch_id`, `name`, `requires_department`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES
  (1, 1, 'Complimentary', 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 'In-House', 1, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 'Promotional', 0, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 'Staff Meal', 1, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- pos_reservation_slots
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_reservation_slots`;
create table `pos_reservation_slots` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `outlet_id` bigint unsigned not null, `slot_time` time not null, `max_booking` smallint unsigned not null default '0', `status` tinyint not null default '1', `deleted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_reservation_slots` add unique `pos_reservation_slots_branch_id_outlet_id_slot_time_unique`(`branch_id`, `outlet_id`, `slot_time`);

alter table `pos_reservation_slots` add index `pos_reservation_slots_branch_id_index`(`branch_id`);

alter table `pos_reservation_slots` add index `pos_reservation_slots_outlet_id_index`(`outlet_id`);
INSERT INTO `pos_reservation_slots` (`id`, `branch_id`, `outlet_id`, `slot_time`, `max_booking`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES
  (1, 1, 1, '12:30:00', 5, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 1, '13:30:00', 5, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 1, '19:30:00', 8, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 1, '20:30:00', 8, 1, NULL, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- pos_orders
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_orders`;
create table `pos_orders` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `outlet_id` bigint unsigned null, `order_no` varchar(40) not null, `order_type` enum('dine_in', 'room_service', 'delivery', 'take_away') not null default 'dine_in', `table_no` varchar(30) null, `room_id` bigint unsigned null, `check_in_id` bigint unsigned null, `guest_name` varchar(255) null, `pax` tinyint unsigned not null default '1', `pos_table_id` bigint unsigned null, `pos_steward_id` bigint unsigned null, `pos_rate_plan_id` bigint unsigned null, `nc_type_id` bigint unsigned null, `nc_department_id` bigint unsigned null, `opened_at` datetime not null, `closed_at` datetime null, `is_complimentary` tinyint not null default '0', `kot_count` smallint unsigned not null default '0', `sub_total` decimal(12, 2) not null default '0', `discount_percent` decimal(5, 2) not null default '0', `discount_total` decimal(12, 2) not null default '0', `service_charge` decimal(12, 2) not null default '0', `tax_total` decimal(12, 2) not null default '0', `round_off` decimal(8, 2) not null default '0', `net_amount` decimal(12, 2) not null default '0', `status` enum('open', 'billed', 'settled', 'cancelled') not null default 'open', `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_orders` add unique `pos_orders_branch_id_order_no_unique`(`branch_id`, `order_no`);

alter table `pos_orders` add index `pos_orders_branch_id_index`(`branch_id`);

alter table `pos_orders` add index `pos_orders_outlet_id_index`(`outlet_id`);

alter table `pos_orders` add index `pos_orders_order_type_index`(`order_type`);

alter table `pos_orders` add index `pos_orders_status_index`(`status`);

alter table `pos_orders` add index `pos_orders_pos_table_id_index`(`pos_table_id`);

alter table `pos_orders` add index `pos_orders_pos_steward_id_index`(`pos_steward_id`);

alter table `pos_orders` add index `pos_orders_pos_rate_plan_id_index`(`pos_rate_plan_id`);
-- -----------------------------------------------------------------------------
-- pos_order_items
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_order_items`;
create table `pos_order_items` (`id` bigint unsigned not null auto_increment primary key, `pos_order_id` bigint unsigned not null, `pos_menu_item_id` bigint unsigned null, `pos_department_id` bigint unsigned null, `kot_no` smallint unsigned not null default '0', `fired_at` datetime null, `kitchen_status` enum('pending', 'preparing', 'ready', 'served') not null default 'pending', `is_nc` tinyint not null default '0', `sort` smallint unsigned not null default '0', `item_name` varchar(255) not null, `qty` decimal(10, 2) not null default '1', `price` decimal(12, 2) not null default '0', `discount` decimal(12, 2) not null default '0', `tax_percent` decimal(6, 2) not null default '0', `tax_amount` decimal(12, 2) not null default '0', `amount` decimal(12, 2) not null default '0', `total_amount` decimal(12, 2) not null default '0', `remark` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_order_items` add index `pos_order_items_pos_order_id_index`(`pos_order_id`);

alter table `pos_order_items` add index `pos_order_items_pos_department_id_index`(`pos_department_id`);

alter table `pos_order_items` add index `pos_order_items_kitchen_status_index`(`kitchen_status`);

alter table `pos_order_items` add index `pos_order_items_pos_menu_item_id_index`(`pos_menu_item_id`);
-- -----------------------------------------------------------------------------
-- pos_invoices
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_invoices`;
create table `pos_invoices` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `outlet_id` bigint unsigned null, `pos_order_id` bigint unsigned null, `invoice_no` varchar(40) not null, `invoice_at` datetime not null, `guest_name` varchar(255) null, `check_in_id` bigint unsigned null, `folio_charge_id` bigint unsigned null, `folio_amount` decimal(12, 2) not null default '0', `settled_at` datetime null, `sub_total` decimal(12, 2) not null default '0', `discount_total` decimal(12, 2) not null default '0', `tax_total` decimal(12, 2) not null default '0', `net_amount` decimal(12, 2) not null default '0', `paid_amount` decimal(12, 2) not null default '0', `print_count` smallint unsigned not null default '0', `status` enum('open', 'settled', 'cancelled') not null default 'open', `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_invoices` add unique `pos_invoices_branch_id_invoice_no_unique`(`branch_id`, `invoice_no`);

alter table `pos_invoices` add index `pos_invoices_branch_id_index`(`branch_id`);

alter table `pos_invoices` add index `pos_invoices_outlet_id_index`(`outlet_id`);

alter table `pos_invoices` add index `pos_invoices_pos_order_id_index`(`pos_order_id`);

alter table `pos_invoices` add index `pos_invoices_status_index`(`status`);

alter table `pos_invoices` add index `pos_invoices_check_in_id_index`(`check_in_id`);
-- -----------------------------------------------------------------------------
-- pos_payments
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_payments`;
create table `pos_payments` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `pos_invoice_id` bigint unsigned not null, `pay_mode_id` bigint unsigned null, `amount` decimal(12, 2) not null default '0', `reference_no` varchar(60) null, `paid_at` datetime not null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_payments` add index `pos_payments_branch_id_index`(`branch_id`);

alter table `pos_payments` add index `pos_payments_pos_invoice_id_index`(`pos_invoice_id`);

alter table `pos_payments` add index `pos_payments_pay_mode_id_index`(`pay_mode_id`);
-- -----------------------------------------------------------------------------
-- pos_audit_logs
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pos_audit_logs`;
create table `pos_audit_logs` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `action` enum('invoice_deleted', 'item_removed', 'item_modified', 'invoice_reprinted') not null, `pos_order_id` bigint unsigned null, `pos_invoice_id` bigint unsigned null, `particulars` varchar(255) null, `amount` decimal(12, 2) not null default '0', `happened_at` datetime not null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pos_audit_logs` add index `pos_audit_logs_branch_id_index`(`branch_id`);

alter table `pos_audit_logs` add index `pos_audit_logs_action_index`(`action`);

alter table `pos_audit_logs` add index `pos_audit_logs_happened_at_index`(`happened_at`);





-- >>> BEGIN generated by scripts/patch-dump (migrations 19-29)

-- -----------------------------------------------------------------------------
-- 0001_01_01_000019_tax_is_a_choice
-- -----------------------------------------------------------------------------
-- Nothing in this system adds tax to a figure on its own any more. Every table
-- that holds money holds the choice that was made beside it, and the default is
-- `none`.
alter table `check_ins` add `tax_percent` decimal(6, 2) not null default '0' after `tax_type`;
alter table `check_ins` add `tax_choice` varchar(20) not null default 'none' after `tax_percent`;
alter table `reservation_rooms` add `tax_choice` varchar(20) not null default 'none' after `tax_percent`;
alter table `reservation_services` add `tax_choice` varchar(20) not null default 'none' after `tax_percent`;
alter table `folio_charges` add `tax_choice` varchar(20) not null default 'none' after `tax_percent`;
alter table `pos_orders` add `tax_choice` varchar(20) not null default 'none' after `tax_total`;

-- -----------------------------------------------------------------------------
-- app_notifications
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `app_notifications`;
create table `app_notifications` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `user_id` bigint unsigned null, `event` varchar(60) not null, `title` varchar(255) not null, `body` text null, `url` varchar(255) null, `icon` varchar(40) null, `level` enum('info', 'success', 'warning', 'danger') not null default 'info', `data` text null, `read_at` timestamp null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `app_notifications` add index `app_notifications_branch_id_index`(`branch_id`);

alter table `app_notifications` add index `app_notifications_user_id_index`(`user_id`);

alter table `app_notifications` add index `app_notifications_event_index`(`event`);

alter table `app_notifications` add index `app_notifications_bell_index`(`branch_id`, `user_id`, `read_at`);

alter table `app_notifications` add index `app_notifications_branch_id_created_at_index`(`branch_id`, `created_at`);

-- -----------------------------------------------------------------------------
-- notification_settings
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `notification_settings`;
create table `notification_settings` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `event` varchar(60) not null, `channels` varchar(60) not null default 'app', `mail_to` text null, `whatsapp_to` text null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `notification_settings` add index `notification_settings_branch_id_index`(`branch_id`);

alter table `notification_settings` add unique `notification_settings_branch_id_event_unique`(`branch_id`, `event`);

-- -----------------------------------------------------------------------------
-- notification_deliveries
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `notification_deliveries`;
-- `app_notification_id` is nullable (migration 23): a WhatsApp sent straight
-- to a guest has no parent row in the staff bell, and should not have one.
create table `notification_deliveries` (`id` bigint unsigned not null auto_increment primary key, `app_notification_id` bigint unsigned null, `branch_id` bigint unsigned not null, `event` varchar(60) null, `audience` varchar(10) not null default 'staff', `channel` varchar(20) not null, `target` varchar(255) not null, `status` enum('pending', 'sent', 'failed') not null default 'pending', `error` text null, `sent_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `notification_deliveries` add index `notification_deliveries_app_notification_id_index`(`app_notification_id`);

alter table `notification_deliveries` add index `notification_deliveries_branch_id_index`(`branch_id`);

alter table `notification_deliveries` add index `notification_deliveries_status_index`(`status`);

-- Where a notification reaches a person, and whether they want it.
alter table `users` add `email` varchar(255) null after `mobile`;
alter table `users` add `notify_web` tinyint not null default '1';
alter table `users` add `notify_mail` tinyint not null default '0';
alter table `users` add `whatsapp_no` varchar(20) null;

-- -----------------------------------------------------------------------------
-- pools
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pools`;
create table `pools` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(20) null, `capacity` smallint unsigned not null default '0', `open_time` time null, `close_time` time null, `adult_rate` decimal(10, 2) not null default '0', `child_rate` decimal(10, 2) not null default '0', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pools` add index `pools_branch_id_index`(`branch_id`);

-- -----------------------------------------------------------------------------
-- pool_bookings
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pool_bookings`;
create table `pool_bookings` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `booking_no` varchar(40) not null, `pool_id` bigint unsigned not null, `check_in_id` bigint unsigned null, `guest_name` varchar(255) null, `mobile` varchar(20) null, `room_no` varchar(20) null, `booking_date` date not null, `from_time` time not null, `to_time` time not null, `adults` smallint unsigned not null default '1', `children` smallint unsigned not null default '0', `adult_rate` decimal(10, 2) not null default '0', `child_rate` decimal(10, 2) not null default '0', `discount` decimal(12, 2) not null default '0', `amount` decimal(12, 2) not null default '0', `tax_choice` varchar(20) not null default 'none', `tax_percent` decimal(6, 2) not null default '0', `tax_amount` decimal(12, 2) not null default '0', `total_amount` decimal(12, 2) not null default '0', `status` enum('booked', 'in_use', 'completed', 'cancelled') not null default 'booked', `post_to_room` tinyint not null default '0', `folio_charge_id` bigint unsigned null, `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pool_bookings` add index `pool_bookings_branch_id_index`(`branch_id`);

alter table `pool_bookings` add index `pool_bookings_booking_no_index`(`booking_no`);

alter table `pool_bookings` add index `pool_bookings_pool_id_index`(`pool_id`);

alter table `pool_bookings` add index `pool_bookings_check_in_id_index`(`check_in_id`);

alter table `pool_bookings` add index `pool_bookings_booking_date_index`(`booking_date`);

alter table `pool_bookings` add index `pool_bookings_status_index`(`status`);

alter table `pool_bookings` add index `pool_bookings_branch_id_pool_id_booking_date_index`(`branch_id`, `pool_id`, `booking_date`);

-- -----------------------------------------------------------------------------
-- halls
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `halls`;
create table `halls` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(20) null, `floor` varchar(20) null, `capacity` smallint unsigned not null default '0', `hour_rate` decimal(10, 2) not null default '0', `day_rate` decimal(10, 2) not null default '0', `amenities` text null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `halls` add index `halls_branch_id_index`(`branch_id`);

-- -----------------------------------------------------------------------------
-- hall_bookings
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hall_bookings`;
create table `hall_bookings` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `booking_no` varchar(40) not null, `hall_id` bigint unsigned not null, `check_in_id` bigint unsigned null, `company_id` bigint unsigned null, `guest_name` varchar(255) not null, `mobile` varchar(20) null, `room_no` varchar(20) null, `email` varchar(255) null, `event_type` varchar(60) null, `from_date` date not null, `from_time` time not null, `to_date` date not null, `to_time` time not null, `pax` smallint unsigned not null default '0', `rate_type` enum('hour', 'day', 'event') not null default 'event', `rate` decimal(12, 2) not null default '0', `qty` decimal(8, 2) not null default '1', `discount` decimal(12, 2) not null default '0', `amount` decimal(12, 2) not null default '0', `tax_choice` varchar(20) not null default 'none', `tax_percent` decimal(6, 2) not null default '0', `tax_amount` decimal(12, 2) not null default '0', `total_amount` decimal(12, 2) not null default '0', `advance` decimal(12, 2) not null default '0', `status` enum('tentative', 'confirmed', 'in_use', 'completed', 'cancelled') not null default 'confirmed', `post_to_room` tinyint not null default '0', `folio_charge_id` bigint unsigned null, `remark` text null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `hall_bookings` add index `hall_bookings_branch_id_index`(`branch_id`);

alter table `hall_bookings` add index `hall_bookings_booking_no_index`(`booking_no`);

alter table `hall_bookings` add index `hall_bookings_hall_id_index`(`hall_id`);

alter table `hall_bookings` add index `hall_bookings_check_in_id_index`(`check_in_id`);

alter table `hall_bookings` add index `hall_bookings_from_date_index`(`from_date`);

alter table `hall_bookings` add index `hall_bookings_to_date_index`(`to_date`);

alter table `hall_bookings` add index `hall_bookings_status_index`(`status`);

alter table `hall_bookings` add index `hall_bookings_branch_id_hall_id_from_date_index`(`branch_id`, `hall_id`, `from_date`);

-- -----------------------------------------------------------------------------
-- hall_booking_items
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hall_booking_items`;
create table `hall_booking_items` (`id` bigint unsigned not null auto_increment primary key, `hall_booking_id` bigint unsigned not null, `particulars` varchar(255) not null, `qty` decimal(8, 2) not null default '1', `price` decimal(12, 2) not null default '0', `tax_choice` varchar(20) not null default 'none', `tax_percent` decimal(6, 2) not null default '0', `tax_amount` decimal(12, 2) not null default '0', `amount` decimal(12, 2) not null default '0', `total_amount` decimal(12, 2) not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `hall_booking_items` add index `hall_booking_items_hall_booking_id_index`(`hall_booking_id`);

-- -----------------------------------------------------------------------------
-- parking_slots
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `parking_slots`;
create table `parking_slots` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `code` varchar(20) not null, `zone` varchar(40) null, `vehicle_type` enum('car', 'bike', 'bus', 'other') not null default 'car', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `parking_slots` add index `parking_slots_branch_id_index`(`branch_id`);

-- -----------------------------------------------------------------------------
-- parking_records
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `parking_records`;
create table `parking_records` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `ticket_no` varchar(40) not null, `parking_slot_id` bigint unsigned null, `check_in_id` bigint unsigned null, `guest_name` varchar(255) null, `mobile` varchar(20) null, `room_no` varchar(20) null, `vehicle_no` varchar(20) not null, `vehicle_type` enum('car', 'bike', 'bus', 'other') not null default 'car', `make_model` varchar(60) null, `colour` varchar(30) null, `driver_name` varchar(80) null, `driver_mobile` varchar(20) null, `in_at` datetime not null, `out_at` datetime null, `is_chargeable` tinyint not null default '0', `rate` decimal(10, 2) not null default '0', `hours` decimal(8, 2) not null default '0', `amount` decimal(12, 2) not null default '0', `tax_choice` varchar(20) not null default 'none', `tax_percent` decimal(6, 2) not null default '0', `tax_amount` decimal(12, 2) not null default '0', `total_amount` decimal(12, 2) not null default '0', `status` enum('parked', 'out', 'cancelled') not null default 'parked', `post_to_room` tinyint not null default '0', `folio_charge_id` bigint unsigned null, `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `parking_records` add index `parking_records_branch_id_index`(`branch_id`);

alter table `parking_records` add index `parking_records_ticket_no_index`(`ticket_no`);

alter table `parking_records` add index `parking_records_parking_slot_id_index`(`parking_slot_id`);

alter table `parking_records` add index `parking_records_check_in_id_index`(`check_in_id`);

alter table `parking_records` add index `parking_records_in_at_index`(`in_at`);

alter table `parking_records` add index `parking_records_status_index`(`status`);

alter table `parking_records` add index `parking_records_branch_id_status_in_at_index`(`branch_id`, `status`, `in_at`);

-- -----------------------------------------------------------------------------
-- vehicles
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `vehicles`;
create table `vehicles` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `vehicle_no` varchar(20) null, `type` enum('car', 'suv', 'tempo', 'bus', 'other') not null default 'car', `seats` smallint unsigned not null default '4', `driver_name` varchar(80) null, `driver_mobile` varchar(20) null, `km_rate` decimal(10, 2) not null default '0', `trip_rate` decimal(10, 2) not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `vehicles` add index `vehicles_branch_id_index`(`branch_id`);

-- -----------------------------------------------------------------------------
-- guest_trips
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `guest_trips`;
create table `guest_trips` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `trip_no` varchar(40) not null, `trip_type` enum('pickup', 'drop') not null default 'pickup', `reservation_id` bigint unsigned null, `check_in_id` bigint unsigned null, `guest_name` varchar(255) not null, `mobile` varchar(20) null, `room_no` varchar(20) null, `pax` smallint unsigned not null default '1', `luggage` smallint unsigned not null default '0', `vehicle_id` bigint unsigned null, `driver_name` varchar(80) null, `driver_mobile` varchar(20) null, `from_place` varchar(255) not null, `to_place` varchar(255) not null, `flight_no` varchar(30) null, `trip_date` date not null, `trip_time` time not null, `km` decimal(8, 2) not null default '0', `is_chargeable` tinyint not null default '0', `rate_type` enum('km', 'trip') not null default 'trip', `rate` decimal(10, 2) not null default '0', `amount` decimal(12, 2) not null default '0', `tax_choice` varchar(20) not null default 'none', `tax_percent` decimal(6, 2) not null default '0', `tax_amount` decimal(12, 2) not null default '0', `total_amount` decimal(12, 2) not null default '0', `status` enum('scheduled', 'started', 'completed', 'cancelled') not null default 'scheduled', `post_to_room` tinyint not null default '0', `folio_charge_id` bigint unsigned null, `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `guest_trips` add index `guest_trips_branch_id_index`(`branch_id`);

alter table `guest_trips` add index `guest_trips_trip_no_index`(`trip_no`);

alter table `guest_trips` add index `guest_trips_trip_type_index`(`trip_type`);

alter table `guest_trips` add index `guest_trips_reservation_id_index`(`reservation_id`);

alter table `guest_trips` add index `guest_trips_check_in_id_index`(`check_in_id`);

alter table `guest_trips` add index `guest_trips_vehicle_id_index`(`vehicle_id`);

alter table `guest_trips` add index `guest_trips_trip_date_index`(`trip_date`);

alter table `guest_trips` add index `guest_trips_status_index`(`status`);

alter table `guest_trips` add index `guest_trips_branch_id_trip_date_status_index`(`branch_id`, `trip_date`, `status`);

-- -----------------------------------------------------------------------------
-- 0001_01_01_000022_housekeeping_pipeline_and_ledger_links
-- -----------------------------------------------------------------------------
-- "Being cleaned" becomes a state of its own, so the board's middle column has
-- somewhere to stand and a supervisor can tell a room nobody has started from
-- one that is being made up.
alter table `rooms` modify `housekeeping_status` enum('clean', 'dirty', 'cleaning', 'inspected', 'touch_up', 'out_of_order') not null default 'clean';

alter table `rooms` add `cleaning_started_at` timestamp null after `housekeeping_remark`;

DROP TABLE IF EXISTS `housekeeping_logs`;
create table `housekeeping_logs` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `room_id` bigint unsigned not null, `from_status` varchar(20) null, `to_status` varchar(20) not null, `housekeeper_id` bigint unsigned null, `remark` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `housekeeping_logs` add index `housekeeping_logs_branch_id_index`(`branch_id`);

alter table `housekeeping_logs` add index `housekeeping_logs_room_id_index`(`room_id`);

alter table `housekeeping_logs` add index `housekeeping_logs_branch_id_created_at_index`(`branch_id`, `created_at`);

-- A voucher that names the document it came from can only be posted once —
-- which is what stops a re-opened checkout screen putting one bill in the
-- books twice.
alter table `vouchers` add `source_type` varchar(40) null after `reference_no`;
alter table `vouchers` add `source_id` bigint unsigned null after `source_type`;
alter table `vouchers` add `is_auto` tinyint not null default '0' after `source_id`;
alter table `vouchers` add `is_cancelled` tinyint not null default '0' after `is_auto`;
alter table `vouchers` add index `vouchers_source_index`(`branch_id`, `source_type`, `source_id`);

-- A ledger that IS the cash box, or IS a bank account, says so outright.
alter table `ledgers` add `code` varchar(30) null after `name`;
alter table `ledgers` add `cash_type` enum('none', 'cash', 'bank') not null default 'none' after `code`;
alter table `ledgers` add `bank_account_no` varchar(40) null after `gst_no`;
alter table `ledgers` add `bank_ifsc` varchar(20) null after `bank_account_no`;

-- -----------------------------------------------------------------------------
-- A standard Indian chart of accounts (AccountingSeeder)
-- -----------------------------------------------------------------------------
INSERT INTO `account_groups` (`id`, `branch_id`, `parent_id`, `name`, `nature`, `is_system`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, NULL, 'Capital Account', 'liability', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, NULL, 'Current Assets', 'asset', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, NULL, 'Current Liabilities', 'liability', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 2, 'Sundry Debtors', 'asset', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 3, 'Sundry Creditors', 'liability', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 1, 2, 'Cash-in-Hand', 'asset', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (7, 1, 2, 'Bank Accounts', 'asset', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (8, 1, NULL, 'Fixed Assets', 'asset', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (9, 1, 3, 'Duties & Taxes', 'liability', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (10, 1, NULL, 'Direct Income', 'income', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (11, 1, NULL, 'Indirect Income', 'income', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (12, 1, NULL, 'Direct Expenses', 'expense', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (13, 1, NULL, 'Indirect Expenses', 'expense', 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

INSERT INTO `ledgers` (`id`, `branch_id`, `account_group_id`, `name`, `code`, `cash_type`, `opening_balance`, `balance_type`, `gst_no`, `bank_account_no`, `bank_ifsc`, `mobile`, `email`, `address`, `is_system`, `status`, `created_at`, `updated_at`) VALUES
  (1, 1, 6, 'Cash', NULL, 'cash', '0.00', 'dr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (2, 1, 7, 'Bank', NULL, 'bank', '0.00', 'dr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (3, 1, 10, 'Room Revenue', NULL, 'none', '0.00', 'cr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (4, 1, 10, 'Food & Beverage Revenue', NULL, 'none', '0.00', 'cr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (5, 1, 10, 'Banquet Revenue', NULL, 'none', '0.00', 'cr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (6, 1, 11, 'Pool & Other Revenue', NULL, 'none', '0.00', 'cr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (7, 1, 9, 'GST Payable', NULL, 'none', '0.00', 'cr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (8, 1, 13, 'Discount Allowed', NULL, 'none', '0.00', 'dr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (9, 1, 13, 'Salaries & Wages', NULL, 'none', '0.00', 'dr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (10, 1, 13, 'Electricity & Water', NULL, 'none', '0.00', 'dr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (11, 1, 12, 'Laundry Expenses', NULL, 'none', '0.00', 'dr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39'),
  (12, 1, 12, 'Kitchen Purchases', NULL, 'none', '0.00', 'dr', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-09 22:07:39', '2026-09-09 22:07:39');

-- -----------------------------------------------------------------------------
-- 0001_01_01_000024_business_days
-- -----------------------------------------------------------------------------
-- The hotel's own calendar. One row per branch per day: while it is `open` the
-- day is still being traded, and once the night auditor closes it the figures
-- in `figures` are frozen and the business date becomes the day after it.
--
-- The unique key is the point of the table. Two clerks pressing Run at the same
-- moment is exactly how a day gets closed twice and every figure doubles, and
-- the database refuses it rather than the code remembering to.
DROP TABLE IF EXISTS `business_days`;
create table `business_days` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `business_date` date not null, `status` enum('open', 'closed') not null default 'open', `nights_posted` int unsigned not null default '0', `no_shows` int unsigned not null default '0', `rooms_sold` int unsigned not null default '0', `figures` json null, `note` text null, `closed_by` bigint unsigned null, `closed_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `business_days` add index `business_days_branch_id_index`(`branch_id`);

alter table `business_days` add index `business_days_status_index`(`status`);

alter table `business_days` add unique `business_days_branch_id_business_date_unique`(`branch_id`, `business_date`);

-- -----------------------------------------------------------------------------
-- 0001_01_01_000025_rate_management
-- -----------------------------------------------------------------------------
-- What a room costs tonight. Three tables, each answering one question:
-- `rate_seasons` is WHEN, `rate_plans` is WHO FOR, and `rate_rules` is HOW
-- MUCH. Nothing here ever rewrites what a booking was sold at -- the rate is
-- copied onto the booking when it is taken.
DROP TABLE IF EXISTS `rate_seasons`;
create table `rate_seasons` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(20) null, `from_date` date not null, `to_date` date not null, `priority` tinyint unsigned not null default '0', `colour` varchar(20) null, `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `rate_seasons` add index `rate_seasons_branch_id_index`(`branch_id`);

alter table `rate_seasons` add index `rate_seasons_branch_id_from_date_to_date_index`(`branch_id`, `from_date`, `to_date`);

DROP TABLE IF EXISTS `rate_plans`;
create table `rate_plans` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(20) null, `business_market_id` bigint unsigned null, `company_id` bigint unsigned null, `is_default` tinyint(1) not null default '0', `priority` tinyint unsigned not null default '0', `valid_from` date null, `valid_to` date null, `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `rate_plans` add index `rate_plans_branch_id_index`(`branch_id`);

alter table `rate_plans` add index `rate_plans_business_market_id_index`(`business_market_id`);

alter table `rate_plans` add index `rate_plans_company_id_index`(`company_id`);

DROP TABLE IF EXISTS `rate_rules`;
create table `rate_rules` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `rate_plan_id` bigint unsigned not null, `room_type_id` bigint unsigned not null, `rate_season_id` bigint unsigned null, `from_date` date null, `to_date` date null, `weekdays` varchar(40) null, `amount` decimal(12, 2) not null default '0', `extra_adult` decimal(12, 2) not null default '0', `extra_child` decimal(12, 2) not null default '0', `min_stay` smallint unsigned not null default '0', `max_stay` smallint unsigned not null default '0', `stop_sell` tinyint(1) not null default '0', `closed_to_arrival` tinyint(1) not null default '0', `closed_to_departure` tinyint(1) not null default '0', `priority` tinyint unsigned not null default '0', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `rate_rules` add index `rate_rules_branch_id_index`(`branch_id`);

alter table `rate_rules` add index `rate_rules_rate_plan_id_index`(`rate_plan_id`);

alter table `rate_rules` add index `rate_rules_room_type_id_index`(`room_type_id`);

alter table `rate_rules` add index `rate_rules_rate_season_id_index`(`rate_season_id`);

alter table `rate_rules` add index `rate_rules_lookup_index`(`branch_id`, `rate_plan_id`, `room_type_id`);

-- Which plan a booking was sold on. Kept BESIDE the rate it was sold at, never
-- instead of it: the amount on the booking row is the contract, and this only
-- says where the number came from.
alter table `reservations` add `rate_plan_id` bigint unsigned null after `business_market_id`;
alter table `reservation_rooms` add `rate_plan_id` bigint unsigned null after `plan_type_id`;
alter table `check_ins` add `rate_plan_id` bigint unsigned null after `plan_type_id`;

-- -----------------------------------------------------------------------------
-- 0001_01_01_000026_india_compliance
-- -----------------------------------------------------------------------------
-- The paperwork the hotel owes to somebody other than the guest: Form C for
-- the FRRO, the daily register for the local police, and the GST details that
-- turn a bill into an invoice somebody can claim on.
--
-- Form C is per PERSON, not per room -- a couple in one room is two forms --
-- so the unique key is the (stay, person) pair. `check_in_pax_id` is null for
-- the person the booking is in the name of.
DROP TABLE IF EXISTS `form_c_entries`;
create table `form_c_entries` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `check_in_id` bigint unsigned not null, `check_in_pax_id` bigint unsigned null, `name` varchar(255) not null, `nationality_id` bigint unsigned null, `date_of_birth` date null, `sex` enum('male', 'female', 'other') null, `passport_no` varchar(40) null, `passport_place_of_issue` varchar(255) null, `passport_issue_date` date null, `passport_expiry_date` date null, `visa_no` varchar(40) null, `visa_type` varchar(60) null, `visa_place_of_issue` varchar(255) null, `visa_issue_date` date null, `visa_expiry_date` date null, `arrived_in_india_on` date null, `arrived_from` varchar(255) null, `purpose_of_visit` varchar(120) null, `permanent_address` text null, `address_in_india` text null, `next_destination` varchar(255) null, `next_destination_on` date null, `employed_in_india` tinyint(1) not null default '0', `employer` varchar(255) null, `filed_at` timestamp null, `reference_no` varchar(60) null, `remark` text null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `form_c_entries` add index `form_c_entries_branch_id_index`(`branch_id`);

alter table `form_c_entries` add index `form_c_entries_check_in_id_index`(`check_in_id`);

alter table `form_c_entries` add index `form_c_entries_check_in_pax_id_index`(`check_in_pax_id`);

alter table `form_c_entries` add unique `form_c_person_unique`(`check_in_id`, `check_in_pax_id`);

-- The main guest's own ID. `check_in_pax` has carried one per accompanying
-- person since the start; the person whose name is on the booking had none,
-- which is exactly the one the police register asks for first.
alter table `check_ins` add `id_type` varchar(40) null after `mobile`;
alter table `check_ins` add `id_number` varchar(60) null after `id_type`;
alter table `check_ins` add `nationality_id` bigint unsigned null after `id_number`;
alter table `check_ins` add `is_foreign` tinyint(1) not null default '0' after `nationality_id`;

alter table `check_ins` add index `check_ins_is_foreign_index`(`is_foreign`);

-- What a bill needs to become a GST invoice somebody can claim on. The buyer's
-- GSTIN is COPIED onto the bill rather than read from the company: a
-- registration can change, and last year's invoice has to keep saying what it
-- said when it was issued.
alter table `bills` add `buyer_gstin` varchar(20) null after `guest_id`;
alter table `bills` add `buyer_name` varchar(255) null after `buyer_gstin`;
alter table `bills` add `buyer_address` varchar(255) null after `buyer_name`;
alter table `bills` add `place_of_supply` varchar(4) null after `buyer_address`;
alter table `bills` add `place_of_supply_name` varchar(60) null after `place_of_supply`;
alter table `bills` add `is_igst` tinyint(1) not null default '0' after `place_of_supply_name`;

-- The hotel's own GST state, plus the two numbers the authorities know it by.
alter table `branches` add `gst_state_code` varchar(4) null after `gst_no`;
alter table `branches` add `gst_state_name` varchar(60) null after `gst_state_code`;
alter table `branches` add `frro_hotel_code` varchar(40) null after `gst_state_name`;
alter table `branches` add `police_station` varchar(255) null after `frro_hotel_code`;

-- -----------------------------------------------------------------------------
-- 0001_01_01_000027_guest_crm
-- -----------------------------------------------------------------------------
-- Remembering a guest between visits. The totals on `guests` are a CACHE,
-- rebuilt from the bookings by App\Support\GuestCrm whenever a stay closes;
-- the tier is always derived from them and is never written by hand.
DROP TABLE IF EXISTS `guest_notes`;
create table `guest_notes` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `guest_id` bigint unsigned not null, `check_in_id` bigint unsigned null, `kind` enum('preference', 'complaint', 'compliment', 'note') not null default 'note', `body` text not null, `pinned` tinyint(1) not null default '0', `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `guest_notes` add index `guest_notes_branch_id_index`(`branch_id`);

alter table `guest_notes` add index `guest_notes_guest_id_index`(`guest_id`);

alter table `guest_notes` add index `guest_notes_check_in_id_index`(`check_in_id`);

alter table `guest_notes` add index `guest_notes_kind_index`(`kind`);

-- One row per stay, made when the LINK IS SENT rather than when the guest
-- answers -- so "we asked and they did not reply" is a fact the hotel holds.
DROP TABLE IF EXISTS `guest_feedback`;
create table `guest_feedback` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `guest_id` bigint unsigned null, `check_in_id` bigint unsigned null, `token` varchar(40) not null, `overall` tinyint unsigned null, `room` tinyint unsigned null, `cleanliness` tinyint unsigned null, `staff` tinyint unsigned null, `food` tinyint unsigned null, `value` tinyint unsigned null, `liked` text null, `improve` text null, `would_return` tinyint(1) null, `sent_at` timestamp null, `answered_at` timestamp null, `handled_at` timestamp null, `handled_by` bigint unsigned null, `handled_note` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `guest_feedback` add unique `guest_feedback_token_unique`(`token`);

alter table `guest_feedback` add index `guest_feedback_branch_id_index`(`branch_id`);

alter table `guest_feedback` add index `guest_feedback_guest_id_index`(`guest_id`);

alter table `guest_feedback` add index `guest_feedback_check_in_id_index`(`check_in_id`);

alter table `guest_feedback` add index `guest_feedback_answered_at_index`(`answered_at`);

-- Points as a LEDGER, never as a number: a balance somebody can argue with has
-- to be explainable line by line. Negative for anything that takes points
-- away, so a balance is a SUM and never a subtraction somebody has to remember.
DROP TABLE IF EXISTS `loyalty_entries`;
create table `loyalty_entries` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `guest_id` bigint unsigned not null, `check_in_id` bigint unsigned null, `entry_date` date not null, `kind` enum('earned', 'redeemed', 'adjusted', 'expired') not null default 'earned', `points` int not null, `reason` varchar(255) not null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `loyalty_entries` add index `loyalty_entries_branch_id_index`(`branch_id`);

alter table `loyalty_entries` add index `loyalty_entries_guest_id_index`(`guest_id`);

alter table `loyalty_entries` add index `loyalty_entries_guest_id_entry_date_index`(`guest_id`, `entry_date`);

-- The figures a profile screen would otherwise recompute on every page load.
alter table `guests` add `stays` int unsigned not null default '0' after `is_blacklisted`;
alter table `guests` add `nights` int unsigned not null default '0' after `stays`;
alter table `guests` add `total_spend` decimal(14, 2) not null default '0' after `nights`;
alter table `guests` add `first_stay_at` date null after `total_spend`;
alter table `guests` add `last_stay_at` date null after `first_stay_at`;
alter table `guests` add `totals_at` timestamp null after `last_stay_at`;
alter table `guests` add `tier` varchar(20) null after `totals_at`;
alter table `guests` add `loyalty_points` int not null default '0' after `tier`;
alter table `guests` add `preferences` text null after `loyalty_points`;
alter table `guests` add `anniversary` date null after `preferences`;
alter table `guests` add `blacklist_reason` varchar(255) null after `anniversary`;
alter table `guests` add `blacklisted_on` date null after `blacklist_reason`;
alter table `guests` add `blacklisted_by` bigint unsigned null after `blacklisted_on`;

alter table `guests` add index `guests_tier_index`(`tier`);

-- -----------------------------------------------------------------------------
-- 0001_01_01_000028_store_and_inventory
-- -----------------------------------------------------------------------------
-- The store: what the hotel bought, what it has, and where it went.
--
-- `stock_ledger` has one row per movement, ever, and is never updated or
-- deleted -- a correction is another row and a cancelled document posts its
-- reverse. `store_items.current_qty` and `avg_rate` are a CACHE of running
-- that ledger, rebuildable at any time from App\Support\Store::rebuild().
-- If the two ever disagree, the ledger is right.
--
-- One `store_docs` with a `kind` rather than six near-identical pairs of
-- tables: a purchase order, a goods receipt, an issue and a wastage note are
-- the same shape, and six near-identical controllers means the sixth is the
-- one with the bug in it.
DROP TABLE IF EXISTS `store_categories`;
create table `store_categories` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(20) null, `department` varchar(40) null, `sort` tinyint unsigned not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `store_categories` add index `store_categories_branch_id_index`(`branch_id`);

DROP TABLE IF EXISTS `store_items`;
create table `store_items` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `store_category_id` bigint unsigned null, `name` varchar(255) not null, `code` varchar(40) null, `unit` varchar(20) not null default 'kg', `current_qty` decimal(14, 3) not null default '0', `avg_rate` decimal(12, 2) not null default '0', `last_rate` decimal(12, 2) not null default '0', `reorder_level` decimal(14, 3) not null default '0', `opening_qty` decimal(14, 3) not null default '0', `opening_rate` decimal(12, 2) not null default '0', `hsn_code` varchar(12) null, `tax_percent` decimal(6, 2) not null default '0', `is_ingredient` tinyint(1) not null default '1', `remark` varchar(255) null, `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `store_items` add index `store_items_branch_id_index`(`branch_id`);

alter table `store_items` add index `store_items_store_category_id_index`(`store_category_id`);

alter table `store_items` add index `store_items_code_index`(`code`);

alter table `store_items` add index `store_items_branch_id_status_index`(`branch_id`, `status`);

DROP TABLE IF EXISTS `store_docs`;
create table `store_docs` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `kind` enum('po', 'grn', 'issue', 'adjustment', 'wastage') not null, `doc_no` varchar(40) not null, `doc_date` date not null, `vendor_id` bigint unsigned null, `department` varchar(40) null, `issued_to` varchar(255) null, `invoice_no` varchar(60) null, `invoice_date` date null, `expected_on` date null, `against_id` bigint unsigned null, `sub_total` decimal(14, 2) not null default '0', `tax_total` decimal(14, 2) not null default '0', `other_charges` decimal(14, 2) not null default '0', `net_amount` decimal(14, 2) not null default '0', `status` enum('draft', 'posted', 'partial', 'closed', 'cancelled') not null default 'draft', `posted_at` timestamp null, `posted_by` bigint unsigned null, `remark` text null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `store_docs` add index `store_docs_branch_id_index`(`branch_id`);

alter table `store_docs` add index `store_docs_kind_index`(`kind`);

alter table `store_docs` add index `store_docs_doc_no_index`(`doc_no`);

alter table `store_docs` add index `store_docs_vendor_id_index`(`vendor_id`);

alter table `store_docs` add index `store_docs_department_index`(`department`);

alter table `store_docs` add index `store_docs_against_id_index`(`against_id`);

alter table `store_docs` add index `store_docs_status_index`(`status`);

alter table `store_docs` add unique `store_docs_branch_id_kind_doc_no_unique`(`branch_id`, `kind`, `doc_no`);

DROP TABLE IF EXISTS `store_doc_items`;
create table `store_doc_items` (`id` bigint unsigned not null auto_increment primary key, `store_doc_id` bigint unsigned not null, `store_item_id` bigint unsigned not null, `qty` decimal(14, 3) not null default '0', `received_qty` decimal(14, 3) not null default '0', `rate` decimal(12, 2) not null default '0', `amount` decimal(14, 2) not null default '0', `tax_percent` decimal(6, 2) not null default '0', `tax_amount` decimal(14, 2) not null default '0', `total_amount` decimal(14, 2) not null default '0', `remark` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `store_doc_items` add index `store_doc_items_store_doc_id_index`(`store_doc_id`);

alter table `store_doc_items` add index `store_doc_items_store_item_id_index`(`store_item_id`);

DROP TABLE IF EXISTS `stock_ledger`;
create table `stock_ledger` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `store_item_id` bigint unsigned not null, `entry_date` date not null, `direction` enum('in', 'out') not null, `kind` varchar(20) not null, `store_doc_id` bigint unsigned null, `reference` varchar(255) null, `qty` decimal(14, 3) not null, `rate` decimal(12, 2) not null default '0', `value` decimal(14, 2) not null default '0', `balance_qty` decimal(14, 3) not null default '0', `balance_rate` decimal(12, 2) not null default '0', `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `stock_ledger` add index `stock_ledger_branch_id_index`(`branch_id`);

alter table `stock_ledger` add index `stock_ledger_store_item_id_index`(`store_item_id`);

alter table `stock_ledger` add index `stock_ledger_entry_date_index`(`entry_date`);

alter table `stock_ledger` add index `stock_ledger_store_doc_id_index`(`store_doc_id`);

alter table `stock_ledger` add index `stock_ledger_item_date_index`(`branch_id`, `store_item_id`, `entry_date`);

-- What a dish is made of, and therefore what it costs. `yield_qty` is how many
-- portions the recipe makes: a biryani written for four is costed per one.
DROP TABLE IF EXISTS `recipes`;
create table `recipes` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `pos_item_id` bigint unsigned null, `name` varchar(255) not null, `yield_qty` decimal(10, 3) not null default '1', `yield_unit` varchar(20) not null default 'portion', `method` text null, `status` tinyint not null default '1', `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `recipes` add index `recipes_branch_id_index`(`branch_id`);

alter table `recipes` add index `recipes_pos_item_id_index`(`pos_item_id`);

DROP TABLE IF EXISTS `recipe_items`;
create table `recipe_items` (`id` bigint unsigned not null auto_increment primary key, `recipe_id` bigint unsigned not null, `store_item_id` bigint unsigned not null, `qty` decimal(14, 4) not null default '0', `remark` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `recipe_items` add index `recipe_items_recipe_id_index`(`recipe_id`);

alter table `recipe_items` add index `recipe_items_store_item_id_index`(`store_item_id`);

-- -----------------------------------------------------------------------------
-- 0001_01_01_000029_shifts_and_audit
-- -----------------------------------------------------------------------------
-- One cashier, one drawer, one stretch of time.
--
-- Note what `cashier_shifts` does NOT hold: a copy of the payments. A shift's
-- figures are a question asked of `settlements`, `advance_deposits`,
-- `pos_payments` and the two petty cash tables, so a shift report can never
-- disagree with the day book. What it does hold is what the cashier COUNTED,
-- which exists nowhere else, and `expected` -- a frozen copy of the figures
-- taken at the moment of closing, so a bill corrected next week cannot rewrite
-- a variance somebody has already signed for.
DROP TABLE IF EXISTS `cashier_shifts`;
create table `cashier_shifts` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned not null, `user_id` bigint unsigned not null, `shift_no` varchar(40) not null, `name` varchar(40) null, `opened_at` datetime not null, `closed_at` datetime null, `opening_float` decimal(12, 2) not null default '0', `declared` json null, `expected` json null, `cash_expected` decimal(12, 2) not null default '0', `cash_counted` decimal(12, 2) not null default '0', `variance` decimal(12, 2) not null default '0', `status` enum('open', 'closed') not null default 'open', `remark` text null, `close_note` text null, `closed_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `cashier_shifts` add index `cashier_shifts_branch_id_index`(`branch_id`);

alter table `cashier_shifts` add index `cashier_shifts_user_id_index`(`user_id`);

alter table `cashier_shifts` add index `cashier_shifts_opened_at_index`(`opened_at`);

alter table `cashier_shifts` add index `cashier_shifts_status_index`(`status`);

alter table `cashier_shifts` add unique `cashier_shifts_branch_id_shift_no_unique`(`branch_id`, `shift_no`);

alter table `cashier_shifts` add index `cashier_shifts_branch_id_user_id_status_index`(`branch_id`, `user_id`, `status`);

-- One thing somebody did. Written once and never touched again: there is no
-- edit screen and no delete button anywhere in the application for this table.
-- `user_name` and `subject_label` are COPIED in rather than joined -- a deleted
-- user must not turn a year of history into blank cells, which is exactly what
-- a join would do, and always on the day somebody is looking for something.
DROP TABLE IF EXISTS `activity_logs`;
create table `activity_logs` (`id` bigint unsigned not null auto_increment primary key, `branch_id` bigint unsigned null, `user_id` bigint unsigned null, `user_name` varchar(120) null, `action` varchar(40) not null, `area` varchar(40) null, `subject_type` varchar(80) null, `subject_id` bigint unsigned null, `subject_label` varchar(255) null, `summary` varchar(255) null, `changes` json null, `ip` varchar(45) null, `agent` varchar(255) null, `url` varchar(255) null, `method` varchar(10) null, `happened_at` datetime not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `activity_logs` add index `activity_logs_branch_id_index`(`branch_id`);

alter table `activity_logs` add index `activity_logs_user_id_index`(`user_id`);

alter table `activity_logs` add index `activity_logs_action_index`(`action`);

alter table `activity_logs` add index `activity_logs_area_index`(`area`);

alter table `activity_logs` add index `activity_logs_subject_type_index`(`subject_type`);

alter table `activity_logs` add index `activity_logs_subject_id_index`(`subject_id`);

alter table `activity_logs` add index `activity_logs_happened_at_index`(`happened_at`);

alter table `activity_logs` add index `activity_logs_branch_id_happened_at_index`(`branch_id`, `happened_at`);

alter table `activity_logs` add index `activity_logs_subject_type_subject_id_index`(`subject_type`, `subject_id`);

-- <<< END generated by scripts/patch-dump

-- -----------------------------------------------------------------------------
-- migrations
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `migrations`;
create table `migrations` (
  `id` int unsigned not null auto_increment primary key,
  `migration` varchar(255) not null,
  `batch` int not null
) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

INSERT INTO `migrations` (`migration`, `batch`) VALUES
  ('0001_01_01_000000_create_core_tables', 1),
  ('0001_01_01_000001_create_module_tables', 1),
  ('0001_01_01_000002_create_master_tables', 1),
  ('0001_01_01_000003_create_reservation_tables', 1),
  ('0001_01_01_000004_create_front_office_tables', 1),
  ('0001_01_01_000005_create_housekeeping_tables', 1),
  ('0001_01_01_000006_create_accounting_tables', 1),
  ('0001_01_01_000007_add_payment_details_to_advance_deposits', 1),
  ('0001_01_01_000008_add_block_type_to_room_blocks', 1),
  ('0001_01_01_000009_add_tax_details_to_branches', 1),
  ('0001_01_01_000010_add_checkout_tables', 1),
  ('0001_01_01_000010_create_cache_table', 1),
  ('0001_01_01_000011_add_housekeeping_to_rooms', 1),
  ('0001_01_01_000011_create_jobs_table', 1),
  ('0001_01_01_000012_link_plan_and_services_to_folio', 1),
  ('0001_01_01_000013_laundry_issue_and_receipt', 1),
  ('0001_01_01_000014_work_order_details', 1),
  ('0001_01_01_000015_create_pos_tables', 1),
  ('0001_01_01_000016_pos_setup_screens', 1),
  ('0001_01_01_000017_pos_billing', 1),
  ('0001_01_01_000018_group_bills_and_reports', 1),
  ('0001_01_01_000019_tax_is_a_choice', 1),
  ('0001_01_01_000020_notifications', 1),
  ('0001_01_01_000021_pool_hall_and_car', 1),
  ('0001_01_01_000022_housekeeping_pipeline_and_ledger_links', 1),
  ('0001_01_01_000023_guest_messages', 1),
  ('0001_01_01_000024_business_days', 1),
  ('0001_01_01_000025_rate_management', 1),
  ('0001_01_01_000026_india_compliance', 1),
  ('0001_01_01_000027_guest_crm', 1),
  ('0001_01_01_000028_store_and_inventory', 1),
  ('0001_01_01_000029_shifts_and_audit', 1);

SET FOREIGN_KEY_CHECKS = 1;
