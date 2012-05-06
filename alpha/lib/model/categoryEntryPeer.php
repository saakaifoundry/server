<?php


/**
 * Skeleton subclass for performing query and update operations on the 'category_entry' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package Core
 * @subpackage model
 */
class categoryEntryPeer extends BasecategoryEntryPeer {

	private static $skipEntrySave = false;
	
	public static function getSkipSave()
	{
		return self::$skipEntrySave;
	}
	
	/**
	 * @param Criteria $criteria
	 * @param PropelPDO $con
	 */
	public static function doSelect(Criteria $criteria, PropelPDO $con = null)
	{
		$c = clone $criteria;
			
		if($c instanceof KalturaCriteria)
		{
			$c->applyFilters();
			$criteria->setRecordsCount($c->getRecordsCount());
		}

		return parent::doSelect($c, $con);
	}
	
	public static function retrieveByCategoryIdAndEntryId($categoryId, $entryId)
	{
		$c = new Criteria();
		$c->add(self::CATEGORY_ID, $categoryId);
		$c->add(self::ENTRY_ID, $entryId);
		
		return self::doSelectOne($c);
	}
	
	public static function selectByEntryId($entryId)
	{
		$c = new Criteria();
		$c->add(self::ENTRY_ID, $entryId);
		
		return self::doSelect($c);
	}
		
