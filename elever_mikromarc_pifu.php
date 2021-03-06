<?php
function encodeCSV(&$value){ //Funksjon for å lage riktig tegnsett for windows (http://stackoverflow.com/questions/12488954/php-fputcsv-encoding)
	if(!is_string($value))
		$value='';
	else
	    //$value = iconv('UTF-8', 'UTF-16', $value);
	    $value = iconv('UTF-8', 'Windows-1252', $value);
		$value=str_replace('"','',$value);
}

require 'vendor/autoload.php';
$pifu=new pifu_parser;

$fp=fopen(dirname(__FILE__).'/elever_mikromarc.csv','w+'); //Åpne utfil
if($fp===false)
	die("Kan ikke åpne fil");
$fieldnames=array("Etternavn","Fornavn","Født","Skole","Klasse","Satsnummer","Personnr","Kjønn","Postadresse","Postnummer","Poststed","Gruppe","Telefon","Mobil","Epost");
//unset($fieldnames[6]);
array_walk($fieldnames,'encodeCSV');
fputcsv($fp,$fieldnames,';','"'); //Lag første linje med feltnavn

foreach($pifu->schools() as $school) //Loop gjennom skoler
{
	$skolekey=$school->sourcedid->id;
	$skole=$school->description->long;

	$members = $pifu->group_members($school);
    foreach($members as $member) //Loop gjennom elever i klassen
    {
        $person_key=(string)$member->sourcedid->id;
        $person=$pifu->person($person_key)[0];
        if(stripos($person->name->n->family,'Test')!==false)
            continue;
        if($person->demographics->gender==1)
            $gender='F';
        else
            $gender='M';
        $role = (string)$member->role->attributes()['roletype'];

        if($role!='01')
        {
            $gruppe='Ansatte';
            $class='';
        }
        else
        {
            $gruppe='Elever';
            $xpath = sprintf('/enterprise/membership/member/sourcedid/id[.="%s"]/ancestor::membership/sourcedid/id[contains(., "schoolclass")]', $person_key);
            $group_id = $pifu->xml->xpath($xpath);
            if(empty($group_id))
                $class='';
            else
            {
                $group = $pifu->group_info_id((string)$group_id[0]);
                $class = (string)$group->description->short;
            }
        }

        if(empty($person->extension->pifu_adr))
        {
            $adr=new stdClass();
            $adr->street='';
            $adr->pcode='';
            $adr->locality='';
        }
        else
            $adr=$person->extension->pifu_adr->adr;
        //"Etternavn","Fornavn","Født","Skole","Klasse","Satsnummer","Personnr","Kjønn","Postadresse","Postnummer","Poststed","Gruppe","Telefon","Mobil","Epost"
        $fields=array((string)$person->name->n->family, //Etternavn
                      (string)$person->name->n->given, //Fornavn
                      date('d.m.y',strtotime($person->demographics->bday)), //Født
                      (string)$school->description->long, //Skole
                      $class, //Klasse
                      (string)$person->userid[1], //GUID
                      (string)$person->userid[0], //Fødselsnummer
                      $gender, //Kjønn
                      (string)$adr->street, //Postadresse
                      (string)$adr->pcode, //Postnummer
                      (string)$adr->locality, //Poststed
                      $gruppe, //Gruppe
                      $pifu->phone($person,1), //Telefon
                      $pifu->phone($person,3), //Mobil
                      (string)$person->email, //Epost
                     );
        unset(/*$fields[6],*/$adr);

        array_walk($fields,'encodeCSV'); //Bruk encodeCSV på alle feltene
        fputcsv_eol($fp,$fields,"\r\n"); //Skriv en elev til fil
    }
}
fclose($fp); //Lukk filen
function fputcsv_eol($fp, $array, $eol) {
  fputcsv($fp, $array,';');
  if("\n" != $eol && 0 === fseek($fp, -1, SEEK_CUR)) {
    fwrite($fp, $eol);
  }
}
