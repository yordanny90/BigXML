<?php

namespace BigXLSX;

class SharedStrings{
	/**
	 * @var \BigXML\File
	 */
	private $xml;
	private $cache=[];

	public function __construct(){ }

	public function assign(\BigXML\File $xml=null){
		$this->cache=[];
		$this->xml=$xml;
	}

	/**
	 * Prepara la lista de strings del xml, en caso de que no se hayan cargado aún
	 * @return bool Devuelve TRUE si el xml existe y se pudo cargar la lista de strings.<br>
	 * En caso de fallo, devuelve FALSE, pero la lista cargada anteriormente sigue intacta
	 */
	public function prepare(){
		if(!$this->xml) return false;
		$this->cache=[];
		$success=false;
		if($reader=$this->xml->getReader('sst/si')){
			foreach($reader as $i=>$ss){
				$this->cache[$i]=self::normalizeString($ss->readString());
			}
			$success=true;
		}
		$this->xml=null;
		return $success;
	}

	public function get(int $index){
		return $this->cache[$index]??null;
	}

	public static function normalizeString(string $str){
		$new_str=preg_replace_callback('/_x([\dA-F]{2})([\dA-F]{2})_/i', function(&$m){
			if($m[1]=='00') $m[1]='';
			return utf8_encode(hex2bin($m[1].$m[2]));
		}, $str);
		return $new_str;
	}
}