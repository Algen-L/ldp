<?php
/**
 * Activity Repository
 * Handles all database operations related to the ld_activities table.
 */
class ActivityRepository
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Get activity by ID
     */
    public function getActivityById($activityId)
    {
        $sql = "SELECT ld.*, u.full_name, u.office_station, u.position as user_position, u.profile_picture 
                FROM ld_activities ld 
                JOIN users u ON ld.user_id = u.id 
                WHERE ld.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$activityId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get activities for a specific user with filtering
     */
    public function getActivitiesByUser($userId, $filters = [])
    {
        $whereClauses = ["user_id = ?"];
        $params = [$userId];

        if (!empty($filters['search'])) {
            $whereClauses[] = "title LIKE ?";
            $params[] = "%" . $filters['search'] . "%";
        }

        if (isset($filters['status'])) {
            if ($filters['status'] === 'Approved') {
                $whereClauses[] = "approved_sds = 1";
            } elseif ($filters['status'] === 'Recommending') {
                $whereClauses[] = "recommending_asds = 1 AND approved_sds = 0";
            } elseif ($filters['status'] === 'Reviewed') {
                $whereClauses[] = "reviewed_by_supervisor = 1 AND recommending_asds = 0";
            } elseif ($filters['status'] === 'Pending') {
                $whereClauses[] = "reviewed_by_supervisor = 0";
            }
        }

        if (!empty($filters['start_date'])) {
            $whereClauses[] = "DATE(date_attended) >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $whereClauses[] = "DATE(date_attended) <= ?";
            $params[] = $filters['end_date'];
        }

        $sql = "SELECT * FROM ld_activities WHERE " . implode(" AND ", $whereClauses) . " ORDER BY created_at DESC";

        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int) $filters['limit'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create new activity
     */
    public function createActivity($data)
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));

        $sql = "INSERT INTO ld_activities ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute(array_values($data))) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Update existing activity
     */
    public function updateActivity($activityId, $userId, $data)
    {
        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }

        $params[] = $activityId;
        $params[] = $userId;

        $sql = "UPDATE ld_activities SET " . implode(", ", $fields) . " WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update activity approval status
     */
    public function updateApprovalStatus($activityId, $stage, $time, $extraData = [])
    {
        $sql = "";
        $params = [$time, $activityId];

        if ($stage === 'supervisor') {
            $sql = "UPDATE ld_activities SET reviewed_by_supervisor = 1, reviewed_at = ? WHERE id = ?";
        } elseif ($stage === 'asds') {
            $sql = "UPDATE ld_activities SET recommending_asds = 1, recommended_at = ? WHERE id = ?";
        } elseif ($stage === 'sds') {
            $sql = "UPDATE ld_activities SET approved_sds = 1, approved_at = ?, approved_by = ?, signature_path = ? WHERE id = ?";
            array_splice($params, 1, 0, [$extraData['approved_by'], $extraData['signature_path']]);
        }

        if (empty($sql))
            return false;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update activity status field (Viewed/Pending etc)
     */
    public function updateStatus($activityId, $status)
    {
        $stmt = $this->db->prepare("UPDATE ld_activities SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $activityId]);
    }

    /**
     * Delete activity
     */
    public function deleteActivity($activityId, $userId)
    {
        $stmt = $this->db->prepare("DELETE FROM ld_activities WHERE id = ? AND user_id = ?");
        return $stmt->execute([$activityId, $userId]);
    }

    /**
     * Get activity statistics for a user
     */
    public function getUserStats($userId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN approved_sds = 1 THEN 1 ELSE 0 END) as approved FROM ld_activities WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all activities with advanced filtering (Admin)
     */
    public function getAllActivities($filters = [])
    {
        $sql = "SELECT ld.*, ld.created_at as activity_created_at, u.full_name, u.office_station, u.profile_picture,
                o.category as office_division
                FROM ld_activities ld 
                JOIN users u ON ld.user_id = u.id 
                LEFT JOIN offices o ON UPPER(u.office_station) = UPPER(o.name)
                WHERE 1=1";
        $params = [];

        // Handle temporal shortcuts for dashboard
        if (!empty($filters['filter_type'])) {
            if ($filters['filter_type'] === 'week') {
                $sql .= " AND ld.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            } elseif ($filters['filter_type'] === 'month') {
                $sql .= " AND ld.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            } elseif ($filters['filter_type'] === 'year') {
                $sql .= " AND ld.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            }
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND ld.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['status_filter'])) {
            if ($filters['status_filter'] === 'Reviewed') {
                $sql .= " AND ld.reviewed_by_supervisor = 1 AND ld.recommending_asds = 0";
            } elseif ($filters['status_filter'] === 'Recommending') {
                $sql .= " AND ld.recommending_asds = 1 AND ld.approved_sds = 0";
            } elseif ($filters['status_filter'] === 'Approved') {
                $sql .= " AND ld.approved_sds = 1";
            } elseif ($filters['status_filter'] === 'Pending') {
                $sql .= " AND ld.reviewed_by_supervisor = 0";
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (ld.title LIKE ? OR ld.competency LIKE ? OR u.full_name LIKE ? OR u.office_station LIKE ?)";
            $term = "%" . $filters['search'] . "%";
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['offices'])) {
            $placeholders = implode(',', array_fill(0, count($filters['offices']), '?'));
            $sql .= " AND u.office_station IN ($placeholders)";
            $params = array_merge($params, $filters['offices']);
        }

        if (!empty($filters['office_division'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM offices o WHERE UPPER(o.name) = UPPER(u.office_station) AND o.category = ?)";
            $params[] = $filters['office_division'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND ld.date_attended >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND ld.date_attended <= ?";
            $params[] = $filters['end_date'];
        }

        $sql .= " ORDER BY ld.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get counts for different statuses for all activities
     */
    public function getGlobalStatusStats()
    {
        $sql = "SELECT 
                    user_id,
                    COUNT(*) as total,
                    SUM(CASE WHEN approved_sds = 1 THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN reviewed_by_supervisor = 0 THEN 1 ELSE 0 END) as pending
                FROM ld_activities
                GROUP BY user_id";

        $stmt = $this->db->query($sql);
        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['user_id']] = $row;
        }
        return $stats;
    }

    /**
     * Get activity counts grouped by time units (week, month, year)
     */
    public function getTimelineData($userId, $type)
    {
        if ($type === 'week') {
            $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as time_key, COUNT(*) as count
                    FROM ld_activities 
                    WHERE user_id = ? AND created_at >= DATE((NOW() - INTERVAL 6 DAY))
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d')";
        } elseif ($type === 'month') {
            $sql = "SELECT YEARWEEK(created_at, 1) as time_key, COUNT(*) as count
                    FROM ld_activities 
                    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
                    GROUP BY YEARWEEK(created_at, 1)";
        } else {
            $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as time_key, COUNT(*) as count
                    FROM ld_activities 
                    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
