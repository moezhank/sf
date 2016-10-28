<?php

class RestClient {
   private $mPath;

   private $mUser;

   private $mPass;

   private $mPostStatus = false;

   private $mProxyPort;

   private $mProxyStatus = false;

   private $mProxyAddress;

   private $mAuthenticationMethodList = array("default" => CURLAUTH_BASIC, "digest" => CURLAUTH_DIGEST, "ntlm" => CURLAUTH_NTLM, "gssngotiate" => CURLAUTH_GSSNEGOTIATE);

   private $mAuthenticationMethod;

   private $mKeycode;

   private $mPost = "";

   private $mGet = "";

   private $setHeader = 0;

   private $setHttpHeader = array();

   private $userAgent;

   private $followLocation;

   private $secure;

   private $allowedHost;

   private $debugStatus = false;

   private $oriStatus = false;

   static $mrInstance;

   public function __constract() {
      $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
      $this->followLocation = true;
      $this->mAuthenticationMethod = $this->mAuthenticationMethodList["default"];
   }

   public function setHeaderPage($header) {
      $this->setHeader = $header;
   }

   public function setUserPassword($user, $pass) {
      $this->mUser = $user;
      $this->mPass = $pass;
   }

   public function setProxyAddress($address, $port) {
      $this->mProxyAddress = $address;
      $this->mProxyPort = $port;
   }

   public function setPostOn() {
      $this->setHttpHeader = array(
         "Expect: 100-continue",
         'Content-type: multipart/form-data; charset="utf-8"',
         'Connection: Keep-Alive');
      $this->mPostStatus = true;
   }

   public function setProxyType($type = "http") {
      if ($type == "http") {
         $this->mProxyType = CURLPROXY_HTTP;
      } else {
         $this->mProxyType = CURLPROXY_SOCKS5;
      }

   }

   public function setAuthenticationMethod($method = "default") {
      $this->mAuthenticationMethod = $this->mAuthenticationMethodList[$method];
   }

   public function setProxyOn() {
      $this->mProxyStatus = true;
   }

   public function getProxyType() {
      return $this->mProxyType;
   }

   public function SetGet($queryString) {
      $add = "";

      if (!empty($this->mGet)) {
         $add = "&";
      }

      //if (empty($this->mGet)) $this->mGet.= "?";
      $this->mGet .= $queryString;
   }

   public function GetGet() {
      return $this->mGet;
   }

   public function SetPost($arrPost) {
      $this->setPostOn();
      //$add = "";

      //if (!empty($this->mPost)) $add = "&";
      $this->mPost = $arrPost;
   }

   public function GetPost() {
      return $this->mPost;
   }