	public static function syncEntriesCategories(entry $entry)
	{					 
		//TODO save on entry only with no privacy context 
		self::$skipEntrySave = true;
		
		if($entry->getNewCategories() != null && $entry->getNewCategories() !== "")
			$newCats = explode(entry::ENTRY_CATEGORY_SEPARATOR, $entry->getNewCategories());
		else
			$newCats = array();
			
		if($entry->getNewCategoriesIds() != null && $entry->getNewCategoriesIds() !== "")
			$newCatsIds = explode(entry::ENTRY_CATEGORY_SEPARATOR, $entry->getNewCategoriesIds());
		else
			$newCatsIds = array();	
			
		
		KalturaCriterion::disableTag(KalturaCriterion::TAG_ENTITLEMENT_CATEGORY);
		$dbCategories = categoryPeer::retrieveByPKs($newCatsIds);
		KalturaCriterion::restoreTag(KalturaCriterion::TAG_ENTITLEMENT_CATEGORY);

		foreach ($dbCategories as $dbCategory)
		{
			$newCats[] = $dbCategory->getFullName();
		}
		
		$newCats = array_unique($newCats);
		
		$allIds = array();
		$allCats = array();
		$allIdsWithParents = array ();
		
		$addedCats = array();
		$removedCats = array();
		$remainingCats = array();
		$oldCats = array();
		$oldCatsIds = array();
		
		$dbOldCategoriesEntry = categoryEntryPeer::selectByEntryId($entry->getId());
		foreach ($dbOldCategoriesEntry as $dbOldCategoryEntry)
			$oldCatsIds[] = $dbOldCategoryEntry->getCategoryId();

		
		$oldCategoris = categoryPeer::retrieveByPKsNoFilter($oldCatsIds);
		foreach($oldCategoris as $category)
			$oldCats[] = $category->getFullName();
				
		
		foreach ( $oldCats as $cat )
		{
			if (array_search ( $cat, $newCats ) === false)
				$removedCats [] = $cat;
		}

		foreach ( $newCats as $cat )
		{
			if (array_search ( $cat, $oldCats ) === false)
				$addedCats [] = $cat;
			else
				$remainingCats [] = $cat;
		}
		
		foreach ( $remainingCats as $cat ) 
		{
			KalturaCriterion::disableTag(KalturaCriterion::TAG_ENTITLEMENT_CATEGORY);
			$category = categoryPeer::getByFullNameExactMatch ( $cat );
			KalturaCriterion::restoreTag(KalturaCriterion::TAG_ENTITLEMENT_CATEGORY);
			if ($category) 
			{
				if($category->getPrivacyContext() == '' || $category->getPrivacyContext() == null)
				{
					$allCats[] = $category->getFullName();
					$allIds [] = $category->getId ();
				}
					
				$allIdsWithParents [] = $category->getId ();
				$allIdsWithParents = array_merge ( $allIdsWithParents, $category->getAllParentsIds () );
			}
		}

		$alreadyAddedCatIds = $allIdsWithParents;
		
		foreach ( $addedCats as $cat )
		{
			$category = categoryPeer::getByFullNameExactMatch ( $cat );
			if (!$category)
			{
				KalturaCriterion::disableTag(KalturaCriterion::TAG_ENTITLEMENT_CATEGORY);
				$unentitedCategory = categoryPeer::getByFullNameExactMatch ( $cat );
				KalturaCriterion::restoreTag(KalturaCriterion::TAG_ENTITLEMENT_CATEGORY);

				if(!$unentitedCategory)
				{
					$category = category::createByPartnerAndFullName ( $entry->getPartnerId (), $cat );
				}
			}
			else
			{
				$categoryKuser = categoryKuserPeer::retrieveByCategoryIdAndActiveKuserId($category->getId(), kCurrentContext::$ks_kuser_id);
				if(kEntitlementUtils::getEntitlementEnforcement() && (!$categoryKuser || $categoryKuser->getPermissionLevel() == CategoryKuserPermissionLevel::MEMBER))
				{
					//user is not entitled to add entry to this category
					$category = null;
				}
			}
				
			if (!$category)
				continue;

			//when use caetgoryEntry->add categoryEntry object was alreay created - and no need to create it.
			//when using baseEntry->categories = 'my category' will need to add the new category.
			$categoryEntry = categoryEntryPeer::retrieveByCategoryIdAndEntryId($category->getId(), $entry->getId())	;
			
			if(!$categoryEntry)
			{
				$categoryEntry = new categoryEntry();
				$categoryEntry->setEntryId($entry->getId());
				$categoryEntry->setCategoryId($category->getId());
				$categoryEntry->setEntryCategoriesAddedIds($alreadyAddedCatIds);
				$categoryEntry->setPartnerId($entry->getPartnerId());
				$categoryEntry->save();
			}
			
			if($category->getPrivacyContext() == '' || $category->getPrivacyContext() == null)
			{
				// only categories with no context should be set on entry->categories and entry->categoriesIds
				$allCats[] = $category->getFullName();
				$allIds [] = $category->getId ();
			}

			$alreadyAddedCatIds [] = $category->getId ();
			$alreadyAddedCatIds = array_merge ( $alreadyAddedCatIds, $category->getAllParentsIds () );
		}

		$alreadyRemovedCatIds = $allIdsWithParents;
		
		foreach ( $removedCats as $cat ) 
		{
			$category = categoryPeer::getByFullNameExactMatch ( $cat );

			if ($category)
			{
				$categoryEntryToDelete = categoryEntryPeer::retrieveByCategoryIdAndEntryId($category->getId(), $entry->getId());
				if($categoryEntryToDelete)
				{
					$categoryKuser = categoryKuserPeer::retrieveByCategoryIdAndActiveKuserId($categoryEntryToDelete->getCategoryId(), kCurrentContext::$ks_kuser_id);
					if(kEntitlementUtils::getEntitlementEnforcement() && (!$categoryKuser || $categoryKuser->getPermissionLevel() == CategoryKuserPermissionLevel::MEMBER))
					{
						//not entiteld to delete - should be set back on the entry.
						$allCats[] = $category->getFullName();
						$allIds[] = $category->getId ();
					}
					else
					{
						$categoryEntryToDelete->setEntryCategoriesRemovedIds($alreadyRemovedCatIds);
						$categoryEntryToDelete->delete();
					}
				}
				
				$alreadyRemovedCatIds[] = $category->getId ();
				$alreadyRemovedCatIds = array_merge ( $alreadyRemovedCatIds, $category->getAllParentsIds () );
			}
			else
			{
				//category was not found - it could be that user is not entitled to remove it 
				KalturaCriterion::disableTag(KalturaCriterion::TAG_ENTITLEMENT_CATEGORY);
				$category = categoryPeer::getByFullNameExactMatch ( $cat );
				KalturaCriterion::restoreTag(KalturaCriterion::TAG_ENTITLEMENT_CATEGORY);
				
				$allCats[] = $category->getFullName();
				$allIds[] = $category->getId ();
			}
		}
		self::$skipEntrySave = false;
		
		$categories = implode ( ",", $allCats);
		$categoriesIds = implode (',', $allIds);
		return array($categories, $categoriesIds);
		
	} 
} // categoryEntryPeer
