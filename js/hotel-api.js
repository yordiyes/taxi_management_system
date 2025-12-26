// --- HOTEL MANAGEMENT INTEGRATION ---
const HOTEL_PROXY_URL = "api/hotels.php";

/**
 * Fetch data from the Hotel Management API via Local Proxy
 * @param {string} endpoint - API endpoint (e.g., "/hotels.php", "/bookings.php")
 * @param {string} method - HTTP method (GET, POST)
 * @param {object|null} body - Request body for POST requests
 * @param {object} queryParams - Query parameters for GET requests
 * @returns {Promise<object>} API response
 */
async function fetchHotelApi(
  endpoint,
  method = "GET",
  body = null,
  queryParams = {}
) {
  let url = new URL(
    HOTEL_PROXY_URL,
    window.location.origin + window.location.pathname.replace(/\/[^/]*$/, "/")
  );

  // Pass the target endpoint to the proxy
  url.searchParams.append("endpoint", endpoint);

  Object.keys(queryParams).forEach((key) => {
    if (queryParams[key]) url.searchParams.append(key, queryParams[key]);
  });

  const options = {
    method: method,
    headers: {
      "Content-Type": "application/json",
    },
  };

  // API Key is handled by the backend proxy now

  if (body) options.body = JSON.stringify(body);

  try {
    const fetchOptions = {
      ...options,
      credentials: "include", // Important for PHP session
    };

    const response = await fetch(url, fetchOptions);
    const data = await response.json();
    return data;
  } catch (error) {
    console.error("Hotel API Error:", error);
    return { status: "error", message: error.message };
  }
}

/**
 * Load and display available hotels
 */
async function loadHotels() {
  const list = document.getElementById("hotel-list");
  if (!list) return;

  list.innerHTML = '<p class="text-muted">Loading hotels...</p>';

  try {
    const res = await fetchHotelApi("/hotels.php");

    if (res.status === "success" && res.data) {
      // Handle both array directly or nested under hotels
      const hotels = res.data.hotels || res.data;

      if (!Array.isArray(hotels) || hotels.length === 0) {
        list.innerHTML = '<p class="text-muted">No hotels found.</p>';
        return;
      }

      list.innerHTML = hotels
        .map(
          (hotel) => `
        <div class="card service-card">
          <div class="card-image">
            <img src="${
              hotel.image_url ||
              "https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80"
            }" alt="${
            hotel.name
          }" onerror="this.src='https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80'">
            <div class="card-badge">
              <span>${hotel.stars} ★</span>
            </div>
          </div>
          <div class="card-content">
            <h3 class="card-title">${hotel.name}</h3>
            <p class="card-description">
              <span class="location-icon">���</span> ${hotel.city}, ${
            hotel.country
          }
            </p>
            <p class="text-sm text-muted mb-4">${
              hotel.description || "Luxury accommodation"
            }</p>
            <div class="card-footer">
              <button onclick="loadHotelRooms(${hotel.id}, '${
            hotel.name
          }')" class="btn btn-primary full-width">
                View Rooms
              </button>
            </div>
          </div>
        </div>
      `
        )
        .join("");
    } else {
      list.innerHTML = `<p class="error-message">Failed to load hotels: ${
        res.message || "Unknown error"
      }</p>`;
    }
  } catch (error) {
    list.innerHTML = `<p class="error-message">Error loading hotels. Please try again later.</p>`;
  }
}

/**
 * Load and display rooms for a specific hotel
 * @param {number} hotelId
 * @param {string} hotelName
 */
async function loadHotelRooms(hotelId, hotelName) {
  const list = document.getElementById("hotel-list");
  if (!list) return;

  const headerHtml = `
    <div style="grid-column: 1 / -1; margin-bottom: 1rem; display: flex; align-items: center; gap: 1rem;">
      <button onclick="loadHotels()" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
        ← Back to Hotels
      </button>
      <h3 style="margin: 0;">Rooms at ${hotelName}</h3>
    </div>
  `;

  list.innerHTML =
    headerHtml +
    '<p class="text-muted" style="grid-column: 1 / -1;">Loading rooms...</p>';

  const today = new Date();
  const checkIn = today.toISOString().split("T")[0];
  const checkOutDate = new Date(today);
  checkOutDate.setDate(today.getDate() + 3);
  const checkOut = checkOutDate.toISOString().split("T")[0];

  try {
    const res = await fetchHotelApi("/rooms.php", "GET", null, {
      hotel_id: hotelId,
      check_in: checkIn,
      check_out: checkOut,
    });

    if (res.status === "success" && res.data) {
      const rooms = res.data.rooms || res.data;

      if (!Array.isArray(rooms) || rooms.length === 0) {
        list.innerHTML =
          headerHtml +
          '<p class="text-muted" style="grid-column: 1 / -1;">No rooms available for the default dates.</p>';
        return;
      }

      const roomsHtml = rooms
        .map(
          (room) => `
        <div class="card service-card">
          <div class="card-content">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
              <h3 class="card-title">${room.room_type_name}</h3>
              <span class="price-tag">$${room.base_price}/night</span>
            </div>
            <p class="text-sm text-muted mb-2">Room ${room.room_number}</p>
            <div class="features-list mb-4">
              <span class="feature-item">��� Max ${
                room.max_occupancy
              } Guests</span>
              ${
                room.is_available
                  ? '<span class="feature-item" style="color: var(--success);">✓ Available</span>'
                  : '<span class="feature-item" style="color: var(--error);">✗ Unavailable</span>'
              }
            </div>
            <button onclick="openHotelBookingForm(${hotelId}, ${room.id}, '${
            room.room_type_name
          }', ${room.base_price})" 
                    class="btn btn-primary full-width" 
                    ${!room.is_available ? "disabled" : ""}>
              Book Now
            </button>
          </div>
        </div>
      `
        )
        .join("");

      list.innerHTML = headerHtml + roomsHtml;
    } else {
      list.innerHTML =
        headerHtml +
        `<p class="error-message" style="grid-column: 1 / -1;">Failed to load rooms: ${
          res.message || "Unknown error"
        }</p>`;
    }
  } catch (error) {
    list.innerHTML =
      headerHtml +
      `<p class="error-message" style="grid-column: 1 / -1;">Error loading rooms.</p>`;
  }
}

