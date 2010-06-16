<?php

/**
 *
 * Get your CloudCache credentials for free at www.quetzall.com, then:
 * $cache = new CloudCacheNormal(CC_ACCESS_KEY, CC_SECRET_KEY);
 *
 * Basic usage:
 * $cache->put('IdForData', 'DataYouWantToStore');
 * $data = $cache->get('IdForData');
 *
 * get for an unset key will return NULL.
 * put accepts ttl in seconds and an isRaw bool to base64 encode/decode or not.
 *
 * multi_get allows for multiple simultaneous gets.
 * $array_of_values = $cache->multi_get(array('Id1','Id2','Id3'));
 *
 * $cache->delete('IdToDelete');
 *
 * //TODO support a pipeline for multiple actions e.g. multiple puts, gets in one call.
 *
 * Introspection:
 * $stored_keys = $cache->list_keys();
 *
 * $cache->incr and $cache->decr are used to increment and decrement a value
 * currently, the value must already exist (//TODO fix)
 *
 * $cache->put('IntToAdd',0);
 * $cache->incr('IntToAdd');
 * $cache->incr('IntToAdd');
 * $cache->incr('IntToAdd',2);
 *
 * should have a value of 4.  decr is the inverse operation
 *
 * $cache->fulsh();  // will clear the cache of all values
 *
 * Finally, in the case of a key that does not exist the API returns NULL.
 * See $cache->exists for details
 * 
 */


class CloudCacheNormal
{
    private    $_akey;
    private    $_skey;
    private    $_host;
    /*
     * curlMe, socketMe, httpMe, depending on weather you want to use cURL, PEAR HTTP_Request or raw PHP sockets
     * dependencies are not my problem ;) make sure what PEAR http is included if you want to use it
     * Currently I only support curlMe.  If someone wants to finish the other two, it's all of 20 mins...
     */
    private    $_transport = 'curlMe';

    public function __construct($A_KEY = '', $S_KEY = '', $HOST = 'http://cloudcache.ws') {
        $this->_host = $HOST;
        $this->_akey = $A_KEY;
        $this->_skey = $S_KEY;
    }

    public function auth() {
        return $this->{$this->_transport}('GET', 'auth', 'auth');
    }

    public function get($id, $isRaw = false) {
        if ( !$id || !is_string($id) ) {
            throw new CloudCacheExceptions('ID must be a trueish string');
        }
        $output = $this->{$this->_transport}('GET', 'GET', urlencode($id));
        //base64_decode will convert null to ''.  if a value does not exist, we pass null
        $output = $isRaw || is_null($output) ? $output : base64_decode($output);
        return $output;
    }
    public function read($id, $isRaw = false) {
        $this->get($id, $isRaw);
    }
    public function fetch($id, $isRaw = false) {
        $this->get($id, $isRaw);
    }
    public function get_multi(array $ids, $isRaw = false) {
        if ( !count($ids) ) {
            throw new CloudCacheExceptions('You must request at least one ID');
        }
        //TODO does json_encode also urlencode?
        $output = $this->{$this->_transport}('GET', 'GET', 'getmulti', '', array('keys:'. json_encode($ids)) );
        // New response format is:
        // VALUE <key>  <bytes> \r\n
        // <data block>\r\n
        // VALUE <key>  <bytes> \r\n
        // <data block>\r\n
        // END
        // 
        //TODO this may not be efficent for large strings or lots of binnary data...???
        $keys = preg_split('/(VALUE|\r\nVALUE) (.+?) (.+?)\r\n|END\r\n$/', $output, -1, PREG_SPLIT_DELIM_CAPTURE);
        array_shift($keys);
        $total = count($keys);
        if ( $total % 4 ) {
            $putout = array();
            $i = 3;
            do {
                $putout[urldecode($keys[$i-2])] =  $isRaw ? $keys[$i] : base64_decode($keys[$i]);
                $i += 4;
            } while ($i < $total );
        } else {
            throw new CloudCacheExceptions('There was a problem. Output:' . $output . "\r\n" . print_r($keys, true));
        }

        return $putout;
    }
    public function exists($id) {
        $var = $this->get($id);
        return !is_null($var);
    }
    
