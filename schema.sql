-- Blood Donation System Relational Schema (MySQL DDL)

-- Create Database
CREATE DATABASE IF NOT EXISTS blood_donation_system;
USE blood_donation_system;

-- 1. Generalization / Specialization & Role Definitions
-- Superclass: User
CREATE TABLE IF NOT EXISTS User (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    Email VARCHAR(255) UNIQUE NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    Role ENUM('Admin', 'Donor', 'Patient', 'Hospital', 'BloodBank') NOT NULL,
    RegistrationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Subclass 1: Admin
CREATE TABLE IF NOT EXISTS Admin (
    UserID INT PRIMARY KEY,
    AdminLevel INT NOT NULL DEFAULT 1,
    FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
);

-- Subclass 2: Person (Generalizes Donor, Patient)
CREATE TABLE IF NOT EXISTS Person (
    UserID INT PRIMARY KEY,
    FirstName VARCHAR(100) NOT NULL,
    LastName VARCHAR(100) NOT NULL,
    DateOfBirth DATE NOT NULL,
    BloodGroup ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
);

-- Subclass 3: Organization
CREATE TABLE IF NOT EXISTS Organization (
    UserID INT PRIMARY KEY,
    OrgName VARCHAR(255) NOT NULL,
    OrgType ENUM('Hospital', 'BloodBank') NOT NULL,
    FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
);

-- Subclass 2A: Donor (Specialization of Person) - Role: Giver
CREATE TABLE IF NOT EXISTS Donor (
    UserID INT PRIMARY KEY,
    AvailabilityStatus BOOLEAN DEFAULT TRUE,
    LastDonationDate DATE,
    FOREIGN KEY (UserID) REFERENCES Person(UserID) ON DELETE CASCADE
);

-- Subclass 2B: Patient (Specialization of Person) - Role: Receiver
CREATE TABLE IF NOT EXISTS Patient (
    UserID INT PRIMARY KEY,
    MedicalCondition TEXT,
    FOREIGN KEY (UserID) REFERENCES Person(UserID) ON DELETE CASCADE
);

-- 6. Composite & Multivalued Attributes
-- Multivalued Attribute: ContactNumber (for Person & Organization)
CREATE TABLE IF NOT EXISTS UserContact (
    UserID INT,
    ContactNumber VARCHAR(20),
    PRIMARY KEY (UserID, ContactNumber),
    FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
);

-- Composite Attribute: Address mapped as separate fields in a dependent table
CREATE TABLE IF NOT EXISTS UserAddress (
    UserID INT PRIMARY KEY,
    Street VARCHAR(255),
    City VARCHAR(100),
    ZIP VARCHAR(20),
    FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
);

-- 7. Weak Entities and Identifying Relationships
-- Weak Entity: MedicalHistory (Depends on Patient)
CREATE TABLE IF NOT EXISTS MedicalHistory (
    PatientID INT,
    RecordNo INT, -- Partial Key
    Description TEXT NOT NULL,
    DateRecorded DATE NOT NULL,
    PRIMARY KEY (PatientID, RecordNo),
    FOREIGN KEY (PatientID) REFERENCES Patient(UserID) ON DELETE CASCADE
);

-- Derived Attributes: FullName, Age
-- We simulate Age calculation via view, we don't store it explicitly.
CREATE OR REPLACE VIEW PersonDetails AS 
SELECT 
    p.UserID, 
    p.FirstName, 
    p.LastName, 
    CONCAT(p.FirstName, ' ', p.LastName) AS FullName, -- Derived Attribute
    p.DateOfBirth,
    p.BloodGroup,
    TIMESTAMPDIFF(YEAR, p.DateOfBirth, CURDATE()) AS Age -- Derived Attribute
FROM Person p;

-- 2. Relationships (1:1, 1:N, M:N)
-- 1:1 Relationship: Donor to HealthScreeningLog
CREATE TABLE IF NOT EXISTS HealthScreeningLog (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    DonorID INT UNIQUE, -- Ensures 1:1 relationship
    ScreeningDate DATE NOT NULL,
    Weight DECIMAL(5,2),
    HemoglobinLevel DECIMAL(4,1),
    BloodPressure VARCHAR(20),
    IsEligible BOOLEAN NOT NULL,
    FOREIGN KEY (DonorID) REFERENCES Donor(UserID) ON DELETE CASCADE
);

-- 1:N Relationship: Organization makes many BloodRequests
CREATE TABLE IF NOT EXISTS BloodRequest (
    RequestID INT AUTO_INCREMENT PRIMARY KEY,
    RequesterID INT NOT NULL, -- The Organization ID or Patient ID
    RequiredBloodGroup ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    Quantity INT NOT NULL, -- in units/ml
    UrgencyLevel ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    Status ENUM('Pending', 'Fulfilled', 'Cancelled') DEFAULT 'Pending',
    RequestDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (RequesterID) REFERENCES User(UserID) ON DELETE CASCADE
);

-- Donation Camp Entity
CREATE TABLE IF NOT EXISTS DonationCamp (
    CampID INT AUTO_INCREMENT PRIMARY KEY,
    CampName VARCHAR(255) NOT NULL,
    Location VARCHAR(255) NOT NULL,
    CampDate DATE NOT NULL
);

-- M:N Relationship: Donor participates in DonationCamp
CREATE TABLE IF NOT EXISTS CampParticipation (
    DonorID INT,
    CampID INT,
    ParticipationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (DonorID, CampID),
    FOREIGN KEY (DonorID) REFERENCES Donor(UserID) ON DELETE CASCADE,
    FOREIGN KEY (CampID) REFERENCES DonationCamp(CampID) ON DELETE CASCADE
);

-- 5. Ternary and higher-arity relationships
-- Ternary Relationship: DonationArrangement links Donor, Patient, and Organization
CREATE TABLE IF NOT EXISTS DonationArrangement (
    ArrangementID INT AUTO_INCREMENT PRIMARY KEY,
    DonorID INT NOT NULL,
    PatientID INT NOT NULL,
    OrgID INT NOT NULL, -- E.g. Hospital where donation occurs
    DonationDate DATE NOT NULL,
    QuantityDonated INT NOT NULL,
    FOREIGN KEY (DonorID) REFERENCES Donor(UserID) ON DELETE CASCADE,
    FOREIGN KEY (PatientID) REFERENCES Patient(UserID) ON DELETE CASCADE,
    FOREIGN KEY (OrgID) REFERENCES Organization(UserID) ON DELETE CASCADE
);
