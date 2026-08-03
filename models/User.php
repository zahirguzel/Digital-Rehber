<?php
/**
 * User Model
 */

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    protected $table = 'users';
    
    /**
     * Register a new user
     */
    public function register($name, $email, $password) {
        $hashedPassword = SecurityHelper::hashPassword($password);
        
        return $this->create([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => 'user',
            'is_active' => 1
        ]);
    }
    
    /**
     * Attempt login
     */
    public function login($email, $password) {
        $user = $this->whereFirst('email', $email);
        
        if ($user && SecurityHelper::verifyPassword($password, $user['password'])) {
            if (isset($user['is_active']) && intval($user['is_active']) === 0) {
                return 'banned';
            }
            return $user;
        }
        
        return false;
    }
}