    public function put($id, $data ='', $ttl = 0, $isRaw = false ) {
        if ( !is_numeric($ttl) ) {
            throw new CloudCacheExceptions('ttl must be an intereger');
        }
        if ( !$id || !is_string($id) ) {
            throw new CloudCacheExceptions('ID must be a trueish string');
        }
        $data = $isRaw ? $data : base64_encode($data);

        return $this->{$this->_transport}('PUT', 'PUT', urlencode($id), $data, array('Ttl:'.$ttl));
    }
    public function write($id, $data ='', $ttl = 0, $isRaw = false ) {
        $this->put($id, $data, $ttl, $isRaw);
    }
    function set($id, $data ='', $ttl = 0, $isRaw = false ) {
        $this->put($id, $data, $ttl, $isRaw);
    }

    public function list_keys() {
        $output = $this->{$this->_transport}('GET', 'listkeys', 'listkeys');
        return array_flip(json_decode($output, true));
    }

    public function flush() {
        return $this->{$this->_transport}('GET', 'flush', 'flush');
    }
    public function clear() {
        $this->flush();
    }

    public function delete($id) {
        if ( !$id || !is_string($id) ) {
            throw new CloudCacheExceptions('ID must be a trueish string');
        }
        return $this->{$this->_transport}('DELETE', 'DELETE', urlencode($id));
    }
    public function remove($id) {
        $this->delete($id);
    }
         
    public function incr($id, $val = 1, $set_if_not_found = true ) {
        if ( !is_numeric($val) ) {
            throw new CloudCacheExceptions('how do you increment something that is not a number? e.g. (' . $val . ')');
        }
        if ( !$id || !is_string($id) ) {
            throw new CloudCacheExceptions('ID must be a trueish string');
}
        $aditional_headers = array(); //array('val:'. $val);
        if ( $set_if_not_found ) {
            $aditional_headers[] = 'x-cc-set-if-not-found:1';
        }
        
        return intval($this->{$this->_transport}('POST', 'POST', $id . '/incr', 'val=' . $val, $aditional_headers));
    }
    public function decr($id, $val = 1, $set_if_not_found = true ) {
        if ( !is_numeric($val) ) {
            throw new CloudCacheExceptions('how do you increment something that is not a number? e.g. (' . $val . ')');
        }
        if ( !$id || !is_string($id) ) {
            throw new CloudCacheExceptions('ID must be a trueish string');
}
        $aditional_headers = array(); //array('val:'. $val);
        if ( $set_if_not_found ) {
            $aditional_headers[] = 'x-cc-set-if-not-found:1';
        }

        return intval($this->{$this->_transport}('POST', 'POST', $id . '/decr', 'val=' . $val, $aditional_headers));
    }
    
    public function myusage() {
        return $this->{$this->_transport}('GET', 'myusage', 'myusage');
    }
    public function stats() {
        return $this->myusage();
    }
    public function usage() {
        return $this->myusage();
    }

    public function setKeys($_akey, $_skey) {
        $this->_akey = $_akey;
        $this->_skey = $_skey;
    }

    public function setAPIHost($serverPath) {
        if (strstr($serverPath, "http://")) {
            $this->_host = $serverPath;
        } else {
            $this->_host = "http://".$serverPath;
        }
    }

    /*
     * Transport methods
     */

