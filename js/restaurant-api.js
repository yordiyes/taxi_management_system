// --- RESTAURANT MANAGEMENT INTEGRATION ---
const RESTAURANT_PROXY_URL = "api/restaurants.php";

/**
 * Fetch data from the Restaurant Management API via Local Proxy
 * @param {string} action - API action (e.g., "restaurants", "restaurant_details")
 * @param {string} method - HTTP method (GET, POST)
 * @param {object|null} body - Request body for POST requests
 * @param {object} queryParams - Query parameters for GET requests
 * @returns {Promise<object>} API response
 */
async function fetchRestaurantApi(
  action,
  method = "GET",
  body = null,
  queryParams = {}
) {
  let url = new URL(RESTAURANT_PROXY_URL, window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/'));
  
  // Pass action to proxy
  url.searchParams.append("action", action);
  
  Object.keys(queryParams).forEach((key) => {
    if (queryParams[key]) url.searchParams.append(key, queryParams[key]);
  });

  const options = {
    method: method,
    headers: {
      "Content-Type": "application/json",
    },
  };

  // API Key is handled by backend proxy

  if (body) options.body = JSON.stringify(body);

  const fetchOptions = {
      ...options,
      credentials: 'include'
  };

  const res = await fetch(url, fetchOptions);
  
  if (!res.ok) {
    const errorData = await res.json().catch(() => ({ message: "Request failed" }));
    throw new Error(errorData.message || `HTTP ${res.status}: ${res.statusText}`);
  }
  
  return await res.json();
}

/**
 * Load and display restaurants
 */
async function loadRestaurants() {
  const list = document.getElementById("restaurant-list");
  if (!list) return;
  
  list.innerHTML = '<p class="text-muted">Loading restaurants...</p>';
  
  try {
    const res = await fetchRestaurantApi("restaurants");
    
    if (res.status === "success" && res.data) {
      if (res.data.length === 0) {
        list.innerHTML = '<p class="text-muted">No restaurants found.</p>';
        return;
      }
      
      list.innerHTML = res.data.map(restaurant => `
        <div class="card service-card">
          <div class="card-image">
            <img src="${restaurant.image_url || 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=800&q=80'}" alt="${restaurant.name}" onerror="this.src='https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=800&q=80'">
            <div class="card-badge">
              <span>${restaurant.cuisine_type || 'Restaurant'}</span>
            </div>
          </div>
          <div class="card-content">
            <h3 class="card-title">${restaurant.name}</h3>
            <p class="card-description">
              <span class="location-icon">���</span> ${restaurant.address}
            </p>
            <div class="features-list mb-4">
              <span class="feature-item">⭐ ${restaurant.rating || 'N/A'} (${restaurant.review_count || 0} reviews)</span>
              <span class="feature-item">��� ${restaurant.price_range}</span>
            </div>
            <button onclick="openRestaurantBookingForm(${restaurant.id}, '${String(restaurant.name).replace(/'/g, "\\'")}')" class="btn btn-primary full-width">
              Reserve Table
            </button>
          </div>
        </div>
      `).join("");
    } else {
      list.innerHTML = `<p class="error-message">Failed to load restaurants: ${res.message || "Unknown error"}</p>`;
    }
  } catch (error) {
    list.innerHTML = `<p class="error-message">Error loading restaurants. Please try again later.</p>`;
  }
}

/**
 * Open the restaurant booking form
 */
function openRestaurantBookingForm(restaurantId, restaurantName) {
  const formContainer = document.getElementById("restaurant-booking-form-container");
  const display = document.getElementById("selected-restaurant-display");
  const list = document.getElementById("restaurant-list");
  
  if (!formContainer || !display) return;

  document.getElementById("restaurant-id-input").value = restaurantId;
  
  // Populate time slots (mock for now, ideally fetch from API)
  const timeSelect = document.getElementById("restaurant-time");
  timeSelect.innerHTML = '<option value="">Select time</option>';
  ['12:00', '13:00', '14:00', '18:00', '19:00', '20:00', '21:00'].forEach(time => {
    timeSelect.innerHTML += `<option value="${time}">${time}</option>`;
  });

  display.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <strong>${restaurantName}</strong>
        <div class="text-sm text-muted">Reservation</div>
      </div>
      <div class="price-tag">Selected</div>
    </div>
  `;

  formContainer.style.display = "block";
  list.style.display = "none";
  formContainer.scrollIntoView({ behavior: 'smooth' });
}

/**
 * Close the restaurant booking form
 */
function closeRestaurantBookingForm() {
  const formContainer = document.getElementById("restaurant-booking-form-container");
  const list = document.getElementById("restaurant-list");
  
  if (formContainer) formContainer.style.display = "none";
  if (list) list.style.display = "grid";
}

/**
 * Handle restaurant booking submission
 */
async function handleRestaurantBooking(e) {
  e.preventDefault();
  
  const btn = e.target.querySelector('button[type="submit"]');
  const originalText = btn.innerHTML;
  btn.innerHTML = 'Processing...';
  btn.disabled = true;

  const restaurantId = document.getElementById("restaurant-id-input").value;
  const name = document.getElementById("restaurant-name").value;
  const email = document.getElementById("restaurant-email").value;
  const phone = document.getElementById("restaurant-phone").value;
  const date = document.getElementById("restaurant-date").value;
  const time = document.getElementById("restaurant-time").value;
  const guests = document.getElementById("restaurant-guests").value;
  const specialRequests = document.getElementById("restaurant-special-requests").value;

  const bookingData = {
    restaurant_id: parseInt(restaurantId),
    customer_name: name,
    customer_email: email,
    customer_phone: phone,
    reservation_date: date,
    reservation_time: time,
    party_size: parseInt(guests),
    special_requests: specialRequests,
    service_reference: "TAXI_SYS_RES_" + Date.now()
  };

  try {
    const res = await fetchRestaurantApi("book_table", "POST", bookingData);
    
    const messageDiv = document.getElementById("restaurant-booking-message");
    if (messageDiv) {
      messageDiv.style.display = "block";
      if (res.status === "success") {
        messageDiv.innerHTML = `
          <div class="alert alert-success">
            <strong>Reservation Confirmed!</strong><br>
            Reservation ID: ${res.data.id}<br>
            Status: ${res.data.status}
          </div>
        `;
        document.getElementById("restaurant-booking-form").reset();
        setTimeout(() => {
          closeRestaurantBookingForm();
          messageDiv.style.display = "none";
        }, 3000);
      } else {
        messageDiv.innerHTML = `
          <div class="alert alert-error">
            <strong>Reservation Failed</strong><br>
            ${res.message}
          </div>
        `;
      }
    }
  } catch (error) {
    console.error("Booking error:", error);
    alert("An error occurred while processing your reservation.");
  } finally {
    btn.innerHTML = originalText;
    btn.disabled = false;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("restaurant-booking-form");
  if (form) {
    form.addEventListener("submit", handleRestaurantBooking);
  }
});
