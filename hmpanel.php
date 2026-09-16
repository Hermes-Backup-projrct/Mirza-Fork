<?php
require_once 'config.php';
require_once 'request.php';
date_default_timezone_set('Asia/Tehran');

/**
 * ---------------------------------------------------------------------------
 *  HMPanel (Community Edition) integration layer for Mirza Bot
 * ---------------------------------------------------------------------------
 *  HMPanel is a NestJS management layer built ON TOP of 3x-ui. Unlike a raw
 *  3x-ui panel, it does NOT expose /panel/api/* publicly — nginx only proxies
 *  /api/ (NestJS backend), /s/ and /sub/ (subscription endpoints). Therefore
 *  every operation has to go through the HMPanel REST API.
 *
 *  Authentication:
 *      POST /api/auth/login  { username, password } -> { accessToken, refreshToken }
 *      accessToken lives 24h, refreshToken 30d. The token pair is cached in the
 *      `datelogin` column exactly like the Marzban integration does it, and is
 *      transparently refreshed once it is older than 20 hours.
 *
 *  Identifier note:
 *      HMPanel keys clients by an internal UUID (`id`), while Mirza Bot keys
 *      them by email. hmpanel_find_client() resolves email -> record (which
 *      exposes the UUID) before issuing any mutation.
 *
 *  Inbound note:
 *      HMPanel `POST /api/clients` accepts inboundIds as Prisma UUIDs, NOT the
 *      numeric 3x-ui ids. numeric_to_uuid_inbounds() maps them once per panel.
 *
 *  All HTTP is done through CurlRequest (same as x-ui_single.php).
 */

#-----------------------------
# Authentication
#-----------------------------

function hmpanel_token($code_panel)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    if ($panel === null || $panel === false) {
        return array("error" => "Panel Not Found");
    }

    if (!empty($panel['datelogin'])) {
        $cached = json_decode($panel['datelogin'], true);
        if (is_array($cached) && !empty($cached['time']) && !empty($cached['accessToken'])) {
            $age = time() - strtotime($cached['time']);
            if ($age !== false && $age >= 0 && $age < 72000) {
                return array(
                    'accessToken'  => $cached['accessToken'],
                    'refreshToken' => isset($cached['refreshToken']) ? $cached['refreshToken'] : null,
                );
            }
            if (!empty($cached['refreshToken']) && $age < 2592000) {
                $refreshed = hmpanel_refresh($panel, $cached['refreshToken']);
                if (empty($refreshed['error'])) {
                    return $refreshed;
                }
            }
        }
    }

    return hmpanel_login($panel);
}

function hmpanel_login($panel)
{
    $url = rtrim($panel['url_panel'], '/') . '/api/auth/login';
    $payload = json_encode(array(
        'username' => $panel['username_panel'],
        'password' => $panel['password_panel'],
    ));

    $req = new CurlRequest($url);
    $req->setHeaders(array(
        'Accept: application/json',
        'Content-Type: application/json',
    ));
    $response = $req->post($payload);

    if (!empty($response['error'])) {
        return array("error" => $response['error']);
    }
    $body = json_decode(isset($response['body']) ? $response['body'] : '', true);
    if (empty($body['accessToken'])) {
        $detail = is_array($body) && !empty($body['message'])
            ? $body['message']
            : ('HTTP ' . (isset($response['status']) ? $response['status'] : 0));
        return array("error" => $detail);
    }

    $cache = json_encode(array(
        'time'         => date('Y/m/d H:i:s'),
        'accessToken'  => $body['accessToken'],
        'refreshToken' => isset($body['refreshToken']) ? $body['refreshToken'] : null,
    ));
    update("marzban_panel", "datelogin", $cache, 'name_panel', $panel['name_panel']);

    return array(
        'accessToken'  => $body['accessToken'],
        'refreshToken' => isset($body['refreshToken']) ? $body['refreshToken'] : null,
    );
}

function hmpanel_refresh($panel, $refreshToken)
{
    $url = rtrim($panel['url_panel'], '/') . '/api/auth/refresh';
    $payload = json_encode(array('refreshToken' => $refreshToken));

    $req = new CurlRequest($url);
    $req->setHeaders(array(
        'Accept: application/json',
        'Content-Type: application/json',
    ));
    $response = $req->post($payload);

    if (!empty($response['error'])) {
        return array("error" => $response['error']);
    }
    $body = json_decode(isset($response['body']) ? $response['body'] : '', true);
    if (empty($body['accessToken'])) {
        return array("error" => 'refresh failed');
    }

    $cache = json_encode(array(
        'time'         => date('Y/m/d H:i:s'),
        'accessToken'  => $body['accessToken'],
        'refreshToken' => isset($body['refreshToken']) ? $body['refreshToken'] : $refreshToken,
    ));
    update("marzban_panel", "datelogin", $cache, 'name_panel', $panel['name_panel']);

    return array(
        'accessToken'  => $body['accessToken'],
        'refreshToken' => isset($body['refreshToken']) ? $body['refreshToken'] : $refreshToken,
    );
}

