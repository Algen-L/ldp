# System Functionality & Logic Documentation

This document explains the technical logic behind the L&D Passbook System, focusing on how the data flows between the Database, PHP Backend, and JavaScript Frontend.

## 1. Core Architecture (The "Brain")

The system is built on a **Repository Pattern**. This means the code that talks to the database is separated from the code that displays the page.

### `includes/init_repos.php`
This is the **Bootstrap file**. It runs at the top of almost every page.
-   **Function**: It connects to the database (`$pdo`) and loads all the "Repository" classes.
-   **Why**: Instead of writing `SELECT * FROM users` in every single file, we just call `$userRepo->getAllUsers()`.

### `includes/error_handler.php`
-   **Function**: Sits silently in the background catching errors.
-   **Logic**: If PHP crashes or throws an exception, this script catches it, logs it to a file, and shows the user a "System Error" page instead of a white screen or scary code details.

---

## 2. Repositories (Data Layer)

Located in `includes/repositories/`, these files handle all database operations.

-   **`UserRepository.php`**:
    -   `login($email, $password)`: Checks credentials.
    -   `updateUserProfile(...)`: updates name, station, etc.
-   **`ActivityRepository.php`**:
    -   `getAllActivities($filters)`: Builds complex SQL queries dynamically based on what dates or office filters you select.
    -   `updateApprovalStatus(...)`: Handles the logic for "Review" -> "Recommend" -> "Approve".
-   **`ILDNRepository.php`**:
    -   Manages the "Individual Learning Needs" (the competencies users want to improve).

---

## 3. Admin Module (The "Control Center")

### `admin/dashboard.php`
-   **Old Way**: It used to calculate stats directly on the page.
-   **New Way (Logic)**:
    1.  It loads the basic HTML structure.
    2.  It loads `js/admin/dashboard.js`.
    3.  It passes initial variables (like user counts) from PHP to JS using `json_encode`.

### `admin/api/dashboard_api.php` **(The Connector)**
-   **Function**: This is an **API Endpoint**. It doesn't show a webpage; it only replies with data (JSON).
-   **Logic**:
    1.  Receives a request (e.g., `?filter=week`).
    2.  Asks `ActivityRepository` for the data.
    3.  Calculates the stats (submission counts, office distribution).
    4.  Sends the answers back as a JSON text string.

### `js/admin/dashboard.js` **(The Viewer)**
-   **Function**: Handles the interactive parts of the dashboard.
-   **Key Function `refreshDashboardData(filters)`**:
    1.  It calls `dashboard_api.php` without reloading the page (AJAX).
    2.  It waits for the JSON answer.
    3.  It updates the **Chart.js** graphs instantly with the new numbers.

### `admin/manage_users.php`
-   **Logic**:
    -   Lists users by asking `$userRepo->getAllUsers()`.
    -   Uses `js/admin/manage_users.js` to handle the "Confirm Delete" modal popup.
    -   When you click "Delete", it sends a POST request back to itself to run `$userRepo->deleteUser()`.

---

## 4. User Pages & Activity Logic

### `pages/view_activity.php`
-   **Function**: The detailed view of a submission.
-   **Logic**:
    -   **Access Control**: Checks `$_SESSION['role']`. If you aren't an Admin or the owner of the activity, it kicks you out.
    -   **Approval Workflow**: Detects which button you clicked (Review, Recommend, Approve). It then timestamps that action in the database.
    -   **Signature Pad**: If you are the SDS (Superintendent), it captures the drawing from the canvas, saves it as an image file, and stores the path in the database.

### `hr/profile.php` / `admin/profile.php`
-   **Logic**:
    -   Displays user stats.
    -   **Certificate Upload**: When you select a file:
        1.  PHP checks if it is a valid image/PDF.
        2.  It moves the file to `uploads/certificates/`.
        3.  It updates the database record to point to that file.

---

## 5. Global Functions (`includes/functions/`)

-   **`file-functions.php`**: Helpers for saving signatures (`saveAdminSignature`).
-   **`activity-functions.php`**: Helpers for formatting activity data.

## Summary of Flow

1.  **User Action**: Clicks "Filter by Month" on Dashboard.
2.  **JavaScript**: `dashboard.js` catches the click.
3.  **AJAX**: Sends a stealthy message to `dashboard_api.php`.
4.  **Database**: API asks `ActivityRepository`, which asks MySQL.
5.  **Response**: Data comes back as JSON.
6.  **Update**: JavaScript redraws the charts using the new numbers.
