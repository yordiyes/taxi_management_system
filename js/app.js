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

  const json = await res.json();

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

  if (user) {
    let links = "";
    if (user.role === "admin" || user.role === "manager") {
      links += `<li><a href="provider_dashboard.html">Dashboard</a></li>`;
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
