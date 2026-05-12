-- Allow land property records to store an intentional N/A value for date acquired.

SET NAMES utf8mb4;

ALTER TABLE land_properties
    MODIFY date_acquired VARCHAR(20) NOT NULL;

ALTER TABLE land_property_history
    MODIFY date_acquired VARCHAR(20) NOT NULL;