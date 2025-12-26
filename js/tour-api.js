// --- TOUR MANAGEMENT INTEGRATION ---
const TOUR_API_BASE = "https://tour-management-web.onrender.com/api/v1/";
const TOUR_API_KEY = "demo-api-key";

/**
 * Fetch data from the Tour Management API
 * @param {string} endpoint - API endpoint (e.g., "places.php", "tours.php")
 * @param {string} method - HTTP method (GET, POST, etc.)
 * @param {object|null} body - Request body for POST requests
 * @param {object} queryParams - Query parameters for GET requests
 * @returns {Promise<object>} API response
 */
async function fetchTourApi(
  endpoint,
  method = "GET",
  body = null,
  queryParams = {}
) {
  let url = new URL(TOUR_API_BASE + endpoint);
  Object.keys(queryParams).forEach((key) => {
    if (queryParams[key]) url.searchParams.append(key, queryParams[key]);
  });

  const options = {
    method: method,
    headers: {
      "Content-Type": "application/json",
      "X-API-KEY": TOUR_API_KEY,
    },
  };
  if (body) options.body = JSON.stringify(body);

  const res = await fetch(url, options);
  return await res.json();
}

/**
 * Load and display places from the Tour API
 */
async function loadPlaces() {
  const list = document.getElementById("places-list");
  if (!list) return;
  
  list.innerHTML = "<p>Loading places...</p>";
  try {
    const res = await fetchTourApi("places.php");
    if (!res.data || res.data.length === 0) {
      list.innerHTML = "<p>No places found.</p>";
      return;
    }
    list.innerHTML = res.data
      .map(
        (p) => `
            <div class="glass-card">
                <img src="https://via.placeholder.com/400x200/6366f1/ffffff?text=${encodeURIComponent(
                  p.name
                )}" style="width: 100%; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                <h3 style="margin-bottom: 0.5rem;">${p.name}</h3>
                <p class="text-muted">📍 ${p.type || "Destination"}</p>
                <div style="margin-top: 1.5rem;">
                  <button class="btn btn-glass" style="width: 100%" onclick="alert('View details for ${p.name}')">View Details</button>
                </div>
            </div>
        `
      )
      .join("");
  } catch (e) {
    console.error(e);
    list.innerHTML = '<p style="color:red">Error loading places.</p>';
  }
}

/**
 * Load and display tours from the Tour API
 */
async function loadTours() {
  const list = document.getElementById("tours-list");
  if (!list) return;
  
  const searchInput = document.getElementById("tour-search");
  const q = searchInput ? searchInput.value : "";
  
  list.innerHTML = "<p>Loading tours...</p>";
  try {
    const res = await fetchTourApi("tours.php", "GET", null, { q });
    if (!res.data || res.data.length === 0) {
      list.innerHTML = "<p>No tours found.</p>";
      return;
    }
    list.innerHTML = res.data
      .map(
        (t) => `
            <div class="glass-card">
                <h3 style="margin-bottom: 0.5rem;">${t.title}</h3>
                <p class="text-muted">📍 ${t.location || 'Location TBD'} • 📅 ${t.schedule_date || 'Date TBD'}</p>
                <p style="margin-top: 1rem; font-weight: 700; font-size: 1.25rem;">$${t.price || '0'}</p>
                <div style="margin-top: 1.5rem;">
                  <button class="btn btn-glass" style="width: 100%" onclick="bookTourPrompt(${t.id}, '${(t.title || '').replace(/'/g, "\\'")}', '${(t.location || '').replace(/'/g, "\\'")}', '${t.price || '0'}', '${(t.schedule_date || '').replace(/'/g, "\\'")}')">Book Now</button>
                </div>
            </div>
        `
      )
      .join("");
  } catch (e) {
    console.error(e);
    list.innerHTML = '<p style="color:red">Error loading tours.</p>';
  }
}

/**
 * Show the tour booking form with selected tour details
 * @param {number} tourId - Tour ID
 * @param {string} tourTitle - Tour title
 * @param {string} tourLocation - Tour location
 * @param {string|number} tourPrice - Tour price
 * @param {string} tourDate - Tour schedule date
 */
