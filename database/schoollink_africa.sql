-- SchoolLink Africa Database Schema
-- Created: November 2, 2025

SET FOREIGN_KEY_CHECKS = 0;

-- Drop all tables in reverse dependency order (child tables first)
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS message_threads;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS connections;
DROP TABLE IF EXISTS opportunity_interests;
DROP TABLE IF EXISTS opportunities;
DROP TABLE IF EXISTS event_rsvps;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS post_likes;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS join_requests;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS schools;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'school_admin', 'student') NOT NULL DEFAULT 'student',
    school_id INT NULL,
    year_group VARCHAR(20) NULL,
    graduation_year VARCHAR(4) NULL,
    status ENUM('Graduated', 'Left', 'Current Student') NULL,
    student_id VARCHAR(50) NULL,
    current_occupation VARCHAR(200) NULL,
    industry VARCHAR(100) NULL,
    location VARCHAR(100) NULL,
    bio TEXT NULL,
    linkedin_profile VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    skills TEXT NULL,
    interests TEXT NULL,
    approved BOOLEAN DEFAULT FALSE,
    profile_photo VARCHAR(255) NULL,
    reset_token VARCHAR(64) NULL,
    reset_token_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_school_id (school_id),
    INDEX idx_role (role),
    INDEX idx_approved (approved)
);

-- Schools table
CREATE TABLE schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    location VARCHAR(200) NOT NULL,
    logo VARCHAR(255) NULL,
    contact_email VARCHAR(100) NOT NULL,
    website VARCHAR(255) NULL,
    description TEXT NULL,
    approved BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_approved (approved),
    INDEX idx_status (status)
);

-- Posts table
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    author_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    post_type ENUM('update', 'event', 'opportunity') DEFAULT 'update',
    event_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_school_id (school_id),
    INDEX idx_created_at (created_at)
);

-- Join requests table
CREATE TABLE join_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    school_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_school (user_id, school_id),
    INDEX idx_status (status),
    INDEX idx_school_id (school_id)
);

-- Comments table for posts
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id)
);

-- Insert default super admin (password: admin123)
INSERT INTO users (name, email, password, role, approved) VALUES 
('Super Administrator', 'admin@schoollink.africa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', TRUE);

-- Sample schools for testing
INSERT INTO schools (name, location, contact_email, website, description, approved, status) VALUES 
('Lagos State Model College', 'Kankon, Lagos State', 'admin@lagosmodel.edu.ng', 'https://lagosmodel.edu.ng', 'Premier secondary school in Lagos State, established in 1958. Known for academic excellence and producing leaders across various fields.', TRUE, 'active'),
('Government Secondary School Maitama', 'Maitama, FCT Abuja', 'info@gssmaitama.edu.ng', 'https://gssmaitama.edu.ng', 'Government secondary school located in the heart of Abuja, serving students from diverse backgrounds with quality education.', TRUE, 'active'),
('Loyola Jesuit College', 'Gidan Mangoro, FCT Abuja', 'office@loyolajesuit.org', 'https://loyolajesuit.org', 'Catholic Jesuit school committed to academic excellence and character formation, founded on Ignatian spirituality and values.', TRUE, 'active');

