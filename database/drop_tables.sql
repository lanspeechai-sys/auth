-- Run this in phpMyAdmin SQL tab to drop all existing tables
-- This will delete all data and tables, then you can import fresh

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS schools;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS event_registrations;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS opportunities;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS order_items;

SET FOREIGN_KEY_CHECKS = 1;
