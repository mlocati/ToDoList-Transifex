<?php

/** A class that encapsulates curl. */
class Curl
{
    /**
     * The current curl session handler.
     * 
     * @var resource
     */
    private $_hCurl = null;

    /**
     * Remember if the result will contain the response headers.
     *
     * @var bool
     */
    private $_headersInResponse = false;

    /**
     * Initialize the class instance, with the default cURL options modified by:
     * - CURLOPT_BINARYTRANSFER: true
     * - CURLOPT_RETURNTRANSFER: true
     * - CURLOPT_HEADER: true
     * - CURLOPT_SSL_VERIFYPEER: false.
     *
     * @param string $url
     *     The url to be called.
     *
     * @throws Exception
     *     Throws an exception in case of errors.
     * 
     * @link http://www.php.net/manual/en/function.curl-init.php
     * @link http://www.php.net/manual/en/function.curl-setopt.php
     */
    public function __construct($url)
    {
        if (!function_exists('curl_init')) {
            throw new Exception('The cURL PHP extension is not installed');
        }
        $this->_hCurl = @curl_init($url);
        if ($this->_hCurl === false) {
            global $php_errormsg;
            throw new Exception("Error during curl_init: $php_errormsg");
        }
        try {
            $this->setOpt(CURLOPT_BINARYTRANSFER, true);
            $this->setOpt(CURLOPT_RETURNTRANSFER, true);
            $this->setOpt(CURLOPT_HEADER, true);
            $this->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        } catch (Exception $x) {
            try {
                $this->__destruct();
            } catch (Exception $x2) {
            }
            throw $x;
        }
    }

    /**
     * Set an option for a cURL transfer.
     *
     * @param int $option
     *     The CURLOPT_XXX option to set.
     * @param mixed $value
     *     The value to be set on option.
     *
     * @throws Exception
     *     Throws an exception in case of errors.
     *
     * @link http://www.php.net/manual/en/function.curl-setopt.php
     */
    public function setOpt($option, $value)
    {
        if (@curl_setopt($this->_hCurl, $option, $value) === false) {
            throw new Exception($this->getErrorDescription('curl_setopt'));
        }
        switch ($option) {
            case CURLOPT_HEADER:
                $this->_headersInResponse = $value ? true : false;
                break;
        }
    }

    /**
     * Perform a cURL session.
     * 
     * @return array
     *     Returns an array with the following keys:
     *     - 'info': array with the result of curl_getinfo
     *     - 'headers': ResponseHeaders with the response headers (if the CURLOPT_HEADER or CURLOPT_RETURNTRANSFER options haven't been set to false)
     *     - 'body': string with the response body (if the CURLOPT_RETURNTRANSFER option haven't been set to false)
     *
     * @throws Exception
     *     Throws an exception in case of errors.
     *
     * @link http://www.php.net/manual/en/function.curl-exec.php
     * @link http://www.php.net/manual/en/function.curl-getinfo.php
     * @link http://www.php.net/manual/en/function.curl-setopt.php
     */
    public function exec()
    {
        $exec = @curl_exec($this->_hCurl);
        if ($exec === false) {
            throw new Exception($this->getErrorDescription('curl_setopt'));
        }
        $info = @curl_getinfo($this->_hCurl);
        if (!is_array($info)) {
            throw new Exception($this->getErrorDescription('curl_getinfo'));
        }
        if (!isset($info['http_code'])) {
            throw new Exception('http_code missing in curl info');
        }
        if (is_string($info['http_code']) && is_numeric($info['http_code'])) {
            $info['http_code'] = (int) $info['http_code'];
        }
        if (!is_int($info['http_code'])) {
            throw new Exception('http_code is invalid in curl info');
        }
        $result = array();
        $result['info'] = $info;
        if ($exec !== true) {
            if (!is_string($exec)) {
                throw new Exception('curl_exec returned an unknown variable type: '.gettype($exec));
            }
            if ($this->_headersInResponse) {
                $parts = self::splitHeaders($exec);
                if (preg_match('/^HTTP\/\d+(\.\d+)* 100 Continue$/', $parts['headers']) && preg_match('/^HTTP\/\d+(\.\d+)* \d+ \w+/', $parts['body'])) {
                    $parts = self::splitHeaders($parts['body']);
                }
                $result['headers'] = new ResponseHeaders($parts['headers']);
                $result['body'] = $parts['body'];
            } else {
                $result['body'] = $exec;
            }
        }

        return $result;
    }
    
