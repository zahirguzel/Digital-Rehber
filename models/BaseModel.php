<?php
/**
 * BaseModel
 * Abstract class providing base CRUD functionality for all models.
 */

require_once __DIR__ . '/../autoload.php';

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Find a record by its primary key
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Get all records
     */
    public function all($orderBy = 'id DESC', $limit = null) {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy}";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        return $this->db->fetchAll($sql);
    }

    /**
     * Find records by condition
     */
    public function where($column, $value, $operator = '=') {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ?";
        return $this->db->fetchAll($sql, [$value]);
    }
    
    /**
     * Find a single record by condition
     */
    public function whereFirst($column, $value, $operator = '=') {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ? LIMIT 1";
        return $this->db->fetchOne($sql, [$value]);
    }

    /**
     * Create a new record
     */
    public function create(array $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        return $this->db->insert($sql, array_values($data));
    }

    /**
     * Update an existing record
     */
    public function update($id, array $data) {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        
        $sql = "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = ?";
        
        $params = array_values($data);
        $params[] = $id;
        
        return $this->db->execute($sql, $params);
    }

    /**
     * Delete a record
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->execute($sql, [$id]);
    }
}
