CREATE DATABASE IF NOT EXISTS tekno_cube_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tekno_cube_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS Reservation_breakage_report;
DROP TABLE IF EXISTS Reserved_item;
DROP TABLE IF EXISTS Breakage_report;
DROP TABLE IF EXISTS Borrow_transaction;
DROP TABLE IF EXISTS Reservation_batch;
DROP TABLE IF EXISTS Inventory_item;
DROP TABLE IF EXISTS Instructor;
DROP TABLE IF EXISTS Student;
DROP TABLE IF EXISTS Department;
DROP TABLE IF EXISTS `User`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `User` (
    UserID VARCHAR(20) PRIMARY KEY,
    FirstName VARCHAR(50) NOT NULL,
    LastName VARCHAR(50) NOT NULL,
    UserType ENUM('Student','Instructor','Admin') NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE Student (
    UserID VARCHAR(20) PRIMARY KEY,
    Course VARCHAR(100) NOT NULL,
    EnrollmentStatus ENUM('Officially Enrolled','Inactive') NOT NULL DEFAULT 'Officially Enrolled',
    HasLiability BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_student_user FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Instructor (
    UserID VARCHAR(20) PRIMARY KEY,
    Department VARCHAR(50) NOT NULL,
    CONSTRAINT fk_instructor_user FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Department (
    DepartmentID VARCHAR(20) PRIMARY KEY,
    DepartmentName VARCHAR(100) NOT NULL UNIQUE,
    Location VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE Inventory_item (
    AssetNumber VARCHAR(20) PRIMARY KEY,
    ItemName VARCHAR(100) NOT NULL,
    Category VARCHAR(50) NOT NULL,
    ItemType ENUM('Consumable','Reusable','Returnable') NOT NULL,
    CurrentCondition ENUM('Good','Worn','Damaged') NOT NULL DEFAULT 'Good',
    ReplacementCost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    QuantityAvailable INT NOT NULL DEFAULT 0,
    DepartmentID VARCHAR(20) NOT NULL,
    CONSTRAINT chk_inventory_replacement_cost CHECK (ReplacementCost >= 0),
    CONSTRAINT chk_inventory_quantity CHECK (QuantityAvailable >= 0),
    CONSTRAINT fk_inventory_department FOREIGN KEY (DepartmentID) REFERENCES Department(DepartmentID) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Borrow_transaction (
    TransactionNumber VARCHAR(20) PRIMARY KEY,
    BorrowDateTime DATETIME NOT NULL,
    DueDateTime DATETIME NOT NULL,
    ActualReturnDateTime DATETIME NULL,
    TransactionStatus ENUM('Active','Return Requested','Returned','Overdue','Late') NOT NULL DEFAULT 'Active',
    ReturnCondition ENUM('Pending Inspection','Good','Worn','Damaged') NOT NULL DEFAULT 'Pending Inspection',
    InspectorComment TEXT NULL,
    UserID VARCHAR(20) NOT NULL,
    AssetNumber VARCHAR(20) NOT NULL,
    CONSTRAINT fk_borrow_user FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_borrow_inventory FOREIGN KEY (AssetNumber) REFERENCES Inventory_item(AssetNumber) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Breakage_report (
    ReportNumber VARCHAR(20) PRIMARY KEY,
    DateGenerated DATE NOT NULL,
    QuantityMissing INT NOT NULL DEFAULT 0,
    QuantityDamaged INT NOT NULL DEFAULT 0,
    PenaltyFeeAmount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    DamageDescription TEXT NULL,
    SettlementStatus ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending',
    TransactionNumber VARCHAR(20) NOT NULL UNIQUE,
    CONSTRAINT chk_breakage_missing CHECK (QuantityMissing >= 0),
    CONSTRAINT chk_breakage_damaged CHECK (QuantityDamaged >= 0),
    CONSTRAINT chk_breakage_penalty CHECK (PenaltyFeeAmount >= 0),
    CONSTRAINT fk_breakage_transaction FOREIGN KEY (TransactionNumber) REFERENCES Borrow_transaction(TransactionNumber) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Reservation_batch (
    BatchID VARCHAR(20) PRIMARY KEY,
    ScheduleDate DATE NOT NULL,
    StartTime TIME NOT NULL,
    EndTime TIME NOT NULL,
    Purpose TEXT NOT NULL,
    ReservationStatus ENUM('Reserved','Return Requested','Returned','Cancelled') NOT NULL DEFAULT 'Reserved',
    ConflictStatus ENUM('Clear','At Risk') NOT NULL DEFAULT 'Clear',
    ConflictNote TEXT NULL,
    ActualReturnDateTime DATETIME NULL,
    UserID VARCHAR(20) NOT NULL,
    CONSTRAINT fk_reservation_user FOREIGN KEY (UserID) REFERENCES `User`(UserID) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_reservation_time CHECK (EndTime > StartTime)
) ENGINE=InnoDB;

CREATE TABLE Reserved_item (
    BatchID VARCHAR(20) NOT NULL,
    AssetNumber VARCHAR(20) NOT NULL,
    QuantityReserved INT NOT NULL DEFAULT 1,
    QuantityReturned INT NOT NULL DEFAULT 0,
    QuantityMissing INT NOT NULL DEFAULT 0,
    QuantityDamaged INT NOT NULL DEFAULT 0,
    ReturnCondition ENUM('Pending Inspection','Good','Worn','Damaged') NOT NULL DEFAULT 'Pending Inspection',
    InspectorComment TEXT NULL,
    PRIMARY KEY (BatchID, AssetNumber),
    CONSTRAINT chk_reserved_quantity CHECK (QuantityReserved > 0),
    CONSTRAINT chk_returned_quantity CHECK (QuantityReturned >= 0),
    CONSTRAINT chk_missing_quantity CHECK (QuantityMissing >= 0),
    CONSTRAINT chk_damaged_quantity CHECK (QuantityDamaged >= 0),
    CONSTRAINT fk_reserved_batch FOREIGN KEY (BatchID) REFERENCES Reservation_batch(BatchID) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reserved_inventory FOREIGN KEY (AssetNumber) REFERENCES Inventory_item(AssetNumber) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;


CREATE TABLE Reservation_breakage_report (
    ReportNumber VARCHAR(20) PRIMARY KEY,
    DateGenerated DATE NOT NULL,
    QuantityMissing INT NOT NULL DEFAULT 0,
    QuantityDamaged INT NOT NULL DEFAULT 0,
    PenaltyFeeAmount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    DamageDescription TEXT NULL,
    SettlementStatus ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending',
    BatchID VARCHAR(20) NOT NULL,
    AssetNumber VARCHAR(20) NOT NULL,
    UNIQUE KEY uq_reservation_breakage_item (BatchID, AssetNumber),
    CONSTRAINT chk_reservation_breakage_missing CHECK (QuantityMissing >= 0),
    CONSTRAINT chk_reservation_breakage_damaged CHECK (QuantityDamaged >= 0),
    CONSTRAINT chk_reservation_breakage_penalty CHECK (PenaltyFeeAmount >= 0),
    CONSTRAINT fk_reservation_breakage_reserved_item FOREIGN KEY (BatchID, AssetNumber) REFERENCES Reserved_item(BatchID, AssetNumber) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO Department (DepartmentID, DepartmentName, Location) VALUES
('DEPT-CEA', 'College of Engineering and Architecture', 'Engineering and Architecture Building'),
('DEPT-CMBA', 'College of Management, Business & Accountancy', 'Management and Business Building'),
('DEPT-CASE', 'College of Arts, Sciences, & Education', 'Arts, Sciences, and Education Building'),
('DEPT-CNAHS', 'College of Nursing & Allied Health Sciences', 'Nursing and Allied Health Sciences Building'),
('DEPT-CCS', 'College of Computer Studies', 'NGE Building'),
('DEPT-CCJ', 'College of Criminal Justice', 'Criminal Justice Building');

-- Password for all seeded accounts: admin123
INSERT INTO `User` (UserID, FirstName, LastName, UserType, Email, PasswordHash) VALUES
('ADMIN-001', 'Tekno', 'Admin', 'Admin', 'admin@teknocube.local', '$2y$12$HHR7mqWuDjPtiDqh.7/v9uIYbaXkGG.iwzF8XXlgEIIK2ZOJ1xZmi'),
('22-1234-567', 'Juan', 'Dela Cruz', 'Student', 'juan.delacruz@cit.edu', '$2y$12$HHR7mqWuDjPtiDqh.7/v9uIYbaXkGG.iwzF8XXlgEIIK2ZOJ1xZmi'),
('INST-404', 'Sophia', 'Tan', 'Instructor', 'sophia.tan@cit.edu', '$2y$12$HHR7mqWuDjPtiDqh.7/v9uIYbaXkGG.iwzF8XXlgEIIK2ZOJ1xZmi');

INSERT INTO Student (UserID, Course, EnrollmentStatus, HasLiability) VALUES
('22-1234-567', 'BS Information Technology', 'Officially Enrolled', FALSE);

INSERT INTO Instructor (UserID, Department) VALUES
('INST-404', 'DEPT-CEA');

INSERT INTO Inventory_item (AssetNumber, ItemName, Category, ItemType, CurrentCondition, ReplacementCost, QuantityAvailable, DepartmentID) VALUES
('CEA-ARCH-001', 'Drafting Board Set', 'Architecture Tools', 'Returnable', 'Good', 4500.00, 20, 'DEPT-CEA'),
('CEA-CIV-001', 'Surveying Tripod and Level Kit', 'Civil Engineering Equipment', 'Returnable', 'Good', 18000.00, 8, 'DEPT-CEA'),
('CEA-ECE-001', 'Rigol Digital Oscilloscope', 'Electronics', 'Returnable', 'Good', 45000.00, 6, 'DEPT-CEA'),
('CEA-ME-001', 'Vernier Caliper Set', 'Mechanical Tools', 'Returnable', 'Worn', 2500.00, 18, 'DEPT-CEA'),
('CEA-CHE-001', 'Laboratory Beaker 250ml', 'Glassware', 'Reusable', 'Good', 250.00, 70, 'DEPT-CEA'),
('CMBA-ACC-001', 'Financial Calculator', 'Business Tools', 'Returnable', 'Good', 2200.00, 28, 'DEPT-CMBA'),
('CMBA-HM-001', 'Food Thermometer', 'Hospitality Lab Tools', 'Returnable', 'Good', 900.00, 35, 'DEPT-CMBA'),
('CMBA-TOUR-001', 'Presentation Clicker', 'Presentation Equipment', 'Returnable', 'Good', 1200.00, 12, 'DEPT-CMBA'),
('CMBA-BA-001', 'POS Training Scanner', 'Simulation Equipment', 'Returnable', 'Worn', 3500.00, 10, 'DEPT-CMBA'),
('CMBA-OA-001', 'Label Printer Tape', 'Office Supplies', 'Consumable', 'Good', 350.00, 50, 'DEPT-CMBA'),
('CASE-BIO-001', 'Compound Microscope', 'Biology Equipment', 'Returnable', 'Good', 28000.00, 40, 'DEPT-CASE'),
('CASE-BIO-002', 'Petri Dish Pack', 'Biology Supplies', 'Consumable', 'Good', 180.00, 120, 'DEPT-CASE'),
('CASE-MMA-001', 'Drawing Tablet', 'Multimedia Arts Equipment', 'Returnable', 'Good', 9500.00, 16, 'DEPT-CASE'),
('CASE-EDU-001', 'Portable Projector', 'Education Equipment', 'Returnable', 'Good', 26000.00, 7, 'DEPT-CASE'),
('CASE-PSY-001', 'Stopwatch Set', 'Psychology Lab Tools', 'Returnable', 'Good', 800.00, 30, 'DEPT-CASE'),
('CNAHS-NUR-001', 'Stethoscope Training Kit', 'Nursing Equipment', 'Returnable', 'Good', 3200.00, 40, 'DEPT-CNAHS'),
('CNAHS-MT-001', 'Centrifuge Tube Pack', 'Medical Technology Supplies', 'Consumable', 'Good', 450.00, 100, 'DEPT-CNAHS'),
('CNAHS-PHAR-001', 'Mortar and Pestle Set', 'Pharmacy Equipment', 'Reusable', 'Good', 1200.00, 35, 'DEPT-CNAHS'),
('CNAHS-NUR-002', 'Blood Pressure Apparatus', 'Nursing Equipment', 'Returnable', 'Worn', 2800.00, 25, 'DEPT-CNAHS'),
('CNAHS-MT-002', 'Micropipette', 'Medical Technology Equipment', 'Returnable', 'Good', 6500.00, 18, 'DEPT-CNAHS'),
('CCS-CS-001', 'Arduino Starter Kit', 'Computer Studies Equipment', 'Returnable', 'Good', 2800.00, 25, 'DEPT-CCS'),
('CCS-IT-001', 'Network Cable Tester', 'Networking Equipment', 'Returnable', 'Good', 1800.00, 15, 'DEPT-CCS'),
('CCS-IT-002', 'Crimping Tool', 'Networking Tools', 'Returnable', 'Worn', 1500.00, 20, 'DEPT-CCS'),
('CCS-CS-002', 'Jumper Wires Pack', 'Electronics Supplies', 'Consumable', 'Good', 120.00, 80, 'DEPT-CCS'),
('CCS-IT-003', 'Raspberry Pi Kit', 'Computing Equipment', 'Returnable', 'Good', 4500.00, 12, 'DEPT-CCS'),
('CCJ-CRIM-001', 'Fingerprint Dusting Kit', 'Criminal Justice Lab Tools', 'Returnable', 'Good', 3500.00, 22, 'DEPT-CCJ'),
('CCJ-CRIM-002', 'Evidence Marker Set', 'Criminal Justice Supplies', 'Reusable', 'Good', 900.00, 35, 'DEPT-CCJ'),
('CCJ-CRIM-003', 'Measuring Tape Forensics Kit', 'Criminal Justice Lab Tools', 'Returnable', 'Good', 1100.00, 18, 'DEPT-CCJ'),
('CCJ-CRIM-004', 'Crime Scene Barrier Tape', 'Criminal Justice Supplies', 'Consumable', 'Good', 250.00, 90, 'DEPT-CCJ');

INSERT INTO Borrow_transaction (TransactionNumber, BorrowDateTime, DueDateTime, ActualReturnDateTime, TransactionStatus, ReturnCondition, InspectorComment, UserID, AssetNumber) VALUES
('TR-10024', '2026-05-01 08:30:00', '2026-05-01 17:00:00', NULL, 'Active', 'Pending Inspection', NULL, '22-1234-567', 'CCS-IT-001');