-- Sample school admin users (password: password123)
INSERT INTO users (name, email, password, role, school_id, approved) VALUES 
('John Adebayo', 'admin@lagosmodel.edu.ng', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'school_admin', 1, TRUE),
('Mary Okafor', 'admin@gssmaitama.edu.ng', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'school_admin', 2, TRUE),
('David Okechukwu', 'admin@loyolajesuit.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'school_admin', 3, TRUE);

-- Sample student users (password: student123)
INSERT INTO users (name, email, password, role, school_id, year_group, graduation_year, status, current_occupation, industry, location, bio, linkedin_profile, phone, skills, interests, approved) VALUES 
('Alice Johnson', 'alice@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1, '2020', '2020', 'Graduated', 'Software Engineer', 'Technology', 'Lagos, Nigeria', 'Passionate software developer with expertise in web technologies. Love connecting with fellow alumni and sharing opportunities.', 'https://linkedin.com/in/alicejohnson', '+234-801-234-5678', 'JavaScript, Python, React, Node.js', 'Technology, Innovation, Mentoring', TRUE),
('Bob Smith', 'bob@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1, '2021', NULL, 'Current Student', NULL, NULL, 'Lagos, Nigeria', 'Final year student studying Computer Science. Excited to graduate and join the workforce!', NULL, '+234-802-345-6789', 'Java, Python, Database Design', 'Programming, Gaming, Sports', TRUE),
('Carol Davis', 'carol@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 2, '2019', '2019', 'Graduated', 'Marketing Manager', 'Marketing & Advertising', 'Abuja, Nigeria', 'Marketing professional with 5+ years experience. Always happy to help fellow alumni with career advice.', 'https://linkedin.com/in/caroldavis', '+234-803-456-7890', 'Digital Marketing, Brand Management, Analytics', 'Marketing, Business Development, Networking', TRUE),
('Daniel Wilson', 'daniel@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 3, '2022', NULL, 'Current Student', NULL, NULL, 'Abuja, Nigeria', 'Studying Business Administration. Interested in entrepreneurship and startups.', NULL, '+234-804-567-8901', 'Business Analysis, Project Management', 'Entrepreneurship, Leadership, Community Service', TRUE);

-- Sample posts
INSERT INTO posts (school_id, author_id, title, content, post_type, event_date) VALUES 
(1, 2, 'Alumni Homecoming 2024', 'We are excited to announce our annual Alumni Homecoming event scheduled for December 15th, 2024. Join us for a day of reconnection, networking, and celebration!', 'event', '2024-12-15'),
(1, 2, 'Career Opportunity: Software Developer', 'A tech startup is looking for talented software developers. Great opportunity for recent graduates in Computer Science or related fields.', 'opportunity', NULL),
(2, 3, 'School Updates: New Library Opening', 'Our new state-of-the-art library will be officially opened next month. It features modern study spaces and digital resources.', 'update', NULL),
(3, 4, 'Class of 2019 Reunion', 'Calling all Class of 2019 graduates! Let us plan a reunion to celebrate our 5th year after graduation.', 'event', '2024-11-30');

-- Sample comments
INSERT INTO comments (post_id, user_id, content, created_at) VALUES
(1, 5, 'Great initiative! Looking forward to this event.', '2024-01-16 10:30:00'),
(1, 6, 'Count me in! Will definitely attend.', '2024-01-16 11:15:00'),
(2, 6, 'Thanks for sharing this opportunity.', '2024-01-17 09:45:00'),
(4, 5, 'Congratulations to all graduates!', '2024-01-18 14:20:00');

-- Table for post likes
CREATE TABLE post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_post_like (post_id, user_id)
);

-- Sample post likes
INSERT INTO post_likes (post_id, user_id, created_at) VALUES
(1, 5, '2024-01-16 10:31:00'),
(1, 6, '2024-01-16 11:16:00'),
(2, 6, '2024-01-17 09:46:00'),
(4, 2, '2024-01-18 08:30:00'),
(4, 5, '2024-01-18 14:21:00');

-- Events table
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    event_type ENUM('general', 'reunion', 'networking', 'career_fair', 'webinar', 'fundraising') DEFAULT 'general',
    event_date DATETIME NOT NULL,
    location TEXT NULL,
    max_attendees INT NULL,
    registration_required BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'cancelled', 'completed') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_school_id (school_id),
    INDEX idx_event_date (event_date),
    INDEX idx_status (status)
);

-- Event RSVPs table
CREATE TABLE event_rsvps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('attending', 'not_attending', 'maybe') DEFAULT 'attending',
    additional_info TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_event_rsvp (event_id, user_id),
    INDEX idx_event_id (event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Sample events
INSERT INTO events (school_id, title, description, event_type, event_date, location, registration_required, created_by) VALUES
(1, 'Annual Alumni Reunion 2025', 'Join us for our biggest alumni gathering of the year! Reconnect with old friends, share memories, and celebrate our shared history at Lagos State Model College.', 'reunion', '2025-12-15 10:00:00', 'Lagos State Model College Main Hall', TRUE, 2),
(1, 'Career Networking Evening', 'Professional networking event for alumni and current students. Industry leaders will share insights and opportunities.', 'networking', '2025-11-20 18:00:00', 'Victoria Island Conference Center, Lagos', TRUE, 2),
(2, 'Class of 2020 Mini Reunion', 'Special gathering for the Class of 2020 to celebrate 5 years since graduation.', 'reunion', '2025-12-01 14:00:00', 'GSS Maitama School Grounds', FALSE, 3),
(3, 'Virtual Career Fair 2025', 'Connect with potential employers and explore career opportunities in this virtual event.', 'career_fair', '2025-11-25 09:00:00', 'Online via Zoom', TRUE, 4);

-- Sample event RSVPs
INSERT INTO event_rsvps (event_id, user_id, status) VALUES
(1, 5, 'attending'),
(1, 6, 'attending'),
(2, 5, 'maybe'),
(3, 7, 'attending'),
(4, 8, 'attending'),
(4, 5, 'not_attending');

-- Create opportunities table
CREATE TABLE opportunities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    opportunity_type ENUM('job', 'internship', 'scholarship', 'mentorship', 'volunteer') NOT NULL DEFAULT 'job',
    location VARCHAR(200),
    salary_range VARCHAR(100),
    requirements TEXT,
    application_process TEXT,
    contact_email VARCHAR(100),
    deadline DATE,
    school_id INT NOT NULL,
    posted_by INT NOT NULL,
    status ENUM('active', 'closed', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Create opportunity_interests table (for tracking user interest in opportunities)
CREATE TABLE opportunity_interests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    opportunity_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_interest (opportunity_id, user_id)
);

-- Sample opportunities data
INSERT INTO opportunities (title, description, company_name, opportunity_type, location, salary_range, requirements, application_process, contact_email, deadline, school_id, posted_by) VALUES
('Software Developer - Full Stack', 'Join our growing tech team as a Full Stack Developer. You will work with modern technologies including React, Node.js, and cloud platforms to build scalable web applications.', 'TechHub Nigeria', 'job', 'Lagos, Nigeria', '₦2,500,000 - ₦4,000,000 annually', 'Bachelor\'s degree in Computer Science or related field\n3+ years experience with JavaScript frameworks\nExperience with cloud platforms (AWS/Azure)\nStrong problem-solving skills', 'Send your resume and portfolio to careers@techhub.ng\nInclude \"Full Stack Developer\" in the subject line', 'careers@techhub.ng', '2025-12-31', 1, 2),

('Data Science Internship Program', 'Exciting 6-month internship opportunity for students interested in data science and machine learning. Work on real projects with mentorship from senior data scientists.', 'DataCorp Analytics', 'internship', 'Remote/Lagos', '₦150,000 monthly stipend', 'Currently pursuing degree in Computer Science, Statistics, or related field\nBasic knowledge of Python and SQL\nFamiliarity with data analysis libraries (pandas, numpy)\nStrong analytical mindset', 'Apply through our website: datacorp.ng/internships\nSubmit academic transcripts and a cover letter', 'internships@datacorp.ng', '2025-11-30', 1, 2),

('Merit-Based Engineering Scholarship', 'Full scholarship covering tuition and living expenses for outstanding students pursuing engineering degrees at top universities.', 'Future Engineers Foundation', 'scholarship', 'Various Universities', 'Full tuition + ₦200,000 annual stipend', 'Minimum GPA of 3.5\nDemonstrated financial need\nActive participation in community service\nIntention to study engineering', 'Complete online application at futureengineers.org\nProvide academic records, recommendation letters, and personal statement', 'scholarships@futureengineers.org', '2025-10-15', 1, 2),

('Marketing Manager Position', 'Lead marketing initiatives for a fast-growing fintech startup. Drive customer acquisition and brand awareness through innovative campaigns.', 'PayMax Solutions', 'job', 'Abuja, Nigeria', '₦3,000,000 - ₦5,000,000 annually', 'Bachelor\'s degree in Marketing or Business\n5+ years marketing experience\nExperience in fintech or financial services\nDigital marketing expertise\nTeam leadership skills', 'Email resume to hr@paymax.ng\nInclude examples of successful marketing campaigns', 'hr@paymax.ng', '2025-12-15', 2, 3),

('Mentorship Program - Career Guidance', 'Connect with experienced professionals for career guidance and industry insights. Available for recent graduates and career changers.', 'Professional Mentors Network', 'mentorship', 'Virtual/In-person options', 'Free program', 'Recent graduates or early-career professionals\nCommitment to 6-month mentorship program\nWillingness to attend monthly sessions\nClear career goals and objectives', 'Register at mentorsnetwork.ng\nComplete mentee application and career assessment', 'program@mentorsnetwork.ng', '2025-11-20', 2, 3),

('Community Health Volunteer Program', 'Make a difference in underserved communities by volunteering in health education and awareness programs.', 'Health for All Initiative', 'volunteer', 'Various Communities in FCT', 'Volunteer position (transport reimbursed)', 'Passion for community service\nBasic health knowledge preferred\nGood communication skills\nWeekend availability\nCommitment to 3-month program', 'Apply through healthforall.org/volunteer\nAttend orientation session before starting', 'volunteers@healthforall.org', '2025-10-30', 2, 3),

('Junior Frontend Developer', 'Entry-level position perfect for recent graduates. Join our creative team building user interfaces for mobile and web applications.', 'Creative Digital Agency', 'job', 'Lagos, Nigeria', '₦1,800,000 - ₦2,500,000 annually', 'Degree in Computer Science or related field\nProficiency in HTML, CSS, JavaScript\nExperience with React or Vue.js\nUnderstanding of responsive design\nPortfolio of web projects', 'Send portfolio and resume to jobs@creativedigital.ng', 'jobs@creativedigital.ng', '2025-11-25', 3, 4),

('Research Scholarship - Environmental Science', 'Funded research opportunity for graduate students focusing on climate change and environmental sustainability in West Africa.', 'West Africa Research Institute', 'scholarship', 'University of Lagos/Remote fieldwork', 'Full funding + research allowance', 'Graduate student in Environmental Science or related field\nResearch proposal on climate change\nPrevious research experience\nStrong academic record', 'Submit research proposal and academic documents to research@wari.org', 'research@wari.org', '2025-09-30', 3, 4);

-- Sample opportunity interests
INSERT INTO opportunity_interests (opportunity_id, user_id) VALUES
(1, 5), (1, 6), (2, 7), (3, 8), (4, 5), (5, 6), (6, 7), (7, 8), (8, 5);

-- Create connections table for alumni networking
CREATE TABLE connections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    connected_user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (connected_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_connection (user_id, connected_user_id)
);

-- Sample connection data
INSERT INTO connections (user_id, connected_user_id, status) VALUES
(5, 6, 'accepted'),
(7, 8, 'accepted'),
(5, 7, 'pending'),
(6, 8, 'pending');

-- Create messages table for alumni messaging system
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    deleted_by_sender BOOLEAN DEFAULT FALSE,
    deleted_by_recipient BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sender (sender_id),
    INDEX idx_recipient (recipient_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Create message_threads table for grouping related messages
CREATE TABLE message_threads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    participant_1 INT NOT NULL,
    participant_2 INT NOT NULL,
    last_message_id INT NULL,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (participant_1) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_2) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (last_message_id) REFERENCES messages(id) ON DELETE SET NULL,
    UNIQUE KEY unique_thread (participant_1, participant_2),
    INDEX idx_last_message_at (last_message_at)
);

-- Sample messages data
INSERT INTO messages (sender_id, recipient_id, subject, message, is_read) VALUES
(5, 6, 'Welcome to the platform!', 'Hi! Great to see you here. Let\'s stay connected!', TRUE),
(6, 5, 'Re: Welcome to the platform!', 'Thanks! Yes, it\'s great to reconnect with everyone from our class.', TRUE),
(5, 7, 'Networking opportunity', 'I saw you work in tech. I\'m looking to transition into the field. Would love to chat!', FALSE),
(7, 8, 'Event planning', 'Hey! Are you interested in helping organize the upcoming reunion event?', TRUE),
(8, 7, 'Re: Event planning', 'Absolutely! I\'d love to help. When are we meeting to discuss?', FALSE);

-- E-commerce: Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    INDEX idx_school_id (school_id)
);

-- E-commerce: Brands table
CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    logo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    INDEX idx_school_id (school_id)
);

-- E-commerce: Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    category_id INT NULL,
    brand_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_path VARCHAR(255) NULL,
    keywords VARCHAR(255) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    INDEX idx_school_id (school_id),
    INDEX idx_category_id (category_id),
    INDEX idx_brand_id (brand_id),
    INDEX idx_status (status)
);

-- Orders table for e-commerce system
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    school_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NULL,
    delivery_address TEXT NULL,
    order_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50) NULL,
    payment_reference VARCHAR(100) NULL,
    notes TEXT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_school_id (school_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_order_date (order_date)
);

-- Order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_title VARCHAR(200) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id)
);

SET FOREIGN_KEY_CHECKS = 1;