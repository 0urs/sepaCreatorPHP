<?php

class SEPAFormater
{

	private $_sMsgId;
	private $_sNomClient;
	private $_sCode;
	private $_iMontant;
	private $_sSIRET;
	private $_iMontantGlobal;
	private $_sMatricule;
	private $_sSautLigne;
	private $_dDate;
	private $_sName;
	private $_iCompteur = 0;
	private $_aArrayCode;

	private $_bEmetteurSet;
	private $_bGroupHeaderSet;
	private $_bDTDSet;
	private $_bPrologueSet;

	public function __construct($sNomClient=null) {
		if (!is_null($sNomClient)) {
			self::setSNomClient($sNomClient);
		}
	}

	public function init($iNbLigne, $iMontantGlobal) {
		$this->_sCode 			    = "";
		$this->_bGroupHeaderSet 	= false;
		$this->_bEmetteurSet 	  	= false;
		$this->_bDTDSet 		    = false;
		$this->_bPrologueSet 	  	= false;
		$this->_aArrayCode 		  	= array();
		$this->_iMontantGlobal 		= $iMontantGlobal;

		self::setPrologue();
		self::setDTD();
		self::getGroupHeader($iNbLigne);
	}

	public function testMontant($sMontant) {
		/*  Longueur maximal de 18 caracteres separateur de décimal compris  */
		if (strlen($sMontant)>18) {
			return false;
        }

		if (!preg_match("#^[0-9]{1,}\\.?[0-9]{0,5}$#", $sMontant)) {
			return false;
		}
 
		return true;
	}

	public function getGroupHeader($iNbLigne, $iMontantGlobal=null) {
		if (self::isGroupHeaderSet()) {
			return false;
        }

		$this->_sCode .= "<CstmrCdtTrfInitn><GrpHdr>";
		$this->_sCode .= "<MsgId>".$this->getSMsgId()."</MsgId>";
		$this->_sCode .= "<CreDtTm>".$this->getSCreDtTm()."</CreDtTm>";
		$this->_sCode .= "<NbOfTxs>".$iNbLigne."</NbOfTxs>";
		$this->_sCode .= "<CtrlSum>".$this->_iMontantGlobal."</CtrlSum>";
		$this->_sCode .= "<InitgPty>";
		$this->_sCode .= "<Nm>".$this->getSNomClient()."</Nm>";
		$this->_sCode .= "</InitgPty>";
		$this->_sCode .= "</GrpHdr>";

		$this->_bGroupHeaderSet = true;		
	}

	public function getEmetteur($iNb) {
		if (self::isEmetteurSet()) {
			return false;
        }

		$this->_sCode .= "<PmtInf>";
		$this->_sCode .= "<PmtInfId>".$this->getSMsgId()." /".$iNb."</PmtInfId>";
		$this->_sCode .= "<PmtMtd>TRF</PmtMtd>";
		$this->_sCode .= "<BtchBookg>false</BtchBookg>";
		$this->_sCode .= "<NbOfTxs>1</NbOfTxs>";
		$this->_sCode .= "<CtrlSum>".$this->getIMontant()."</CtrlSum>";
		$this->_sCode .= "<PmtTpInf>";
		$this->_sCode .= "<SvcLvl><Cd>SEPA</Cd></SvcLvl>";
		$this->_sCode .= "</PmtTpInf>";
		$this->_sCode .= "<ReqdExctnDt>".$this->getDDate()."</ReqdExctnDt>";
		$this->_sCode .= "<Dbtr><Nm>".$this->getSNomClient()."</Nm></Dbtr>";
		$this->_sCode .= "<DbtrAcct><Id><IBAN>".$this->getSIBANDebiteur()."</IBAN></Id></DbtrAcct>";
		$this->_sCode .= "<DbtrAgt><FinInstnId><BIC>".$this->getSBICDebiteur()."</BIC></FinInstnId></DbtrAgt>";
		$this->_sCode .= "<ChrgBr>SLEV</ChrgBr>";

		$this->_bEmetteurSet = true;
	}

	//La meme que getEmetteur() mais en version DOM.
	public function getEmetteurWithDom() {
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;
		
		$pmtInf = $doc->createElement('PmtInf');
		$doc->appendChild($pmtInf);
		
		$pmtInfId = $doc->createElement('PmtInfId', $this->getSMsgId());
		$doc->appendChild($pmtInfId);

		$pmtMtd = $doc->createElement('PmtMtd', 'TRF');
		$doc->appendChild($pmtMtd);

		/*  Continue comme ca avec tout les elements noeud...  */
	}

