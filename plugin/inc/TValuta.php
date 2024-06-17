<?php
// Imported from iService2, needs refactoring.
/*
=================TValuta=============
v. 2.1
Preluare automata a cursului valutar de pe site-ul BNR.
(c) 2003-2011 Lucian Sabo - luciansabo@gmail.com
Clasa TValuta
Proprietati:
------------------
STRING euro;				//cursul oficial EURO asa cum a fost el preluat
STRING dolar;				//cursul oficial USD asa cum a fost el preluat
STRING data;				//data pentru care este valabil cursul asa cum a fost ea prealuata
INT EuroCaNumar;		//cursul oficial EURO ca un numar intreg
INT DolarCaNumar;		//cursul oficial USD ca un numar intreg
STRING Limba;				//limba in care se afiseaza cursul valutar
INT Ziua;				//ziua cursului valutar (intreg)
INT Luna;				//luna cursului valutar (intreg)
INT Anul;				//anul cursului valutar (intreg)

Metode
--------------
Constructor: TValuta TValuta(STRING $limba)

timestamp DataCaTimeStamp()	//intoarce data cursului valutar ca timestamp UNIX
STRING DataInFormatLocal()	//intoarce data ca un sir de caractere, corespunzator formatului local

Iata cum se foloseste:

try {
	$valuta = new TValuta("RO");	//Instantiem clasa TValuta, al carui contructor se ocupa cu preluarea datelor
	echo "1 dolar = $valuta->dolar lei<br>"; //apoi este suficent sa folosim proprietatile corespunzatoare
	echo "1 euro = $valuta->euro lei<br>";
	echo "Data: ".$valuta->DataInFormatLocal();
}
catch (Exception $Er) {
	echo $Er->getMessage();
}
*/
function TraducereRomana() {
	if (!defined("DATA_NECUNOSCUTA")) {
		define("DATA_NECUNOSCUTA", "necunoscuta");
		define("NEDISPONIBIL", "nedisponibil");
		define("EROARE_PRELUARE", "Eroare la preluarea datelor.");
	}
}

class TValuta{
	var $euro;				//cursul oficial EURO asa cum a fost el preluat
	var $dolar;				//cursul oficial USD asa cum a fost el preluat
	var $data;				//data pentru care este valabil cursul asa cum a fost ea prealuata
	var $EuroCaNumar;		//cursul oficial EURO ca un numar intreg
	var $DolarCaNumar;		//cursul oficial USD ca un numar intreg
	var $Limba;				//Limba in care se afiseaza cursul valutar
	var $Ziua;				//ziua cursului valutar (intreg)
	var $Luna;				//luna cursului valutar (intreg)
	var $Anul;				//anul cursului valutar (intreg)
//-----------------------------------------------------------------

	//data pentru care este valabil cursul, ca timestamp UNIX

	function DataCaTimeStamp() {
		return mktime(0,0,0,$this->Luna,$this->Ziua,$this->Anul);
	}
//-----------------------------------------------------------------
	function __construct($limba = '') {

		$fisier_cursv="cursv.txt";
		$this->Limba = $limba;
		
		if (empty($limba)) {
			$this->Limba = "RO";
		}
			
		if (file_exists("TValuta.".$this->Limba.".php")) {
			include("TValuta.".$this->Limba.".php");
		}
		else {
			TraducereRomana();
		}
			
		$preluare_corecta = true;
		
        if (!file_exists($fisier_cursv) || date("d", @filemtime($fisier_cursv)) != date("d")) { //daca data ultimei modificari a fisierului ce retine cursul valutar este diferita de ziua data de azi, il preluam
            $sursa_date = "http://www.bnro.ro/nbrfxrates.xml";
        } else {
            $sursa_date = $fisier_cursv;
        }

        $xmlstr = @file_get_contents($sursa_date, false, stream_context_create(array("ssl"=>array("verify_peer"=>false, "verify_peer_name"=>false))));

        if (empty ($xmlstr) ){
            throw new Exception(EROARE_PRELUARE);		
        }
		
		$xml = new SimpleXMLElement($xmlstr);
		
		foreach ($xml->Body[0]->Cube[0]->Rate as $rate) {
		    switch($rate['currency']) {
		    case 'EUR': {
		        $this->euro = (string)$rate;
				$this->EuroCaNumar = floatval($rate);
		        break;
				}
		    case 'USD': {
		        $this->dolar = (string)$rate;
				$this->DolarCaNumar = floatval($rate);
		        break;
				}		
		    } 	 
		}	

		$this->data = (string) $xml->Body[0]->Cube['date'];
			
		$parsed_date = date_parse($this->data);
		
		if($parsed_date) {
	   		$this->Ziua = $parsed_date['day'];
			$this->Luna = $parsed_date['month'];
			$this->Anul = $parsed_date['year'];
		}
			
		if( empty($this->data) || !checkdate ($this->Luna, $this->Ziua, $this->Anul) ) {
			$this->data = DATA_NECUNOSCUTA;
			$preluare_corecta = FALSE;
		}
		if(!$this->EuroCaNumar) {
			$this->euro = NEDISPONIBIL;
			$preluare_corecta = FALSE;
		}
		if(!$this->DolarCaNumar) {
			$this->dolar = NEDISPONIBIL;
			$preluare_corecta = FALSE;
		}
		
		//actualizam informatiile din fisierul ce retine cursul valutar
		if ($preluare_corecta) {
			$hfisier_cursv = @fopen($fisier_cursv,"w");
			@fputs($hfisier_cursv, $xmlstr);
			@fclose($hfisier_cursv);
		}

	}//end functie preluare (constructor)
//-----------------------------------------------------------------
	function DataInFormatLocal()
	{
		switch($this->Limba) {
			case "EN": $FormatData = "m-d-Y"; break;
			case "RO": $FormatData = "d.m.Y"; break;
			
			default: $FormatData = "m-d-Y";
		}

		$ret = @date($FormatData,$this->DataCaTimeStamp());

		return $ret ? $ret : DATA_NECUNOSCUTA;
	}
//-----------------------------------------------------------------
}//end definitie clasa
//=================================================================
?>
