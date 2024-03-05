<?php

namespace BigXLSX;

class SharedStrings{
	private $cache=[];
    /**
     * @var \Iterator|null
     */
    private $iterator;

	private function __construct(){ }

    public static function empty(){
        return new static();
    }

    public static function fromXML(\BigXML\File $xml=null, \ArrayAccess $cache=null){
        $new=new static();
        $new->cache=$cache??[];
        $reader=$xml->getReader('sst/si');
        if($reader){
            $new->iterator=$reader->getIterator();
            $new->iterator->rewind();
        }
        return $new;
    }

    public function get(int $index){
        if(!isset($this->cache[$index]) && $this->iterator){
            while($this->iterator->valid()){
                $k=$this->iterator->key();
                $curr=$this->iterator->current();
                $this->cache[$k]=self::normalizeString($curr->readString());
                $this->iterator->next();
                if(isset($this->cache[$index])) break;
            }
            if(!$this->iterator->valid()) $this->iterator=null;
        }
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