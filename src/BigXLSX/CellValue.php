<?php

namespace BigXLSX;

class CellValue implements \JsonSerializable{
	private $value;
	private $type;

	public function __construct($value, ?string $type){
		$this->value=$value??'';
		$this->type=$type;
	}

	public function type(){
		return $this->type;
	}

	public function isError(){
		return $this->type=='e';
	}

	public function errorMessage(){
		return ($this->type==='e')?$this->value:'';
	}

	public function value(){
		return ($this->type!=='e')?$this->value:'';
	}

	public function __toString(){
		return $this->value();
	}

	public function jsonSerialize(){
		return $this->value();
	}

	public static function extractErrors(array $row){
		$err=array_filter($row, function($v) use (&$class){
			return (is_a($v, self::class) && $v->isError());
		}, ARRAY_FILTER_USE_BOTH)?:null;
		if($err) $err=array_map(function($v){
			return $v->errorMessage();
		}, $err);
		return $err;
	}

}