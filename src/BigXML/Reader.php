<?php

namespace BigXML;

use XMLReader;

/**
 *
 */
class Reader extends Node implements Explorer, \IteratorAggregate{

    /**
     * @var int[]
     */
    private $indexRoute_=[];
    private $path_=[];
    /**
     * @var File|null
     */
    private $file_;

    public function __construct(XMLReader &$xml, ?File &$file=null){
        parent::__construct($xml);
        $this->file_=&$file;
    }

	public static function fromXML($xml_str){
		$xml=new XMLReader();
		$xml->XML($xml_str);
		$reader=new self($xml);
		$reader->read();
		return $reader;
	}

	public function cloneNode(){
		return static::XML($this->readOuterXml());
	}

    public function reset(array $indexRoute){
        if(!$this->file_) return null;
        $reader=$this->file_->getReader();
        if($reader->apply_indexRoute($indexRoute)){
            return $reader;
        }
        return null;
    }

    public function copy(){
        return $this->reset($this->indexRoute_);
    }

    private function apply_indexRoute(array $indexRoute){
        foreach($indexRoute AS $next){
            if(!$this->xml->read()) return false;
            $this->path_=array_slice($this->path_, 0, $this->depth);
            $this->path_[]=$this->name;
            if($next>0){
                while($next-->0){
                    if(!$this->xml->next()) return false;
                }
                $this->path_=array_slice($this->path_, 0, $this->depth);
                $this->path_[]=$this->name;
            }
        }
        $this->indexRoute_=$indexRoute;
        return true;
    }

    private function updatePath(){
        $this->path_=array_slice($this->path_, 0, $this->depth);
        $this->path_[]=$this->name;
    }

    private function updateIndex(){
        if(($this->depth+1)<count($this->indexRoute_)) $this->indexRoute_=array_slice($this->indexRoute_, 0, $this->depth+1);
        if(!in_array($this->nodeType, [
            XMLReader::END_ELEMENT,
            XMLReader::END_ENTITY
        ])){
            $this->indexRoute_[$this->depth]=($this->indexRoute_[$this->depth] ?? -1)+1;
        }
    }

    public function isReady(){
        return $this->nodeType===XMLReader::ELEMENT;
    }

    public function getReady(){
        return $this->isReady()?:$this->read();
    }

    public function read(){
        while($this->xml->read()){
            $this->updateIndex();
            if($this->isReady()){
                $this->updatePath();
                return true;
            }
        }
        $this->updatePath();
        return false;
    }

    public function up(){
        $depth=$this->depth-1;
        while($this->xml->next()){
            $this->updateIndex();
            if($this->depth==$depth){
                $this->updatePath();
                return ($this->depth==$depth);
            }
        }
        $this->updatePath();
        return false;
    }

    public function upTo(int $depth){
        if($this->depth==$depth) return true;
        if($this->depth<$depth) return false;
        while($this->xml->next()){
            $this->updateIndex();
            if($this->depth==$depth){
                $this->updatePath();
                return ($this->depth==$depth);
            }
        }
        $this->updatePath();
        return false;
    }

    /**
     * @return bool
     */
    public function down(){
        if($this->isEmptyElement || !$this->isReady()) return false;
        $depth=$this->depth+1;
        if($this->xml->read()) do{
            $this->updateIndex();
            if($this->depth!=$depth || $this->isReady()){
                $this->updatePath();
                return ($this->depth==$depth && $this->isReady());
            }
        }while($this->xml->next());
        $this->updatePath();
        return false;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function downName(string $name){
        if($this->isEmptyElement || !$this->isReady()) return false;
        $depth=$this->depth+1;
        if($this->xml->read()) do{
            $this->updateIndex();
            if($this->depth!=$depth || ($this->name===$name && $this->isReady())){
                $this->updatePath();
                return ($this->depth==$depth && $this->name===$name && $this->isReady());
            }
        }while($this->xml->next());
        $this->updatePath();
        return false;
    }

    public function next(){
        if(!$this->isReady()) return false;
        $depth=$this->depth;
        while($this->xml->next()){
            $this->updateIndex();
            if($this->depth!=$depth || $this->isReady()){
                $this->updatePath();
                return ($this->depth==$depth && $this->isReady());
            }
        }
        $this->updatePath();
        return false;
    }

    public function nextName(string $name){
        if(!$this->isReady()) return false;
        $depth=$this->depth;
        while($this->xml->next()){
            $this->updateIndex();
            if($this->depth!=$depth || ($this->isReady() && $this->name===$name)){
                $this->updatePath();
                return ($this->depth==$depth && $this->name===$name && $this->isReady());
            }
        }
        $this->updatePath();
        return false;
    }

    /**
     * @return int[]
     */
    public function getIndexRoute(){
        return $this->indexRoute_;
    }

    /**
     * @return File|null
     */
    public function getFile(){
        return $this->file_;
    }

    /**
     * @return string|null
     */
    public function path(){
        return ($this->isReady()?implode('/', $this->path_):null);
    }

    /**
     * Busca el nodo correspondiente a la ruta indicada
     * @param string $path Ruta del nodo. Ejemplo: "NodoPrincipal/NodoSecundario/NodoFinal".<br>
     * Opcionalmente, se puede indicar mediante llaves [], la posición del nodo de ese nivel que se quiere buscar.<br>
     * por ejemplo, "info/detalle[1]/id" busca el nodo "id" en el segundo "detalle" del nodo principal "info"
     * @return bool
     */
    public function findPath(string $path){
        return $this->find(explode('/', $path));
    }

    private function find(array $path){
        if($this->nodeType===XMLReader::NONE && !$this->read()) return false;
        while(!is_null($name=array_shift($path))){
            $offset=0;
            if(preg_match('/^(.*)\[(\d+)\]$/', $name, $m)){
                $name=$m[1];
                $offset=$m[2];
            }
            if($this->name!==$name){
                if(!$this->nextName($name)) return false;
            }
            while(0<$offset){
                --$offset;
                if(!$this->nextName($name)) return false;
            }
            if(count($path) && !$this->down()) return false;
        }
        return true;
    }

    /**
     * @return \Iterator
     */
    public function getIterator(){
        return new Iterator($this, $this->name);
    }

    /**
     * @return Iterator
     */
    public function getIteratorAnyName(){
        return new Iterator($this);
    }

    public function getNode(){
        return new Node($this->xml);
    }

}