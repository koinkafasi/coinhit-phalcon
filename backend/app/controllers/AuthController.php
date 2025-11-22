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
     * Register new user (web form)
     */
    public function registerAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/auth/register');
        }

        $email = $this->request->getPost('email', 'email');
        $password = $this->request->getPost('password');
        $passwordConfirm = $this->request->getPost('password_confirm');
        $firstName = $this->request->getPost('first_name', 'string');
        $lastName = $this->request->getPost('last_name', 'string');
        $username = $this->request->getPost('username', 'alphanum');

        // Validation
        if (empty($email) || empty($password) || empty($firstName) || empty($lastName) || empty($username)) {
            $this->flashSession->error('Tüm alanları doldurmanız gerekiyor');
            return $this->response->redirect('/auth/register');
        }

        if ($password !== $passwordConfirm) {
            $this->flashSession->error('Şifreler eşleşmiyor');
            return $this->response->redirect('/auth/register');
        }

        if (strlen($password) < 6) {
            $this->flashSession->error('Şifre en az 6 karakter olmalıdır');
            return $this->response->redirect('/auth/register');
        }

        // Check if email already exists
        if (User::findFirst(['conditions' => 'email = ?0', 'bind' => [$email]])) {
            $this->flashSession->error('Bu e-posta adresi zaten kayıtlı');
            return $this->response->redirect('/auth/register');
        }

        // Check if username already exists
        if (User::findFirst(['conditions' => 'username = ?0', 'bind' => [$username]])) {
            $this->flashSession->error('Bu kullanıcı adı zaten kullanılıyor');
            return $this->response->redirect('/auth/register');
        }

        // Create new user
        $user = new User();
        $user->email = $email;
        $user->username = $username;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->full_name = trim($firstName . ' ' . $lastName);
        $user->role = 'user';
        $user->membership_tier = 'free';
        $user->is_active = true;
        $user->is_verified = false;
        $user->created_at = date('Y-m-d H:i:s');

        if (!$user->save()) {
            $this->flashSession->error('Kayıt sırasında bir hata oluştu');
            return $this->response->redirect('/auth/register');
        }

        $this->flashSession->success('Kayıt başarılı! Şimdi giriş yapabilirsiniz.');
        return $this->response->redirect('/auth/login');
    }

    /**
     * Login user (web form)
     */
    public function loginAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/auth/login');
        }

        $email = $this->request->getPost('email', 'email');
        $password = $this->request->getPost('password');

        if (empty($email) || empty($password)) {
            $this->flashSession->error('E-posta ve şifre gereklidir');
            return $this->response->redirect('/auth/login');
        }

        $user = User::findFirst(['conditions' => 'email = ?0', 'bind' => [$email]]);

        if (!$user || !password_verify($password, $user->password)) {
            $this->flashSession->error('Geçersiz kullanıcı adı veya şifre');
            return $this->response->redirect('/auth/login');
        }

        if (!$user->is_active) {
            $this->flashSession->error('Hesabınız aktif değil');
            return $this->response->redirect('/auth/login');
        }

        // Set session
        $this->session->set('auth', [
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'role' => $user->role
        ]);

        $this->flashSession->success('Başarıyla giriş yaptınız');
        return $this->response->redirect('/user/dashboard');
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