	public function getRecepteur() {
		$sText = "";

		$sText .= "<CdtTrfTxInf>";
		$sText .= "<PmtId>";
		$sText .= "<InstrId>".$this->getSMsgId()."</InstrId>";
		$sText .= "<EndToEndId>".$this->getSMsgId()."</EndToEndId>";
		$sText .= "</PmtId>";
		$sText .= "<Amt><InstdAmt Ccy=\"EUR\">".$this->getIMontant()."</InstdAmt></Amt>";
		$sText .= "<CdtrAgt><FinInstnId><BIC>".$this->getSBIC()."</BIC></FinInstnId></CdtrAgt>";
		$sText .= "<Cdtr>";
		$sText .= "<Nm>".$this->getSName()."</Nm>";
		$sText .= "</Cdtr>";
		$sText .= "<CdtrAcct><Id><IBAN>".$this->getSIBAN()."</IBAN></Id></CdtrAcct>";
		$sText .= "<RmtInf><Ustrd>".$this->getSMsgId()."</Ustrd></RmtInf>";
		$sText .= "</CdtTrfTxInf>";
		
		$this->_aArrayCode[$this->_iCompteur] = $sText;
		$this->_iCompteur++;
		self::copyEmetteur();
	}

	public function copyEmetteur() {
		$this->_sCode .= implode("", $this->_aArrayCode);		
	}


	/*	SETTERS  */

	public function setSSIRET($sSIRET) { $this->_sSIRET = $sSIRET; }
  
    public function setSNomClient($sNomClient) { $this->_sNomClient = $sNomClient; }

    public function setBIC($sBIC) { $this->_sBIC = $sBIC; }

    public function setSMatricule($sMatricule) { $this->_sMatricule = $sMatricule; }

    public function setSMsgId($sMsgId) { $this->_sMsgId = $sMsgId; }

    public function setSIBAN($sIBAN) { $this->_sIBAN = str_replace(" ", "", $sIBAN); }

    public function setSBIC($sBIC) { $this->_sBIC = $sBIC; }

    public function setSBICDebiteur($sBICDebiteur) { $this->_sBICDebiteur = $sBICDebiteur; }

    public function setSSautLigne() { $this->_sSautLigne = '\n'; }

    public function setCtrlEmeteurFalse() {	$this->_bEmetteurSet = false; }

    public function setCloseEmeteur() { $this->_sCode .= "</PmtInf>"; }

    public function setSIBANDebiteur($sIBANDebiteur) { $this->_sIBANDebiteur = str_replace(" ", "", $sIBANDebiteur); }

    public function setRecepteurEmpty() { $this->_aArrayCode = array(); }

    public function setPrologue() {
        if (!self::isPrologueSet()) {
            $this->_sCode = '<?xml version="1.0" encoding="UTF-8"?>'.$this->getSSautLigne();
            $this->_bPrologueSet = true;
        }
    }

	public function setDTD() {
		if (!self::isDTDSet()) {
			$this->_sCode .= '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">'.$this->getSSautLigne();
			$this->_bDTDSet = true;
		}
	}

	public function setSName($sName) {
		if (strlen($sName)>70) {
			$this->_sName = substr($sName, 0, 70);
		}
        else {
			$this->_sName = $sName;
        }
	}

	public function setDDate($dDate=null) {
		if ($dDate==null) {
			$this->_dDate = date("Y-m-d");
		}
        else {
			@list($jour, $mois, $annee) = explode('/', $dDate);
			$this->_dDate = date('Y-m-d', mktime(0, 0, 0, $mois, $jour, $annee));
		}
	}

	public function setIMontant($iMontant) {
        if (self::testMontant($iMontant)) {
                $this->_iMontant = $iMontant;
        }
        else {
            $this->_iMontant = "0.00";
        }
	}

	public function setEndEmetteur() {
		$this->_sCode .= "</CstmrCdtTrfInitn></Document>";
	}


	/*  GETTERS  */
	
    public function getSNomClient() { return $this->_sNomClient; }

    public function getSMsgId() { return $this->_sMsgId; }

    public function getDDate() { return $this->_dDate; }

    public function getSIBAN() { return $this->_sIBAN; }

    public function getSBIC() { return $this->_sBIC; }

    public function getSName() { return $this->_sName; }

    public function getIMontant() { return $this->_iMontant; }

    public function getSSIRET() { return $this->_sSIRET; }

    public function getSSautLigne() { return $this->_sSautLigne; }

    public function getSCode() { return $this->_sCode; }

    public function getSBICDebiteur() { return $this->_sBICDebiteur; }

    public function getSIBANDebiteur() { return $this->_sIBANDebiteur; }

    public function getSCreDtTm() {
        $oDate = new DateTime();
        $sDate = $oDate->format('Y-m-d H:i:s');
        return str_replace(" ", "T", $sDate);
    }


    /*  Fonction de controle d'écriture  */

    public function isDTDSet() { return $this->_bDTDSet; }
    public function isGroupHeaderSet() { return $this->_bGroupHeaderSet;}
    public function isEmetteurSet() { return $this->_bEmetteurSet; }
    public function isPrologueSet() { return $this->_bPrologueSet; }


}


?>
