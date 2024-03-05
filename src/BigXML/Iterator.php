<?php

namespace BigXML;

use XMLReader;

class Iterator implements \Iterator{
	private $_key=-1;
	/**
	 * @var Reader
	 */
	private $_reader;
	private $_initRoute;
	/**
	 * @var string|null
	 */
	private $_name;

	public function __construct(Reader &$reader, ?string $name=null){
		$this->_reader=&$reader;
		$this->_initRoute=$this->_reader->getIndexRoute();
		$this->_name=$name;
	}

    /**
     * @return Node
     */
	public function current(){
		return $this->_reader->getNode();
	}

	public function next(){
		if($this->_key<0) return;
		++$this->_key;
		if(is_string($this->_name)) $this->_reader->nextName($this->_name);
		else $this->_reader->next();
	}

	public function key(){
		return $this->_key;
	}

	public function valid(){
		return $this->_key>=0 && $this->_reader->nodeType===XMLReader::ELEMENT && (is_null($this->_name) || $this->_name===$this->_reader->name);
	}

	public function rewind(){
        $this->_reader=$this->_reader->reset($this->_initRoute);
		$this->_key=0;
	}
}