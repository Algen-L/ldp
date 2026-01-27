<?php
/**
 * Activity Log Repository
 * Handles all database operations related to the activity_logs table.
 */
class ActivityLogRepository
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Log a new user action
     */
    public function logAction($userId, $action, $details = null, $ipAddress = null)
    {
        $stmt = $this->db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$userId, $action, $details, $ipAddress ?: $_SERVER['REMOTE_ADDR']]);
    }

    /**
     * Get logs for a specific user
     */
    public function getLogsByUser($userId, $limit = 50)
    {
        $stmt = $this->db->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all logs with filtering (Admin)
     */
    public function getAllLogs($filters = [])
    {
        $sql = "SELECT l.*, u.full_name as user_name, u.profile_picture 
                FROM activity_logs l 
                JOIN users u ON l.user_id = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (u.full_name LIKE ? OR u.office_station LIKE ? OR u.position LIKE ?)";
            $term = "%" . $filters['search'] . "%";
            array_push($params, $term, $term, $term);
        }

        if (!empty($filters['offices'])) {
            $placeholders = implode(',', array_fill(0, count($filters['offices']), '?'));
            $sql .= " AND u.office_station IN ($placeholders)";
            $params = array_merge($params, $filters['offices']);
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND l.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action_type'])) {
            if ($filters['action_type'] === 'Viewed Specific') {
                $sql .= " AND l.action LIKE 'Viewed Activity Details%'";
            } elseif ($filters['action_type'] === 'Viewed') {
                $sql .= " AND (l.action LIKE 'Viewed%' AND l.action NOT LIKE 'Viewed Activity Details%')";
            } else {
                $sql .= " AND l.action LIKE ?";
                $params[] = "%" . $filters['action_type'] . "%";
            }
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(l.created_at) >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(l.created_at) <= ?";
            $params[] = $filters['end_date'];
        }

        $sql .= " ORDER BY l.created_at DESC";

        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int) $filters['limit'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent actions for a list of users
     */
    public function getRecentActionsGrouped()
    {
        return $this->db->query("SELECT user_id, MAX(created_at) as last_action_time FROM activity_logs GROUP BY user_id")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
