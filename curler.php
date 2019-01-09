<?php

class Curler
{
    const CURL_ATTEMPTS_LIMIT = 10;

    /**
     * Information about errors occurring during curl requests
     *
     * @var string
     */
    public $errorMessage;

    public function get($url, $decode = false)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

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
        foreach ($urls as $url) {
            $curl = curl_init($url);
            $handles[] = $curl;
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_multi_add_handle($multiCurl, $curl);
        }

        $running = false;
        do {
            curl_multi_exec($multiCurl, $running);
        } while ($running > 0);

        $result = [];
        foreach ($handles as $handle) {
            $result[] = curl_multi_getcontent($handle);
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

    public function hasError()
    {
        return $this->errorMessage;
    }
}