# Recommended PHP Application Structure (MVC)

For a system like the L&D Passbook, the best professional standard is the **MVC (Model-View-Controller)** pattern. This structure improves security, organization, and scalability.

## 🌟 The Ideal Directory Layout

```text
/ldp-system
│
├── /public            <-- WEB ROOT (Only this folder is accessible to the world)
│   ├── index.php      <-- The "Front Controller" (Single Entry Point)
│   ├── css/
│   ├── js/
│   └── uploads/       <-- User uploaded files
│
├── /app               <-- Your Application Logic (Hidden from web)
│   ├── /Config        <-- Database connections
│   ├── /Controllers   <-- Handles logic (e.g., AdminController.php, AuthController.php)
│   ├── /Models        <-- Database interaction (User.php, Activity.php)
│   ├── /Views         <-- HTML Templates (The design)
│   └── /Core          <-- Router, Database helper, Validator
│
├── /logs              <-- Error logs (Private)
└── composer.json      <-- Dependency management (Standard PHP practice)
```

---

## 🏗️ Why This Is better

### 1. Security (The "Public" Folder)
**Current Issue:** your `admin/dashboard.php` and `includes/db.php` are all inside the main folder. If configured poorly, a hacker might access sensitive files directly.
**The Fix (`/public`):** In the recommended structure, the web server (Apache/Nginx) is pointed to the `/public` folder.
*   The Internet can **ONLY** see `index.php`, CSS, and Images.
*   The Internet **CANNOT** see your logic, database passwords, or core files because they are sitting *outside* the public folder.

### 2. The "Front Controller" (Single Entry Point)
**Current Issue:** You have `login.php`, `register.php`, `admin/dashboard.php`. Each file acts as its own gatekeeper. You have to repeat `session_start()` and permission checks in every single file.
**The Fix (`public/index.php`):** *Every* request goes through `index.php` first.
*   URL: `yoursite.com/admin/dashboard` -> sends to `index.php`.
*   `index.php` starts the session, checks if you are banned, and *then* loads the Admin Controller.
*   **Result:** You write security logic **ONCE**, and it protects the entire app.

### 3. Separation of Concerns (MVC)
**Current Issue:** In `profile.php`, you have SQL queries, PHP form handling, AND HTML code all mixed together.
**The Fix:**
*   **Model (`app/Models/User.php`)**: "I fetch user data from the database."
*   **Controller (`app/Controllers/ProfileController.php`)**: "I handle the uploaded file, validate it, and tell the Model to save it."
*   **View (`app/Views/profile.php`)**: "I only contain HTML and variables. I don't know about databases."

### 4. Grouping by Function, Not Role
**Current Issue:** You have folders named `admin/`, `hr/`, `user/`.
*   What happens if a user is both an Admin and HR? You duplicate code.
*   What happens if you change the navbar? You have to edit it in 3 places.
**The Fix:** Organize by **function**.
*   `AuthController` handles login for everyone.
*   `ActivityController` handles viewing activities for everyone (restrictions are handled in logic, not by folder location).

---

## 🚀 How to Transition (Eventually)

You do **not** need to rewrite everything today. But if you were to start Version 2.0:

1.  **Move Assets**: Put all CSS, JS, and Images in `public/`.
2.  **Centralize Logic**: Stop writing SQL in page files. You already started this with your "Repositories" (which act like **Models**) - this is great!
3.  **Adopt a Router**: Instead of linking to `dashboard.php`, you link to `/dashboard` and have a small script load the correct file.

### Professional Verdict
Your current structure is a "Flat PHP" structure. It is standard for beginners and small internal tools.
The recommended structure is "Modern MVC" (used by Laravel, Symfony, CodeIgniter). It is standard for **Professional Enterprise Applications**.
