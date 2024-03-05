<?php

namespace BigXLSX;

/**
 *
 * @property-read int $sheetId Identificador de la hoja
 * @property-read string $name Nombre de la hoja
 * @property-read string $state Estado de la hoja
 * @property-read string $rId Identificador del recurso
 * @property-read string $path Ruta de recurso
 */
class Sheet implements \IteratorAggregate{
	// Esquemas de tablas dinámicas encontrado en: "xl/worksheets/_rels/{SHEETNAME.xml}.rels"
	const SCHEMA_TABLE='http://schemas.openxmlformats.org/officeDocument/2006/relationships/table';
	const SCHEMA_TABLE_OOXML='http://purl.oclc.org/ooxml/officeDocument/relationships/table';

	/**
	 * @var Reader
	 */
	private $reader;
	/**
	 * @var array
	 */
	private $info;
	/**
	 * @var string
	 */
	private $file;
	private $cellObject=false;
	private $excludeHidden=true;
	/**
	 * @var array|null
	 */
	protected $alias;

	public function __construct(Reader &$reader, array $info){
		$this->reader=&$reader;
		$info['rId']=$info['r:id']??null;
		$this->info=&$info;
		$this->file=new \BigXML\File($reader->getEntryPath($this->path));
	}

	public function __get($name){
		return $this->info[$name]??null;
	}

	public function isHidden(){
		return ($this->state=='hidden');
	}

	/**
	 * @param bool $excludeHidden
	 */
	public function setExcludeHidden(bool $excludeHidden){
		$this->excludeHidden=$excludeHidden;
	}

	/**
	 * @return bool
	 */
	public function isExcludeHidden(){
		return $this->excludeHidden;
	}

	/**
	 * @param array|null $alias
	 */
	public function alias(?array $alias){
		if($alias){
			foreach($alias as $k=>&$v){
				if(is_null($v)) $v=$k;
			}
		}
		$this->alias=$alias;
	}

	/**
	 * @return array|null
	 */
	public function getAlias(){
		return $this->alias;
	}

	/**
	 * Establece si los valores en cada fila son incluidos como un objeto {@see CellValue}
	 * @param bool $cellObject
	 */
	public function setCellObject(bool $cellObject){
		$this->cellObject=$cellObject;
	}

	/**
	 * @return bool
	 */
	public function isCellObject(){
		return $this->cellObject;
	}

	/**
	 * @return string
	 */
	public function getFile(){
		return $this->file;
	}

	public function &getReader(){
		return $this->reader;
	}

	/**
	 * @return Table[]
     * @throws \Exception
     */
	public function getTables(){
		$tables=[];
		if($srels=(new \BigXML\File($this->getReader()->getEntryPath(dirname($this->path).'/_rels/'.basename($this->path).'.rels')))->getReader('Relationships/Relationship')){
			foreach($srels->getIterator() AS $srel){
				if(in_array($srel['Type'], [
					self::SCHEMA_TABLE,
					self::SCHEMA_TABLE_OOXML
				])){
					$pathTable=dirname($this->path).'/'.$srel['Target'];
					if($rtable=(new \BigXML\File($this->getReader()->getEntryPath($pathTable)))->getReader('table')){
						$infoTable=$rtable->attr_all();
						$infoTable['r:id']=$srel['Id'];
						$infoTable['path']=$pathTable;
                        if(($tb=Table::init($this, $infoTable))){
                            $tables[$infoTable['r:id']]=$tb;
                        }
					}
				}
			}
		}
		return $tables;
	}

    /**
     * @param $tablerId
     * @return Table|null
     * @throws \Exception
     */
	public function getTableByrId($tablerId){
		if($srels=(new \BigXML\File($this->getReader()->getEntryPath(dirname($this->path).'/_rels/'.basename($this->path).'.rels')))->getReader('Relationships/Relationship')){
			foreach($srels->getIterator() AS $srel){
				if(in_array($srel['Type'], [
					self::SCHEMA_TABLE,
					self::SCHEMA_TABLE_OOXML
				]) && $tablerId==$srel['Id']){
					$pathTable=dirname($this->path).'/'.$srel['Target'];
					if($rtable=(new \BigXML\File($this->getReader()->getEntryPath($pathTable)))->getReader('table')){
						$infoTable=$rtable->attr_all();
						$infoTable['r:id']=$srel['Id'];
						$infoTable['path']=$pathTable;
						return Table::init($this, $infoTable);
					}
				}
			}
		}
		return null;
	}

	/**
	 * @return SheetIterator
	 */
	public function getIterator(){
		return new SheetIterator($this, $this->getAlias());
	}

}