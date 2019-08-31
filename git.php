<?php
// remote repo ssh url to be verified
$config_remote = 'git@bitbucket.org:turkeryildirim/bitbucket-webhook';

// remote repository name
$config_remote_name = 'bitbucket-webhook';

// full path of the local folder
$config_locals = [
    'master' => '/home/development/domains/development.name.tr/public_html/',
];

// tracking branch to be deployed to local
$config_branch = [ 'master' ];

// tracking event
$config_action = 'repo:push';

// secret word will be used in webhook url
$config_secret = 'secret';

// token weill be used in Post header
$config_token = 'token';

// allowed bitbucket usernames, * means all
$config_allowed_users = [ '*' ];

// allow connections from
$config_ip_white_list = [
    '18.205.93.0/25',
    '18.234.32.128/25',
    '13.52.5.0/25',
    '127.0.0.1',
];

$config_remote_user_agent = 'Bitbucket-Webhooks/2.0';

// executables
$config_php_bin_path      = '/usr/bin/php';
$config_git_bin_path      = '/usr/bin/git';
$config_composer_bin_path = '/usr/bin/composer';

/* ========================================================================================================== */
if (! isset($_GET['secret']) || $_GET['secret'] != $config_token) {
    die('No access');
}

$required_keys = [
    'X-Event-Key',
    'X-Hook-Uuid',
    'X-Request-Uuid',
    'X-Attempt-Number',
    'Content-Type',
    'User-Agent',
];

$headers = getheaders();
if (count(array_intersect_key(array_flip($required_keys), $headers)) != count($required_keys)) {
    die('Missing key(s) in header');
}

if (! isset($headers['X-Event-Key']) || $headers['X-Event-Key'] != $config_action) {
    die('No PUSH event, instead: ' . $headers['X-Event-Key']);
}

if (! isset($headers['User-Agent']) || $headers['User-Agent'] != $config_remote_user_agent) {
    die('Incorrect user agent in header: ' . $headers['User-Agent']);
}

if (! isset($headers['Content-Type']) || $headers['Content-Type'] != "application/json") {
    die('Incorrect content type: ' . $headers['Content-Type']);
}

if (! check_ip_white_list($_SERVER['REMOTE_ADDR'], $config_ip_white_list)) {
    die('Request IP is not in white list');
}

$payload = file_get_contents('php://input');
if (empty($payload)) {
    die('No PAYLOAD');
}

if (! $payload = json_decode($payload)) {
    die('Json issue');
}

if ($config_allowed_users[0] != '*') {
    if (! in_array($payload->actor->username, $config_allowed_users)) {
        die($payload->actor->username . ' does not have access rights');
    }
}

if ($payload->repository->name != $config_remote_name) {
    die($payload->repository->name . ' is not a correct repository');
}

$ref = $payload->push->changes[0]->new->name;
if (! in_array($ref, $config_branch)) {
    die($ref . ' is not a matching branch');
}

$dir = $config_locals[ $ref ];
if (__DIR__ != rtrim($dir, '/')) {
    die($dir . ' is not a correct directory for ' . $ref);
}

$x1 = shell_exec('cd ' . $dir . ' && ' . $config_git_bin_path . ' fetch 2>&1');
$x2 = shell_exec('cd ' . $dir . ' && ' . $config_git_bin_path . ' checkout ' . $ref . ' 2>&1');
$x3 = shell_exec('cd ' . $dir . ' && ' . $config_git_bin_path . ' pull origin ' . $ref . ' 2>&1');
//$x4 = shell_exec( 'cd ' . $config_local . ' && ' . $config_composer_bin_path . ' dumpautoload 2>&1' );
var_dump($x1, $x2, $x3);


function getheaders()
{
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $name             = str_replace(
                ' ',
                '-',
                ucwords(strtolower(str_replace('_', ' ', substr($name, 5))))
            );
            $headers[ $name ] = $value;
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

    $new_list = [];
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
    list( $range, $netmask ) = explode('/', $range, 2);
    $range_decimal    = ip2long($range);
    $ip_decimal       = ip2long($ip);
    $wildcard_decimal = pow(2, ( 32 - $netmask )) - 1;
    $netmask_decimal  = ~$wildcard_decimal;

    return ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal );
}
