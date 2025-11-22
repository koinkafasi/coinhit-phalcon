<?php

// API version prefix
$router->setDefaultNamespace('Tahmin\Controllers');

// ==================== FRONTEND ROUTES ====================
// Homepage
$router->addGet('/', [
    'controller' => 'index',
    'action' => 'index'
]);

// Auth pages
$router->addGet('/auth/login', [
    'controller' => 'auth',
    'action' => 'loginPage'
]);

$router->addGet('/auth/register', [
    'controller' => 'auth',
    'action' => 'registerPage'
]);

$router->addPost('/auth/login', [
    'controller' => 'auth',
    'action' => 'login'
]);

$router->addPost('/auth/register', [
    'controller' => 'auth',
    'action' => 'register'
]);

$router->addGet('/auth/logout', [
    'controller' => 'auth',
    'action' => 'logout'
]);

// Match pages
$router->addGet('/matches', [
    'controller' => 'match',
    'action' => 'listPage'
]);

$router->addGet('/matches/view/{id:[0-9]+}', [
    'controller' => 'match',
    'action' => 'viewPage'
]);

// Prediction pages
$router->addGet('/predictions', [
    'controller' => 'prediction',
    'action' => 'listPage'
]);

$router->addGet('/predictions/view/{id:[0-9]+}', [
    'controller' => 'prediction',
    'action' => 'viewPage'
]);

// Coupon pages
$router->addGet('/coupons', [
    'controller' => 'coupon',
    'action' => 'listPage'
]);

$router->addGet('/coupons/view/{id:[0-9]+}', [
    'controller' => 'coupon',
    'action' => 'viewPage'
]);

$router->addGet('/coupons/create', [
    'controller' => 'coupon',
    'action' => 'createPage'
]);

// User pages
$router->addGet('/user/dashboard', [
    'controller' => 'user',
    'action' => 'dashboard'
]);

$router->addGet('/user/profile', [
    'controller' => 'user',
    'action' => 'profile'
]);

// ==================== API ROUTES ====================
// Health check
$router->addGet('/api/health', [
    'controller' => 'index',
    'action' => 'health'
]);

// Auth routes
$router->addPost('/api/auth/register', [
    'controller' => 'auth',
    'action' => 'register'
]);

$router->addPost('/api/auth/login', [
    'controller' => 'auth',
    'action' => 'login'
]);

$router->addPost('/api/auth/refresh', [
    'controller' => 'auth',
    'action' => 'refresh'
]);

$router->addGet('/api/auth/me', [
    'controller' => 'auth',
    'action' => 'me'
]);

// Match routes
$router->addGet('/api/matches', [
    'controller' => 'match',
    'action' => 'index'
]);

$router->addGet('/api/matches/upcoming', [
    'controller' => 'match',
    'action' => 'upcoming'
]);

$router->addGet('/api/matches/live', [
    'controller' => 'match',
    'action' => 'live'
]);

$router->addGet('/api/matches/{id:[0-9]+}', [
    'controller' => 'match',
    'action' => 'show'
]);

$router->addGet('/api/leagues', [
    'controller' => 'match',
    'action' => 'leagues'
]);

// Prediction routes
$router->addGet('/api/predictions', [
    'controller' => 'prediction',
    'action' => 'index'
]);

$router->addGet('/api/predictions/featured', [
    'controller' => 'prediction',
    'action' => 'featured'
]);

$router->addGet('/api/predictions/high-confidence', [
    'controller' => 'prediction',
    'action' => 'highConfidence'
]);

$router->addGet('/api/predictions/{id:[0-9]+}', [
    'controller' => 'prediction',
    'action' => 'show'
]);

// Coupon routes
$router->addGet('/api/coupons', [
    'controller' => 'coupon',
    'action' => 'index'
]);

$router->addPost('/api/coupons', [
    'controller' => 'coupon',
    'action' => 'create'
]);

$router->addGet('/api/coupons/{id}', [
    'controller' => 'coupon',
    'action' => 'show'
]);

$router->addPut('/api/coupons/{id}', [
    'controller' => 'coupon',
    'action' => 'update'
]);

$router->addDelete('/api/coupons/{id}', [
    'controller' => 'coupon',
    'action' => 'delete'
]);

$router->addPost('/api/coupons/{id}/share', [
    'controller' => 'coupon',
    'action' => 'share'
]);

// Admin routes
$router->addGet('/api/admin/dashboard', [
    'controller' => 'admin',
    'action' => 'dashboard'
]);

$router->addGet('/api/admin/users', [
    'controller' => 'admin',
    'action' => 'users'
]);

$router->addPut('/api/admin/users/{id:[0-9]+}', [
    'controller' => 'admin',
    'action' => 'updateUser'
]);

$router->addGet('/api/admin/matches', [
    'controller' => 'admin',
    'action' => 'matches'
]);

$router->addPut('/api/admin/matches/{id:[0-9]+}', [
    'controller' => 'admin',
    'action' => 'updateMatch'
]);

$router->addGet('/api/admin/predictions', [
    'controller' => 'admin',
    'action' => 'predictions'
]);

$router->addPost('/api/admin/predictions', [
    'controller' => 'admin',
    'action' => 'createPrediction'
]);

$router->addPost('/api/admin/collect-data', [
    'controller' => 'admin',
    'action' => 'collectData'
]);

$router->addGet('/api/admin/analytics', [
    'controller' => 'admin',
    'action' => 'analytics'
]);

// Export routes
$router->addGet('/api/export/coupon/{id}/pdf', [
    'controller' => 'export',
    'action' => 'couponPdf'
]);

$router->addGet('/api/export/predictions/excel', [
    'controller' => 'export',
    'action' => 'predictionsExcel'
]);

$router->addGet('/api/export/user-stats/pdf', [
    'controller' => 'export',
    'action' => 'userStatsPdf'
]);

// Subscription routes
$router->addGet('/api/subscriptions/plans', [
    'controller' => 'subscription',
    'action' => 'plans'
]);

$router->addGet('/api/subscriptions/current', [
    'controller' => 'subscription',
    'action' => 'current'
]);

$router->addPost('/api/subscriptions', [
    'controller' => 'subscription',
    'action' => 'create'
]);

$router->addPost('/api/subscriptions/activate', [
    'controller' => 'subscription',
    'action' => 'activate'
]);

$router->addPost('/api/subscriptions/cancel', [
    'controller' => 'subscription',
    'action' => 'cancel'
]);

// Formula routes
$router->addGet('/api/formulas', [
    'controller' => 'formula',
    'action' => 'index'
]);

$router->addPost('/api/formulas', [
    'controller' => 'formula',
    'action' => 'create'
]);

$router->addPut('/api/formulas/{id:[0-9]+}', [
    'controller' => 'formula',
    'action' => 'update'
]);

$router->addDelete('/api/formulas/{id:[0-9]+}', [
    'controller' => 'formula',
    'action' => 'delete'
]);

// Handle OPTIONS requests for CORS
$router->add('/{any:.*}', [
    'controller' => 'index',
    'action' => 'options'
])->via(['OPTIONS']);

// 404 handler
$router->notFound([
    'controller' => 'index',
    'action' => 'notFound'
]);

return $router;