/**
 * Open the hotel booking form
 */
function openHotelBookingForm(hotelId, roomId, roomName, price) {
  const formContainer = document.getElementById("hotel-booking-form-container");
  const display = document.getElementById("selected-hotel-display");
  const hotelList = document.getElementById("hotel-list");

  if (!formContainer || !display) return;

  document.getElementById("hotel-id-input").value = hotelId;
  document.getElementById("room-id-input").value = roomId;

  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate() + 1);

  document.getElementById("hotel-check-in").value = today
    .toISOString()
    .split("T")[0];
  document.getElementById("hotel-check-out").value = tomorrow
    .toISOString()
    .split("T")[0];

  display.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <strong>${roomName}</strong>
        <div class="text-sm text-muted">Price: $${price}/night</div>
      </div>
      <div class="price-tag">Selected</div>
    </div>
  `;

  formContainer.style.display = "block";
  hotelList.style.display = "none";
  formContainer.scrollIntoView({ behavior: "smooth" });
}

/**
 * Close the hotel booking form
 */
function closeHotelBookingForm() {
  const formContainer = document.getElementById("hotel-booking-form-container");
  const hotelList = document.getElementById("hotel-list");

  if (formContainer) formContainer.style.display = "none";
  if (hotelList) hotelList.style.display = "grid";
}

/**
 * Handle hotel booking submission
 */
async function handleHotelBooking(e) {
  e.preventDefault();

  const btn = e.target.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = "Processing...";
  btn.disabled = true;

  const hotelId = document.getElementById("hotel-id-input").value;
  const roomId = document.getElementById("room-id-input").value;
  const checkIn = document.getElementById("hotel-check-in").value;
  const checkOut = document.getElementById("hotel-check-out").value;
  const guests = document.getElementById("hotel-guests").value;
  const name = document.getElementById("hotel-guest-name").value;
  const email = document.getElementById("hotel-guest-email").value;
  const phone = document.getElementById("hotel-guest-phone").value;
  const specialRequests = document.getElementById(
    "hotel-special-requests"
  ).value;

  const bookingData = {
    hotel_id: parseInt(hotelId),
    room_id: parseInt(roomId),
    check_in: checkIn,
    check_out: checkOut,
    guests: parseInt(guests),
    guest_name: name,
    guest_email: email,
    guest_phone: phone,
    special_requests: specialRequests,
    service_reference: "TAXI_SYS_BOOKING_" + Date.now(),
  };

  try {
    // POST to /bookings.php via proxy
    // The proxy handles the endpoint routing for POST automatically if we send to api/hotels.php with POST
    const res = await fetchHotelApi("/bookings.php", "POST", bookingData);

    const messageDiv = document.getElementById("hotel-booking-message");
    if (messageDiv) {
      messageDiv.style.display = "block";
      if (res.status === "success") {
        messageDiv.innerHTML = `
          <div class="alert alert-success">
            <strong>Booking Confirmed!</strong><br>
            Booking ID: ${res.data.id}<br>
            Total Price: $${res.data.total_price}
          </div>
        `;
        document.getElementById("hotel-booking-form").reset();
        setTimeout(() => {
          closeHotelBookingForm();
          messageDiv.style.display = "none";
          loadHotels();
        }, 3000);
      } else {
        messageDiv.innerHTML = `
          <div class="alert alert-error">
            <strong>Booking Failed</strong><br>
            ${res.message}
          </div>
        `;
      }
    }
  } catch (error) {
    console.error("Booking error:", error);
    alert("An error occurred while processing your booking.");
  } finally {
    btn.innerHTML = originalText;
    btn.disabled = false;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("hotel-booking-form");
  if (form) {
    form.addEventListener("submit", handleHotelBooking);
  }
});
