<?php
/**
 * User Repository
 * Handles all database operations related to the users table.
 */
class UserRepository
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Get user by ID
     */
    public function getUserById($userId)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by Username
     */
    public function getUserByUsername($username)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update user profile information
     */
    public function updateUserProfile($userId, $data)
    {
        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }

        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update user role and office
     */
    public function updateUserRole($userId, $role, $office)
    {
        $stmt = $this->db->prepare("UPDATE users SET role = ?, office_station = ? WHERE id = ?");
        return $stmt->execute([$role, $office, $userId]);
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus($userId, $isActive)
    {
        $stmt = $this->db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        return $stmt->execute([$isActive ? 1 : 0, $userId]);
    }

    /**
     * Delete user
     */
    public function deleteUser($userId)
    {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    /**
     * Get all users with optional role filtering
     */
    public function getAllUsers($excludeRoles = [])
    {
        $query = "SELECT * FROM users";
        $params = [];

        if (!empty($excludeRoles)) {
            $placeholders = implode(',', array_fill(0, count($excludeRoles), '?'));
            $query .= " WHERE role NOT IN ($placeholders)";
            $params = $excludeRoles;
        }

        $query .= " ORDER BY full_name ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get users with activity metrics
     */
    public function getUsersWithMetrics()
    {
        $sql = "SELECT 
                    u.*, u.created_at as joined_at,
                    (SELECT id FROM ld_activities WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as latest_activity_id,
                    (SELECT title FROM ld_activities WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as latest_activity_title,
                    (SELECT MAX(created_at) FROM ld_activities WHERE user_id = u.id) as latest_submission,
                    (SELECT created_at FROM activity_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_action_time
                FROM users u
                WHERE u.role != 'admin' AND u.role != 'super_admin' 
                ORDER BY latest_submission DESC";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total non-admin user count
     */
    public function getTotalUserCount()
    {
        return $this->db->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
    }

    /**
     * Get all users with administrative details (creator, last login)
     */
    public function getUsersForManagement($filters = [])
    {
        $sql = "SELECT u.id, u.username, u.full_name, u.office_station, u.role, u.is_active, u.created_at, u.profile_picture,
                creator.full_name as creator_name,
                (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id AND action = 'Logged In') as last_login
                FROM users u 
                LEFT JOIN users creator ON u.created_by = creator.id
                WHERE u.is_active = 1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ?)";
            $term = "%" . $filters['search'] . "%";
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['role'])) {
            $sql .= " AND u.role = ?";
            $params[] = $filters['role'];
        }

        if (!empty($filters['office'])) {
            $sql .= " AND u.office_station LIKE ?";
            $params[] = "%" . $filters['office'] . "%";
        }

        $sql .= " ORDER BY u.full_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create new user
     */
    public function createUser($data)
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));

        $sql = "INSERT INTO users ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    /**
     * Get pending users (is_active = 0)
     */
    public function getPendingUsers()
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE is_active = 0 ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Activate a user
     */
    public function activateUser($userId)
    {
        $stmt = $this->db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
        return $stmt->execute([$userId]);
    }
}
