<?php

namespace BigXLSX;

use SimpleXMLElement;

class SheetIterator implements \Iterator{
	protected static $columnsName=[];

	protected $sheet;
	/**
	 * @var SharedStrings
	 */
	protected $sharedStrings;
	/**
	 * @var StylesNumeric
	 */
	protected $stylesNum;
	/**
	 * @var \BigXML\Iterator|null
	 */
	protected $sheetData;
	/**
	 * @var bool Si es true, lo errores son incluidos como objetos en cada fila
	 */
	protected $cellObject=false;
	protected $key=-1;
	protected $cache=[];
	protected $excludeHidden=true;
	protected $hiddenCols=[];
	protected $alias;

	public function __construct(Sheet &$sheet){
		$this->sheet=&$sheet;
		$this->sharedStrings=$sheet->getReader()->getSharedStrings();
		$this->stylesNum=$sheet->getReader()->getStylesNumeric();
		$this->cellObject=$sheet->isCellObject();
		$this->excludeHidden=$sheet->isExcludeHidden();
		$this->alias=$sheet->getAlias();
	}

	/**
	 * @return bool
	 */
	public function isExcludeHidden(){
		return $this->excludeHidden;
	}

	/**
	 * @param \BigXML\File $file
	 * @return void
	 */
	private function parse(\BigXML\File $file){
		$xml=$file->getReader('worksheet/sheetData/row');
		$this->sheetData=$xml?$xml->getIterator():null;
		if(!count($this->hiddenCols) && $cols=$file->getReader('worksheet/cols/col')){
			$hiddenCols=[];
			foreach($cols->getIterator() As $col){
				if($col->attr('hidden') && strtolower($col->attr('hidden'))!=='false' && is_numeric($col->attr('min'))){
					$adds=array_keys(array_fill(intval($col->attr('min'))-1,intval($col->attr('max')??$col->attr('min'))-intval($col->attr('min'))+1,null));
					$hiddenCols=array_merge($hiddenCols, $adds);
				}
			}
			$hiddenCols=array_combine($hiddenCols, $hiddenCols);
			$this->hiddenCols=$hiddenCols;
		}
	}

	/**
	 * @return void
	 */
	private function parseData(){
		if(!($row=$this->currentRow())) return;
		$r=$row->attr('r');
		$rowData=[];
		$this->cache[$r-1]=&$rowData;
		if($row->isEmptyElement) return;
		foreach($row->toSimpleXMLElement()->c as $cell){
			$col=static::colToIndex(str_replace($r, '', $cell['r']));
			if(is_null($col)) continue;
			if($this->excludeHidden && isset($this->hiddenCols[$col])) continue;
			if($this->alias && is_null($col=($this->alias[$col]??null))) continue;
			$rowData[$col]=$this->parseCellValue($cell);
		}
	}

	/**
	 * @param SimpleXMLElement $cell
	 * @return CellValue|string
	 */
	protected function parseCellValue(SimpleXMLElement $cell){
		$value=null;
		switch($type=strval($cell['t'])){
			case "s": // shared string
				if(isset($cell->v)){
					$value=$this->sharedStrings->get(intval($cell->v));
				}
				break;
			case "b": // boolean
				$value=strval($cell->v)?1:0;
				break;
			case "inlineStr": // rich text inline
				$x=new \XMLReader();
				if($x->XML($cell->is->asXML()) && $x->read()){
					$value=SharedStrings::normalizeString($x->readString());
				}
				break;
			case "e": // error message
				$value=strval($cell->v);
				break;
			case "n": // Number
				if(isset($cell->v)) $value=strval($cell->v);
				break;
			default:
				if(isset($cell->v)){
					$value=SharedStrings::normalizeString($cell->v);
					if(isset($cell['s']) && $this->stylesNum->isDate((string)$cell['s'])){
						$type='date';
						$value=$this->stylesNum->parseDate($value);
					}
				}
				break;
		}
		$value=new CellValue($value,$type);
		if(!$this->cellObject) $value=$value->value();
		return $value;
	}

	/**
	 * @return \BigXML\Iterator|null
	 */
	private function currentRow(){
		if($this->sheetData->valid() && intval($this->sheetData->current()->attr('r'))==($this->key+1)){
			return $this->sheetData->current();
		}
		return null;
	}

	public function current(){
		if(isset($this->cache[$this->key])){
			return $this->cache[$this->key];
		}
		elseif($this->currentRow()){
			$this->sharedStrings->prepare();
			$this->stylesNum->prepare();
			$this->cache=[];
			$this->parseData();
			return $this->cache[$this->key]??[];
		}
		else{
			return [];
		}
	}

	public function next(){
		do{
			++$this->key;
			if(isset($this->cache[$this->key])) return;
			while($this->sheetData->valid() && ($this->key+1)>intval($this->sheetData->current()->attr('r'))){
				$this->sheetData->next();
			}
		}while($this->excludeHidden && ($row=$this->currentRow()) && $row->attr('hidden') && strtolower($row->attr('hidden'))!=='false');
	}

	public function key(){
		return $this->key;
	}

	public function valid(){
		return isset($this->cache[$this->key]) || ($this->sheetData && $this->sheetData->valid());
	}

	public function rewind(){
		$this->cache=[];
		$this->key=-1;
		$this->parse($this->sheet->getFile());
		if(!$this->sheetData) return;
		$this->sheetData->rewind();
		$this->key=$this->sheetData->key();
	}

	public static function colToIndex(string $col){
		$index=0;
		foreach(str_split(strrev($col)) As $i=>&$l){
			$n=base_convert($l, 36, 10)-10;
			if($n<0) return null;
			$index+=($n+1)*pow(26, $i);
		}
		$index-=1;
		return $index;
	}

	public static function indexToCol(int $index){
		if($index<0) return null;
		$col='';
		$i=$index;
		while($i>=26){
			$ch=base_convert(($i%26)+10, 10, 36);
			$i=intval($i/26)-1;
			$col.=$ch;
		}
		$ch=base_convert(($i%26)+10, 10, 36);
		$col.=$ch;
		$col=strtoupper(strrev($col));
		return $col;
	}

}