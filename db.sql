-- Student Attendance Management System Database Schema

-- Create database
CREATE DATABASE IF NOT EXISTS student_attendance_db;
USE student_attendance_db;

-- Users table for common login (students, faculty, HOD, admin)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('student', 'faculty', 'hod', 'admin') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Batch table
CREATE TABLE batch (
    batch_id INT PRIMARY KEY AUTO_INCREMENT,
    batch_name VARCHAR(100) NOT NULL,
    batch_year YEAR NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Semester table
CREATE TABLE semester (
    semester_id INT PRIMARY KEY AUTO_INCREMENT,
    semester_name VARCHAR(50) NOT NULL,
    semester_number INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Programme/Class table
CREATE TABLE programme (
    programme_id INT PRIMARY KEY AUTO_INCREMENT,
    programme_name VARCHAR(100) NOT NULL,
    programme_code VARCHAR(20) UNIQUE NOT NULL,
    duration_years INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Class Section table
CREATE TABLE class_section (
    section_id INT PRIMARY KEY AUTO_INCREMENT,
    section_name VARCHAR(10) NOT NULL,
    programme_id INT,
    batch_id INT,
    semester_id INT,
    capacity INT DEFAULT 60,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (programme_id) REFERENCES programme(programme_id),
    FOREIGN KEY (batch_id) REFERENCES batch(batch_id),
    FOREIGN KEY (semester_id) REFERENCES semester(semester_id)
);

-- Students table (as per AP Unified District Information System for Education)
CREATE TABLE students (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    admission_number VARCHAR(20) UNIQUE NOT NULL,
    roll_number VARCHAR(20),
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    father_name VARCHAR(100) NOT NULL,
    mother_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    category ENUM('general', 'obc', 'sc', 'st', 'other') NOT NULL,
    religion VARCHAR(50),
    caste VARCHAR(50),
    mobile_number VARCHAR(15),
    email VARCHAR(100),
    aadhar_number VARCHAR(12) UNIQUE,
    address TEXT,
    district VARCHAR(50),
    state VARCHAR(50) DEFAULT 'Andhra Pradesh',
    pincode VARCHAR(6),
    programme_id INT,
    batch_id INT,
    semester_id INT,
    section_id INT,
    student_status ENUM('active', 'detained', 'dropout', 'passout', 'transferred') DEFAULT 'active',
    admission_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (programme_id) REFERENCES programme(programme_id),
    FOREIGN KEY (batch_id) REFERENCES batch(batch_id),
    FOREIGN KEY (semester_id) REFERENCES semester(semester_id),
    FOREIGN KEY (section_id) REFERENCES class_section(section_id)
);

-- Faculty/Teachers table
CREATE TABLE faculty (
    faculty_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    designation VARCHAR(100),
    department VARCHAR(100),
    qualification VARCHAR(200),
    experience_years INT,
    mobile_number VARCHAR(15),
    email VARCHAR(100),
    address TEXT,
    date_of_joining DATE,
    faculty_type ENUM('regular', 'guest', 'contract') DEFAULT 'regular',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Subjects table
CREATE TABLE subjects (
    subject_id INT PRIMARY KEY AUTO_INCREMENT,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20) UNIQUE NOT NULL,
    credits INT DEFAULT 3,
    programme_id INT,
    semester_id INT,
    subject_type ENUM('theory', 'practical', 'project') DEFAULT 'theory',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (programme_id) REFERENCES programme(programme_id),
    FOREIGN KEY (semester_id) REFERENCES semester(semester_id)
);

-- Subject allocation to faculty
CREATE TABLE subject_faculty (
    allocation_id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT,
    faculty_id INT,
    section_id INT,
    academic_year VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id),
    FOREIGN KEY (section_id) REFERENCES class_section(section_id)
);

-- Attendance table
CREATE TABLE attendance (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    subject_id INT,
    faculty_id INT,
    attendance_date DATE NOT NULL,
    period_number INT,
    status ENUM('present', 'absent', 'late') NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id)
);

-- Password change log table (for security tracking)
CREATE TABLE password_change_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Insert sample data
-- Sample Users (password is 'password' for all)
INSERT INTO users (username, password, user_type, status) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'),
('hod001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hod', 'active'),
('hod002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hod', 'active'),
('prof001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'active'),
('prof002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'active'),
('lect001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'active'),
('lect002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'active'),
('2024001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('2024002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('2024003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('2024004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('2023001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('2023002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active');

-- Sample batch data
INSERT INTO batch (batch_name, batch_year) VALUES 
('2024-2025', 2024),
('2023-2024', 2023),
('2022-2023', 2022);

-- Sample semester data
INSERT INTO semester (semester_name, semester_number, academic_year) VALUES 
('Semester I', 1, '2024-25'),
('Semester II', 2, '2024-25'),
('Semester III', 3, '2024-25'),
('Semester IV', 4, '2024-25');

-- Sample programme data
INSERT INTO programme (programme_name, programme_code, duration_years) VALUES 
('Bachelor of Computer Applications', 'BCA', 3),
('Bachelor of Commerce', 'BCOM', 3),
('Bachelor of Science', 'BSC', 3),
('Master of Computer Applications', 'MCA', 2);

-- Sample class sections
INSERT INTO class_section (section_name, programme_id, batch_id, semester_id, capacity) VALUES 
('A', 1, 1, 1, 60),
('B', 1, 1, 1, 60),
('A', 2, 1, 1, 50),
('A', 3, 1, 1, 40),
('A', 1, 2, 3, 55),
('B', 1, 2, 3, 55);

-- Sample Faculty Data
INSERT INTO faculty (user_id, employee_id, first_name, middle_name, last_name, designation, department, qualification, experience_years, mobile_number, email, address, date_of_joining, faculty_type, status) VALUES
(4, 'PROF001', 'Dr. Rajesh', 'Kumar', 'Sharma', 'Professor', 'Computer Science', 'Ph.D in Computer Science', 15, '9876543210', 'rajesh.sharma@college.edu', '123 Faculty Colony, Narasapur', '2020-06-15', 'regular', 'active'),
(5, 'PROF002', 'Dr. Priya', 'Devi', 'Patel', 'Associate Professor', 'Commerce', 'Ph.D in Commerce', 12, '9876543211', 'priya.patel@college.edu', '124 Faculty Colony, Narasapur', '2021-07-10', 'regular', 'active'),
(6, 'LECT001', 'Suresh', 'Babu', 'Reddy', 'Assistant Professor', 'Computer Science', 'M.Tech', 8, '9876543212', 'suresh.reddy@college.edu', '125 Faculty Colony, Narasapur', '2022-08-01', 'regular', 'active'),
(7, 'LECT002', 'Lakshmi', '', 'Devi', 'Lecturer', 'Science', 'M.Sc', 5, '9876543213', 'lakshmi.devi@college.edu', '126 Faculty Colony, Narasapur', '2023-01-15', 'contract', 'active');

-- Sample Student Data
INSERT INTO students (user_id, admission_number, roll_number, first_name, middle_name, last_name, father_name, mother_name, date_of_birth, gender, category, religion, caste, mobile_number, email, aadhar_number, address, district, state, pincode, programme_id, batch_id, semester_id, section_id, student_status, admission_date) VALUES
(8, '2024001', 'BCA24001', 'Arjun', 'Kumar', 'Singh', 'Ramesh Singh', 'Sunita Singh', '2005-03-15', 'male', 'general', 'Hindu', 'Rajput', '9123456789', 'arjun.singh@student.edu', '123412341234', 'Village Rampur, Narasapur', 'West Godavari', 'Andhra Pradesh', '534275', 1, 1, 1, 1, 'active', '2024-06-01'),
(9, '2024002', 'BCA24002', 'Priya', 'Rani', 'Sharma', 'Suresh Sharma', 'Meera Sharma', '2005-07-22', 'female', 'obc', 'Hindu', 'Sharma', '9123456790', 'priya.sharma@student.edu', '123412341235', 'Main Road, Narasapur', 'West Godavari', 'Andhra Pradesh', '534275', 1, 1, 1, 1, 'active', '2024-06-01'),
(10, '2024003', 'BCA24003', 'Ravi', 'Teja', 'Patel', 'Mohan Patel', 'Sita Patel', '2005-12-10', 'male', 'sc', 'Hindu', 'Patel', '9123456791', 'ravi.patel@student.edu', '123412341236', 'New Colony, Narasapur', 'West Godavari', 'Andhra Pradesh', '534275', 1, 1, 1, 2, 'active', '2024-06-01'),
(11, '2024004', 'BCOM24001', 'Sneha', 'Kumari', 'Reddy', 'Venkat Reddy', 'Laxmi Reddy', '2005-09-05', 'female', 'general', 'Hindu', 'Reddy', '9123456792', 'sneha.reddy@student.edu', '123412341237', 'Gandhi Nagar, Narasapur', 'West Godavari', 'Andhra Pradesh', '534275', 2, 1, 1, 3, 'active', '2024-06-01'),
(12, '2023001', 'BCA23001', 'Kiran', 'Kumar', 'Yadav', 'Rajesh Yadav', 'Kavita Yadav', '2004-04-18', 'male', 'obc', 'Hindu', 'Yadav', '9123456793', 'kiran.yadav@student.edu', '123412341238', 'Station Road, Narasapur', 'West Godavari', 'Andhra Pradesh', '534275', 1, 2, 3, 5, 'active', '2023-06-01'),
(13, '2023002', 'BCA23002', 'Anjali', 'Devi', 'Gupta', 'Prakash Gupta', 'Rekha Gupta', '2004-11-30', 'female', 'general', 'Hindu', 'Gupta', '9123456794', 'anjali.gupta@student.edu', '123412341239', 'Temple Street, Narasapur', 'West Godavari', 'Andhra Pradesh', '534275', 1, 2, 3, 5, 'active', '2023-06-01');

-- Sample Subjects
INSERT INTO subjects (subject_name, subject_code, credits, programme_id, semester_id, subject_type, status) VALUES
('Programming Fundamentals', 'CS101', 4, 1, 1, 'theory', 'active'),
('Computer Fundamentals', 'CS102', 3, 1, 1, 'theory', 'active'),
('Mathematics-I', 'MATH101', 4, 1, 1, 'theory', 'active'),
('English Communication', 'ENG101', 3, 1, 1, 'theory', 'active'),
('Programming Lab', 'CS103', 2, 1, 1, 'practical', 'active'),
('Data Structures', 'CS201', 4, 1, 3, 'theory', 'active'),
('Database Management', 'CS202', 4, 1, 3, 'theory', 'active'),
('Web Technologies', 'CS203', 3, 1, 3, 'theory', 'active'),
('Financial Accounting', 'ACC101', 4, 2, 1, 'theory', 'active'),
('Business Mathematics', 'MATH201', 3, 2, 1, 'theory', 'active'),
('Business Economics', 'ECO101', 4, 2, 1, 'theory', 'active');

-- Sample Subject-Faculty Assignments
INSERT INTO subject_faculty (subject_id, faculty_id, section_id, academic_year, status) VALUES
(1, 1, 1, '2024-25', 'active'),
(1, 1, 2, '2024-25', 'active'),
(2, 3, 1, '2024-25', 'active'),
(2, 3, 2, '2024-25', 'active'),
(3, 4, 1, '2024-25', 'active'),
(4, 4, 1, '2024-25', 'active'),
(5, 3, 1, '2024-25', 'active'),
(6, 1, 5, '2024-25', 'active'),
(7, 3, 5, '2024-25', 'active'),
(8, 3, 5, '2024-25', 'active'),
(9, 2, 3, '2024-25', 'active'),
(10, 2, 3, '2024-25', 'active'),
(11, 2, 3, '2024-25', 'active');

-- Sample Attendance Records
INSERT INTO attendance (student_id, subject_id, faculty_id, attendance_date, period_number, status, remarks) VALUES
-- For student Arjun (student_id = 1)
(1, 1, 1, '2024-11-01', 1, 'present', ''),
(1, 2, 3, '2024-11-01', 2, 'present', ''),
(1, 3, 4, '2024-11-01', 3, 'absent', 'Sick'),
(1, 1, 1, '2024-11-02', 1, 'present', ''),
(1, 2, 3, '2024-11-02', 2, 'late', 'Traffic jam'),
(1, 3, 4, '2024-11-02', 3, 'present', ''),

-- For student Priya (student_id = 2)
(2, 1, 1, '2024-11-01', 1, 'present', ''),
(2, 2, 3, '2024-11-01', 2, 'present', ''),
(2, 3, 4, '2024-11-01', 3, 'present', ''),
(2, 1, 1, '2024-11-02', 1, 'present', ''),
(2, 2, 3, '2024-11-02', 2, 'present', ''),
(2, 3, 4, '2024-11-02', 3, 'present', ''),

-- For student Ravi (student_id = 3)
(3, 1, 1, '2024-11-01', 1, 'absent', 'Family function'),
(3, 2, 3, '2024-11-01', 2, 'present', ''),
(3, 3, 4, '2024-11-01', 3, 'present', ''),
(3, 1, 1, '2024-11-02', 1, 'present', ''),
(3, 2, 3, '2024-11-02', 2, 'present', ''),
(3, 3, 4, '2024-11-02', 3, 'late', 'Bus delay'),

-- For student Sneha (student_id = 4) - Commerce student
(4, 9, 2, '2024-11-01', 1, 'present', ''),
(4, 10, 2, '2024-11-01', 2, 'present', ''),
(4, 11, 2, '2024-11-01', 3, 'present', ''),
(4, 9, 2, '2024-11-02', 1, 'present', ''),
(4, 10, 2, '2024-11-02', 2, 'absent', 'Medical appointment'),
(4, 11, 2, '2024-11-02', 3, 'present', ''),

-- For senior students Kiran and Anjali (3rd semester)
(5, 6, 1, '2024-11-01', 1, 'present', ''),
(5, 7, 3, '2024-11-01', 2, 'present', ''),
(5, 8, 3, '2024-11-01', 3, 'present', ''),
(6, 6, 1, '2024-11-01', 1, 'present', ''),
(6, 7, 3, '2024-11-01', 2, 'present', ''),
(6, 8, 3, '2024-11-01', 3, 'late', 'Hostel late pass');