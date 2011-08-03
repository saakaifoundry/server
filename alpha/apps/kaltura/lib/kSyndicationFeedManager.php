<?php
class kSyndicationFeedManager
{
	
	const ITEMS_PLACEHOLDER = 'ITEMS_PLACEHOLDER';
	
	private static $xsl = null;
	private static $xslItem = null;
	private static $xslMrss = null;
	
		/*
	 * @params string $xslt
	 * @return string
	 */
	public static function getKalturaItemXslt($xslt)
	{
		if(self::$xslItem)
			return self::$xslItem;
			
		self::$xslItem = self::createKalturaItemXslt($xslt);
		return self::$xslItem;
	}
	
	
	/*
	 * @params string $xslt
	 * @return string
	 */
	public static function getKalturaMrssXslt($xslt)
	{
		
		if(self::$xslMrss)
			return self::$xslMrss;

		self::$xslMrss = self::createKalturaMrssXslt($xslt);
		return self::$xslMrss;
	}
	

	/**
	 * @param $syndicatioFeed
	 */
	public static function getXslt(syndicationFeed $syndicationFeed)
	{
		if(self::$xsl)
			return self::$xsl;
		
		self::$xsl = $syndicationFeed->getXslt();
		return self::$xsl;
	}
	
	private static function getKalturaMrssXml($title, $link = null, $description = null)
	{
		$mrss = kMrssManager::getMrssXml($title, $link, $description);
		
		foreach ($mrss->children() as $second_gen) {
			if ($second_gen->getName() == 'channel')
				$second_gen->addChild('items', self::ITEMS_PLACEHOLDER);
		}
		
		return $mrss->asXML();
	}
	
	/**
	 * @param $entry
	 * @param $syndicationFeed
	 * @return string
	 */
	private static function getMrssEntryXml(entry $entry, syndicationFeed $syndicationFeed = null, $link = null)
	{
		$mrss = kMrssManager::getEntryMrssXml($entry, null, $link, $syndicationFeed->getFlavorParamId());
		
		if(!$mrss)
		{
			KalturaLog::err("No MRSS returned for entry [".$entry->getId()."]");
			return null;
		}
		
		$uiconfId = (!is_null($syndicationFeed->getPlayerUiconfId()))? '/ui_conf_id/'.$syndicationFeed->getPlayerUiconfId(): '';
		$playerUrl = 'http://'.kConf::get('www_host').
						'/kwidget/wid/_'.$entry->getPartnerId().
						'/entry_id/'.$entry->getId().'/ui_conf' . ($uiconfId ? "/$uiconfId" : '');

		$player = $mrss->addChild('player');
		$player->addAttribute('url', $playerUrl);
				
		return $mrss->asXML();
	}
	
	/**
	 * @param $title
	 * @param $link
	 * @param $description
	 * @param $syndicationFeed
	 * @return string
	 */
	public static function getMrssHeader($title, $link = null, $description = null, syndicationFeed $syndicationFeed = null)
	{
		$mrss = self::getKalturaMrssXml($title, $link, $description);
	
		if (!is_null($syndicationFeed) && ($syndicationFeed->getType() == syndicationFeedType::KALTURA_XSLT) && (!is_null(self::getXslt($syndicationFeed))))
		{
			$kalturaXslt = self::getKalturaMrssXslt(self::getXslt($syndicationFeed));
			$mrss = self::transformXmlUsingXslt($mrss, $kalturaXslt);
		}
		
		$divideHeaderFromFooter = strpos($mrss,self::ITEMS_PLACEHOLDER);		
		$mrss = substr($mrss,0,$divideHeaderFromFooter);
		$mrss = self::removeXmlHeader($mrss);
		
		return $mrss;
	}
	
	/**
	 * @param $title
	 * @param $link
	 * @param $description
	 * @param $syndicationFeed
	 * @return string
	 */
	public static function getMrssFooter($title, $link = null, $description = null, syndicationFeed $syndicationFeed = null)
	{
		$mrss = self::getKalturaMrssXml($title, $link, $description);
		
		if (!is_null($syndicationFeed) && ($syndicationFeed->getType() == syndicationFeedType::KALTURA_XSLT) && (!is_null(self::getXslt($syndicationFeed))))
		{
			$kalturaXslt = self::getKalturaMrssXslt(self::getXslt($syndicationFeed));
			$mrss = self::transformXmlUsingXslt($mrss, $kalturaXslt);
		}
		
		$divideHeaderFromFooter = strpos($mrss, self::ITEMS_PLACEHOLDER) + strlen(self::ITEMS_PLACEHOLDER);
		$mrss = substr($mrss,$divideHeaderFromFooter);
		
		return $mrss;
	}
	
	/**
	 * @param string $mrss
	 * @return string
	 */
	private static function removeXmlHeader($mrss)
	{
		$position = strpos($mrss,'<?xml version="1.0"?>');
		if($position !== false){
			$divideHeaderFromFooter = $position + strlen('<?xml version="1.0"?>') + 1;
			$mrss = substr($mrss,$divideHeaderFromFooter);
		}
		
		$position = strpos($mrss,'<?xml version="1.0" encoding="UTF-8"?>');
		if($position !== false){
			$divideHeaderFromFooter = $position + strlen('<?xml version="1.0" encoding="UTF-8"?>') + 1;
			$mrss = substr($mrss,$divideHeaderFromFooter);
		}
		
		return $mrss;		
	}
	
