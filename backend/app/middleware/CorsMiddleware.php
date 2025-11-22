<?php

namespace Tahmin\Middleware;

use Phalcon\Events\Event;
use Phalcon\Di\Injectable;

class CorsMiddleware extends Injectable
{
    public function beforeSendResponse(Event $event, $response)
    {
        $config = $this->getDI()->get('config')->cors;
        $request = $this->getDI()->get('request');

        $origin = $request->getHeader('Origin');

        // Check if origin is allowed
        if (in_array($origin, $config->allowedOrigins->toArray()) || in_array('*', $config->allowedOrigins->toArray())) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        }

        $response->setHeader('Access-Control-Allow-Credentials', $config->allowCredentials ? 'true' : 'false');
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $config->allowedMethods->toArray()));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $config->allowedHeaders->toArray()));
        $response->setHeader('Access-Control-Max-Age', '86400'); // 24 hours

        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatusCode(204);
            $response->setContent('');
        }

        return $response;
    }
}
