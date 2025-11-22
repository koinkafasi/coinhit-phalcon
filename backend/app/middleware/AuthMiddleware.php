<?php

namespace Tahmin\Middleware;

use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Di\Injectable;

class AuthMiddleware extends Injectable
{
    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher)
    {
        // Get the current controller and action
        $controller = $dispatcher->getControllerName();
        $action = $dispatcher->getActionName();

        // Public endpoints that don't require authentication
        $publicEndpoints = [
            'auth' => ['login', 'register', 'refresh'],
            'index' => ['index'],
        ];

        // Check if endpoint is public
        if (isset($publicEndpoints[$controller]) &&
            in_array($action, $publicEndpoints[$controller])) {
            return true;
        }

        // Get Authorization header
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader) {
            $this->response->setStatusCode(401);
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'Authorization header missing'
            ]);
            $this->response->send();
            return false;
        }

        // Extract and verify token
        $jwtService = $this->getDI()->get('jwt');
        $token = $jwtService->extractTokenFromHeader($authHeader);

        if (!$token) {
            $this->response->setStatusCode(401);
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'Invalid authorization header format'
            ]);
            $this->response->send();
            return false;
        }

        $user = $jwtService->getUserFromToken($token);

        if (!$user) {
            $this->response->setStatusCode(401);
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'Invalid or expired token'
            ]);
            $this->response->send();
            return false;
        }

        // Check if user is active
        if (!$user->is_active) {
            $this->response->setStatusCode(403);
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'User account is inactive'
            ]);
            $this->response->send();
            return false;
        }

        // Store user in dispatcher for use in controllers
        $dispatcher->setParam('currentUser', $user);

        return true;
    }
}
