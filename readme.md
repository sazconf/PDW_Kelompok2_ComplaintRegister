Complaint Register System

A web-based Complaint Register System developed as a final project for the Web Programming (PDW) course at Universitas Muhammadiyah Yogyakarta (UMY).

The application enables citizens to submit complaints regarding public services or environmental issues, upload supporting image evidence, monitor the complaint status, and receive responses from administrators. Administrators can efficiently manage complaints through a dedicated dashboard by reviewing reports, updating statuses, and responding to users.

---

Live Deployment

Website: https://complaint.pdwtiumy.click/

GitHub Repository

https://github.com/sazconf/PDW_Kelompok2_ComplaintRegister

---

Group Members

Name| Student ID
Md Sazzad Hossain Sohag| 20240140253
Nur Azizah Ulinnuha| 20240140252
Ridho Faiq Ahmad| 20240140216
Nimra Tariq| 20240140146
Muhammad Ilyas| 20240140147
Tasya Maulida Putri| 20240140239
Basna Yanti Djakiman| 20240140238
Chintya Nuryaman| 20240140195

---

Technologies Used

- Frontend: Bootstrap 5
- Backend: Native PHP
- Database: MySQL
- Web Server: Apache (XAMPP)

---

Features

User Features

- User Registration
- User Login
- Submit Complaint
- Upload Image Evidence
- View Complaint Details
- Complaint Status Tracking
- User Dashboard
- Responsive User Interface

Administrator Features

- Admin Login
- Admin Dashboard
- View All Complaints
- Update Complaint Status
- Respond to Complaints
- Complaint Management

---

Installation Guide

Requirements

- XAMPP
- PHP
- MySQL
- Web Browser

Installation Steps

1. Clone the repository

git clone https://github.com/sazconf/PDW_Kelompok2_ComplaintRegister.git

2. Copy the project folder into:

xampp/htdocs/

3. Start Apache and MySQL from XAMPP Control Panel.

4. Import the database SQL file into MySQL using phpMyAdmin.

5. Configure the database connection if necessary.

6. Open the browser and visit:

http://localhost/PDW_Kelompok2_ComplaintRegister

---

User Interface

Home Page

"Home" (screenshots/home.png)

Displays the landing page and provides navigation to the system.

---

Login Page

"Login" (screenshots/login.png)

Allows registered users and administrators to log into the system.

---

Registration Page

"Register" (screenshots/register.png)

Allows new users to create an account before submitting complaints.

---

User Dashboard

"Dashboard" (screenshots/dashboard.png)

Displays user information and submitted complaints.

---

Submit Complaint

"Submit Complaint" (screenshots/submit-complaint.png)

Users can submit a complaint by providing details and uploading image evidence.

---

Complaint Details

"Complaint Details" (screenshots/complaint-details.png)

Displays detailed complaint information, uploaded evidence, current status, and administrator response.

---

Complaint History

"Complaint History" (screenshots/history.png)

Shows all complaints submitted by the logged-in user along with their current statuses.

---

Admin Login

"Admin Login" (screenshots/admin-login.png)

Secure login page for administrators.

---

Admin Dashboard

"Admin Dashboard" (screenshots/admin-dashboard.png)

Provides an overview of all submitted complaints and system statistics.

---

Manage Complaints

"Manage Complaints" (screenshots/manage-complaints.png)

Allows administrators to review complaints, update statuses, and manage reports.

---

Update Complaint Status

"Update Status" (screenshots/update-status.png)

Administrators can change complaint statuses such as Pending, In Progress, Resolved, or Rejected.

---

Admin Response

"Admin Response" (screenshots/admin-response.png)

Allows administrators to send responses or feedback to users regarding submitted complaints.

---

Project Structure

PDW_Kelompok2_ComplaintRegister/
│
├── assets/
├── config/
├── database/
├── uploads/
├── screenshots/
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── complaint.php
├── admin/
└── README.md

---

Database

The project uses a MySQL database.

Import the provided SQL file before running the application.

---

Notes

- The application is fully web-based.
- Bootstrap is used to create a responsive interface.
- Image uploads are stored on the server.
- The application supports complaint status tracking and administrator responses.

---

License

This project was developed for educational purposes as part of the Web Programming (PDW) course at Universitas Muhammadiyah Yogyakarta (UMY).