function hmpanel_request($url, array $token)
{
    $req = new CurlRequest($url);
    $req->setHeaders(array(
        'Accept: application/json',
        'Content-Type: application/json',
    ));
    $req->setBearerToken($token['accessToken']);
    return $req;
}

function hmpanel_call($panel, $path, $method = 'GET', $payload = null)
{
    $token = hmpanel_token($panel['code_panel']);
    if (!empty($token['error'])) {
        return array('status' => null, 'body' => null, 'error' => $token['error']);
    }

    $url = rtrim($panel['url_panel'], '/') . '/' . ltrim($path, '/');
    if (is_array($payload)) {
        $payload = json_encode($payload);
    }

    $req = hmpanel_request($url, $token);
    $method = strtoupper($method);
    $response = hmpanel_dispatch($req, $method, $payload);

    if ((isset($response['status']) ? $response['status'] : 0) === 401) {
        $forced = hmpanel_login($panel);
        if (empty($forced['error'])) {
            $req = hmpanel_request($url, $forced);
            $response = hmpanel_dispatch($req, $method, $payload);
        }
    }
    return $response;
}

function hmpanel_dispatch($req, $method, $payload)
{
    switch (strtoupper($method)) {
        case 'POST':
            return $req->post($payload);
        case 'PATCH':
            return $req->PATCH($payload);
        case 'DELETE':
            return $req->delete($payload);
        case 'PUT':
            return $req->put($payload);
        default:
            return $req->get();
    }
}

#-----------------------------
# Inbound mapping
#-----------------------------

function hmpanel_inbounds($panel)
{
    $response = hmpanel_call($panel, '/api/panels/' . $panel['hmpanel_id'] . '/inbounds', 'GET');
    if (!empty($response['error']) || empty($response['body'])) {
        return array();
    }
    $inbounds = json_decode($response['body'], true);
    return is_array($inbounds) ? $inbounds : array();
}

function numeric_to_uuid_inbounds($panel, $inboundIds)
{
    $numeric = is_string($inboundIds) ? json_decode($inboundIds, true) : $inboundIds;
    $numeric = is_array($numeric) ? array_values(array_filter(array_map('intval', $numeric))) : array();
    if (empty($numeric)) {
        return array();
    }

    $map = array();
    foreach (hmpanel_inbounds($panel) as $inbound) {
        if (isset($inbound['panelInboundId'])) {
            $map[intval($inbound['panelInboundId'])] = $inbound['id'];
        }
    }

    $uuids = array();
    foreach ($numeric as $id) {
        if (isset($map[$id])) {
            $uuids[] = $map[$id];
        }
    }
    return array_values(array_unique($uuids));
}

#-----------------------------
# Client lookup
#-----------------------------

function hmpanel_find_client($panel, $username)
{
    $params = http_build_query(array(
        'search'  => $username,
        'limit'   => 50,
    ));
    $response = hmpanel_call($panel, '/api/clients?' . $params, 'GET');
    if (!empty($response['error']) || empty($response['body'])) {
        return null;
    }
    $body = json_decode($response['body'], true);
    if (!is_array($body) || empty($body['data'])) {
        return null;
    }
    foreach ($body['data'] as $client) {
        if (isset($client['email']) && strtolower($client['email']) === strtolower($username)) {
            return $client;
        }
    }
    return null;
}

#-----------------------------
# Client operations
#-----------------------------

function hmpanel_add_client($panel, $username, $expire, $total, $inboundIds, $note = '')
{
    $inboundUuids = numeric_to_uuid_inbounds($panel, $inboundIds);
    if (empty($inboundUuids)) {
        return array(
            'status' => null,
            'body'   => null,
            'error'  => 'No valid inbound for this panel - sync inbounds in HMPanel first.',
        );
    }

    $expiryTime = 0;
    if ($expire != 0) {
        $expiryTime = intval($expire) * 1000;
    }

    $payload = array(
        'email'       => $username,
        'inboundIds'  => $inboundUuids,
        'total'       => intval($total),
        'expiryTime'  => $expiryTime,
        'enable'      => true,
    );
    if (!empty($note)) {
        $payload['remark'] = $note;
    }

    return hmpanel_call($panel, '/api/clients', 'POST', $payload);
}