    /**
     * Splits a full response into headers + body
     * 
     * @param string $full
     *     The full response
     * 
     * @return array
     *     Returns an array with the keys 'headrs' and 'body'
     */
    private static function splitHeaders($full)
    {
        $result = array('headers' => '', 'body' => '');
        $pLinux = strpos($full, "\n\n");
        if ($pLinux === false) {
            $pLinux = PHP_INT_MAX;
        }
        $pWin = strpos($full, "\r\n\r\n");
        if ($pWin === false) {
            $pWin = PHP_INT_MAX;
        }
        $pMac = strpos($full, "\r\r");
        if ($pMac === false) {
            $pMac = PHP_INT_MAX;
        }
        $pEmptyLine = min($pLinux, $pWin, $pMac);
        if ($pEmptyLine === PHP_INT_MAX) {
            $result['headers'] = trim($full);
        } elseif ($pEmptyLine === 0) {
            $result['body'] = $full;
        } else {
            $result['headers'] = trim(substr($full, 0, $pEmptyLine));
            $result['body'] = substr($full, $pEmptyLine + (($pEmptyLine === $pWin) ? 4 : 2));
        }

        return $result;
    }

    /**
     * Frees the resources used by this instance.
     */
    public function __destruct()
    {
        if (isset($this->_hCurl) && $this->_hCurl) {
            @curl_close($this->_hCurl);
            $this->_hCurl = null;
        }
    }

    /**
     * Retrieves the error message associaed to this curl session.
     *
     * @param string $functionFailing
     *     The name of the function that failed.
     *
     * @return string
     *     Returns the error description.
     */
    private function getErrorDescription($functionFailing)
    {
        $result = @curl_error($this->_hCurl);
        $result = is_string($result) ? trim($result) : '';
        if ($result === '') {
            global $php_errormsg;
            if (isset($php_errormsg) && is_string($php_errormsg)) {
                $result = trim($php_errormsg);
            }
            if ($result === '') {
                $errNo = @curl_errno($this->_hCurl);
                $errNo = is_numeric($errNo) ? @intval($errNo) : 0;
                if ($errNo !== 0) {
                    $result = "$functionFailing failed with error code $errNo.";
                }
                if ($result === '') {
                    $result = "$functionFailing failed.";
                }
            }
        }

        return $result;
    }
}

/**
 * A class that holds cURL response headers.
 */
class ResponseHeaders
{
    /**
     * The initial HTTP/... header.
     *
     * @var string
     */
    public $HTTP_HEADER;
    
    /**
     * The HTTP response code.
     *
     * @var int|null
     */
    public $HTTP_HEADER_CODE;
    
    /**
     * The initial HTTP/... header description.
     *
     * @var string
     */
    public $HTTP_HEADER_CODE_DESCRIPTION;

    /**
     * Stores the parsed headers.
     * 
     * @var array
     */
    private $_list;
   
    /**
     * Initializes the instance.
     * 
     * @param string $headers
     *     The headers to be parsed.
     *
     * @throws Exception
     *     Throws an Exception in case of problems.
     */
    public function __construct($headers)
    {
        if (!is_string($headers)) {
            throw new Exception('ResponseHeaders expects a string');
        }
        $headers = trim($headers);
        $this->HTTP_HEADER = '';
        $this->HTTP_HEADER_CODE = null;
        $this->HTTP_HEADER_CODE_DESCRIPTION = null;
        $match = null;
        $this->_list = array();
        foreach (explode("\n", str_replace("\r", "\n", str_replace("\r\n", "\n", $headers))) as $index => $line) {
            if ($index === 0 && preg_match('/^HTTP\/\d+(\.\d+)*\s+(\d+)\s+(\w.*?)\s*$/', $line, $match)) {
                $this->HTTP_HEADER = $line;
                $this->HTTP_HEADER_CODE = intval($match[2]);
                $this->HTTP_HEADER_CODE_DESCRIPTION = $match[3];
            }
            else {
                $chunks = explode(':', $line, 2);
                if (!isset($chunks[1])) {
                    throw new Exception("Bad header line: $line");
                }
                $key = strtolower($chunks[0]);
                if (!isset($this->_list[$key])) {
                    $this->_list[$key] = array();
                }
                $this->_list[$key][] = $chunks[1];
            }
        }
    }

    /**
     * Retrieve the specific header.
     *
     * @param string $header
     *     The header to retrieve.
     * 
     * @param mixed $onNotFound
     *     What to return if $header is not found.
     *
     * @return array|mixed
     *     Return the list of header values (or $onNotFound if the requested header is not found).
     *
     * @throws Exception
     *     Throws an Exception in case of problems.
     */
    public function getList($header, $onNotFound = array())
    {
        if (!is_string($header)) {
            throw new Exception('ResponseHeaders::getList expects a string as $header');
        }
        $header = strtolower($header);
        return isset($this->_list[$header]) ? $this->_list[$header] : $onNotFound;
    }

    /**
     * Retrieve the first value of a specific header.
     *
     * @param string $header
     *     The header to retrieve.
     * 
     * @param mixed $onNotFound
     *     What to return if $header is not found.
     *  
     * @return string|mixed
     *     Return the first header value (or $onNotFound if the requested header is not found).
     *
     * @throws Exception
     *     Throws an Exception in case of problems.
     *
     */
    public function getFirst($header, $onNotFound = '')
    {
        $list = $this->getList($header);
        return empty($list) ? $onNotFound : $list[0];
    }
}
