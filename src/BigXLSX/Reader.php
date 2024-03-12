<?php

namespace BigXLSX;

use Exception;

class Reader{
    protected static $useSQLite=false;
    protected static $saveCacheStylesNumeric=false;
    protected static $saveCacheSharedStrings=false;

	/**
	 * @var \BigXML\File|null
	 */
	protected $sharedStrings;
	/**
	 * @var \BigXML\File|null
	 */
	protected $styles;
    protected $cache_SS;
    protected $cache_SN;
	protected $calendar=1900;
	/**
	 * @var Sheet[] Hojas del archivo
	 */
	protected $sheets;
	protected $file;

	const SCHEMA_OFFICEDOCUMENT='http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
	const SCHEMA_OFFICEDOCUMENT_OOXML='http://purl.oclc.org/ooxml/officeDocument/relationships/officeDocument';
	const SCHEMA_SHAREDSTRINGS='http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings';
	const SCHEMA_SHAREDSTRINGS_OOXML='http://purl.oclc.org/ooxml/officeDocument/relationships/sharedStrings';
	const SCHEMA_STYLES='http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';
	const SCHEMA_STYLES_OOXML='http://purl.oclc.org/ooxml/officeDocument/relationships/styles';
	const SCHEMA_WORKSHEETRELATION='http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet';
	const SCHEMA_WORKSHEETRELATION_OOXML='http://purl.oclc.org/ooxml/officeDocument/relationships/worksheet';

	/**
	 * @param $file
	 * @throws Exception
	 */
	public function __construct($file){
		$filepath=realpath($file);
		if(!$filepath) throw new Exception("File not found: ".$filepath);
		$this->file=$filepath;
		$this->parse();
	}

	/**
	 * @return string
	 */
	public function getFile(){
		return $this->file;
	}

	/**
	 * @throws Exception
	 */
	protected function getEntryData($name){
		$data=file_get_contents($this->getEntryPath($name));
		if($data===false){
			throw new Exception("Entry does not exist in the Excel file: ".$name);
		}
		else{
			return $data;
		}
	}

	public function getEntryPath($name){
		$name=preg_replace('/(\/|^)[^\/]+\/\.\.\//', '$1', $name);
		$path='zip://'.$this->file.'#'.$name;
		return $path;
	}

	/**
	 * @throws Exception
	 */
	protected function parse(){
		$sheets=[];
		$rels_reader=(new \BigXML\File($this->getEntryPath("_rels/.rels")))->getReader('Relationships/Relationship');
		if(!$rels_reader) throw new Exception('XLSX rels not found');
		foreach($rels_reader->getIterator() as $node){
			if(in_array($node['Type'], [self::SCHEMA_OFFICEDOCUMENT, self::SCHEMA_OFFICEDOCUMENT_OOXML])){
				$workbook=$node['Target'];
				$work_dir=dirname($workbook);
				$workfile=new \BigXML\File($this->getEntryPath($workbook));
				$work_sheets=$workfile->getReader('workbook/sheets/sheet');
				if($workbookPr=$workfile->getReader('workbook/workbookPr')){
					if(in_array($workbookPr['date1904'], [
						'1',
						'true'
					])){
						$this->calendar=1094;
					}
				}
				if(!$work_sheets) throw new Exception('XLSX workbook sheets not found');
				$work_rels=(new \BigXML\File($this->getEntryPath($work_dir.'/_rels/'.basename($workbook).'.rels')))->getReader('Relationships/Relationship');
				if(!$work_rels) throw new Exception('XLSX workbook rels not found');
				foreach($work_sheets->getIterator() as $sheet){
					$info=$sheet->attr_all();
					$sheets[$info['r:id']]=$info;
				}
				foreach($work_rels as $wrel){
					switch($wrel['Type']){
						case self::SCHEMA_WORKSHEETRELATION:
						case self::SCHEMA_WORKSHEETRELATION_OOXML:
							if(isset($sheets[(string)$wrel['Id']])) $sheets[(string)$wrel['Id']]['path']=$work_dir.'/'.$wrel['Target'];
							break;
						case self::SCHEMA_SHAREDSTRINGS:
						case self::SCHEMA_SHAREDSTRINGS_OOXML:
							$this->sharedStrings=new \BigXML\File($this->getEntryPath($work_dir.'/'.$wrel['Target']));
							break;
						case self::SCHEMA_STYLES:
						case self::SCHEMA_STYLES_OOXML:
							$this->styles=new \BigXML\File($this->getEntryPath($work_dir.'/'.$wrel['Target']));
							break;
					}
				}
			}
		}
		$this->sheets=[];
		foreach($sheets as $info){
			if(!isset($info['path'])) continue;
			$this->sheets[$info['r:id']]=new Sheet($this, $info);
		}
	}