function hmpanel_get_client($panel, $username)
{
    $client = hmpanel_find_client($panel, $username);
    if (!is_array($client) || empty($client['id'])) {
        return array('status' => 'Unsuccessful', 'msg' => 'User not found');
    }

    $response = hmpanel_call($panel, '/api/clients/' . $client['id'], 'GET');
    if (!empty($response['error'])) {
        return array('status' => 'Unsuccessful', 'msg' => $response['error']);
    }
    if ((isset($response['status']) ? $response['status'] : 0) !== 200) {
        return array('status' => 'Unsuccessful', 'msg' => 'error code : ' . $response['status']);
    }
    $detail = json_decode($response['body'], true);
    if (!is_array($detail)) {
        return array('status' => 'Unsuccessful', 'msg' => 'object invalid');
    }

    $up   = isset($detail['up']) ? intval($detail['up']) : 0;
    $down = isset($detail['down']) ? intval($detail['down']) : 0;
    $used = $up + $down;

    $total = isset($detail['total']) ? intval($detail['total']) : 0;
    $expiryTime = isset($detail['expiryTime']) ? intval($detail['expiryTime']) : 0;
    $expire = $expiryTime > 0 ? intval($expiryTime / 1000) : 0;

    $status = !empty($detail['enable']) ? 'active' : 'disabled';
    if ($total != 0 && ($total - $used) <= 0) {
        $status = 'limited';
    }
    if ($expiryTime != 0 && ($expire - time()) <= 0) {
        $status = 'expired';
    }
    if ($expiryTime < -10000) {
        $status = 'on_hold';
        $expire = 0;
    }

    $subId = null;
    if (isset($detail['subId'])) {
        $subId = $detail['subId'];
    } elseif (isset($detail['subToken'])) {
        $subId = $detail['subToken'];
    }

    $linksub = null;
    $links_user = array();
    if (!empty($subId)) {
        $base = !empty($panel['linksubx']) ? $panel['linksubx'] : rtrim($panel['url_panel'], '/');
        $linksub = rtrim($base, '/') . '/s/' . $subId;
        $links_user = hmpanel_fetch_subscription_links($linksub);
    }

    return array(
        'status'              => $status,
        'username'            => isset($detail['email']) ? $detail['email'] : $username,
        'data_limit'          => $total,
        'expire'              => $expire,
        'used_traffic'        => $used,
        'online_at'           => null,
        'links'               => $links_user,
        'subscription_url'    => $linksub,
        'sub_updated_at'      => null,
        'sub_last_user_agent' => null,
    );
}

function hmpanel_fetch_subscription_links($url)
{
    $req = new CurlRequest($url);
    $req->setHeaders(array('Accept: text/plain'));
    $response = $req->get();
    if (!empty($response['error']) || empty($response['body'])) {
        return array();
    }
    $body = trim($response['body']);
    if ($body === '') {
        return array();
    }
    if (isBase64($body)) {
        $decoded = base64_decode($body, true);
        if ($decoded !== false && strpos($decoded, '://') !== false) {
            $body = $decoded;
        }
    }
    $links = explode("\n", $body);
    return array_values(array_filter(array_map('trim', $links)));
}

function hmpanel_update_client($panel, $username, array $config)
{
    $client = hmpanel_find_client($panel, $username);
    if (!is_array($client) || empty($client['id'])) {
        return array(
            'status' => null,
            'body'   => null,
            'error'  => 'User not found',
        );
    }

    $payload = array();
    foreach (array('enable', 'total', 'expiryTime', 'subId', 'remark', 'flow', 'limitIp') as $field) {
        if (array_key_exists($field, $config)) {
            $payload[$field] = $config[$field];
        }
    }
    if (empty($payload)) {
        return array(
            'status' => null,
            'body'   => null,
            'error'  => 'Nothing to update',
        );
    }

    return hmpanel_call($panel, '/api/clients/' . $client['id'], 'PATCH', $payload);
}

function hmpanel_remove_client($panel, $username)
{
    $client = hmpanel_find_client($panel, $username);
    if (!is_array($client) || empty($client['id'])) {
        return array(
            'status' => null,
            'body'   => null,
            'error'  => 'User not found',
        );
    }
    return hmpanel_call($panel, '/api/clients/' . $client['id'], 'DELETE');
}

