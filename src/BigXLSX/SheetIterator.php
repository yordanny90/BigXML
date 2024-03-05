<?php

namespace BigXLSX;

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
	protected $rowBegin;
	protected $rowEnd;
	protected $colBegin;
	protected $colEnd;

	public function __construct(Sheet &$sheet, ?array $alias, ?int $rowBegin=null, ?int $colBegin=null, ?int $rowEnd=null, ?int $colEnd=null){
		$this->sheet=&$sheet;
		$this->sharedStrings=$sheet->getReader()->getSharedStrings();
		$this->stylesNum=$sheet->getReader()->getStylesNumeric();
		$this->cellObject=$sheet->isCellObject();
		$this->excludeHidden=$sheet->isExcludeHidden();
		$this->alias=$alias;
		$this->rowBegin=$rowBegin;
		$this->rowEnd=$rowEnd;
		$this->colBegin=$colBegin;
		$this->colEnd=$colEnd;
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
				if($col['hidden'] && strtolower($col['hidden'])!=='false' && is_numeric($col['min'])){
					$adds=array_keys(array_fill(intval($col['min'])-1,intval($col['max']??$col['min'])-intval($col['min'])+1,null));
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
		if(!$this->isCurrentRow()) return;
        $row=$this->sheetData->current();
		$r=$row['r'];
		$rowData=[];
		$this->cache[$r-1]=&$rowData;
		if($row->isEmptyElement) return;
		foreach($row->toSimpleXMLElement()->c as $cell){
			$col=static::colToIndex(str_replace($r, '', $cell['r']));
			if(is_null($col)) continue;
			if(!is_null($this->colBegin) && $col<$this->colBegin) continue;
			if(!is_null($this->colEnd) && $col>$this->colEnd) continue;
			if($this->excludeHidden && isset($this->hiddenCols[$col])) continue;
			if($this->alias && is_null($col=($this->alias[$col]??null))) continue;
			$rowData[$col]=$this->parseCellValue($cell);
		}
	}

	/**
	 * @param \SimpleXMLElement $cell
	 * @return CellValue|string
	 */
	protected function parseCellValue(\SimpleXMLElement $cell){
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
            case "d": // Date ISO8601
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
                if($type!=''){
                    $type.='';
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
	private function isCurrentRow(){
		return ($this->sheetData->valid() && intval($this->sheetData->current()['r'])==($this->key+1));
	}

	public function current(){
		if(isset($this->cache[$this->key])){
			return $this->cache[$this->key];
		}
		elseif($this->isCurrentRow()){
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
			while($this->sheetData->valid() && ($this->key+1)>intval($this->sheetData->current()['r'])){
				$this->sheetData->next();
			}
		}while($this->excludeHidden && $this->isCurrentRow() && ($row=$this->sheetData->current()) && ($row['hidden']??null) && strtolower($row['hidden'])!=='false');
	}

	public function key(){
		return $this->key;
	}

	public function valid(){
		if(!is_null($this->rowEnd) && $this->key()>$this->rowEnd){
			return false;
		}
		return isset($this->cache[$this->key]) || ($this->sheetData && $this->sheetData->valid());
	}

	public function rewind(){
		$this->cache=[];
		$this->key=-1;
		$this->parse($this->sheet->getFile());
		if(!$this->sheetData) return;
		$this->sheetData->rewind();
		$this->key=$this->sheetData->key();
		if(!is_null($this->rowBegin)){
			while($this->key()<$this->rowBegin && $this->valid()){
				$this->next();
			}
		}
	}

    static $cc=[];

	public static function colToIndex(string $col){
        if(isset(self::$cc[$col])) return self::$cc[$col];
		$index=0;
		foreach(str_split(strrev($col)) As $i=>&$l){
			$n=intval(base_convert($l, 36, 10))-10;
			if($n<0) return null;
			$index+=($n+1)*(26**$i);
		}
		$index-=1;
		return self::$cc[$col]=intval($index);
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