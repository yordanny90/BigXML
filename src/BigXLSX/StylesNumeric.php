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
    /**
     * @var \Iterator|null
     */
    private $numFmt;
    /**
     * @var \Iterator|null
     */
    private $xf;
	private $calendar=1900;

	private function __construct(){ }

    public static function empty(){
        return new static();
    }

    public static function fromXML(\BigXML\File $xml=null, \ArrayAccess $cache=null){
        $new=new static();
        $new->cache=$cache??[];
        $reader=$xml->getReader('styleSheet/numFmts/numFmt');
        if($reader){
            $new->numFmt=$reader->getIterator();
            $new->numFmt->rewind();
        }
        $reader=$xml->getReader('styleSheet/cellXfs/xf');
        if($reader){
            $new->xf=$reader->getIterator();
            $new->xf->rewind();
        }
        return $new;
    }

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
				$numFmt[$nf['numFmtId']]=[
					'nfId'=>$nf['numFmtId'],
					'nfCode'=>$nf['formatCode'],
				];
			}
			if($reader=$this->xml->getReader('styleSheet/cellXfs/xf')){
				foreach($reader as $i=>$xf){
					if($xf['applyNumberFormat'] && !is_null($nfId=$xf['numFmtId'])){
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
        if(!isset($this->cache[$index]) && $this->xf){
            while($this->xf->valid()){
                $xf=$this->xf->current();
                if($xf['applyNumberFormat'] && !is_null($xf_numFmtId=$xf['numFmtId'])){
                    $i=$this->xf->key();
                    $this->cache[$i]=array_merge($this->cache[$i]??[], ['numFmt'=>$xf_numFmtId]);
                    if(!isset($this->cache[$xf_numFmtId]) && $this->numFmt){
                        while($this->numFmt->valid()){
                            $nf=$this->numFmt->current();
                            $numFmtId=$nf['numFmtId'];
                            $this->cache[$numFmtId]=[];
                            if(is_string($nf['formatCode'] ?? null) && preg_match('/^(\[\$\-\w+\])?([ymdHis][ymdHis\\/\-\\\,: ]+)(AM\/PM|;@)?$/i', $nf['formatCode'])){
                                $this->cache[$numFmtId]['date']=true;
                            }
                            $this->numFmt->next();
                            if($xf_numFmtId==$numFmtId) break;
                        }
                        if(!$this->numFmt->valid()) $this->numFmt=null;
                    }
                }
                $this->xf->next();
                if(isset($this->cache[$index])) break;
            }
            if(!$this->xf->valid()) $this->xf=null;
        }
        if(isset($this->cache[$index])){
            if(isset($this->cache[$index]['numFmt']) && isset($this->cache[$this->cache[$index]['numFmt']])){
                return array_merge($this->cache[$index], $this->cache[$this->cache[$index]['numFmt']]);
            }
        }
        return null;
	}

	public function isDate(int $index){
		return boolval($this->get($index)['date'] ?? false);
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