    private function curlMe($http_cmd, $cloudcache_cmd, $path, $data ='', array $aditional_headers = array() ) {
        //T and Z are actual delimiters in the Ruby client
        $ts = gmdate('Y-m-d\TH:G:s\Z');
        $userAgent = 'CloudCache PHP Client';
        $sig = base64_encode(hash_hmac('sha1','CloudCache' . $cloudcache_cmd .  $ts , $this->_skey, true));

        $ch = curl_init($this->_host . '/' . $path );
        curl_setopt_array( $ch, array(
            //CURLOPT_HEADER => 1,
            CURLOPT_HTTPHEADER => array_merge(array(
                'User-Agent:' . $userAgent,
                'Akey:' . $this->_akey,
                'Timestamp:' . $ts,
                'Signature:' . $sig,
                'Connection: close'
            ),$aditional_headers),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_BINARYTRANSFER => 1,

            CURLOPT_POST => 1,
            CURLOPT_CUSTOMREQUEST => $http_cmd,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);

        if ( $info['http_code'] == 404 ) {
            //requested information does not exist
            return null;
        }

        if ($output === false || !in_array($info['http_code'], array(200,201,202)) ) {
            $msg = 'CloudCache error ' . $output . "\r\n";
             if (curl_error($ch)) {
                 $msg .= 'cURL error: ' .curl_error($ch);
             }
             throw new CloudCacheExceptions($msg);
        }
        curl_close($ch);

        return $output;
    }

    private function socketMe($http_cmd, $cloudcache_cmd, $path, $data ='', array $aditional_headers = array()) {
        $ts = gmdate('Y-m-d\TH:G:s\Z');
        $userAgent = 'CloudCache PHP Client';
        $sig = base64_encode(hash_hmac('sha1','CloudCache' . $cloudcache_cmd .  $ts , $this->_skey, true));
        $fp = fsockopen($this->_host, 80); //, $timeout);

        $request =  array_merge(array(
            $http_cmd . ' /' . $path . ' HTTP/1.1',
            'Host: ' . $this->_host,
            'User-Agent:' . $userAgent,
            'Akey:' . $this->_akey,
            'Timestamp:' . $ts,
            'Signature:' . $sig,
            'Connection: close',
            'Content-Length:' .strlen($data),
            'Content-Type: application/x-www-form-urlencoded'
        ),$aditional_headers, array(
            '',
            $data
        ));

        fwrite($fp, implode("\r\n", $request));
        $headers_end = false; $c=0;
        $reply = '';
        do {
            $reply .= fgets($fp, 2048);
        } while (!feof($fp));
        fclose($fp);
        
        list($headders, $data) = split("\r\n\r\n", $reply);
        return $data;

    }

    private function httpMe($http_cmd, $cloudcache_cmd, $path, $data ='', array $aditional_headers = array()) {
        $ts = gmdate('Y-m-d\TH:G:s\Z');
        $userAgent = 'CloudCache PHP Client';
        $sig = base64_encode(hash_hmac('sha1','CloudCache' . $cloudcache_cmd .  $ts , $this->_skey, true));

        $req = new HTTP_Request('http://' . $this->_host . '/' . $path );

        $req->setMethod($http_cmd);
        $req->addHeader("User-Agent", $userAgent);
        $req->addHeader("timestamp", $ts);
        $req->addHeader("signature", $sig);
        $req->addHeader("akey", $this->_akey);
        $req->setBody($data);
        $req->sendRequest();
        $ct = $req->getResponseHeader("content-type");
        $rspCode =$req->getResponseCode();
        return $req->getResponseBody();
    }

}

/**
 * File is use CloudCacheException
 *
 */
class CloudCacheExceptions extends Exception
{
    const RECORD_NOT_FOUND = "Rec not found";
    const KEY_NOT_FOUND = "Key not found";
    const NULL_KEY = "Key can not be null";
    const NULL_KEY_AND_DATA = "Key and data can not be null";

    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 0)
    {
        // make sure everything is assigned properly
        parent::__construct($message, $code);
    }

    public function __toString()
    {
        return "exception '".__CLASS__ ."' with message '".$this->getMessage()."' in ".$this->getFile().":".$this->getLine()."\nStack trace:\n".$this->getTraceAsString();
    }

    public function errorMessage()
    {
        return $this->getMessage();
    }
}