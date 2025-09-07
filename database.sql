CREATE TABLE Admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('owner', 'manager') NOT NULL
);

CREATE TABLE Owner (
    owner_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNIQUE,
    FOREIGN KEY (admin_id) REFERENCES Admin(admin_id) ON DELETE CASCADE
);

CREATE TABLE Station (
    station_id INT AUTO_INCREMENT PRIMARY KEY,
    station_name VARCHAR(100) NOT NULL,
    owner_id INT NULL,
    type_of_fuel VARCHAR(50),
    location VARCHAR(255),
    capacity INT NOT NULL,
    fuel_available ENUM('yes', 'no') DEFAULT 'yes',
    gas_available ENUM('yes', 'no') DEFAULT 'yes',
    safety_review TEXT,
    station_status ENUM('on', 'off') DEFAULT 'on',
    service_count INT DEFAULT 0,
    fuel_amount DECIMAL(10,2) DEFAULT 0.00,
    gas_amount DECIMAL(10,2) DEFAULT 0.00,
    cash_amount DECIMAL(10,2) DEFAULT 0.00,
    employee_count INT DEFAULT 0,
    total_sale DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (owner_id) REFERENCES Owner(owner_id) ON DELETE SET NULL
);
ALTER TABLE Station
ADD COLUMN octane_available ENUM('yes', 'no') DEFAULT 'yes',
ADD COLUMN diesel_available ENUM('yes', 'no') DEFAULT 'yes',
ADD COLUMN petrol_available ENUM('yes', 'no') DEFAULT 'yes',
ADD COLUMN octane_amount DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN diesel_amount DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN petrol_amount DECIMAL(10,2) DEFAULT 0.00;

CREATE TABLE Manager (
    manager_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNIQUE,
    station_id INT,
    FOREIGN KEY (admin_id) REFERENCES Admin(admin_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES Station(station_id) ON DELETE CASCADE
);

CREATE TABLE Customer (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20) UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE Service (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    station_id INT,
    service_date DATE,
    service_time TIME,
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    pre_booking ENUM('yes', 'no') DEFAULT 'no',
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES Station(station_id) ON DELETE CASCADE
);

CREATE TABLE Review (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    station_id INT,
    review_text TEXT,
    rating INT CHECK(rating BETWEEN 1 AND 5),
    review_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES Station(station_id) ON DELETE CASCADE
);

CREATE TABLE Product (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT,
    product_type VARCHAR(50),
    product_price DECIMAL(10,2),
    FOREIGN KEY (station_id) REFERENCES Station(station_id) ON DELETE CASCADE
);

CREATE TABLE Food_Corner (
    food_id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT,
    dry_food ENUM('yes', 'no') DEFAULT 'no',
    set_menu ENUM('yes', 'no') DEFAULT 'no',
    drinks ENUM('yes', 'no') DEFAULT 'no',
    FOREIGN KEY (station_id) REFERENCES Station(station_id) ON DELETE CASCADE
);

CREATE TABLE Booking (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    station_id INT,
    booking_type ENUM('fuel', 'gas', 'food') NOT NULL,
    fuel_type ENUM('octane', 'diesel', 'petrol', 'none') DEFAULT 'none',
    quantity DECIMAL(10,2),
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    total_amount DECIMAL(10,2),
    special_requests TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES Station(station_id) ON DELETE CASCADE
);
