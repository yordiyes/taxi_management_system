// --- RESTAURANT MANAGEMENT INTEGRATION ---
const RESTAURANT_API_BASE = "https://restaurant-management.page.gd/api/service-provider.php";
const RESTAURANT_API_KEY = "TAXI_SERVICE_KEY_2025"; // Using Taxi Service Group API Key

/**
 * Fetch data from the Restaurant Management API
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
  let url = new URL(RESTAURANT_API_BASE);
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

  // Add API key for write operations (POST requests)
  if (method === "POST") {
    options.headers["X-API-Key"] = RESTAURANT_API_KEY;
  }

  if (body) options.body = JSON.stringify(body);

  const res = await fetch(url, options);
  
  if (!res.ok) {
    const errorData = await res.json().catch(() => ({ message: "Request failed" }));
    throw new Error(errorData.message || `HTTP ${res.status}: ${res.statusText}`);
  }
  
  return await res.json();
}

/**
 * Load and display restaurants from the Restaurant API
 */
async function loadRestaurants() {
  const list = document.getElementById("restaurant-list");
  if (!list) return;
  
  list.innerHTML = "<p class='text-muted'>Loading restaurants...</p>";
  
  try {
    const res = await fetchRestaurantApi("restaurants");
    
    if (!res.success || !res.data || res.data.length === 0) {
      list.innerHTML = "<p class='text-muted'>No restaurants found.</p>";
      return;
    }
    
    list.innerHTML = res.data
      .map(
        (r) => `
            <div class="glass-card">
                ${r.image ? `<img src="${escapeHtml(r.image)}" alt="${escapeHtml(r.name)}" style="width: 100%; height: 200px; object-fit: cover; border-radius: var(--radius-md); margin-bottom: 1.5rem;">` : ''}
                <h3 style="margin-bottom: 0.5rem;">${escapeHtml(r.name)}</h3>
                <p class="text-muted" style="font-size: 0.9rem;">🍽️ ${escapeHtml(r.cuisine || "Cuisine")} • ${r.rating ? "⭐ " + escapeHtml(r.rating) : "No rating"}</p>
                ${r.address ? `<p class="text-muted" style="font-size: 0.85rem; margin-top: 0.5rem;">📍 ${escapeHtml(r.address)}</p>` : ''}
                <div style="margin-top: 1.5rem;">
                  <button class="btn btn-glass" style="width: 100%" onclick="bookRestaurantPrompt(${r.id}, '${(r.name || '').replace(/'/g, "\\'")}', '${(r.cuisine || '').replace(/'/g, "\\'")}', '${(r.address || '').replace(/'/g, "\\'")}', '${(r.rating || '').replace(/'/g, "\\'")}')">Book Table</button>
                </div>
            </div>
        `
      )
      .join("");
  } catch (e) {
    console.error("Error loading restaurants:", e);
    list.innerHTML = `<p style="color:red">Error loading restaurants: ${escapeHtml(e.message)}</p>`;
  }
}

/**
 * Show the restaurant booking form with selected restaurant details
 * @param {number} restaurantId - Restaurant ID
 * @param {string} restaurantName - Restaurant name
 * @param {string} cuisine - Cuisine type
 * @param {string} address - Restaurant address
 * @param {string} rating - Restaurant rating
 */
