<?php

// full path of the local folder
$config_local = '/home/turkeryildirim/public_html/webhook-test';

// remote repo ssh url
$config_remote = 'git@bitbucket.org:turkeryildirim/webhook-test.git';

// tracking branch to be deployed to local
$config_branch = 'master';

// tracking event
$config_action = 'push';

// secret word will be used in webhook url
$config_secret = 'secret';

// * means all
$config_allowed_users = array('*');

// bitbucket ip list - https://confluence.atlassian.com/bitbucket/manage-webhooks-735643732.html#Managewebhooks-trigger_webhookTriggeringwebhooks
$config_ip_white_list = array(
    '104.192.136.0/21',
    '34.198.203.127',
    '34.198.178.64',
    '34.198.32.85',
    '127.0.0.1',
);

// git executable
$config_php_bin_path = '/usr/bin/php';
$config_git_bin_path = '/usr/bin/git';
$config_composer_bin_path = '/usr/bin/composer';

/* ========================================================================================================== */
if (!isset($_GET['secret']) || $_GET['secret'] != $config_secret) {
    die('No access');
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die('Request method is not POST');
}

$required_keys = array(
    'X-Request-Uuid',
    'X-Event-Key',
    'User-Agent',
    'X-Attempt-Number',
    'X-Hook-Uuid',
    'Content-Type',
    'Content-Length',
);

$headers = getheaders();
if (count(array_intersect_key(array_flip($required_keys), $headers)) != count($required_keys)) {
    die('Missing key(s) in header');
}

if (!isset($headers['Content-Type']) || $headers['Content-Type'] != 'application/json') {
    die('No event key in header');
}

if ($config_action == 'push' && $headers['X-Event-Key'] != 'repo:push') {
    die('No PUSH event, instead: '.$headers['X-Event-Key']);
}

if (!check_ip_white_list($_SERVER['REMOTE_ADDR'], $config_ip_white_list)) {
    die('Request IP is not in white list');
}

$payload = file_get_contents('php://input');
if (empty($payload)) {
    die('No PAYLOAD');
}

if (!$payload = json_decode($payload)) {
    die('Json issue');
}

if ($config_allowed_users[0] != '*') {
    if (!in_array($payload->actor->username, $config_allowed_users)) {
        die($payload->actor->username.' does not have access rights');
    }
}

$update = false;
$changes = $payload->push->changes;
foreach ($changes as $change) {
    if ($change->new->type == 'branch' && $change->new->name == $config_branch) {
        $update = true;
    }
}

if ($update) {
    $x1 = shell_exec('cd '.$config_local.' && '.$config_git_bin_path.' fetch 2>&1');
    $x2 = shell_exec('cd '.$config_local.' && '.$config_git_bin_path.' checkout '.$config_branch.' 2>&1');
    $x3 = shell_exec('cd '.$config_local.' && '.$config_git_bin_path.' pull origin '.$config_branch.' 2>&1');
    $x4 = shell_exec('cd '.$config_local.' && '.$config_composer_bin_path.' dumpautoload 2>&1');
    $x5 = shell_exec('cd '.$config_local.' && '.$config_php_bin_path.' artisan migrate:fresh --seed 2>&1');
    var_dump($x1, $x2, $x3, $x4, $x5);
}

function getheaders()
{
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$name] = $value;
        } elseif ($name == 'CONTENT_TYPE') {
            $headers['Content-Type'] = $value;
        } elseif ($name == 'CONTENT_LENGTH') {
            $headers['Content-Length'] = $value;
        } elseif ($name == 'USER_AGENT') {
            $headers['User-Agent'] = $value;
        }
    }

    return $headers;
}

function check_ip_white_list($remote_ip, $ip_white_list)
{
    if (empty($ip_white_list)) {
        return true;
    }

    $new_list = array();
    foreach ($ip_white_list as $ip) {
        if (stristr($ip, '/')) {
            if (ip_in_range($remote_ip, $ip)) {
                return true;
            }
        } else {
            $new_list[] = $ip;
        }
    }

    return in_array($remote_ip, $new_list);
}

function ip_in_range($ip, $range)
{
    if (strpos($range, '/') == false) {
        $range .= '/32';
    }
    // $range is in IP/CIDR format eg 127.0.0.1/24
    list($range, $netmask) = explode('/', $range, 2);
    $range_decimal = ip2long($range);
    $ip_decimal = ip2long($ip);
    $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
    $netmask_decimal = ~$wildcard_decimal;

    return  ($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal);
}
