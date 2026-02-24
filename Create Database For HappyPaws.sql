-- 1. Create Database
CREATE DATABASE IF NOT EXISTS happypawsvet_V2;



USE happypawsvet_V2;

-- 2. Staff Table
CREATE TABLE staff (
    Staff_ID INT AUTO_INCREMENT PRIMARY KEY,
    Staff_Fname VARCHAR(50) NOT NULL,
    Staff_Lname VARCHAR(50) NOT NULL,
    Staff_Email VARCHAR(100) UNIQUE NOT NULL,
    Password VARCHAR(45) NOT NULL
);

-- 3. Owner Table (Original version)
CREATE TABLE owner (
    Owner_ID INT AUTO_INCREMENT PRIMARY KEY,
    Owner_Fname VARCHAR(50) NOT NULL,
    Owner_Lname VARCHAR(50) NOT NULL,
    Email VARCHAR(100) UNIQUE, 
    Phone VARCHAR(11) UNIQUE NOT NULL,
    Password VARCHAR(45) NOT NULL,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Pet Table
CREATE TABLE pet (
    Pet_ID INT AUTO_INCREMENT PRIMARY KEY,
    Owner_ID INT NOT NULL,
    Pet_Name VARCHAR(50) NOT NULL,
    Pet_Type VARCHAR(30) NOT NULL, 
    Breed VARCHAR(50),
    Age INT, -- Fixed to match medical_history logic
    FOREIGN KEY (Owner_ID) REFERENCES owner(Owner_ID) ON DELETE CASCADE
);

-- 6. Inventory & Categories
CREATE TABLE categories (
    Category_ID INT AUTO_INCREMENT PRIMARY KEY,
    Category_Name VARCHAR(50) NOT NULL
);

CREATE TABLE inventory (
    Item_ID INT AUTO_INCREMENT PRIMARY KEY,
    Item_Name VARCHAR(100) NOT NULL,
    Category_ID INT NOT NULL, -- Keep this: Every item MUST have a category
    Price_Per_Unit DECIMAL(10,2) NOT NULL,
    Min_Stock_Level INT DEFAULT 5,
    Staff_ID INT,
    FOREIGN KEY (Category_ID) REFERENCES categories(Category_ID),
    FOREIGN KEY (Staff_ID) REFERENCES staff(Staff_ID) ON DELETE SET NULL 
);

CREATE TABLE stock (
    Stock_ID INT AUTO_INCREMENT PRIMARY KEY,
    Item_ID INT NOT NULL,
    Current_Stock INT NOT NULL,
    FOREIGN KEY (Item_ID) REFERENCES inventory(Item_ID) ON DELETE CASCADE
);

-- 7. Service & Vet Setup
CREATE TABLE service_type (
    Service_ID INT AUTO_INCREMENT PRIMARY KEY,
    Service_Type VARCHAR(100) NOT NULL,
    Base_Price DECIMAL(10,2) NOT NULL 
);

CREATE TABLE vet (
    Vet_ID INT AUTO_INCREMENT PRIMARY KEY,
    Vet_Fname VARCHAR(50) NOT NULL,
    Vet_Lname VARCHAR(50) NOT NULL
);

CREATE TABLE vet_schedule (
    Schedule_ID INT AUTO_INCREMENT PRIMARY KEY,
    Vet_ID INT NOT NULL,
    Day_of_Week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    Start_Time TIME NOT NULL,
    End_Time TIME NOT NULL,
    FOREIGN KEY (Vet_ID) REFERENCES vet(Vet_ID) ON DELETE CASCADE
);

-- 8. Appointment & Payment
CREATE TABLE appointment (
    Appointment_ID INT AUTO_INCREMENT PRIMARY KEY,
    Owner_ID INT NOT NULL,
    Pet_ID INT NOT NULL, 
    Service_ID INT NOT NULL,
    Vet_ID INT NOT NULL,
    Staff_ID INT, -- Added to track which staff managed the booking
    Appointment_Date DATETIME NOT NULL,
    Status ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending',
    Notes TEXT, 
    FOREIGN KEY (Owner_ID) REFERENCES owner(Owner_ID),
    FOREIGN KEY (Pet_ID) REFERENCES pet(Pet_ID),
    FOREIGN KEY (Service_ID) REFERENCES service_type(Service_ID),
    FOREIGN KEY (Vet_ID) REFERENCES vet(Vet_ID),
    -- New Foreign Key linking to the staff table
    FOREIGN KEY (Staff_ID) REFERENCES staff(Staff_ID) ON DELETE SET NULL
);

CREATE TABLE payment (
    Payment_ID INT AUTO_INCREMENT PRIMARY KEY,
    Appointment_ID INT NULL, 
    Amount_Paid DECIMAL(10,2) NOT NULL,
    Payment_Method ENUM('Cash', 'E-Wallet', 'Card') NOT NULL, 
    Payment_Date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Appointment_ID) REFERENCES appointment(Appointment_ID) ON DELETE CASCADE
);

-- Create a dedicated table for Clinical Records
CREATE TABLE medical_history (
    History_ID INT AUTO_INCREMENT PRIMARY KEY,
    Pet_ID INT NOT NULL,
    Appointment_ID INT NOT NULL,
    Diagnosis TEXT NOT NULL,
    Date_Recorded TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Pet_ID) REFERENCES pet(Pet_ID) ON DELETE CASCADE,
    FOREIGN KEY (Appointment_ID) REFERENCES appointment(Appointment_ID) ON DELETE CASCADE
);