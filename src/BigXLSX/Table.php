<?php

namespace BigXLSX;

/**
 *
 * @property-read string $rId Identificador del recurso
 * @property-read string $name Nombre de la hoja
 * @property-read string $displayName Nombre visible de la hoja
 * @property-read string $path Ruta de recurso
 * @property-read string $ref
 * @property-read int $totalsRowShown
 */
class Table implements \IteratorAggregate{
	protected $sheet;
	protected $file;
	/**
	 * @var
	 */
	protected $info;
    protected $colBegin;
    protected $rowBegin;
    protected $colEnd;
    protected $rowEnd;

    /**
     * @param Sheet $sheet
     * @param array $info
     */
	private function __construct(Sheet &$sheet, array &$info){
		$this->sheet=clone $sheet;
		$this->info=&$info;
		$info['rId']=$info['r:id']??null;
		$this->file=new \BigXML\File($sheet->getReader()->getEntryPath($this->path));
	}

    /**
     * @param Sheet $sheet
     * @param array $info
     * @return static|null
     */
    public static function init(Sheet &$sheet, array $info){
        if(!is_string($info['ref']??null) || !($range=self::rangeToArray($info['ref']))) return null;
        $new=new static($sheet, $info);
        $new->colBegin=$range[0];
        $new->rowBegin=$range[1];
        $new->colEnd=$range[2];
        $new->rowEnd=$range[3];
        return $new;
    }

    /**
     * @param string $ref
     * @return array|null Si es válido<ul>
     * <li>0: Número de columna inicial</li>
     * <li>1: Número de fila inicial</li>
     * <li>2: Número de columna final</li>
     * <li>3: Número de fila final</li>
     * </ul>
     */
    public static function rangeToArray(string $ref){
        if(!preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $ref, $m)){
            return null;
        }
        if(is_null($colI=SheetIterator::colToIndex($m[1]))) return null;
        if(is_null($colF=SheetIterator::colToIndex($m[3]))) return null;
        $range=[
            $colI,
            intval($m[2])-1,
            $colF,
            intval($m[4])-1,
        ];
        return $range;
    }

    public function __get($name){
		return $this->info[$name]??null;
	}

	/**
	 * @param bool $excludeHidden
	 * @return void
	 */
	public function setExcludeHidden(bool $excludeHidden){
		$this->sheet->setExcludeHidden($excludeHidden);
	}

    public function getRowBegin(){
		return $this->rowBegin;
	}

	public function getRowEnd(){
		return $this->rowEnd-($this->totalsRowShown?1:0);
	}

	public function getColumnBegin(){
		return $this->colBegin;
	}

	public function getColumnEnd(){
		return $this->colEnd;
	}

	public function alias(?array $alias){
		foreach(array_keys($alias) AS $k){
			if(is_numeric($k) && ($k<$this->getColumnBegin() || $k>$this->getColumnEnd())){
				unset($alias);
			}
		}
		$this->sheet->alias($alias);
	}

	/**
	 * @return array|null
	 */
	public function getAlias(){
		return $this->sheet->getAlias();
	}

    /**
     * @return array|null
     */
	public function getTableColumns(){
		if($cols=$this->file->getReader('table/tableColumns/tableColumn')){
			$list=[];
			foreach($cols->getIterator() AS $col){
				$pos=$this->getColumnBegin()+$col['id']-1;
				$list[$pos]=$col['name'];
			}
			return $list;
		}
		return null;
	}

    /**
     * @return array
     */
    public function getHeaderRow(){
        $it=new SheetIterator($this->sheet, null, $this->getRowBegin(), $this->getColumnBegin(), $this->getRowBegin(), $this->getColumnEnd());
        $it->rewind();
        $it=$it->current();
        if(!is_array($it)) return [];
        return $it;
    }

    /**
	 * @return SheetIterator
	 */
	public function getIterator(){
		return new SheetIterator($this->sheet, $this->getAlias()??$this->getTableColumns(), $this->getRowBegin(), $this->getColumnBegin(), $this->getRowEnd(), $this->getColumnEnd());
	}

}