async function bookRestaurantPrompt(restaurantId, restaurantName, cuisine, address, rating) {
  // Set hidden input
  const restaurantIdInput = document.getElementById("restaurant-id-input");
  if (restaurantIdInput) restaurantIdInput.value = restaurantId;

  // Update selected restaurant display
  const display = document.getElementById("selected-restaurant-display");
  if (display) {
    display.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 0.5rem 0; color: var(--primary);">${escapeHtml(restaurantName)}</h4>
          <p class="text-muted" style="margin: 0.25rem 0; font-size: 0.9rem;">🍽️ ${escapeHtml(cuisine || 'Cuisine')}</p>
          ${rating ? `<p class="text-muted" style="margin: 0.25rem 0; font-size: 0.9rem;">⭐ ${escapeHtml(rating)}</p>` : ''}
          ${address ? `<p class="text-muted" style="margin: 0.25rem 0; font-size: 0.9rem;">📍 ${escapeHtml(address)}</p>` : ''}
        </div>
      </div>
    `;
  }

  // Load restaurant details to get available time slots
  try {
    const detailsRes = await fetchRestaurantApi("restaurant_details", "GET", null, { id: restaurantId });
    if (detailsRes.success && detailsRes.data) {
      const timeSlotSelect = document.getElementById("restaurant-time");
      if (timeSlotSelect && detailsRes.data.available_time_slots) {
        timeSlotSelect.innerHTML = '<option value="">Select time</option>' +
          detailsRes.data.available_time_slots.map(slot => 
            `<option value="${escapeHtml(slot)}">${escapeHtml(slot)}</option>`
          ).join('');
      }
      
      // Update display with opening hours if available
      if (detailsRes.data.opening_hours && display) {
        const existingContent = display.innerHTML;
        display.innerHTML = existingContent + 
          `<p class="text-muted" style="margin: 0.5rem 0 0 0; font-size: 0.85rem;">🕐 ${escapeHtml(detailsRes.data.opening_hours)}</p>`;
      }
    }
  } catch (e) {
    console.error("Error loading restaurant details:", e);
  }

  // Show booking form
  const formContainer = document.getElementById("restaurant-booking-form-container");
  if (formContainer) {
    formContainer.style.display = "block";
    
    // Set minimum date to today
    const dateInput = document.getElementById("restaurant-date");
    if (dateInput) {
      const today = new Date().toISOString().split('T')[0];
      dateInput.setAttribute('min', today);
    }
    
    // Clear previous messages
    const messageDiv = document.getElementById("restaurant-booking-message");
    if (messageDiv) {
      messageDiv.style.display = "none";
      messageDiv.innerHTML = "";
    }

    // Scroll to form
    formContainer.scrollIntoView({ behavior: "smooth", block: "nearest" });

    // Focus first field
    const nameInput = document.getElementById("restaurant-name");
    if (nameInput) nameInput.focus();
  }
}

/**
 * Close the restaurant booking form
 */
function closeRestaurantBookingForm(hideMessage = true) {
  const formContainer = document.getElementById("restaurant-booking-form-container");
  if (formContainer) formContainer.style.display = "none";
  
  const form = document.getElementById("restaurant-booking-form");
  if (form) form.reset();
  
  const restaurantIdInput = document.getElementById("restaurant-id-input");
  if (restaurantIdInput) restaurantIdInput.value = "";
  
  if (hideMessage) {
    const messageDiv = document.getElementById("restaurant-booking-message");
    if (messageDiv) messageDiv.style.display = "none";
  }
}

/**
 * Check availability for selected date, time, and guests
 */
async function checkRestaurantAvailability() {
  const restaurantIdInput = document.getElementById("restaurant-id-input");
  const dateInput = document.getElementById("restaurant-date");
  const timeInput = document.getElementById("restaurant-time");
  const guestsInput = document.getElementById("restaurant-guests");
  
  if (!restaurantIdInput || !dateInput || !timeInput || !guestsInput) return;
  
  const restaurantId = restaurantIdInput.value;
  const date = dateInput.value;
  const time = timeInput.value;
  const guests = guestsInput.value;
  
  if (!restaurantId || !date || !time || !guests) {
    return; // Don't check if fields are incomplete
  }
  
  try {
    const res = await fetchRestaurantApi("check_availability", "GET", null, {
      restaurant_id: restaurantId,
      date: date,
      time: time,
      guests: guests
    });
    
    if (res.success && res.data) {
      const availabilityMsg = document.getElementById("restaurant-availability-message");
      if (availabilityMsg) {
        if (res.data.available) {
          availabilityMsg.innerHTML = `
            <div style="padding: 0.75rem; background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; border-radius: var(--radius-md); color: #10b981; font-size: 0.9rem;">
              ✓ Table available for ${guests} guests
            </div>
          `;
        } else {
          availabilityMsg.innerHTML = `
            <div style="padding: 0.75rem; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; border-radius: var(--radius-md); color: #ef4444; font-size: 0.9rem;">
              ✗ ${res.data.message || "Table not available for this time"}
            </div>
          `;
        }
        availabilityMsg.style.display = "block";
      }
    }
  } catch (e) {
    console.error("Error checking availability:", e);
    // Don't show error for availability check failures
  }
}

/**
 * Escape HTML to prevent XSS attacks
 * @param {string} text - Text to escape
 * @returns {string} Escaped HTML
 */
function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/**
 * Show a message in the restaurant booking message area
 * @param {string} message - Message to display
 * @param {string} type - Message type: 'error', 'success', 'warning', 'info'
 */
function showRestaurantMessage(message, type = "info") {
  const messageDiv = document.getElementById("restaurant-booking-message");
  if (!messageDiv) return;
  
  const colors = {
    error: { bg: "rgba(239, 68, 68, 0.15)", border: "#ef4444", text: "#ef4444", icon: "M6 18L18 6M6 6l12 12" },
    success: { bg: "rgba(16, 185, 129, 0.15)", border: "#10b981", text: "#10b981", icon: "M20 6L9 17L4 12" },
    warning: { bg: "rgba(245, 158, 11, 0.15)", border: "#f59e0b", text: "#f59e0b", icon: "M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" },
    info: { bg: "rgba(59, 130, 246, 0.15)", border: "#3b82f6", text: "#3b82f6", icon: "M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" }
  };
  const color = colors[type] || colors.info;
  
  messageDiv.innerHTML = `
    <div class="glass-card" style="background: ${color.bg}; border: 2px solid ${color.border}; padding: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(${type === 'error' ? '239, 68, 68' : type === 'success' ? '16, 185, 129' : type === 'warning' ? '245, 158, 11' : '59, 130, 246'}, 0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: ${color.text};">
            <path d="${color.icon}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <p style="margin: 0; color: ${color.text}; line-height: 1.6;"><strong>${type.charAt(0).toUpperCase() + type.slice(1)}:</strong> ${escapeHtml(message)}</p>
      </div>
    </div>
  `;
  messageDiv.style.display = "block";
  setTimeout(() => {
    messageDiv.scrollIntoView({ behavior: "smooth", block: "center" });
  }, 100);
}

// Initialize restaurant booking form submission handler when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  const restaurantBookingForm = document.getElementById("restaurant-booking-form");
  if (!restaurantBookingForm) return;

  // Add event listeners for availability checking
  const dateInput = document.getElementById("restaurant-date");
  const timeInput = document.getElementById("restaurant-time");
  const guestsInput = document.getElementById("restaurant-guests");
  
  if (dateInput) {
    dateInput.addEventListener("change", checkRestaurantAvailability);
  }
  if (timeInput) {
    timeInput.addEventListener("change", checkRestaurantAvailability);
  }
  if (guestsInput) {
    guestsInput.addEventListener("change", checkRestaurantAvailability);
  }

  restaurantBookingForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    
    const restaurantIdInput = document.getElementById("restaurant-id-input");
    const nameInput = document.getElementById("restaurant-name");
    const emailInput = document.getElementById("restaurant-email");
    const phoneInput = document.getElementById("restaurant-phone");
    const dateInput = document.getElementById("restaurant-date");
    const timeInput = document.getElementById("restaurant-time");
    const guestsInput = document.getElementById("restaurant-guests");
    const specialRequestsInput = document.getElementById("restaurant-special-requests");
    const messageDiv = document.getElementById("restaurant-booking-message");

    if (!restaurantIdInput || !nameInput || !emailInput || !dateInput || !timeInput || !guestsInput) return;

    const restaurantId = restaurantIdInput.value;
    const name = nameInput.value.trim();
    const email = emailInput.value.trim();
    const phone = phoneInput ? phoneInput.value.trim() : "";
    const date = dateInput.value;
    const time = timeInput.value;
    const guests = guestsInput.value;
    const specialRequests = specialRequestsInput ? specialRequestsInput.value.trim() : "";

    if (!restaurantId) {
      showRestaurantMessage("Please select a restaurant first.", "error");
      return;
    }

    if (!name || !email || !date || !time || !guests) {
      showRestaurantMessage("Please fill in all required fields.", "error");
      return;
    }

    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : "Confirm Booking";
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Booking...";
    }

    try {
      // Generate external booking ID
      const externalBookingId = `TAXI-BOOK-${Date.now()}-${Math.random().toString(36).substr(2, 9).toUpperCase()}`;
      
      const res = await fetchRestaurantApi("create_reservation", "POST", {
        restaurant_id: parseInt(restaurantId),
        customer_name: name,
        customer_email: email,
        customer_phone: phone || undefined,
        date: date,
        time: time,
        guests: parseInt(guests),
        special_requests: specialRequests || undefined,
        external_booking_id: externalBookingId
      });

      if (res.success) {
        let successDetails = [];
        
        if (res.message) {
          successDetails.push(`<p style="margin: 0.5rem 0; line-height: 1.6;"><strong>Message:</strong> ${escapeHtml(res.message)}</p>`);
        }
        
        if (res.data) {
          if (res.data.reservation_id) {
            successDetails.push(`<p style="margin: 0.5rem 0; line-height: 1.6;"><strong>Reservation ID:</strong> <span style="font-family: monospace; background: rgba(16, 185, 129, 0.2); padding: 0.2rem 0.5rem; border-radius: 4px;">#${res.data.reservation_id}</span></p>`);
          }
          
          if (res.data.confirmation_code) {
            successDetails.push(`<p style="margin: 0.5rem 0; line-height: 1.6;"><strong>Confirmation Code:</strong> <span style="font-family: monospace; background: rgba(16, 185, 129, 0.2); padding: 0.2rem 0.5rem; border-radius: 4px;">${escapeHtml(res.data.confirmation_code)}</span></p>`);
          }
          
          if (res.data.status) {
            successDetails.push(`<p style="margin: 0.5rem 0; line-height: 1.6;"><strong>Status:</strong> <span style="text-transform: capitalize;">${escapeHtml(res.data.status)}</span></p>`);
          }
        }

        let messageHtml = `
          <div class="glass-card" style="background: rgba(16, 185, 129, 0.15); border: 2px solid #10b981; padding: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(16, 185, 129, 0.2); display: flex; align-items: center; justify-content: center;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: #10b981;">
                  <path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <h3 style="margin: 0; color: #10b981; font-size: 1.5rem;">Reservation Confirmed!</h3>
            </div>
            <div style="padding-left: 0;">
              ${successDetails.length > 0 ? successDetails.join('') : '<p style="margin: 0.5rem 0; line-height: 1.6;">Your table reservation has been successfully confirmed.</p>'}
              <p style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.9rem; color: var(--text-muted);">📧 You will receive a confirmation email shortly.</p>
            </div>
          </div>
        `;
        
        // Reset form first
        e.target.reset();
        
        // Close form but keep message visible
        closeRestaurantBookingForm(false);
        
        // Set and show message AFTER closing form
        messageDiv.innerHTML = messageHtml;
        messageDiv.style.display = "block";
        messageDiv.style.visibility = "visible";
        messageDiv.style.opacity = "1";
        
        // Scroll to message after a brief delay
        setTimeout(() => {
          messageDiv.scrollIntoView({ behavior: "smooth", block: "center" });
        }, 150);
      } else {
        const errorMsg = res.message || "Reservation failed. Please try again.";
        showRestaurantMessage(errorMsg, "error");
      }
    } catch (error) {
      showRestaurantMessage(`Reservation failed: ${error.message}`, "error");
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    }
  });
});

