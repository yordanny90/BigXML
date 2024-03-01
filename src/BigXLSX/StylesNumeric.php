<?php

namespace BigXLSX;

class StylesNumeric{
	const CALENDAR_DATE=[
		1900=>'1899-12-30', // Equivalente a 1900-00-30
		1904=>'1904-01-01',
	];
	/**
	 * @var \BigXML\File
	 */
	private $xml;
	private $cache=[];
	private $calendar=1900;

	public function __construct(){ }

	/**
	 * @param int $calendar Posibles valores: 1900, 1904
	 */
	public function setCalendar(int $calendar=1900){
		if(isset(self::CALENDAR_DATE[$calendar])) $this->calendar=$calendar;
		$this->calendar=1900;
	}

	/**
	 * @return int
	 */
	public function getCalendar(){
		return $this->calendar;
	}

	public function assign(\BigXML\File $xml=null){
		$this->cache=[];
		$this->xml=$xml;
	}

	/**
	 * Prepara la lista de strings del xml, en caso de que no se hayan cargado aún
	 * @return bool Devuelve TRUE si el xml existe y se pudo cargar la lista de strings.<br>
	 * En caso de fallo, devuelve FALSE, pero la lista cargada anteriormente sigue intacta
	 */
	public function prepare(){
		if(!$this->xml) return false;
		$this->cache=[];
		$success=false;
		if($reader=$this->xml->getReader('styleSheet/numFmts/numFmt')){
			$numFmt=[];
			foreach($reader as $nf){
				$numFmt[$nf->attr('numFmtId')]=[
					'nfId'=>$nf->attr('numFmtId'),
					'nfCode'=>$nf->attr('formatCode'),
				];
			}
			if($reader=$this->xml->getReader('styleSheet/cellXfs/xf')){
				foreach($reader as $i=>$xf){
					if($xf->attr('applyNumberFormat') && !is_null($nfId=$xf->attr('numFmtId'))){
						if(!isset($numFmt[$nfId])){
							if(isset($this->cache[$nfId])){
								$this->cache[$i]=&$this->cache[$nfId];
							}
							else{
								$this->cache[$i]=[
									'nfId'=>$nfId,
								];
							}
							continue;
						}
						if(preg_match('/^(\[\$\-\w+\])?([ymdHis][ymdHis\\/\-\\\,: ]+)(AM\/PM|;@)?$/i', $numFmt[$nfId]['nfCode'])){
							$numFmt[$nfId]['date']=true;
						}
						$this->cache[$i]=&$numFmt[$nfId];
					}
				}
				$success=true;
			}
		}
		$this->xml=null;
		return $success;
	}

	public function get(int $index){
		return $this->cache[$index] ?? null;
	}

	public function isDate(int $index){
		return (bool)($this->cache[$index]['date'] ?? false);
	}

	public function parseDate(string $dtValue){
		return self::dateXLStoPHP($dtValue, $this->calendar);
	}

	public static function dateXLStoPHP(string $dtValue, $calendar=1900){
		if(!is_numeric($dtValue) || bccomp(0, $dtValue, 17)>=0) return $dtValue;
		$dtValue=trim($dtValue);
		$dateVal=bcdiv($dtValue, 1, 0);
		$timeVal=bcmod($dtValue, 1, 17);
		$result=[];
		if($dateVal>0){
			isset(self::CALENDAR_DATE[$calendar]) OR $calendar=1900;
			if($dateVal<=60 && $calendar==1900) ++$dateVal; // 1900-02-29 exception
			if($date=date_create(self::CALENDAR_DATE[$calendar].' '.$dateVal.' day')){
				$result[]=$date->format('Y-m-d');
			}
		}
		if(($total_secs=round($timeVal*86400))>0){
			if($date=date_create('today +'.$total_secs.' sec')){
				$result[]=$date->format('H:i:s');
			}
		}
		return implode(' ', $result);
	}

	public static function validDateTime($val){
		return preg_match('/^\d{4,}-\d{2}-\d{2}(?: \d{2}:\d{2}:\d{2})$/', $val)?$val:null;
	}

	public static function validDate($val){
		return preg_match('/^\d{4,}-\d{2}-\d{2}$/', $val)?$val:null;
	}

	public static function validTime($val){
		return preg_match('/^\d{2}:\d{2}:\d{2}$/', $val)?$val:null;
	}
}