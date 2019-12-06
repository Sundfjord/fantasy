<?php

require_once(__DIR__.'/defines.php');

class Curler
{
    const CURL_ATTEMPTS_LIMIT = 10;

    /**
     * Information about errors occurring during curl requests
     *
     * @var string
     */
    public $errorMessage;

    protected $cookieString;

    protected $authenticationAttempts = 0;

    public function authenticate()
    {
        $this->authenticationAttempts++;
        // login url
        $url = 'https://users.premierleague.com/accounts/login/';

        // make a get request to the official fantasy league login page first, before we log in, to grab the csrf token from the hidden input that has the name of csrfmiddlewaretoken
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);

        $dom = new DOMDocument;
        @$dom->loadHTML($response);

        // set the csrf here
        $tags = $dom->getElementsByTagName('input');
        for($i = 0; $i < $tags->length; $i++) {
            $grab = $tags->item($i);
            if($grab->getAttribute('name') === 'csrfmiddlewaretoken') {
                $token = $grab->getAttribute('value');
            }
        }

        // now that we have the token, use our login details to make a POST request to log in along with the essential data form header fields
        if (empty($token)) {
            $this->authenticate();
        }

        $params = array(
            "csrfmiddlewaretoken"   => $token,
            "login"                 => FANTASY_LOGIN_EMAIL,
            "password"              => FANTASY_LOGIN_PASSWORD,
            "app"                   => "plfpl-web",
            "redirect_uri"          => "https://fantasy.premierleague.com/",
        );

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);

        // Store the cookies we get in an array
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
        $this->cookieString = implode('; ', $matches[1]);

        curl_close($ch);

        // set the header field for the token for our final request
        return ['csrftoken ' . $token];
    }

    public function get($url, $decode = false)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        $result = false;
        $attempts = 1;
        do {
            if (!($result = curl_exec($curl))) {
                $attempts++;
            }
        } while (!$result && $attempts <= self::CURL_ATTEMPTS_LIMIT);

        if (!$result) {
            $this->errorMessage = 'Unable to communicate with Fantasy server, please try again later';
            return false;
        }

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpcode == 404) {
            $this->errorMessage = 'Unable to find team with the given team ID';
            return false;
        }

        curl_close($curl);

        if ($decode) {
            $result = json_decode($result, true);
        }
        return $result;
    }

    public function getMulti(array $urls, $decode = false)
    {
        $multiCurl = curl_multi_init();
        $handles = [];
        $headers = $this->authenticate();
        foreach ($urls as $key => $url) {
            $curl = curl_init($url);
            $handles[$key] = $curl;
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_COOKIE, $this->cookieString);
            curl_multi_add_handle($multiCurl, $curl);
        }

        $running = false;
        do {
            curl_multi_exec($multiCurl, $running);
        } while ($running > 0);

        $result = [];
        foreach ($handles as $key => $handle) {
            $result[$key] = curl_multi_getcontent($handle);
            curl_multi_remove_handle($multiCurl, $handle);
        }
        curl_multi_close($multiCurl);

        if ($decode) {
            foreach ($result as $index => $res) {
                $result[$index] = json_decode($res, true);
            }
        }

        return $result;
    }

    protected function dump($data, $die = false)
    {
        echo '<pre>';var_dump($data);echo '</pre>';
        if ($die) {
            die();
        }
    }

    public function hasError()
    {
        return $this->errorMessage;
    }
}