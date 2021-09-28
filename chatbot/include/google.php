<?php

/*
 * ==========================================================
 * GOOGLE.PHP
 * ==========================================================
 *
 * Google and Dialogflow synchronization.
 * © 2021 board.support. All rights reserved.
 *
 */

require('functions.php');

if (isset($_GET['code'])) {
    $client_id = sb_get_multi_setting('google', 'google-client-id');
    $client_secret = sb_get_multi_setting('google', 'google-client-secret');
    if (empty($client_id) || empty($client_secret)) {
        die('<br><br>Please insert Client ID and Client Secret in Support Board > Settings > Dialogflow > Google app details.');
    }
    $query = '{ code: "' . $_GET['code'] . '", grant_type: "authorization_code", client_id: "' . $client_id . '", client_secret: "' . $client_secret . '", redirect_uri: "' . SB_URL . '/include/google.php" }';
    $response = sb_curl('https://accounts.google.com/o/oauth2/token', $query, ['Content-Type: application/json', 'Content-Length: ' . strlen($query)]);
    if ($response && isset($response['refresh_token'])) {
        die('<br><br>Copy the refresh token below and paste it in Support Board > Settings > Dialogflow > Refresh token.<br><br><b>' . $response['refresh_token'] . '</b>');
    } else {
        echo '<br><br>Error: ' . print_r($response, true);
    }
}

if (isset($_GET['refresh-token'])) {
    $query = '{ refresh_token: "' . $_GET['refresh-token'] . '", grant_type: "refresh_token", client_id: "' . sb_get_multi_setting('google', 'google-client-id') . '", client_secret: "' . sb_get_multi_setting('google', 'google-client-secret') . '" }';
    die(json_encode(sb_curl('https://accounts.google.com/o/oauth2/token', $query, ['Content-Type: application/json', 'Content-Length: ' . strlen($query)])));
}

?>