	public function &getSharedStrings(){
		if($this->cache_SS) return $this->cache_SS;
		if($this->sharedStrings){
			$cache=SharedStrings::fromXML($this->sharedStrings, self::isUseSQLite());
		}
        else{
            $cache=SharedStrings::empty();
        }
		if(self::isSaveCacheSharedStrings()) $this->cache_SS=&$cache;
		return $cache;
	}

	public static function saveCacheSharedStrings($enable=true){
        self::$saveCacheSharedStrings=boolval($enable);
	}

	public static function isSaveCacheSharedStrings(){
		return self::$saveCacheSharedStrings;
	}

	public function &getStylesNumeric(){
		if($this->cache_SN) return $this->cache_SN;
		if($this->styles){
			$cache=StylesNumeric::fromXML($this->styles, self::isUseSQLite());
		}
        else{
            $cache=StylesNumeric::empty();
        }
        $cache->setCalendar($this->calendar);
		if(self::isSaveCacheStylesNumeric()) $this->cache_SN=&$cache;
		return $cache;
	}

	public static function saveCacheStylesNumeric($enable=true){
        self::$saveCacheStylesNumeric=boolval($enable);
	}

	public static function isSaveCacheStylesNumeric(){
		return self::$saveCacheStylesNumeric;
	}

    public static function useSQLite($use=true){
        self::$useSQLite=boolval($use);
    }

    /**
     * @return bool
     */
    public static function isUseSQLite(){
        return self::$useSQLite;
    }

    public function getSheetNames($alsoHidden=false){
		$res=[];
		foreach($this->sheets as &$sheet){
			if(!$alsoHidden && $sheet->isHidden()) continue;
			$res[$sheet->rId]=$sheet->name;
		}
		return $res;
	}

	public function getTableNames($alsoHidden=false){
		$res=[];
		foreach($this->sheets as &$sheet){
			if(!$alsoHidden && $sheet->isHidden()) continue;
			$tables=$sheet->getTables();
			foreach($tables AS $tb){
				$res[$sheet->rId.':'.$tb->rId]=$sheet->name.' ['.$tb->name.']';
			}
		}
		return $res;
	}

	public function getSheetrIdNames($alsoHidden=false){
		$res=[];
		foreach($this->sheets as &$sheet){
			if(!$alsoHidden && $sheet->isHidden()) continue;
			$res[]=[
                'type'=> 'sheet',
				'id'=>$sheet->rId,
				'name'=>$sheet->name,
				'hidden'=>$sheet->isHidden()
			];
		}
		return $res;
	}

	public function getTablerIdNames($alsoHidden=false){
		$res=[];
		foreach($this->sheets as &$sheet){
			if(!$alsoHidden && $sheet->isHidden()) continue;
			$tables=$sheet->getTables();
			foreach($tables AS $tb){
				$res[]=[
                    'type'=> 'table',
					'id'=>$sheet->rId.':'.$tb->rId,
					'name'=>$tb->name,
					'hidden'=>$sheet->isHidden()
				];
			}
		}
		return $res;
	}

	/**
	 * @param $tablerId
	 * @return Table|null
	 */
	public function getTableByrId($tablerId){
		list($sheetId, $tbrId)=explode(':', $tablerId, 2);
		if($sheet=$this->getSheetByrId($sheetId)){
			return $sheet->getTableByrId($tbrId);
		}
		return null;
	}

	public function getSheetCount($alsoHidden=false){
		if(!$alsoHidden){
			$c=0;
			foreach($this->sheets as &$sheet){
				if(!$sheet->isHidden()) ++$c;
			}
			return $c;
		}
		return count($this->sheets);
	}

	/**
	 * @param $sheetId
	 * @return Sheet|null
	 */
	public function getSheetByrId($sheetrId){
		if($s=$this->sheets[$sheetrId]??null){
			if($s->rId===$sheetrId) return $s;
		}
		return null;
	}

	/**
	 * @param $sheetName
	 * @return Sheet|null
	 */
	public function getSheetByName($sheetName){
		foreach($this->sheets AS &$s){
			if($s->name===$sheetName) return $s;
		}
		return null;
	}

}