	/**
	 * @param $entry
	 * @param $syndicationFeed
	 * @return string
	 */
	public static function getMrssEntry(entry $entry, syndicationFeed $syndicationFeed = null, $link = null, $hasNextItem = false)
	{
		$entryMrss =  self::getMrssEntryXml($entry, $syndicationFeed, $link);
		
		if(!$entryMrss)
		{
			KalturaLog::err("No MRSS returned for entry [".$entry->getId()."]");
			return null;
		}
				
		if (($syndicationFeed->getType() == syndicationFeedType::KALTURA_XSLT) && (!is_null(self::getXslt($syndicationFeed))))
		{
			$itemXslt = self::getKalturaItemXslt(self::getXslt($syndicationFeed));
			$entryMrss = self::transformXmlUsingXslt($entryMrss, $itemXslt, array('hasNextItem' => $hasNextItem));
			$entryMrss = self::removeNamespaces($entryMrss);
		}
		$entryMrss = self::removeXmlHeader($entryMrss);
		
		return $entryMrss;
	}
	
	
	/**
	 * return xlts with item template only when given xslt compatible with kaltura feed
	 * @param string $xslt
	 * @return string $xslt
	 */
	private static function createKalturaItemXslt($xslt)
	{
		$xsl = new DOMDocument();
		if(!$xsl->loadXML($xslt))
		{
			KalturaLog::debug("Could not load xslt");
			return null;
		}
		
		$xpath = new DOMXpath($xsl);
		$xslStylesheet = $xpath->query("//xsl:stylesheet");		
		$rss = $xpath->query("//xsl:template[@name='rss']");		
		$xslStylesheet->item(0)->removeChild($rss->item(0));	
	
		return $xsl->saveXML();
	}
	
	/**
	 * return xlts with item place holder only when given xslt compatible with kaltura feed
	 * @param string $xslt
	 * @return string $xslt
	 */
	private static function createKalturaMrssXslt($xslt)
	{
		$xsl = new DOMDocument();
		if(!$xsl->loadXML($xslt))
		{
			KalturaLog::debug("Could not load xslt");
			return null;
		}
		
		$xpath = new DOMXpath($xsl);
		
		//remove items template
		$xslStylesheet = $xpath->query("//xsl:stylesheet");
		$item = $xpath->query("//xsl:template[@name='item']");
		$item->item(0)->parentNode->removeChild($item->item(0));
		
		//add place holder for items
		$items = $xpath->query("//xsl:apply-templates[@name='item']"); 
		$itemPlaceHolderNode = $xsl->createTextNode(self::ITEMS_PLACEHOLDER);
		$items->item(0)->parentNode->replaceChild($itemPlaceHolderNode,$items->item(0));
	
		return $xsl->saveXML();
	}
	
	/**
	 * 
	 * @param stinr $xmlStr
	 * @return string
	 */
	private static function removeNamespaces($xmlStr)
	{
	//	return preg_replace("/<.*(xmlns *= *[\"'].[^\"']*[\"']).[^>]*>/i", "", $xmlStr);
		//return preg_replace("/ xmlns:[a-zA-Z0-9_]{1,}=[\"'].[^\"']*[\"']/", "", $xmlStr);
		return preg_replace("/ xmlns:[^= ]{1,}=[\"][^\"]*[\"]/i", "", $xmlStr);
	}
	
	/**
	 * 
	 * @param string $xml
	 * @param string $xslt
	 * @return string  
	 */
	private static function transformXmlUsingXslt($xmlStr, $xslt, $xsltParams = array())
	{
					
		$xml = new DOMDocument();
		if(!$xml->loadXML($xmlStr))
		{
			KalturaLog::debug("Could not load xmlStr");
			return null;
		}
		
		$xsl = new DOMDocument();
		if(!$xsl->loadXML($xslt))
		{
			KalturaLog::debug("Could not load xslt");
			return null;
		}
		
		$proc = new XSLTProcessor;
		foreach ($xsltParams as $key => $value)
		{
			$proc->setParameter( '', $key, $value);
		}		
	    $proc->registerPHPFunctions(kConf::get('xslt_enabled_php_functions'));
		$proc->importStyleSheet($xsl);
		
		$xml = $proc->transformToDoc($xml);

		if(!$xml)
		{
			KalturaLog::err("XML Transformation failed");
			return null;
		}
		
		if (isset($xml->documentElement)) {
			$xml->documentElement->removeAttributeNS('http://php.net/xsl', 'php');
		}
				
		return $xml->saveXML();
	}

	/*
	 * @param string $xsltStr
	 */
	public static function validateXsl($xsltStr)
	{
		$xsl = new DOMDocument();
		if(!$xsl->loadXML($xsltStr))
		{
			KalturaLog::debug("Could not load xslt");
			throw new KalturaAPIException(KalturaErrors::INVALID_XSLT, $xsltStr);
		}
		
		$xpath = new DOMXpath($xsl);
		
		$xslStylesheet = $xpath->query("//xsl:stylesheet");
		$rss = $xpath->query("//xsl:template[@name='rss']");
		if ($rss->length == 0)
			throw new KalturaAPIException(KalturaErrors::INVALID_XSLT_MISSING_TEMPLATE_RSS, $xsltStr);
		
		$item = $xpath->query("//xsl:template[@name='item']");
		if ($item->length == 0)
			throw new KalturaAPIException(KalturaErrors::INVALID_XSLT_MISSING_TEMPLATE_ITEM, $xsltStr);
		
		$items = $xpath->query("//xsl:apply-templates[@name='item']"); 
		if ($items->length == 0)
			throw new KalturaAPIException(KalturaErrors::INVALID_XSLT_MISSING_APPLY_TEMPLATES_ITEM, $xsltStr);

		return true;
	}
}