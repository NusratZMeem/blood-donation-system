-- Sample Data for Blood Donation System
USE blood_donation_system;

-- All demo passwords are 'password123'
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

-- 1. Insert Admins
INSERT INTO User (Email, PasswordHash, Role) VALUES 
('admin@bloodbank.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin');
SET @AdminID = LAST_INSERT_ID();
INSERT INTO Admin (UserID, AdminLevel) VALUES (@AdminID, 1);

-- 2. Insert Hospitals / BloodBanks
INSERT INTO User (Email, PasswordHash, Role) VALUES 
('contact@cityhospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Hospital'),
('redcross@bloodbank.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BloodBank');

SET @HospID = (SELECT UserID FROM User WHERE Email = 'contact@cityhospital.com');
SET @BankID = (SELECT UserID FROM User WHERE Email = 'redcross@bloodbank.org');

INSERT INTO Organization (UserID, OrgName, OrgType) VALUES
(@HospID, 'City General Hospital', 'Hospital'),
(@BankID, 'Red Cross Blood Center', 'BloodBank');

INSERT INTO UserAddress (UserID, Street, City, ZIP) VALUES
(@HospID, '123 Medical Way', 'Metropolis', '10001'),
(@BankID, '456 Donor Ave', 'Metropolis', '10002');

INSERT INTO UserContact (UserID, ContactNumber) VALUES
(@HospID, '800-555-0199'), (@BankID, '800-555-0200');

-- 3. Insert Donors
INSERT INTO User (Email, PasswordHash, Role) VALUES 
('johndoe@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Donor'),
('janedoe@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Donor');

SET @Donor1 = (SELECT UserID FROM User WHERE Email = 'johndoe@example.com');
SET @Donor2 = (SELECT UserID FROM User WHERE Email = 'janedoe@example.com');

INSERT INTO Person (UserID, FirstName, LastName, DateOfBirth, BloodGroup) VALUES
(@Donor1, 'John', 'Doe', '1990-05-15', 'O+'),
(@Donor2, 'Jane', 'Doe', '1995-08-20', 'A-');

INSERT INTO Donor (UserID, AvailabilityStatus, LastDonationDate) VALUES
(@Donor1, TRUE, '2023-01-10'),
(@Donor2, TRUE, NULL);

INSERT INTO UserAddress (UserID, Street, City, ZIP) VALUES
(@Donor1, '789 Elm St', 'Metropolis', '10003'),
(@Donor2, '321 Oak St', 'Gotham', '10004');

INSERT INTO UserContact (UserID, ContactNumber) VALUES
(@Donor1, '555-0101'), (@Donor2, '555-0102');

-- 4. Insert Patients
INSERT INTO User (Email, PasswordHash, Role) VALUES 
('patient1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Patient');

SET @Patient1 = (SELECT UserID FROM User WHERE Email = 'patient1@example.com');

INSERT INTO Person (UserID, FirstName, LastName, DateOfBirth, BloodGroup) VALUES
(@Patient1, 'Bob', 'Smith', '1985-11-30', 'O+');

INSERT INTO Patient (UserID, MedicalCondition) VALUES
(@Patient1, 'Severe Anemia');

INSERT INTO MedicalHistory (PatientID, RecordNo, Description, DateRecorded) VALUES
(@Patient1, 1, 'Diagnosed with Iron Deficiency', '2023-05-01');

-- 5. Blood Requests
INSERT INTO BloodRequest (RequesterID, RequiredBloodGroup, Quantity, UrgencyLevel, Status) VALUES
(@HospID, 'O+', 2, 'High', 'Pending'),
(@Patient1, 'A-', 1, 'Medium', 'Pending');

-- 6. Health Screening Log (1:1 with Donor)
INSERT INTO HealthScreeningLog (DonorID, ScreeningDate, Weight, HemoglobinLevel, BloodPressure, IsEligible) VALUES
(@Donor1, '2023-01-10', 75.5, 14.2, '120/80', TRUE);

-- 7. Donation Camp
INSERT INTO DonationCamp (CampName, Location, CampDate) VALUES
('Winter Blood Drive', 'Metropolis City Hall', '2023-12-01');
SET @Camp1 = LAST_INSERT_ID();

INSERT INTO CampParticipation (DonorID, CampID) VALUES
(@Donor1, @Camp1);

-- 8. Ternary Relationship: Donation Arrangement
INSERT INTO DonationArrangement (DonorID, PatientID, OrgID, DonationDate, QuantityDonated) VALUES
(@Donor1, @Patient1, @HospID, '2023-06-15', 1);
