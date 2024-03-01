<?php

namespace BigXLSX;

use Exception;

class Reader{

	/**
	 * @var \BigXML\File|null
	 */
	protected $sharedStrings;
	/**
	 * @var \BigXML\File|null
	 */
	protected $styles;
	protected $cache=[];
	protected $save_cache=[];
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
		if(!$filepath) throw new Exception("File $file not found");
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
			throw new Exception("File $name does not exist in the Excel file");
		}
		else{
			return $data;
		}
	}

	public function getEntryPath($name){
		$path='zip://'.$this->file.'#'.$name;
		return $path;
	}

	/**
	 * @throws Exception
	 */
	protected function parse(){
		$sheets=[];
		$rels_reader=(new \BigXML\File($this->getEntryPath("_rels/.rels")))->getReader('Relationships/Relationship');
		if(!$rels_reader) throw new Exception('Invalid XLSX file: rels not found');
		foreach($rels_reader->getIterator() as $node){
			if(in_array($node->attr('Type'), [self::SCHEMA_OFFICEDOCUMENT, self::SCHEMA_OFFICEDOCUMENT_OOXML])){
				$workbook=$node->attr('Target');
				$work_dir=dirname($workbook);
				$workfile=new \BigXML\File($this->getEntryPath($workbook));
				$work_sheets=$workfile->getReader('workbook/sheets/sheet');
				if($workbookPr=$workfile->getReader('workbook/workbookPr')){
					if(in_array($workbookPr->attr('date1904'), [
						'1',
						'true'
					])){
						$this->calendar=1094;
					}
				}
				if(!$work_sheets) throw new Exception('Invalid XLSX file: workbook sheets not found');
				$work_rels=(new \BigXML\File($this->getEntryPath($work_dir.'/_rels/'.basename($workbook).'.rels')))->getReader('Relationships/Relationship');
				if(!$work_rels) throw new Exception('Invalid XLSX file: workbook rels not found');
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
			$this->sheets[$info['sheetId']]=new Sheet($this, $info);
		}
	}

	public function &getSharedStrings(){
		if(isset($this->cache['SharedStrings'])) return $this->cache['SharedStrings'];
		$cache=new SharedStrings();
		if($this->sharedStrings){
			$cache->assign($this->sharedStrings);
		}
		if($this->save_cache['SharedStrings']??false) $this->cache['SharedStrings']=&$cache;
		return $cache;
	}

	public function disableCacheSS(){
		$this->save_cache['SharedStrings']=false;
		unset($this->cache['SharedStrings']);
	}

	public function enableCacheSS(){
		$this->save_cache['SharedStrings']=true;
	}

	public function isSaveCacheSS(){
		return $this->save_cache['SharedStrings']??false;
	}

	public function &getStylesNumeric(){
		if(isset($this->cache['StylesNumeric'])) return $this->cache['StylesNumeric'];
		$cache=new StylesNumeric();
		$cache->setCalendar($this->calendar);
		if($this->styles){
			$cache->assign($this->styles);
		}
		if($this->save_cache['StylesNumeric']??false) $this->cache['StylesNumeric']=&$cache;
		return $cache;
	}

	public function disableCacheStylesNumeric(){
		$this->save_cache['StylesNumeric']=false;
		unset($this->cache['StylesNumeric']);
	}

	public function enableCacheStylesNumeric(){
		$this->save_cache['StylesNumeric']=true;
	}

	public function isSaveCacheStylesNumeric(){
		return $this->save_cache['StylesNumeric']??false;
	}

	public function getSheetNames($alsoHidden=false){
		$res=[];
		foreach($this->sheets as &$sheet){
			if(!$alsoHidden && $sheet->isHidden()) continue;
			$res[$sheet->sheetId]=$sheet->name;
		}
		return $res;
	}

	public function getSheetrIdNames($alsoHidden=false){
		$res=[];
		foreach($this->sheets as &$sheet){
			if(!$alsoHidden && $sheet->isHidden()) continue;
			$res[]=[
				'id'=>$sheet->rId,
				'name'=>$sheet->name,
				'hidden'=>$sheet->isHidden()
			];
		}
		return $res;
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
	public function getSheetById($sheetId){
		return $this->sheets[$sheetId]??null;
	}

	/**
	 * @param $sheetId
	 * @return Sheet|null
	 */
	public function getSheetByrId($sheetrId){
		foreach($this->sheets AS &$s){
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