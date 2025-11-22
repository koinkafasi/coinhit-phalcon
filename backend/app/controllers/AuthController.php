<?php

namespace Tahmin\Controllers;

use Tahmin\Models\User;
use Tahmin\Models\UserActivity;

class AuthController extends BaseController
{
    /**
     * Login page
     */
    public function loginPageAction()
    {
        // Redirect if already logged in
        if ($this->session->has('auth')) {
            return $this->response->redirect('/user/dashboard');
        }

        $this->view->setMainView('layouts/main');
    }

    /**
     * Register page
     */
    public function registerPageAction()
    {
        // Redirect if already logged in
        if ($this->session->has('auth')) {
            return $this->response->redirect('/user/dashboard');
        }

        $this->view->setMainView('layouts/main');
    }

    /**
     * Logout
     */
    public function logoutAction()
    {
        $this->session->remove('auth');
        $this->flashSession->success('Başarıyla çıkış yaptınız');
        return $this->response->redirect('/');
    }

    /**
     * Register new user
     */
    public function registerAction()
    {
        $data = $this->request->getJsonRawBody(true);

        // Validate required fields
        $requiredFields = ['email', 'password', 'full_name'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->sendError("Field '{$field}' is required", 400);
            }
        }

        // Check if email already exists
        if (User::findFirst(['email = ?0', 'bind' => [$data['email']]])) {
            return $this->sendError('Email already registered', 400);
        }

        // Create new user
        $user = new User();
        $user->email = $data['email'];
        $user->password = $data['password']; // Will be hashed in beforeSave
        $user->full_name = $data['full_name'];
        $user->role = User::ROLE_USER;
        $user->membership_tier = User::TIER_FREE;
        $user->is_active = true;
        $user->is_verified = false;

        if (!$user->save()) {
            $messages = [];
            foreach ($user->getMessages() as $message) {
                $messages[] = $message->getMessage();
            }
            return $this->sendError('Failed to create user', 400, $messages);
        }

        // Log activity
        $this->logActivity($user, 'user_registered', 'User registered successfully');

        // Generate tokens
        $jwtService = $this->getDI()->get('jwt');
        $accessToken = $jwtService->generateAccessToken($user);
        $refreshToken = $jwtService->generateRefreshToken($user);

        return $this->sendSuccess([
            'user' => $user->toArray(),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ], 'User registered successfully', 201);
    }

    /**
     * Login user
     */
    public function loginAction()
    {
        $data = $this->request->getJsonRawBody(true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->sendError('Email and password are required', 400);
        }

        $user = User::findFirst(['email = ?0', 'bind' => [$data['email']]]);

        if (!$user || !$user->verifyPassword($data['password'])) {
            return $this->sendError('Invalid credentials', 401);
        }

        if (!$user->is_active) {
            return $this->sendError('Account is inactive', 403);
        }

        // Update last login
        $user->last_login_at = new \DateTime();
        $user->save();

        // Log activity
        $this->logActivity($user, 'user_login', 'User logged in');

        // Generate tokens
        $jwtService = $this->getDI()->get('jwt');
        $accessToken = $jwtService->generateAccessToken($user);
        $refreshToken = $jwtService->generateRefreshToken($user);

        return $this->sendSuccess([
            'user' => $user->toArray(),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ], 'Login successful');
    }

    /**
     * Refresh access token
     */
    public function refreshAction()
    {
        $data = $this->request->getJsonRawBody(true);

        if (empty($data['refresh_token'])) {
            return $this->sendError('Refresh token is required', 400);
        }

        $jwtService = $this->getDI()->get('jwt');
        $user = $jwtService->getUserFromToken($data['refresh_token']);

        if (!$user) {
            return $this->sendError('Invalid refresh token', 401);
        }

        $accessToken = $jwtService->generateAccessToken($user);

        return $this->sendSuccess([
            'access_token' => $accessToken,
        ], 'Token refreshed successfully');
    }

    /**
     * Get current user profile
     */
    public function meAction()
    {
        if (!$this->currentUser) {
            return $this->sendError('Not authenticated', 401);
        }

        return $this->sendSuccess([
            'user' => $this->currentUser->toArray(),
        ]);
    }

    /**
     * Log user activity
     */
    private function logActivity(User $user, string $action, string $description = '')
    {
        $activity = new UserActivity();
        $activity->user_id = $user->id;
        $activity->action = $action;
        $activity->description = $description;
        $activity->ip_address = $this->request->getClientAddress();
        $activity->user_agent = $this->request->getUserAgent();
        $activity->metadata = [
            'timestamp' => date('c'),
        ];
        $activity->save();
    }
}
