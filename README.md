# Taxi Service Management System (Group 4 & 8)

## Project Overview

This project implements the **Taxi Service Management** module as part of the larger Tourism Management System. It acts as both a **Service Provider** (offering taxi bookings to other groups) and a **Service Consumer** (providing a Tourist View that integrates/simulates other groups' services).

## 1. Service Provider Functionalities

_Features we provide to the system:_

### User Authentication & Management

- [x] **Registration:** `api/auth.php?action=register` (Supports Customers, Drivers, Managers)
- [x] **Login:** `api/auth.php?action=login`
- [x] **Logout:** `api/auth.php?action=logout`
- [x] **Role-Based Access Control:**
  - **Admin/Manager:** Access to `provider_dashboard.html`
  - **Customer:** Access to `index.html` (Tourist View)
- [x] **Profile Management:** `api/profile.php` (View & Update)

### Taxi Services Management

- [x] **Service Listings:** `api/services.php` (CRUD)
- [x] **Vehicle Management:** `api/vehicles.php` (CRUD)
- [x] **Driver Management:** `api/drivers.php` (CRUD)
- [x] **Booking System:** `api/bookings.php` (Create, Update Status, History)
- [x] **Statistics:** `api/stats.php` (Visitor counts, booking stats)

## 2. Service Consumer Functionalities

_Features where we consume other groups' services (Simulated in `index.html`):_

- [x] **Tour Management (Group 1, 5, 9):** "Browse Places" & "Travel Tickets" tabs in Tourist View.
- [x] **Hotel Management (Group 2, 4):** "Hotels" tab in Tourist View.
- [x] **Restaurant Management (Group 3, 7):** "Restaurants" tab in Tourist View.
- [x] **Taxi Service (Self):** "Taxis" tab in Tourist View (Fully Functional).

## 3. Testing / Tourist Web Page

The **Tourist View** (`index.html`) serves as the central testing hub for tourists to:

- [x] Login/Signup/Logout
- [x] Browse Places
- [x] Search for Services
- [x] Book Travel Tickets
- [x] Reserve Hotel Rooms
- [x] Book Restaurant Seats
- [x] Order Taxis

## Installation & Setup

1. **Database:** Import `database.sql` into your MySQL server.
2. **Config:** Update `api/config.php` with your database credentials.
3. **Run:** Host on a PHP server (e.g., XAMPP, InfinityFree).

## API Documentation

For integration details, please refer to [api_docs.md](api_docs.md).
