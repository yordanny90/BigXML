<?php

namespace BigXML;

use XMLReader;

class Iterator extends Node implements Explorer, \Iterator{
	private $_key=-1;
	/**
	 * @var Reader
	 */
	private $_reader;
	private $_initRoute;
	/**
	 * @var int
	 */
	private $_depth;
	/**
	 * @var string|null
	 */
	private $_name;

	public function __construct(Reader &$reader, ?string $name=null){
		parent::__construct($reader->xml);
		$this->_reader=&$reader;
		$this->_initRoute=$this->getIndexRoute();
		$this->_depth=$this->depth;
		$this->_name=$name;
	}

	public function cloneReader(){
		return $this->_reader->cloneReader();
	}

	public function copyReader(){
		return $this->_reader->copy();
	}

	/**
	 * @return int[]
	 */
	public function getIndexRoute(){
		return $this->_reader->getIndexRoute();
	}

	/**
	 * @return File|null
	 */
	public function getFile(){
		return $this->_reader->getFile();
	}

	/**
	 * @return string|null
	 */
	public function path(){
		return $this->_reader->path();
	}

	/**
	 * @return $this
	 */
	public function current(){
		return $this;
	}

	public function next(){
		if($this->_key<0) return;
		++$this->_key;
		$this->_reader->upTo($this->_depth);
		if(is_string($this->_name)) $this->_reader->nextName($this->_name);
		else $this->_reader->next();
	}

	public function key(){
		return $this->_key;
	}

	public function valid(){
		return $this->_key>=0 && $this->_depth===$this->_reader->depth && $this->_reader->nodeType===XMLReader::ELEMENT && (is_null($this->_name) || $this->_name===$this->_reader->name);
	}

	public function rewind(){
		$this->_key=($this->_key===-1?0:-2);
	}
}