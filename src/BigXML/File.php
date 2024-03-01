<?php
/**
 * Yordanny Mejías V.
 * Creado: 2022-05-10
 * Modificado: 2022-05-10
 */

namespace BigXML;
use XMLReader;

/**
 * Clase para la lectura, validación y recorrido de un XML, sin comprometer el consumo de memoria
 */
class File{
	public $file;
	public $enc;
	public $flags;

	public function __construct(string $file, ?string $enc=null, int $flags=0){
		$this->file=$file;
		$this->enc=$enc;
		$this->flags=$flags;
	}

	/**
	 * Comprobacion rápida de la sintaxis del XML
	 * @return bool|null Devuelve NULL si no se pudo abrir el archivo
	 */
	public function validXML(){
		$xml=new XMLReader();
		if(!$xml->open($this->file, $this->enc, $this->flags)) return null;
		$valid_xml=false;
		if($xml->read()){
			while($xml->read()) ;
			$valid_xml=($xml->depth==0 && !$xml->read());
		}
		$xml->close();
		return $valid_xml;
	}

	public function makeMap($count=true){
		$xml=new XMLReader();
		if(!$xml->open($this->file, $this->enc, $this->flags)) return null;
		$map=[];
		$actual=&$map;
		$path=[&$actual];
		while($xml->read()){
			if($xml->nodeType===XMLReader::ELEMENT){
				$actual[$xml->name]=$actual[$xml->name]??[];
				if($count){
					$actual[$xml->name]['#']=($actual[$xml->name]['#']??0)+1;
				}
				if(!$xml->isEmptyElement){
					$actual=&$actual[$xml->name];
					$path[]=&$actual;
				}
			}
			elseif($xml->nodeType===XMLReader::END_ELEMENT){
				array_pop($path);
				$actual=&$path[count($path)-1];
			}
			else{
				continue;
			}
		}
		return $map;
	}

	public function &makeMapList(){
		$map=null;
		$xml=new XMLReader();
		if(!$xml->open($this->file, $this->enc, $this->flags)) return $map;
		$map=[];
		$path=[];
		while($xml->read()){
			if($xml->nodeType===XMLReader::ELEMENT){
				$path=array_slice($path, 0, $xml->depth);
				$path[]=$xml->name;
				$map[implode('/', $path)]=null;
			}
		}
		$map=array_keys($map);
		return $map;
	}

	/**
	 * @param string|null $path
	 * @return Reader|false|null Devuelve null si el archivo no se pudo abrir.<br>
	 * Davuelve false si la ruta no se encontró.
	 */
	public function getReader(?string $path=null){
		$xml=new XMLReader();
		if(!$xml->open($this->file, $this->enc, $this->flags)) return null;
		$reader=new Reader($xml, $this);
		if(is_string($path)){
			if($reader->findPath($path)){
				return $reader;
			}
			else{
				return false;
			}
		}
		return $reader;
	}

}
