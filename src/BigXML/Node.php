<?php

namespace BigXML;

use XMLReader;

/**
 *
 * @property-read int $attributeCount The number of attributes on the node
 * @property-read string $baseURI The base URI of the node
 * @property-read int $depth Depth of the node in the tree, starting at 0
 * @property-read bool $hasAttributes Indicates if node has attributes
 * @property-read bool $hasValue Indicates if node has a text value
 * @property-read bool $isDefault Indicates if attribute is defaulted from DTD
 * @property-read bool $isEmptyElement Indicates if node is an empty element tag
 * @property-read string $localName The local name of the node
 * @property-read string $name The qualified name of the node
 * @property-read string $namespaceURI The URI of the namespace associated with the node
 * @property-read int $nodeType The node type for the node
 * @property-read string $prefix The prefix of the namespace associated with the node
 * @property-read string $value The text value of the node
 * @property-read string $xmlLang The xml:lang scope which the node resides
 * @see XMLReader
 */
class Node implements \ArrayAccess{

	/**
	 * @var XMLReader
	 */
	protected $xml;

	public function __construct(XMLReader &$xml){
		$this->xml=&$xml;
	}

	public function __get($name){
		return $this->xml->$name;
	}

	public function attr_all(){
		$attr=[];
		if(!$this->xml->hasAttributes) return $attr;
		if($this->xml->moveToFirstAttribute()) do{
			$attr[$this->xml->name]=$this->xml->value;
		}while($this->xml->moveToNextAttribute());
		$this->xml->moveToElement();
		return $attr;
	}

	public function attrNo($index){
		$attr=null;
		if(!$this->xml->hasAttributes) return $attr;
		if($this->xml->moveToAttributeNo($index)){
			$attr=[$this->xml->name=>$this->xml->value];
		}
		$this->xml->moveToElement();
		return $attr;
	}

	public function attr($name){
		$attr=null;
		if(!$this->xml->hasAttributes) return $attr;
		if($this->xml->moveToAttribute($name)){
			$attr=$this->xml->value;
		}
		$this->xml->moveToElement();
		return $attr;
	}

	public function attrExists($name){
		$exists=false;
		if(!$this->xml->hasAttributes) return $exists;
		if($this->xml->moveToAttribute($name)){
			$exists=true;
		}
		$this->xml->moveToElement();
		return $exists;
	}

	public function attrNS($name, $namespace){
		$attr=null;
		if(!$this->xml->hasAttributes) return $attr;
		if($this->xml->moveToAttributeNs($name, $namespace)){
			$attr=$this->xml->value;
		}
		$this->xml->moveToElement();
		return $attr;
	}

	public function readInnerXml(){
		return $this->xml->readInnerXml();
	}

	public function readOuterXml(){
		return $this->xml->readOuterXml();
	}

	public function readString(){
		return $this->xml->readString();
	}

	/**
	 * @return false|\SimpleXMLElement
	 */
	public function toSimpleXMLElement(){
		return simplexml_load_string($this->xml->readOuterXml());
	}

    public function toDOM(\DOMNode $dom=null){
        return $this->xml->expand($dom);
    }

	public function offsetExists($offset){
		return $this->attrExists($offset);
	}

	public function offsetGet($offset){
		return $this->attr($offset);
	}

	public function offsetSet($offset, $value){
		return;
	}

	public function offsetUnset($offset){
		return;
	}

	public function __toString(){
		return $this->readString();
	}
}