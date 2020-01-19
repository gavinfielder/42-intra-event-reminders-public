<?php

require_once(__DIR__.'/error.php');
require_once(__DIR__.'/IAuthenticationHandler.php');

abstract class  AuthenticationHandler implements IAuthenticationHandler {
    protected const CLIENT_ID = FT_API_CLIENT_ID;
    protected const CLIENT_SECRET = FT_API_CLIENT_SECRET;
    protected const REDIRECT_URI = FT_API_REDIRECT_URI;
    protected const AUTH_DIALOG = "https://api.intra.42.fr/oauth/authorize?"
        ."client_id=".self::CLIENT_ID
        ."&redirect_uri=".self::REDIRECT_URI
        ."&response_type=code&scope=public&state=authorizing";

    abstract public function refresh_access();
    abstract public function authorize();
    abstract public function get_current_token();

    public function check_token_validity($token) {
        try {
            $url = 'https://api.intra.42.fr/oauth/token/info';
            if (is_array($token))
                $token = $token['access_token'];
            $options = array('http' => array(
                'header' => "Authorization: Bearer ".$token."\r\n",
                'method' => 'GET',
            ));
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            if ($result === false) {
                contextual_error_log('API call failed while getting token info');
                return (null);
            }
            $arr = json_decode(trim($result), true);
            if (isset($arr['expires_in_seconds']) && intval($arr['expires_in_seconds']) > 20)
                return (true);
            return (false);
        }
        catch (Exception $ex) {
            contextual_error_log("check_token_validity failed: ".$ex->getMessage());
            return (null);
        }
    }

    protected function refresh_token_request($refresh_token) {
        try {
            if (is_array($refresh_token))
                $refresh_token = $refresh_token['refresh_token'];
            $url = 'https://api.intra.42.fr/oauth/token';
            $query = http_build_query(array(
                'grant_type' => 'refresh_token',
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
                'refresh_token' => $refresh_token,
                'redirect_uri' => self::REDIRECT_URI,
                'state' => 'authorizing'
            ));
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n".
                                 "Content-Length: ".strlen($query)."\r\n",
                    'method'  => 'POST',
                    'content' => $query
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            if ($result === FALSE) {
                contextual_error_log('could not get refreshed access token'
                    ."\n   query=\n"
                    .var_export($query, true)."\n"
                );
                return (false);
            }
            $arr = json_decode(trim($result), true);
            if (!(isset($arr['access_token']))) {
                contextual_error_log('access_token was not found in the token exchange response');
                contextual_error_log("Response: $result");
                return (false);
            }
            if (isset($arr['expires_in']))
                $arr['expires_in'] = intval($arr['expires_in']);
            $arr['timestamp'] = time();
            return ($arr);
        }
        catch (Exception $ex) {
            contextual_error_log("AuthenticationHandler: could not refresh token. "
                ."Exception Message: ".$ex->getMessage());
            return (false);
        }
    }
    protected function token_expires_in($token) {
        if (!is_array($token))
            return (-1);
        if (isset($token['expires_in']) && isset($token['timestamp'])) {
            return ($token['expires_in'] - (time() - $token['timestamp']));
        }
        return (-1);
    }
}

class   CliAuthenticationHandler extends AuthenticationHandler {
    private $token;

    function __construct() {
        $this->token = null;
    }

    private function get_new_access_token() {
        try {
            $url = 'https://api.intra.42.fr/oauth/token';
            $query = http_build_query(array(
                'grant_type' => 'client_credentials',
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
                'redirect_uri' => self::REDIRECT_URI,
                'state' => 'authorizing'
            ));
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n".
                                 "Content-Length: ".strlen($query)."\r\n",
                    'method'  => 'POST',
                    'content' => $query
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            if ($result === false) {
                cli_error_log('could not get access token');
                return (false);
            }
            $arr = json_decode(trim($result), true);
            if (!(isset($arr['access_token']))) {
                cli_error_log('access_token was not found in the token exchange response');
                cli_error_log("Response: $result");
                return (false);
            }
            if (isset($arr['expires_in']))
                $arr['expires_in'] = intval($arr['expires_in']);
            $arr['timestamp'] = time();
            return ($arr);
        }
        catch (Exception $ex) {
            cli_error_log("CliAuthenticationHandler: Could not get new access token. "
                ."Exception message: ".$ex->getMessage());
            return (false);
        }
    }

    public function authorize() {
        try {
            if ($this->token)
                $tk = $this->token;
            else
                $tk = $this->load_token();
            if (isset($tk['access_token'])) {
                if ($this->token_expires_in($tk) < 3600) {
                    $tk = $this->refresh_access();
                    if (isset($tk['access_token']))
                        return ($this->set_token($tk));
                    else {
                        cli_error_log("CliAuthenticationHandler: authorize(): refresh failed");
                        if ($this->token_expires_in($this->token) > 600) {
                            cli_error_log("CliAuthenticationHandler: authorize(): existing token valid for at least 10 minutes.");
                            return ($this->token);
                        }
                    }
                }
                else //at least 1 hour left, return current token
                    return ($this->set_token($tk));
            }
            else {
                $tk = $this->get_new_access_token();
                if (isset($tk['access_token'])) {
                    return ($this->set_token($tk));
                }
            }
            //If token was not loaded from data or needed refreshing and
            //refreshing failed and could not get a new token either, then return failure
            cli_error_log('CliAuthenticationHandler: authorize(): could not authenticate');
            return (false);
        }
        catch (Exception $ex) {
            cli_error_log("CliAuthenticationHandler: could not authorize. "
                ."Exception Message: ".$ex->getMessage());
            return (false);
        }
    }

