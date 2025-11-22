<?php

namespace Tahmin\Controllers;

use Phalcon\Mvc\Controller;
use Tahmin\Models\User;

class BaseController extends Controller
{
    protected ?User $currentUser = null;

    public function initialize()
    {
        $this->view->disable();

        // Get current user from dispatcher (set by AuthMiddleware)
        $this->currentUser = $this->dispatcher->getParam('currentUser');
    }

    /**
     * Send JSON success response
     */
    protected function sendSuccess($data = null, string $message = 'Success', int $statusCode = 200)
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $this->response->setStatusCode($statusCode);
        $this->response->setJsonContent($response);
        return $this->response->send();
    }

    /**
     * Send JSON error response
     */
    protected function sendError(string $message = 'Error', int $statusCode = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        $this->response->setStatusCode($statusCode);
        $this->response->setJsonContent($response);
        return $this->response->send();
    }

    /**
     * Paginate query results
     */
    protected function paginate($query, int $page = 1, int $limit = 20): array
    {
        $config = $this->getDI()->get('config');
        $maxLimit = $config->pagination->maxLimit ?? 100;
        $defaultLimit = $config->pagination->limit ?? 20;

        $limit = min($limit ?: $defaultLimit, $maxLimit);
        $page = max($page, 1);
        $offset = ($page - 1) * $limit;

        $total = $query->execute()->count();
        $data = $query->limit($limit, $offset)->execute()->toArray();

        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ]
        ];
    }
}
