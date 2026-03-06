<?php
/**
 * Karibu Pantry Planner — Auth Middleware
 * For API endpoints: validates session + returns JSON errors
 */

require_once __DIR__ . '/config.php';

function requireAuth() {
    if (!isLoggedIn()) {
        jsonError('Not authenticated', 401);
    }
    return currentUser();
}

function requireRole($roles) {
    $user = requireAuth();
    if (!in_array($user['role'], (array)$roles)) {
        jsonError('Access denied', 403);
    }
    return $user;
}

function requireMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        jsonError('Method not allowed', 405);
    }
}

function requireKitchen() {
    $user = requireAuth();
    if (empty($user['kitchen_id'])) {
        jsonError('No kitchen assigned. Contact admin.', 403);
    }
    return $user;
}
