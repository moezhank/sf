<?php
class Config {
	private $mCfg = array();
	public static $mrInstance;
	private $docRoot;
	
	public function getDocRoot(){
		return $this->docRoot;
	}

	public function setDocRoot($docRoot){
		$this->docRoot = $docRoot;
	}

	public function load($file){
		require_once $file;
		$this->mCfg = array_merge($this->mCfg,$cfg);
		$this->additionalConfig();
	}
	
	private function additionalConfig(){

		$this->mCfg['baseaddress'] = 'http://'.$this->mCfg['domain_name'].$this->docRoot;
		$this->mCfg['docbase'] = $this->docRoot . "/sf";

		$this->mCfg['docroot'] = $this->docRoot;
	}

	public function getValue($value=""){
		if($value==""){
			return $this->mCfg;
		}else{
			if(isset($this->mCfg[$value]))
				return $this->mCfg[$value];
			else
				return null;
		}
	}

	public function setValue($key, $value){
		$this->mCfg[$key] = $value;
	}

	public static function init(){
		$class_name = __CLASS__;
		self::$mrInstance = new $class_name();
	}

	static function instance() {
		return self::$mrInstance;
	}	
}
?>