function hmpanel_reset_traffic($panel, $username)
{
    $client = hmpanel_find_client($panel, $username);
    if (!is_array($client) || empty($client['id'])) {
        return array(
            'status' => null,
            'body'   => null,
            'error'  => 'User not found',
        );
    }

    $response = hmpanel_call($panel, '/api/clients/' . $client['id'], 'GET');
    if (!empty($response['error']) || (isset($response['status']) ? $response['status'] : 0) !== 200) {
        return array(
            'status' => null,
            'body'   => null,
            'error'  => 'User not found',
        );
    }
    $detail = json_decode($response['body'], true);
    $total = intval(isset($detail['total']) ? $detail['total'] : 0);

    return hmpanel_call($panel, '/api/clients/' . $client['id'], 'PATCH', array(
        'total' => $total,
    ));
}

function hmpanel_revoke_sub($panel, $username)
{
    $newSubId = bin2hex(random_bytes(8));
    return hmpanel_update_client($panel, $username, array('subId' => $newSubId));
}

function hmpanel_panel_status($panel)
{
    $response = hmpanel_call($panel, '/api/panels/' . $panel['hmpanel_id'], 'GET');
    if (!empty($response['error'])) {
        return array('status' => false, 'error' => $response['error']);
    }
    if ((isset($response['status']) ? $response['status'] : 0) !== 200) {
        return array('status' => false, 'error' => 'HTTP ' . $response['status']);
    }
    $detail = json_decode($response['body'], true);
    if (!is_array($detail)) {
        return array('status' => false, 'error' => 'object invalid');
    }
    return array('status' => true, 'panel' => $detail);
}

function hmpanel_list_panels($panel)
{
    $response = hmpanel_call($panel, '/api/panels', 'GET');
    if (!empty($response['error'])) {
        return array('error' => $response['error']);
    }
    if ((isset($response['status']) ? $response['status'] : 0) !== 200) {
        return array('error' => 'HTTP ' . $response['status']);
    }
    $list = json_decode($response['body'], true);
    return is_array($list) ? $list : array('error' => 'object invalid');
}

#-----------------------------
# Panel id resolution (add-panel flow)
#-----------------------------

/**
 * Resolve the HMPanel internal UUID for a panel by its URL.
 *
 * Called from the add-panel flow once the admin has supplied url + admin
 * credentials. GET /api/panels returns every panel known to HMPanel; we match
 * on the base URL because HMPanel stores normalizedUrl/webBasePath/apiBaseUrl
 * separately and the plain url is the closest stable key.
 *
 * @param string $namepanel  panel name (for logging)
 * @param string $url        panel URL as entered by the admin
 * @param string $username   HMPanel admin username
 * @param string $password   HMPanel admin password
 * @return array ['hmpanel_id' => uuid] or ['error' => ...]
 */
function hmpanel_resolve_panel_id($namepanel, $url, $username, $password)
{
    $url = rtrim(trim($url), '/');
    $loginUrl = $url . '/api/auth/login';

    $payload = json_encode(array('username' => $username, 'password' => $password));
    $req = new CurlRequest($loginUrl);
    $req->setHeaders(array('Accept: application/json', 'Content-Type: application/json'));
    $response = $req->post($payload);

    if (!empty($response['error'])) {
        return array('error' => $response['error']);
    }
    $body = json_decode(isset($response['body']) ? $response['body'] : '', true);
    if (empty($body['accessToken'])) {
        return array('error' => 'HMPanel login failed');
    }

    $cache = json_encode(array(
        'time'         => date('Y/m/d H:i:s'),
        'accessToken'  => $body['accessToken'],
        'refreshToken' => isset($body['refreshToken']) ? $body['refreshToken'] : null,
    ));
    update("marzban_panel", "datelogin", $cache, 'name_panel', $namepanel);

    $req = new CurlRequest($url . '/api/panels');
    $req->setHeaders(array('Accept: application/json', 'Content-Type: application/json'));
    $req->setBearerToken($body['accessToken']);
    $listResponse = $req->get();

    if (!empty($listResponse['error'])) {
        return array('error' => $listResponse['error']);
    }
    $panels = json_decode(isset($listResponse['body']) ? $listResponse['body'] : '', true);
    if (!is_array($panels) || empty($panels)) {
        return array('error' => 'No panels found in HMPanel');
    }

    $wanted = strtolower($url);
    foreach ($panels as $panel) {
        $candidate = strtolower(rtrim(trim(isset($panel['url']) ? $panel['url'] : ''), '/'));
        if ($candidate === $wanted || strpos($candidate, $wanted) === 0 || strpos($wanted, $candidate) === 0) {
            if (!empty($panel['id'])) {
                return array('hmpanel_id' => $panel['id']);
            }
        }
    }
    // Fall back to the first panel when HMPanel only knows one.
    if (count($panels) === 1 && !empty($panels[0]['id'])) {
        return array('hmpanel_id' => $panels[0]['id']);
    }
    return array('error' => 'Panel not found in HMPanel');
}