    private function set_token($token) {
        $this->token = $token;
        $this->save_token();
        return ($token);
    }

    public function refresh_access() {
        try {
            $tk = $this->get_new_access_token();
            if (isset($tk['access_token'])) {
                return ($this->set_token($tk));
            }
            cli_error_log("CliAuthenticationHandler: could not refresh token.");
            return (false);
        }
        catch (Exception $ex) {
            cli_error_log("CliAuthenticationHandler: could not refresh token. "
                ."Exception Message: ".$ex->getMessage());
            return (false);
        }
    }

    private function load_token() {
        try {
            if (!file_exists(__DIR__.'/../credentials/server_42_api_token'))
                return (false);
            $this->token = unserialize(file_get_contents(
                __DIR__.'/../credentials/server_42_api_token'));
            return ($this->token);
        }
        catch (Exception $ex) {
            cli_error_log("CliAuthenticationHandler: Could not load token. "
                .$ex->getMessage());
            return (false);
        }
    }

    private function save_token() {
        try {
            if (!file_exists(__DIR__.'/../credentials'))
                mkdir(__DIR__.'/../credentials');
            if (!file_exists(__DIR__.'/../credentials')) {
                cli_error_log("CliAuthenticationHandler: Could not make credentials directory. "
                    .$ex->getMessage());
                return (false);
            }
            $bytes = file_put_contents(
                __DIR__.'/../credentials/server_42_api_token',
                serialize($this->token));
            if ($bytes > 0)
                return (true);
            cli_error_log("CliAuthenticationHandler: Could not save token. $bytes bytes written.");
            return (false);
        }
        catch (Exception $ex) {
            cli_error_log("CliAuthenticationHandler: Could not save token. "
                .$ex->getMessage());
            return (false);
        }
    }

    public function get_current_token() {
        return ($this->token);
    }
}

class   HttpAuthenticationHandler extends AuthenticationHandler {

    public function authorize() {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE)
                session_start();
            if (isset($_SESSION['access_token'])) {
                $tk = $_SESSION['access_token'];
                if ($this->token_expires_in($tk) < 3600) {
                    $tk = $this->refresh_access();
                    if (isset($tk['access_token']))
                        return ($this->set_token($tk));
                    else {
                        http_error_log("HttpAuthenticationHandler: refresh failed");
                        if ($this->token_expires_in($tk) > 600) {
                            http_error_log("HttpAuthenticationHandler: existing token valid for at least 10 minutes.");
                            return ($this->set_token($tk));
                        }
                    }
                }
                else //at least 1 hour left, return current token
                    return ($this->set_token($tk));
            }
            //If token was not loaded from session or needed refreshing
            //and refreshing failed, then get a brand new token
            if (isset($_GET['code'])) {
                //We're back from the authorization dialog, now exchange the code
                $tk = $this->exchange_code_for_access_token($_GET['code']);
                if (isset($tk['access_token']))
                    return ($this->set_token($tk));
                http_error_log("HttpAuthenticationHandler: could not authenticate. Code exchange failed.");
                return (false);
            }
            else {
                //redirect user to the authorization dialog
                $this->redirect(self::AUTH_DIALOG);
            }
        }
        catch (Exception $ex) {
            cli_error_log("HttpAuthenticationHandler: could not authenticate. "
                ."Exception Message: ".$ex->getMessage());
            return (false);
        }
    }

    private function exchange_code_for_access_token($code) {
        try {
            $url = 'https://api.intra.42.fr/oauth/token';
            $query = http_build_query(array(
                'grant_type' => 'authorization_code',
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
                'code' => $code,
                'redirect_uri' => self::REDIRECT_URI,
                'state' => 'authorizing'
            ));
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n".
                                 "Content-Length: ".strlen($query)."\r\n",
                    'method'  => 'POST',
                    'content' => $query
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            if ($result === false) {
                http_error_log('could not get access token');
                return (false);
            }
            $arr = json_decode(trim($result), true);
            if (!(isset($arr['access_token']))) {
                http_error_log('access_token was not found in the token exchange response');
                http_error_log("Response: $result");
                return (false);
            }
            if (isset($arr['expires_in']))
                $arr['expires_in'] = intval($arr['expires_in']);
            $arr['timestamp'] = time();
            return ($arr);
        }
        catch (Exception $ex) {
            http_error_log("AuthenticationHandler: Could not exchange code for token. "
                ."Exception message: ".$ex->getMessage());
            return (false);
        }
    }

    private function redirect($url) {
        ob_start();
        header('Location: '.$url);
        ob_end_flush();
        exit(0);
    }

    private function set_token($token) {
        $_SESSION['access_token'] = $token;
        return ($token);
    }

    public function refresh_access() {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE)
                session_start();
            if (isset($_SESSION['access_token']['refresh_token'])) {
                $tk = $_SESSION['access_token']['refresh_token'];
            }
            else {
                http_error_log("HttpAuthenticationHandler: Could not refresh token: no access token");
                return (false);
            }
            $tk = $this->refresh_token_request($tk);
            if (isset($tk['access_token'])) {
                return ($this->set_token($tk));
            }
            http_error_log("HttpAuthenticationHandler: could not refresh token.");
            return (false);
        }
        catch (Exception $ex) {
            http_error_log("HttpAuthenticationHandler: could not refresh token. "
                ."Exception Message: ".$ex->getMessage());
            return (false);
        }
    }

    public function get_current_token() {
        if (isset($_SESSION['access_token']))
            return ($_SESSION['access_token']);
        return (null);
    }
}

?>