function bookTourPrompt(tourId, tourTitle, tourLocation, tourPrice, tourDate) {
  // Set hidden input
  const tourIdInput = document.getElementById("tour-id-input");
  if (tourIdInput) tourIdInput.value = tourId;

  // Update selected tour display
  const display = document.getElementById("selected-tour-display");
  if (display) {
    display.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 0.5rem 0; color: var(--primary);">${tourTitle}</h4>
          <p class="text-muted" style="margin: 0.25rem 0; font-size: 0.9rem;">📍 ${tourLocation || 'Location TBD'}</p>
          <p class="text-muted" style="margin: 0.25rem 0; font-size: 0.9rem;">📅 ${tourDate || 'Date TBD'}</p>
          <p style="margin: 0.5rem 0 0 0; font-weight: 700; font-size: 1.1rem; color: var(--primary);">$${tourPrice || '0'}</p>
        </div>
      </div>
    `;
  }

  // Show booking form
  const formContainer = document.getElementById("tour-booking-form-container");
  if (formContainer) {
    formContainer.style.display = "block";
    
    // Clear previous messages
    const messageDiv = document.getElementById("tour-booking-message");
    if (messageDiv) {
      messageDiv.style.display = "none";
      messageDiv.innerHTML = "";
    }

    // Scroll to form
    formContainer.scrollIntoView({ behavior: "smooth", block: "nearest" });

    // Focus first field
    const nameInput = document.getElementById("tour-name");
    if (nameInput) nameInput.focus();
  }
}

/**
 * Close the tour booking form
 * @param {boolean} hideMessage - Whether to hide the message div
 */
function closeTourBookingForm(hideMessage = true) {
  const formContainer = document.getElementById("tour-booking-form-container");
  if (formContainer) formContainer.style.display = "none";
  
  const form = document.getElementById("tour-booking-form");
  if (form) form.reset();
  
  const tourIdInput = document.getElementById("tour-id-input");
  if (tourIdInput) tourIdInput.value = "";
  
  if (hideMessage) {
    const messageDiv = document.getElementById("tour-booking-message");
    if (messageDiv) messageDiv.style.display = "none";
  }
}

/**
 * Escape HTML to prevent XSS attacks
 * @param {string} text - Text to escape
 * @returns {string} Escaped HTML
 */
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/**
 * Show a message in the tour booking message area
 * @param {string} message - Message to display
 * @param {string} type - Message type: 'error', 'success', 'warning', 'info'
 */
function showTourMessage(message, type = "info") {
  const messageDiv = document.getElementById("tour-booking-message");
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

// Initialize tour booking form submission handler when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  const tourBookingForm = document.getElementById("tour-booking-form");
  if (!tourBookingForm) return;

  tourBookingForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    
    const tourIdInput = document.getElementById("tour-id-input");
    const nameInput = document.getElementById("tour-name");
    const emailInput = document.getElementById("tour-email");
    const messageDiv = document.getElementById("tour-booking-message");

    if (!tourIdInput || !nameInput || !emailInput || !messageDiv) return;

    const tourId = tourIdInput.value;
    const name = nameInput.value;
    const email = emailInput.value;

    if (!tourId) {
      showTourMessage("Please select a tour first.", "error");
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
      const res = await fetchTourApi("tours.php", "POST", {
        tour_id: tourId,
        email: email,
        name: name,
      });

      // Debug: Log the response
      console.log("Tour booking response:", res);

      // Check for success - handle various response formats
      const isSuccess = res.success === true || 
                       res.success === "true" || 
                       (res.message && !res.error) ||
                       res.booking_id ||
                       (res.status && (res.status === "success" || res.status === 200));

      console.log("Is success:", isSuccess);

      if (isSuccess) {
        // Build success message with all available details
        let successDetails = [];
        
        if (res.message) {
          successDetails.push(`<p style="margin: 0.5rem 0; line-height: 1.6;"><strong>Message:</strong> ${escapeHtml(res.message)}</p>`);
        }
        
        if (res.booking_id) {
          successDetails.push(`<p style="margin: 0.5rem 0; line-height: 1.6;"><strong>Booking ID:</strong> <span style="font-family: monospace; background: rgba(16, 185, 129, 0.2); padding: 0.2rem 0.5rem; border-radius: 4px;">#${res.booking_id}</span></p>`);
        }
        
        if (res.data && typeof res.data === 'object') {
          const dataStr = JSON.stringify(res.data, null, 2);
          successDetails.push(`<details style="margin: 0.5rem 0;"><summary style="cursor: pointer; font-weight: 600; margin-bottom: 0.5rem;">Booking Details</summary><pre style="background: rgba(0, 0, 0, 0.1); padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.85rem; margin: 0.5rem 0 0 0;">${escapeHtml(dataStr)}</pre></details>`);
        } else if (res.data) {
          successDetails.push(`<p style="margin: 0.5rem 0; line-height: 1.6;"><strong>Details:</strong> ${escapeHtml(String(res.data))}</p>`);
        }

        let messageHtml = `
          <div class="glass-card" style="background: rgba(16, 185, 129, 0.15); border: 2px solid #10b981; padding: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(16, 185, 129, 0.2); display: flex; align-items: center; justify-content: center;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: #10b981;">
                  <path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <h3 style="margin: 0; color: #10b981; font-size: 1.5rem;">Booking Confirmed!</h3>
            </div>
            <div style="padding-left: 0;">
              ${successDetails.length > 0 ? successDetails.join('') : '<p style="margin: 0.5rem 0; line-height: 1.6;">Your tour booking has been successfully confirmed.</p>'}
              <p style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.9rem; color: var(--text-muted);">📧 You will receive a confirmation email shortly.</p>
            </div>
          </div>
        `;
        
        // Reset form first
        e.target.reset();
        
        // Close form but keep message visible
        closeTourBookingForm(false);
        
        // Set and show message AFTER closing form
        messageDiv.innerHTML = messageHtml;
        messageDiv.style.display = "block";
        messageDiv.style.visibility = "visible";
        messageDiv.style.opacity = "1";
        
        // Force a reflow to ensure display is applied
        messageDiv.offsetHeight;
        
        console.log("Success message displayed, element:", messageDiv);
        console.log("Message div display:", window.getComputedStyle(messageDiv).display);
        
        // Scroll to message after a brief delay to ensure it's rendered
        setTimeout(() => {
          messageDiv.scrollIntoView({ behavior: "smooth", block: "center" });
        }, 150);
      } else {
        // Handle error response
        const errorMsg = res.error || res.message || "Booking failed. Please try again.";
        showTourMessage(errorMsg, "error");
      }
    } catch (error) {
      showTourMessage(`Booking failed: ${error.message}`, "error");
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    }
  });
});

