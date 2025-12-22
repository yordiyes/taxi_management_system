# Taxi Management System: Partner Integration Guide

Welcome to the Taxi Management System API. This documentation is dedicated to **external project groups** (Restaurants, Hotels, Events, etc.) looking to integrate our taxi booking services into their own applications.

**Base URL:** `https://taxi-system.infinityfreeapp.com/api`

---

## Group Integration & Authentication

To ensure seamless service-to-service communication, all requests from your group should follow these rules:

The API supports two methods of authentication:

1.  **Session-based:** Standard web application session (Cookie-based).
2.  **API Key:** For external integrations, use the `X-API-KEY` header or `api_key` query parameter.
    - **API Key:** `TAXI_GROUP_SECURE_KEY_2024`

---

## Endpoints

### 1. Authentication (`auth.php`)

| Method | Action                  | Description                | Parameters (JSON)                                                                   |
| :----- | :---------------------- | :------------------------- | :---------------------------------------------------------------------------------- |
| POST   | `?action=register`      | Register a new user        | `username`, `email`, `password`, `role` (optional: 'customer', 'driver', 'manager') |
| POST   | `?action=login`         | Login and start session    | `email`, `password`                                                                 |
| POST   | `?action=logout`        | Terminate session          | None                                                                                |
| GET    | `?action=check_session` | Check current user session | None                                                                                |

---

### 2. Services (`services.php`)

| Method | Description                             | Parameters                                                            |
| :----- | :-------------------------------------- | :-------------------------------------------------------------------- |
| GET    | List all taxi services                  | None                                                                  |
| GET    | Get specific service                    | `?id={service_id}`                                                    |
| POST   | Create new service (Admin/Manager only) | `name`, `description`, `base_price`, `price_per_km`                   |
| PUT    | Update service (Admin/Manager only)     | `?id={id}`, JSON: `name`, `description`, `base_price`, `price_per_km` |
| DELETE | Delete service (Admin/Manager only)     | `?id={id}`                                                            |

---

### 3. Bookings (`bookings.php`)

Requires Authentication (Session or API Key).

| Method | Description           | Parameters                                                                                                                                        |
| :----- | :-------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------ |
| GET    | View bookings history | Provide `email` (for partners) or `user_id` (admin/internal)                                                                                      |
| POST   | Create a booking      | `pickup_location`, `dropoff_location`, `pickup_time`, `service_id` (optional). <br> **Identify via:** `user_id` OR `email` (Auto-creates account) |
| PUT    | Update booking status | `?id={id}`, JSON: `status` ('pending', 'confirmed', 'completed', 'cancelled')                                                                     |

---

### 4. Other Entities

- **Drivers:** `drivers.php` (CRUD)
- **Vehicles:** `vehicles.php` (CRUD)
- **Taxis:** `taxis.php` (CRUD)
- **Profile:** `profile.php` (View/Update logged-in user)
- **Stats:** `stats.php` (Dashboard statistics)

---

## Example Usage (External Integration)

### Create a Booking via Fetch API

```javascript
fetch("https://taxi-system.infinityfreeapp.com/api/bookings.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "X-API-KEY": "TAXI_GROUP_SECURE_KEY_2024",
  },
  body: JSON.stringify({
    user_id: 1, // The ID of the customer
    pickup_location: "Central Station",
    dropoff_location: "Airport Terminal 1",
    pickup_time: "2024-12-25 10:00:00",
    service_id: 2,
  }),
})
  .then((response) => response.json())
  .then((data) => console.log(data));
```
