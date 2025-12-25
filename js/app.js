const API_BASE = "api/";

async function apiFetch(endpoint, method = "GET", data = null) {
  const options = {
    method,
    headers: {
      "Content-Type": "application/json",
    },
    credentials: "include", // Send cookies
  };

  if (data) {
    options.body = JSON.stringify(data);
  }

  const res = await fetch(API_BASE + endpoint, options);

  // Handle 401 Unauthorized globally
  if (res.status === 401) {
    localStorage.removeItem("taxi_user");
    // Only redirect if not already on public pages
    if (
      !window.location.pathname.includes("login.html") &&
      !window.location.pathname.includes("register.html") &&
      !window.location.pathname.includes("index.html")
    ) {
      window.location.href = "login.html";
    }
  }

  let json;
  try {
    const contentType = res.headers.get("content-type");
    if (contentType && contentType.includes("application/json")) {
      json = await res.json();
    } else {
      const text = await res.text();
      console.error("Non-JSON response received:", text);
      throw new Error("Server returned an invalid response format.");
    }
  } catch (e) {
    if (e.message.includes("invalid response format")) throw e;
    console.error("JSON Parse Error:", e);
    throw new Error("Failed to parse server response.");
  }

  if (!res.ok) {
    throw new Error(json.message || "API Error");
  }

  return json;
}

function getUser() {
  const userStr = localStorage.getItem("taxi_user");
  return userStr ? JSON.parse(userStr) : null;
}

function setUser(user) {
  localStorage.setItem("taxi_user", JSON.stringify(user));
}

function logout() {
  apiFetch("auth.php?action=logout").then(() => {
    localStorage.removeItem("taxi_user");
    window.location.href = "login.html";
  });
}

async function checkSession() {
  try {
    const res = await apiFetch("auth.php?action=check_session");
    setUser(res.user);
    updateNav();
  } catch (e) {
    // Session invalid
    localStorage.removeItem("taxi_user");
    updateNav();
  }
}

function updateNav() {
  const user = getUser();
  const navList = document.getElementById("nav-list");
  if (!navList) return;

  // Special handling for Provider Dashboard to preserve its specific links
  if (window.location.pathname.includes("provider_dashboard.html")) {
    if (user && (user.role === "admin" || user.role === "manager")) {
      navList.innerHTML = `
        <li><a href="#" onclick="showSection('stats')">Overview</a></li>
        <li><a href="#" onclick="showSection('services')">Services</a></li>
        <li><a href="#" onclick="showSection('drivers')">Drivers</a></li>
        <li><a href="index.html">Tourist View</a></li>
        <li><a href="#" onclick="logout()">Logout (${user.username})</a></li>
      `;
    }
    return;
  }
  
  // Special handling for Driver Dashboard
  if (window.location.pathname.includes("driver_dashboard.html")) {
    if (user && user.role === "driver") {
      navList.innerHTML = `
        <li><a href="#" class="active" onclick="showSection('cars')">My Cars</a></li>
        <li><a href="#" onclick="showSection('bookings')">Bookings</a></li>
        <li><a href="index.html">Tourist View</a></li>
        <li><a href="#" onclick="logout()">Logout (${user.username})</a></li>
      `;
    }
    return;
  }

  if (user) {
    let links = "";
    if (user.role === "admin" || user.role === "manager") {
      links += `<li><a href="provider_dashboard.html">Dashboard</a></li>`;
    } else if (user.role === "driver") {
      links += `<li><a href="driver_dashboard.html">Driver Dashboard</a></li>`;
    }
    links += `<li><a href="index.html">Tourist View</a></li>`;
    links += `<li><a href="#" onclick="logout()">Logout (${user.username})</a></li>`;
    navList.innerHTML = links;
  } else {
    navList.innerHTML = `
            <li><a href="index.html">Home</a></li>
            <li><a href="login.html">Login</a></li>
            <li><a href="register.html">Register</a></li>
        `;
  }
}

// Check session on load (if not on auth action pages)
// Using a small delay or just call it
document.addEventListener("DOMContentLoaded", checkSession);
