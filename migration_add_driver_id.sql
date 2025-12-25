 -- Migration: Add driver_id column to vehicles table and update schema
-- Run this script to update your existing database

USE taxi_management_system;

-- Step 1: Add driver_id column to vehicles table (nullable for backward compatibility)
-- Check if column exists first to avoid errors on re-run
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'taxi_management_system' 
    AND TABLE_NAME = 'vehicles' 
    AND COLUMN_NAME = 'driver_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE vehicles ADD COLUMN driver_id INT NULL AFTER id',
    'SELECT "Column driver_id already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Add foreign key constraint if it doesn't exist
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'taxi_management_system' 
    AND TABLE_NAME = 'vehicles' 
    AND CONSTRAINT_NAME = 'vehicles_ibfk_1'
    AND COLUMN_NAME = 'driver_id'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE vehicles ADD CONSTRAINT vehicles_ibfk_1 FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL',
    'SELECT "Foreign key already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Update users table to include 'driver' role
ALTER TABLE users 
MODIFY COLUMN role ENUM('admin', 'manager', 'customer', 'driver') DEFAULT 'customer';

-- Step 4: Add user_id column to drivers table if it doesn't exist
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'taxi_management_system' 
    AND TABLE_NAME = 'drivers' 
    AND COLUMN_NAME = 'user_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE drivers ADD COLUMN user_id INT NULL AFTER id',
    'SELECT "Column user_id already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 5: Add foreign key for user_id in drivers table if it doesn't exist
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'taxi_management_system' 
    AND TABLE_NAME = 'drivers' 
    AND CONSTRAINT_NAME LIKE '%user_id%'
    AND COLUMN_NAME = 'user_id'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE drivers ADD CONSTRAINT drivers_ibfk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
    'SELECT "Foreign key for user_id already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed successfully!' AS status;
