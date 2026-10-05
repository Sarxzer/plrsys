<?php
/**
 * Refresh an API token.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed']], 405);
}