   public function Send($sqlQueries = "") {

      $and = "";
      if (!empty($sqlQueries)) {
         //$this->SetGet($sqlQueries);
         //if($this->mPostStatus){
         $this->SetPost($sqlQueries);
         //}
      }
      //echo $this->mPath . $this->mGet;
      $ch = curl_init();
      $pos = strpos($this->mPath, '?');

      if ($pos == true) {
         $pos = "";
         $and = strpos($this->mPath, '&');
         if ($and == true & !empty($sqlQueries)) {
            $and = "&";
         } else {
            $and = "";
         }

      } else {
         $pos = "?";
      }

      if (!isset($_SERVER["HTTP_COOKIE"])) {
         $_SERVER["HTTP_COOKIE"] = "";
      }

      $cookieId = "cookiefile_" . str_replace("GTFWSessID=", "", $_SERVER["HTTP_COOKIE"]);

      curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
      curl_setopt($ch, CURLOPT_URL, $this->mPath . $pos . $and . $this->mGet);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4000);
      curl_setopt($ch, CURLOPT_TIMEOUT, 4000);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HEADER, $this->setHeader);

      if ($this->mPostStatus) {
         curl_setopt($ch, CURLOPT_HTTPHEADER, $this->setHttpHeader);
         curl_setopt($ch, CURLOPT_POST, $this->mPostStatus);
         curl_setopt($ch, CURLOPT_POSTFIELDS, $this->mPost);

      }

      curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->followLocation);
      curl_setopt($ch, CURLOPT_COOKIESESSION, 0);
      curl_setopt($ch, CURLOPT_COOKIEFILE, sys_get_temp_dir() . "/" . $cookieId);
      curl_setopt($ch, CURLOPT_COOKIEJAR, sys_get_temp_dir() . "/" . $cookieId);

      /*
      $_COOKIE = $_COOKIE->AsArray();

      $cookie = array();
      foreach($_COOKIE as $name=>$val)$cookie[] = $name . '=' . urlencode($val);
      curl_setopt($ch, CURLOPT_COOKIE, implode(';', $cookie));
      $ret = curl_exec($ch);
      curl_close($ch);
      preg_match('/^Set-Cookie:\s*([^;]*)/mi', $ret, $m);
      if(isset($m[1])){
      $c = explode('=', $m[1]);
      setcookie($c[0], $c[1]);
      }
      }
       */
      if ($this->mProxyStatus && $this->mProxyAddress != "") {
         curl_setopt($ch, CURLOPT_PROXYTYPE, $this->mProxyType);
         curl_setopt($ch, CURLOPT_PROXY, $this->mProxyAddress . ':' . $this->mProxyPort);
      }

      if ($this->mUser != "") {
         curl_setopt($ch, CURLOPT_USERPWD, $this->mUser . ':' . $this->mPass);
         curl_setopt($ch, CURLOPT_HTTPAUTH, $this->mAuthenticationMethod);
      }

      $data = curl_exec($ch);
      //print_r($data);

      $this->info = curl_getinfo($ch);

      if ($this->oriStatus == false) {
         if ($data[strlen($data) - 1] !== '}') {
            $data = substr($data, 0, strpos($data, '}}Array') + 2);
         }

         $data = json_decode($data, true);
      }
      //print_r($this->debugStatus);
      if ($this->debugStatus) {
         echo "<pre>";
         print_r($this->info);
         echo "</pre>";
      }

      $this->mGet = "";
      $this->mPost = "";

      $dataSplith = $data;

      if ($data != null) {
         list($header, $dataSplith) = @explode("\n\n", $data, 2);
         $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

         if ($http_code == 301 || $http_code == 302) {
            $data = $this->curl_redir_exec($ch);
         }

      }

      curl_close($ch);

      return $data;
   }

   function curl_redir_exec($ch) {
      static $curl_loops = 0;
      static $curl_max_loops = 20;
      if ($curl_loops++ >= $curl_max_loops) {
         $curl_loops = 0;
         return FALSE;
      }
      curl_setopt($ch, CURLOPT_HEADER, true);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $data = curl_exec($ch);
      list($header, $data) = explode("\n\n", $data, 2);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($http_code == 301 || $http_code == 302) {
         $matches = array();
         preg_match('/Location:(.*?)\n/', $header, $matches);
         $url = @parse_url(trim(array_pop($matches)));
         #print_r($url);
         if (!$url) {
            //couldn't process the url to redirect to
            $curl_loops = 0;
            return $data;
         }
         $last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
         if (!$url['scheme']) {
            $url['scheme'] = $last_url['scheme'];
         }

         if (!$url['host']) {
            $url['host'] = $last_url['host'];
         }

         if (!$url['path']) {
            $url['path'] = $last_url['path'];
         }

         if (!$url['port']) {
            $url['port'] = $last_url['port'];
         }
         $new_url = $url['scheme'] . '://' . $url['host'] . ":" . $url['port'] . $url['path'] . ($url['query'] ? '?' . $url['query'] : '');

         curl_setopt($ch, CURLOPT_URL, $new_url);

         return $this->curl_redir_exec($ch);
      } else {
         $curl_loops = 0;
         return $data;
      }
   }

   public function setKeyCode($key) {
      $this->mKeyCode = $key;
   }

   public function getKeyCode() {

      return $this->mKeyCode;
   }

   public function setPath($path) {
      $this->mPath = $path;
   }

   public function getPath() {

      return $this->mPath;
   }

   public function setDebugOn() {
      #code here
      $this->debugStatus = true;
   }

   public function setDebugOff() {
      #code here
      $this->debugStatus = false;
   }

   public function setResultDefault() {
      $this->oriStatus = true;
   }

   public function setResultArray() {
      $this->oriStatus = false;
   }
}

?>
