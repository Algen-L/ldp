<?php
/**
 * ILDN Repository
 * Handles all database operations related to the user_ildn table.
 */
class ILDNRepository
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Get all ILDNs for a user with usage counts
     */
    public function getILDNsByUser($userId)
    {
        $sql = "SELECT i.*, 
                (SELECT COUNT(*) FROM ld_activities l WHERE l.user_id = i.user_id AND FIND_IN_SET(i.need_text, l.competency)) as usage_count
                FROM user_ildn i
                WHERE i.user_id = ?
                ORDER BY i.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get simple list of ILDNs for selection
     */
    public function getILDNList($userId)
    {
        $stmt = $this->db->prepare("SELECT * FROM user_ildn WHERE user_id = ? ORDER BY need_text ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add new development need
     */
    public function createILDN($userId, $needText, $description = null)
    {
        $stmt = $this->db->prepare("INSERT INTO user_ildn (user_id, need_text, description) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $needText, $description]);
    }

    /**
     * Delete development need
     */
    public function deleteILDN($ildnId, $userId)
    {
        $stmt = $this->db->prepare("DELETE FROM user_ildn WHERE id = ? AND user_id = ?");
        return $stmt->execute([$ildnId, $userId]);
    }

    /**
     * Update development need
     */
    public function updateILDN($ildnId, $userId, $data)
    {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $ildnId;
        $params[] = $userId;

        $sql = "UPDATE user_ildn SET " . implode(", ", $fields) . " WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Calculate unaddressed needs count
     */
    public function getUnaddressedCount($userId)
    {
        $sql = "SELECT COUNT(*) FROM user_ildn i 
                WHERE i.user_id = ? AND 
                (SELECT COUNT(*) FROM ld_activities l WHERE l.user_id = i.user_id AND FIND_IN_SET(i.need_text, l.competency)) = 0";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
