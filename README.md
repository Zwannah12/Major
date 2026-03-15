# AgroSphere MarketLink - Agricultural Marketplace

AgroSphere MarketLink is an MVP (Minimum Viable Product) for an agricultural marketplace platform designed to connect farmers, buyers, and transporters in Rwanda. The platform aims to reduce food waste and increase farmer income by facilitating direct sales and efficient logistics.

## Features

### Authentication System
*   User Registration (Farmer, Buyer, Transporter roles)
*   User Login
*   Secure password hashing
*   PHP session management

### Farmer Features
*   Dashboard for managing crop listings
*   Add, edit, and delete crop listings
*   Upload crop images
*   View orders from buyers

### Buyer Features
*   Browse and search for available crops
*   Filter crops by type and location
*   View farmer profiles (implicit via crop listings)
*   Place orders for crops

### Transporter Features
*   View available delivery requests
*   Accept delivery requests
*   Update delivery status for assigned orders

### Admin Features
*   Dashboard for overall platform management
*   View and manage all users (update roles, delete users)
*   Manage all crop listings (delete)
*   Monitor all orders (update status)
*   View platform statistics with interactive charts (Crop sales per month, Orders per region, Top selling crops)

### Flashy Dashboard Requirements & Extra Visual Features
*   Modern, responsive design with Bootstrap 5
*   Animated statistic cards on dashboards
*   Charts using Chart.js for data visualization
*   Gradient backgrounds and animated hover effects
*   Sidebar navigation menu
*   Notification alerts for success/error messages

## Tech Stack

*   **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5, Chart.js, FontAwesome 6
*   **Backend:** PHP (structured in a basic MVC-like pattern)
*   **Database:** MySQL
*   **Server:** Apache (XAMPP compatible)

## Project Structure

```
agrospheremarketlink/
├── assets/
│   ├── css/
│   │   └── style.css           # Custom CSS styles
│   ├── js/
│   │   ├── jquery-3.6.0.min.js # jQuery library
│   │   └── main.js             # Custom JavaScript for animations, etc.
│   └── images/
│       ├── crops/              # Uploaded crop images
│       ├── default_crop.jpg    # Placeholder image
│       └── user_avatar.png     # Placeholder image
├── config/                     # Placeholder for configuration files (e.g., mail settings)
├── dashboard/
│   ├── admin.php               # Admin dashboard
│   ├── buyer.php               # Buyer dashboard
│   ├── farmer.php              # Farmer dashboard
│   └── transporter.php         # Transporter dashboard
├── includes/
│   ├── db.php                  # Database connection
│   └── functions.php           # Common utility functions
├── index.php                   # Landing page
├── login.php                   # User login page
├── logout.php                  # User logout script
├── marketplace.php             # Crop marketplace browsing page
├── register.php                # User registration page
└── database.sql                # SQL script for database setup and sample data
```

## Setup Instructions (Using XAMPP)

Follow these steps to get AgroSphere MarketLink running on your local machine using XAMPP.

### 1. Install XAMPP
If you don't have XAMPP installed, download and install it from the official Apache Friends website: [https://www.apachefriends.org/index.html](https://www.apachefriends.org/index.html)

### 2. Place Project Files
*   Locate your XAMPP installation directory (e.g., `C:\xampp` on Windows, `/Applications/XAMPP/xamppfiles` on macOS).
*   Navigate to the `htdocs` folder within your XAMPP directory (e.g., `C:\xampp\htdocs`).
*   Create a new folder named `agrospheremarketlink` inside `htdocs`.
*   Copy all the project files (the entire `agrospheremarketlink` folder provided) into this newly created `agrospheremarketlink` folder.

    So your path should look like: `C:\xampp\htdocs\agrospheremarketlink\...`

### 3. Start Apache and MySQL
*   Open the XAMPP Control Panel.
*   Start the Apache module.
*   Start the MySQL module.

### 4. Create and Populate the Database
*   Open your web browser and go to `http://localhost/phpmyadmin/`.
*   In phpMyAdmin, click on "New" (or "Databases" tab and create new database).
*   Create a new database named `agrospheremarketlink`.
*   Select the `agrospheremarketlink` database from the left sidebar.
*   Click on the "SQL" tab.
*   Open the `database.sql` file from your project directory (`agrospheremarketlink/database.sql`) with a text editor.
*   Copy all the SQL content from `database.sql`.
*   Paste the copied SQL content into the SQL query window in phpMyAdmin.
*   Click the "Go" button to execute the queries. This will create the necessary tables and populate them with sample data.

### 5. Access the Application
*   Open your web browser and navigate to: `http://localhost/agrospheremarketlink/`
*   You should see the landing page of AgroSphere MarketLink.

### 6. Sample Test Data

The `database.sql` file contains the following sample users. All passwords are `password123`.

| Role        | Name            | Email                      | Password    |
| :---------- | :-------------- | :------------------------- | :---------- |
| Admin       | Admin User      | admin@agrosphere.com       | `password123` |
| Farmer      | Farmer John     | john@example.com           | `password123` |
| Buyer       | Buyer Alice     | alice@example.com          | `password123` |
| Transporter | Transporter Bob | bob@example.com            | `password123` |
| Farmer      | Farmer Jane     | jane@example.com           | `password123` |

You can use these credentials to log in and test different user roles.

## Security Notes

*   **Password Hashing:** Passwords are hashed using `PASSWORD_DEFAULT` (Bcrypt) before storing them in the database.
*   **Prepared Statements:** All database interactions use prepared statements to prevent SQL injection vulnerabilities.
*   **Input Sanitation:** User inputs are sanitized using `htmlspecialchars()` and `trim()` to prevent XSS attacks.
*   **Session Protection:** Basic session management is implemented.

## Missing Features / Future Enhancements (MVP Scope)

This MVP focuses on core functionality. Potential future enhancements include:
*   **Advanced User Profiles:** More detailed profiles for each role.
*   **Messaging System:** Direct messaging between users.
*   **Ratings/Reviews:** System for buyers to rate farmers/transporters.
*   **Real-time Notifications:** WebSocket integration for instant updates.
*   **Payment Gateway Integration:** Online payment processing.
*   **Google Maps Integration:** For location-based services and delivery tracking.
*   **More Robust Image Handling:** Dedicated image service, optimized serving.
*   **Admin Approvals:** Farmers could require admin approval to list crops.
*   **Reporting Tools:** More detailed reports for admin and farmers.
*   **Complex Filtering:** More sophisticated filtering for marketplace.
*   **Loading Animations:** For AJAX requests and page transitions.
*   **Password Reset Functionality.**

## Important Note on Image Uploads

For image uploads to work, ensure the `agrospheremarketlink/assets/images/crops/` directory has write permissions for the web server (Apache). On some systems, you might need to manually set these permissions.
