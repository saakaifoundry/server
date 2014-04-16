<?php
/**
 * @package plugins.mirrorImage
 * @subpackage storage
 */
class DeliveryProfileMirrorImageHttp extends DeliveryProfileHttp
{
    const DEFAULT_ACCESS_WINDOW_SECONDS = 900; // 900 seconds = 15 minutes
    
	/**
	 * @return kUrlTokenizer
	 */
	public function getTokenizer()
	{
		$storageProfile = StorageProfilePeer::retrieveByPK($this->params->getStorageProfileId());
		$tokenizer = parent::getTokenizer();
		$tokenizer->setUseDummuHost(false);
		$tokenizer->setBaseUrl(rtrim($storageProfile->getDeliveryHttpBaseUrl(), '/'));
		if(is_null($tokenizer->getWindow()))
			$tokenizer->setWindow(self::DEFAULT_ACCESS_WINDOW_SECONDS);
		return $tokenizer;
		
	}
}
