# Taxi Management System API Documentation

**For Groups 1, 2, 3, 5, 7, 9**

This documentation outlines how to consume the **Taxi Service** provided by Group 4 & 8.

**Base URL:** `https://taxi-system.infinityfreeapp.com/api` (or `http://localhost/taxi_management_system/api` for local dev)

---

## 1. Authentication

All API requests (except public GET requests) require authentication.

### **Option A: API Key (Server-to-Server)**

Use this for backend integration (e.g., when the Hotel Group server needs to book a taxi for a guest). You can use **either** method below:

1.  **Header (Recommended):** `X-API-KEY: TAXI_GROUP_SECURE_KEY_2024`
2.  **Query Parameter:** `?api_key=TAXI_GROUP_SECURE_KEY_2024` (Useful for simple GET requests)

### **Option B: Session (Browser/Frontend)**

Use this if you are redirecting the user to our frontend or making AJAX calls from a logged-in user's browser.

- **Mechanism:** Standard PHP Session Cookies.

---

## 2. Endpoints

### **A. Get Available Taxi Services**

Retrieve a list of available taxi types (Sedan, SUV, Luxury, etc.) and their prices.

- **Endpoint:** `GET /services.php`
- **Auth Required:** No
- **Response:**

```json
[
  {
    "id": 1,
    "name": "Standard Sedan",
    "description": "Comfortable ride for up to 4 passengers",
    "base_price": "5.00",
    "price_per_km": "2.00"
  },
  {
    "id": 2,
    "name": "Premium SUV",
    "description": "Spacious ride for up to 6 passengers",
    "base_price": "10.00",
    "price_per_km": "3.50"
  }
]
```

### **B. Book a Taxi**

Create a new booking.

- **Endpoint:** `POST /bookings.php`
- **Auth Required:** Yes (API Key or Session)
- **Headers:** `Content-Type: application/json`
- **Body:**

```json
{
  "user_id": 101, // Optional: If known
  "email": "tourist@example.com", // Required if user_id is missing (Auto-registers user)
  "service_id": 1, // ID from GET /services.php
  "pickup_location": "Hotel California",
  "dropoff_location": "City Airport",
  "pickup_time": "2024-12-25 14:30:00"
}
```

- **Response:**

```json
{
  "message": "Booking created successfully.",
  "booking_id": 55
}
```

### **C. Check Booking Status**

Get the status of a specific booking.

- **Endpoint:** `GET /bookings.php?id={booking_id}`
- **Auth Required:** Yes
- **Response:**

```json
{
  "id": 55,
  "status": "confirmed", // pending, confirmed, completed, cancelled
  "driver_name": "John Doe",
  "vehicle_plate": "ABC-123"
}
```

---

## 3. Integration Examples

### **Scenario: Hotel Booking System (Group 2)**

_A user books a room and wants to add an airport transfer._

**Request:**

```javascript
// Hotel Group Frontend
const bookingData = {
  email: userEmail,
  pickup_location: hotelAddress,
  dropoff_location: "Airport",
  pickup_time: flightTime,
  service_id: 1, // Standard Taxi
};

fetch("https://taxi-system.infinityfreeapp.com/api/bookings.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "X-API-KEY": "TAXI_GROUP_SECURE_KEY_2024",
  },
  body: JSON.stringify(bookingData),
})
  .then((res) => res.json())
  .then((data) => console.log("Taxi Booked:", data));
```

### **Scenario: Tour Package (Group 1)**

_A tour package includes free taxi rides._

The Tour Group backend can call our API using PHP/Node/Python:

```php
// Tour Group Backend (PHP)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://taxi-system.infinityfreeapp.com/api/bookings.php");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "email" => $client_email,
    "pickup_location" => "Tour Start Point",
    "dropoff_location" => "Tour End Point",
    "pickup_time" => $schedule_time,
    "service_id" => 2
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-API-KEY: TAXI_GROUP_SECURE_KEY_2024"
]);
$result = curl_exec($ch);
curl_close($ch);
```
