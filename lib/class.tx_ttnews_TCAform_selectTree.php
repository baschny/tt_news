<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005-2008 Rupert Germann <rupi@gmx.li>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * This function displays a selector with nested categories.
 * The original code is borrowed from the extension "Digital Asset Management" (tx_dam) author: René Fritz <r.fritz@colorcube.de>
 *
 * $Id$
 *
 * @author	Rupert Germann <rupi@gmx.li>
 * @package TYPO3
 * @subpackage tt_news
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   75: class tx_ttnews_TCAform_selectTree
 *   82:     function init(&$PA)
 *  103:     function setDefVals()
 *  132:     function renderCategoryFields(&$PA, &$fobj)
 *  342:     function setSelectedItems()
 *  373:     function registerRequiredProperty(&$fobj, $type, $name, $value)
 *  393:     function registerNestedElement(&$fobj, $itemName)
 *  412:     function printError($NACats,$row=array())
 *  430:     function ajaxExpandCollapse($params, &$ajaxObj)
 *  491:     function renderCatTree()
 *  607:     function getCatRootline ($SPaddWhere)
 *  642:     function getNotAllowedItems($SPaddWhere,$allowedItemsList=false)
 *
 *
 *  684: class tx_ttnews_tceforms_categorytree extends tx_ttnews_categorytree
 *  696:     function wrapTitle($title,$v)
 *  719:     function getTitleStyles($v)
 *  741:     function PMiconATagWrap($icon, $cmd, $isExpand = true)
 *
 * TOTAL FUNCTIONS: 14
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once(t3lib_extMgm::extPath('tt_news').'lib/class.tx_ttnews_categorytree.php');
require_once(t3lib_extMgm::extPath('tt_news').'lib/class.tx_ttnews_div.php');



	/**
	 * this class displays a tree selector with nested tt_news categories.
	 *
	 */
class tx_ttnews_TCAform_selectTree {
	var $divObj;
	var $selectedItems = array();
	var $confArr = array();
	var $PA = array();
	var $useAjax = FALSE;

	function init(&$PA) {
		$this->confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_news']);

		if (!is_object($this->divObj)) {
			$this->divObj = t3lib_div::makeInstance('tx_ttnews_div');
		}

		$this->PA = &$PA;
		$this->table = $PA['table'];
		$this->field = $PA['field'];
		$this->row = $PA['row'];
		$this->fieldConfig = $PA['fieldConf']['config'];
		$this->setDefVals();
		$this->setSelectedItems();
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function setDefVals() {
		if (!is_int($this->row['uid'])) { // defVals only for new records
			$defVals = t3lib_div::_GP('defVals');
		
			if (is_array($defVals) && $defVals[$this->table][$this->field]) {
				$defCat = intval($defVals[$this->table][$this->field]);
				/**
				 * TODO:
				 * check for allowed categories
				 */
				if ($defCat) {
					$row = t3lib_BEfunc::getRecord('tt_news_cat', $defCat);
					$title = t3lib_BEfunc::getRecordTitle($this->table,$row);

					$this->PA['itemFormElValue'] = $defCat.'|'.$title;
					$this->row['category'] = $this->PA['itemFormElValue'];
				}
			}
		}
	}


	/**
	 * Generation of TCEform elements of the type "select"
	 * This will render a selector box element, or possibly a special construction with two selector boxes. That depends on configuration.
	 *
	 * @param	array		$PA: the parameter array for the current field
	 * @param	object		$fobj: Reference to the parent object
	 * @return	string		the HTML code for the field
	 */
	function renderCategoryFields(&$PA, &$fobj)    {

		$this->intT3ver = t3lib_div::int_from_ver(TYPO3_version);
		if ($this->intT3ver < 4001000) {
			// load some additional styles for the BE trees in TYPO3 version lower that 4.1
			// expand/collapse is disabled

			$fobj->additionalCode_pre[] = '
				<link rel="stylesheet" type="text/css" href="'.t3lib_extMgm::extRelPath('tt_news').'compat/tree_styles_for_4.0.css" />';

		} else { // enable ajax expand/collapse for TYPO3 versions > 4.1
			if ($this->intT3ver >= 4002000) {
				$jsFile = 'js/tceformsCategoryTree.js';
			} else {
				$jsFile = 'compat/tceformsCategoryTree_for_4.1.js';
			}
			$this->useAjax = TRUE;
			$fobj->additionalCode_pre[] = '
				<script src="'.t3lib_extMgm::extRelPath('tt_news').$jsFile.'" type="text/javascript"></script>';

		}



		$this->init(&$PA);

		$table = $this->table;
		$field = $this->field;
		$row = $this->row;
		$this->recID = $row['uid'];
		$itemFormElName = $this->PA['itemFormElName'];

			// it seems TCE has a bug and do not work correctly with '1'
		$this->fieldConfig['maxitems'] = ($this->fieldConfig['maxitems']==2) ? 1 : $this->fieldConfig['maxitems'];

			// Getting the selector box items from the system
		$selItems = $fobj->addSelectOptionsToItemArray($fobj->initItemArray($this->PA['fieldConf']),$this->PA['fieldConf'],$fobj->setTSconfig($table,$row),$field);
		$selItems = $fobj->addItems($selItems,$this->PA['fieldTSConfig']['addItems.']);

			// Possibly remove some items:
		$removeItems=t3lib_div::trimExplode(',',$this->PA['fieldTSConfig']['removeItems'],1);

		foreach($selItems as $tk => $p)	{
			if (in_array($p[1],$removeItems))	{
				unset($selItems[$tk]);
			}
		}

			// Creating the label for the "No Matching Value" entry.
		$nMV_label = isset($this->PA['fieldTSConfig']['noMatchingValue_label']) ? $fobj->sL($this->PA['fieldTSConfig']['noMatchingValue_label']) : '[ '.$fobj->getLL('l_noMatchingValue').' ]';
		$nMV_label = @sprintf($nMV_label, $this->PA['itemFormElValue']);

					// Set max and min items:
		$maxitems = t3lib_div::intInRange($this->fieldConfig['maxitems'],0);
		if (!$maxitems)	$maxitems = 1000;
		$minitems = t3lib_div::intInRange($this->fieldConfig['minitems'],0);




		if ($this->fieldConfig['treeView'])	{
			if ($row['sys_language_uid'] && $row['l18n_parent'] && ($table == 'tt_news' || $table == 'tt_news_cat')) {
				// the current record is a translation of another record
//				$errorMsg = array();
				$categories = array();
				$NACats = array();
				$na = false;

				// get categories of the translation original
				$catres = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
								'tt_news_cat.uid,tt_news_cat.title',
								'tt_news_cat, tt_news_cat_mm',
								'tt_news_cat_mm.uid_foreign=tt_news_cat.uid AND tt_news_cat_mm.uid_local='.$row['l18n_parent']);

				$assignedCategories = array();
				while (($catrow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($catres))) {
					$assignedCategories[$catrow['uid']] = $catrow['title'];
				}

				$notAllowedItems = array();
				if ($this->divObj->useAllowedCategories()) {
					$allowedCategories = $this->divObj->getAllowedCategories();

					if (($excludeList = $GLOBALS['BE_USER']->getTSConfigVal('tt_newsPerms.tt_news_cat.excludeList'))) {
						$addWhere = ' AND uid NOT IN ('.$excludeList.')';
					}
					$notAllowedItems = $this->getNotAllowedItems($addWhere,$allowedCategories);
				}

				foreach ($assignedCategories as $cuid => $ctitle) {
					if(in_array($cuid,$notAllowedItems)) {
						$categories[$cuid] = $NACats[] = '<p style="padding:0px;color:red;font-weight:bold;">- '.$ctitle.' <span class="typo3-dimmed"><em>['.$cuid.']</em></span></p>';
						$na = true;
					} else {
						$categories[$cuid] = '<p style="padding:0px;">- '.$ctitle.' <span class="typo3-dimmed"><em>['.$cuid.']</em></span></p>';
					}
				}

				if ($na) {
					$this->NA_Items = $this->printError($NACats,$row);
				}
				$item = implode($categories,chr(10));

				if ($item) {
					$item = 'Categories from the translation original of this record:<br />'.$item;
				} else {
					$item = 'The translation original of this record has no categories assigned.<br />';
				}
				$item = '<div class="typo3-TCEforms-originalLanguageValue">'.$item.'</div>';

			} else { // build tree selector

				if ($table == 'tt_news' && $this->intT3ver >= 4001000) {
					$this->registerRequiredProperty($fobj,'range', $itemFormElName, array($minitems,$maxitems,'imgName'=>$table.'_'.$row['uid'].'_'.$field));
				}
				$item.= '<input type="hidden" name="'.$itemFormElName.'_mul" value="'.($this->fieldConfig['multiple']?1:0).'" />';

				if ($this->fieldConfig['treeView'] AND $this->fieldConfig['foreign_table']) {
						// get default items
					$defItems = array();
					if (is_array($this->fieldConfig['items']) && $this->table == 'tt_content' && $this->row['CType']=='list' && $this->row['list_type']==9 && $this->field == 'pi_flexform')	{
						reset ($this->fieldConfig['items']);
						while (list(,$itemValue) = each($this->fieldConfig['items']))	{
							if ($itemValue[0]) {
								$ITitle = $GLOBALS['LANG']->sL($itemValue[0]);
								$defItems[] = '<a href="#" onclick="setFormValueFromBrowseWin(\'data['.$this->table.']['.$this->row['uid'].']['.$this->field.'][data][sDEF][lDEF][categorySelection][vDEF]\','.$itemValue[1].',\''.$ITitle.'\'); return false;" style="text-decoration:none;">'.$ITitle.'</a>';
							}
						}
					}
					$treeContent = '<span id="tt_news_cat_tree">'.$this->renderCatTree().'<span>';

					if ($defItems[0]) { // add default items to the tree table. In this case the value [not categorized]
						$this->treeItemC += count($defItems);
						$treeContent .= '<table border="0" cellpadding="0" cellspacing="0"><tr>
							<td>'.$GLOBALS['LANG']->sL($this->fieldConfig['itemsHeader']).'&nbsp;</td><td>'.implode($defItems,'<br />').'</td>
							</tr></table>';
					}
//					$errorMsg = array();

					$width = 350; // default width for the field with the category tree
					if (intval($this->confArr['categoryTreeWidth'])) { // if a value is set in extConf take this one.
						$width = t3lib_div::intInRange($this->confArr['categoryTreeWidth'],1,600);
					}

					$divStyle = 'position:relative; left:0px; top:0px; width:'.$width.'px; border:solid 1px #999;background:#fff;margin-bottom:5px;padding: 0 10px 10px 0;';
					$thumbnails = '<div  name="'.$itemFormElName.'_selTree" id="tree-div" style="'.htmlspecialchars($divStyle).'">';
					$thumbnails .= $treeContent;
					$thumbnails .= '</div>';
				}

					// Perform modification of the selected items array:
				$itemArray = t3lib_div::trimExplode(',',$this->PA['itemFormElValue'],1);
				foreach($itemArray as $tk => $tv) {
					$tvP = explode('|',$tv,2);
					$evalValue = rawurldecode($tvP[0]);
					if (in_array($evalValue,$removeItems) && !$this->PA['fieldTSConfig']['disableNoMatchingValueElement'])	{
						$tvP[1] = rawurlencode($nMV_label);
					} else {
						$tvP[1] = rawurldecode($tvP[1]);
					}
					$itemArray[$tk]=implode('|',$tvP);
				}
				$sWidth = 200; // default width for the left field of the category select
				if (intval($this->confArr['categorySelectedWidth'])) {
					$sWidth = t3lib_div::intInRange($this->confArr['categorySelectedWidth'],1,600);
				}
				$params = array(
					'autoSizeMax' => $this->fieldConfig['autoSizeMax'],
					'style' => ' style="width:'.$sWidth.'px;"',
					'dontShowMoveIcons' => ($maxitems<=1),
					'maxitems' => $maxitems,
					'info' => '',
					'headers' => array(
						'selector' => $fobj->getLL('l_selected').':<br />',
						'items' => $fobj->getLL('l_items').':<br />'
					),
					'noBrowser' => 1,
					'thumbnails' => $thumbnails
				);
				$item.= $fobj->dbFileIcons($itemFormElName,'','',$itemArray,'',$params,$this->PA['onFocus']);
				// Wizards:
				$altItem = '<input type="hidden" name="'.$itemFormElName.'" value="'.htmlspecialchars($this->PA['itemFormElValue']).'" />';
				$item = $fobj->renderWizards(array($item,$altItem),$this->fieldConfig['wizards'],$table,$row,$field,$this->PA,$itemFormElName,array());
			}
		}
		if (($table == 'tt_news' || $table == 'tt_news_cat') && $this->NA_Items && $this->intT3ver >= 4001000) {
			$this->registerRequiredProperty(
					$fobj,
					'range',
					'data['.$table.']['.$row['uid'].'][noDisallowedCategories]',
					array(1,1,'imgName'=>$table.'_'.$row['uid'].'_noDisallowedCategories'));
			$item .= '<input type="hidden" name="data['.$table.']['.$row['uid'].'][noDisallowedCategories]" value="'.($this->NA_Items?'':'1').'" />';

		}

		return $this->NA_Items.$item;
	}




	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function setSelectedItems() {
		if ($this->table == 'tt_content') {
			if ($this->row['pi_flexform']) {
				$cfgArr = t3lib_div::xml2array($this->row['pi_flexform']);
				if (is_array($cfgArr) && is_array($cfgArr['data']['sDEF']['lDEF']) && is_array($cfgArr['data']['sDEF']['lDEF']['categorySelection'])) {
					$selectedCategories = $cfgArr['data']['sDEF']['lDEF']['categorySelection']['vDEF'];
				}
			}
		} else {
			$selectedCategories = $this->row[$this->field];
		}
				
		if ($selectedCategories) {
			$selvals = explode(',',$selectedCategories);
			if (is_array($selvals)) {
				foreach ($selvals as $vv) {
					$cuid = explode('|',$vv);
					$this->selectedItems[] = $cuid[0];
				}
			}
		}
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$$fobj: ...
	 * @param	[type]		$type: ...
	 * @param	[type]		$name: ...
	 * @param	[type]		$value: ...
	 * @return	[type]		...
	 */
	function registerRequiredProperty(&$fobj, $type, $name, $value) {
		if ($type == 'field' && is_string($value)) {
			$fobj->requiredFields[$name] = $value;
				// requiredFields have name/value swapped! For backward compatibility we keep this:
			$itemName = $value;
		} elseif ($type == 'range' && is_array($value)) {
			$fobj->requiredElements[$name] = $value;
			$itemName = $name;
		}
			// Set the situation of nesting for the current field:
		$this->registerNestedElement($fobj,$itemName);
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$$fobj: ...
	 * @param	[type]		$itemName: ...
	 * @return	[type]		...
	 */
	function registerNestedElement(&$fobj, $itemName) {
		$dynNestedStack = $fobj->getDynNestedStack();
		$match = array();
		if (count($dynNestedStack) && preg_match('/^(.+\])\[(\w+)\]$/', $itemName, $match)) {
			array_shift($match);
			$fobj->requiredNested[$itemName] = array(
				'parts' => $match,
				'level' => $dynNestedStack,
			);
		}
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$NACats: ...
	 * @param	[type]		$row: ...
	 * @return	[type]		...
	 */
	function printError($NACats,$row=array()) {
		$msg = '<table class="warningbox" border="0" cellpadding="3" cellspacing="3">
					<tr><td><div style="padding:10px;"><img src="gfx/icon_fatalerror.gif" class="absmiddle" alt="" height="16" width="18">
					SAVING DISABLED!! <br />'.($row['l18n_parent']&&$row['sys_language_uid']?'The translation original of this':'This')
					.' record has the following categories assigned that are not defined in your BE usergroup: '.urldecode(implode($NACats,chr(10))).'
					</div></td></tr>
				</table>';

		return $msg;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$$params: ...
	 * @param	[type]		$ajaxObj: ...
	 * @return	[type]		...
	 */
	function ajaxExpandCollapse($params, &$ajaxObj) {
		$this->useAjax = TRUE;
		$this->confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_news']);

		if (!is_object($this->divObj)) {
			$this->divObj = t3lib_div::makeInstance('tx_ttnews_div');
		}
		$this->table = trim(t3lib_div::_GP('tceFormsTable'));
		$this->storagePidFromAjax = intval(t3lib_div::_GP('storagePid'));
		$this->recID = trim(t3lib_div::_GP('recID')); // no intval() here because it might be a new record
		if (intval($this->recID) == $this->recID) { 
			$this->row = t3lib_BEfunc::getRecord($this->table,$this->recID);
		}

		// set selected items
		if ($this->table == 'tt_news') {
			$this->field = 'category';
			if (is_array($this->row) && $this->row['pid']) {
				$cRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid_foreign', 'tt_news_cat_mm', 'uid_local='.intval($this->recID));
				while (($cRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($cRes))) {
					$this->selectedItems[] = $cRow['uid_foreign'];
				}
			}
		} else {
			if ($this->table == 'tt_news_cat') {
				$this->field = 'parent_category';
			} elseif ($this->table == 'tt_content') {
				$this->field = 'pi_flexform';
			} else { // be_users or be_groups
				$this->field = 'tt_news_categorymounts';
			}
			if (is_array($this->row)) {
				$this->setSelectedItems($this->row['uid']);
			}

		}
		
		if ($this->table == 'tt_content') {
			$this->PA['itemFormElName'] = 'data[tt_content]['.$this->recID.'][pi_flexform][data][sDEF][lDEF][categorySelection][vDEF]';
		} else {
			$this->PA['itemFormElName'] = 'data['.$this->table.']['.$this->recID.']['.$this->field.']';
		}

		$tree = $this->renderCatTree();

		if (!$this->treeObj_ajaxStatus) {
			$ajaxObj->setError($tree);
		} else	{
			$ajaxObj->addContent('tree', $tree);
		}
	}




	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$cmd: ...
	 * @return	[type]		...
	 */
	function renderCatTree() {

// 		$tStart = microtime(true);
// 		$this->debug['start'] = time();

			// ignore the value of "useStoragePid" if table is be_users or be_groups
		if ($this->confArr['useStoragePid'] && ($this->table == 'tt_news' || $this->table == 'tt_news_cat' || $this->table == 'tt_content')) {

			if ($this->storagePidFromAjax) {
				$this->storagePid = $this->storagePidFromAjax;
			} else {
				$TSconfig = t3lib_BEfunc::getTCEFORM_TSconfig($this->table,$this->row);
				$this->storagePid = $TSconfig['_STORAGE_PID']?$TSconfig['_STORAGE_PID']:0;
			}
			$SPaddWhere = ' AND tt_news_cat.pid IN (' . $this->storagePid . ')';
			
			
			if ($this->table == 'tt_news_cat' && intval($this->row['pid']) > 0 && $this->row['pid'] != $this->storagePid) {
				$msg = '<div style="padding:10px;"><img src="gfx/icon_warning2.gif" class="absmiddle" alt="" height="16" width="18">
					The current category is not located in the "general record storage page" but "useStoragePid" is activated in tt_news configuration. 
					Selecting a parent category from a different pid is not supported.
					</div>';
				$notInGRSP = true;
			}
		}

		if ($this->table == 'tt_news' || $this->table == 'tt_news_cat') {
				// get include/exclude items
			$excludeList = $GLOBALS['BE_USER']->getTSConfigVal('tt_newsPerms.tt_news_cat.excludeList');
			$includeList = $GLOBALS['BE_USER']->getTSConfigVal('tt_newsPerms.tt_news_cat.includeList');
			// get allowed categories from be_users/groups (including subgroups)
			if (($catmounts = $this->divObj->getAllowedCategories())) {
				// if there are some use them and ignore "includeList" from TSConfig
				$includeList = $catmounts;
			}
		}


		if ($excludeList) {
			$catlistWhere = ' AND tt_news_cat.uid NOT IN ('.implode(t3lib_div::intExplode(',',$excludeList),',').')';
		}
		if ($includeList) {
			$catlistWhere .= ' AND tt_news_cat.uid IN ('.implode(t3lib_div::intExplode(',',$includeList),',').')';
		}

		$treeOrderBy = $this->confArr['treeOrderBy']?$this->confArr['treeOrderBy']:'uid';

		// instantiate tree object
		$treeViewObj = t3lib_div::makeInstance('tx_ttnews_tceforms_categorytree');

		$treeViewObj->treeName = $this->table.'_tree';
		$treeViewObj->table = 'tt_news_cat';
		$treeViewObj->tceFormsTable = $this->table;
		$treeViewObj->tceFormsRecID = $this->recID;
		$treeViewObj->storagePid = $this->storagePid;


//		debug(array($SPaddWhere,$catlistWhere,$treeOrderBy), ' ('.__CLASS__.'::'.__FUNCTION__.')', __LINE__, __FILE__, 3);


		$treeViewObj->init($SPaddWhere.$catlistWhere,$treeOrderBy);
		$treeViewObj->backPath = $GLOBALS['BACK_PATH'];
		$treeViewObj->thisScript = 'class.tx_ttnews_tceformsSelectTree.php';
		$treeViewObj->fieldArray = array('uid','title','description','hidden','starttime','endtime','fe_group'); // those fields will be filled to the array $treeViewObj->tree
		$treeViewObj->parentField = 'parent_category';
		$treeViewObj->expandable = $this->useAjax;
		$treeViewObj->expandAll = !$this->useAjax;
		$treeViewObj->useAjax = $this->useAjax;
		$treeViewObj->titleLen = 60;
		$treeViewObj->disableAll = $notInGRSP;
		$treeViewObj->ext_IconMode = '1'; // no context menu on icons
		$treeViewObj->title = $GLOBALS['LANG']->sL($GLOBALS['TCA']['tt_news_cat']['ctrl']['title']);
		$treeViewObj->TCEforms_itemFormElName = $this->PA['itemFormElName'];

		if ($this->table=='tt_news_cat') {

			$treeViewObj->TCEforms_nonSelectableItemsArray[] = $this->row['uid'];
		}

		if ($this->divObj->useAllowedCategories() && !$this->divObj->allowedItemsFromTreeSelector) {
			// 'options.useListOfAllowedItems' is set but not category is selected --> check the 'allowedItems' list
			$notAllowedItems = $this->getNotAllowedItems($SPaddWhere);
		}
		if (is_array($notAllowedItems) && $notAllowedItems[0]) {
			foreach ($notAllowedItems as $k) {
				$treeViewObj->TCEforms_nonSelectableItemsArray[] = $k;
			}
		}
		// mark selected categories
		$treeViewObj->TCEforms_selectedItemsArray = $this->selectedItems;
		$treeViewObj->selectedItemsArrayParents = $this->getCatRootline($SPaddWhere);
	

/*
 * FIXME
 * muss das wirklich 2 mal aufgerufen werden?
 */

		if (!$this->divObj->allowedItemsFromTreeSelector) {
//			$notAllowedItems = $this->getNotAllowedItems($SPaddWhere);
		} else {
			$treeIDs = $this->divObj->getCategoryTreeIDs();
			$notAllowedItems = $this->getNotAllowedItems($SPaddWhere,$treeIDs);
		}
			// render tree html
		$treeContent = $treeViewObj->getBrowsableTree();

		$this->treeObj_ajaxStatus = $treeViewObj->ajaxStatus;




// 		$tEnd = microtime(true);
// 		$this->debug['end'] = time();
//
// 		$exectime = $tEnd-$tStart;
// 		$this->debug['exectime'] = $exectime;
		return $msg.$treeContent;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$selectedItems: ...
	 * @param	[type]		$SPaddWhere: ...
	 * @return	[type]		...
	 */
	function getCatRootline ($SPaddWhere) {
		$selectedItemsArrayParents = array();
		foreach($this->selectedItems as $v) {
			$uid = $v;
			$loopCheck = 100;
			$catRootline = array();
			while ($uid!=0 && $loopCheck>0)	{
				$loopCheck--;
				$row = t3lib_BEfunc::getRecord('tt_news_cat', $uid, 'parent_category', $SPaddWhere);
				if (is_array($row) && $row['parent_category'] > 0)	{
					$uid = $row['parent_category'];
					$catRootline[] = $uid;
				} else {
					break;
				}
			}
			$selectedItemsArrayParents[$v] = $catRootline;
		}
		return $selectedItemsArrayParents;
	}


	/**
	 * This function checks if there are categories selectable that are not allowed for this BE user and if the current record has
	 * already categories assigned that are not allowed.
	 * If such categories were found they will be returned and "$this->NA_Items" is filled with an error message.
	 * The array "$itemArr" which will be returned contains the list of all non-selectable categories. This array will be added
	 * to "$treeViewObj->TCEforms_nonSelectableItemsArray". If a category is in this array the "select item" link will not be added to it.
	 *
	 * @param	array		$PA: the paramter array
	 * @param	string		$SPaddWhere: this string is added to the query for categories when "useStoragePid" is set.
	 * @param	[type]		$allowedItemsList: ...
	 * @return	array		array with not allowed categories
	 * @see tx_ttnews_tceFunc_selectTreeView::wrapTitle()
	 */
	function getNotAllowedItems($SPaddWhere,$allowedItemsList=false) {
		$fTable = 'tt_news_cat';
			// get list of allowed categories for the current BE user
		if (!$allowedItemsList) {
			$allowedItemsList = $GLOBALS['BE_USER']->getTSConfigVal('tt_newsPerms.'.$fTable.'.allowedItems');
		}
		$itemArr = array();
		if ($allowedItemsList) {
				// get all categories
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', $fTable, '1=1' .$SPaddWhere. ' AND deleted=0');
			while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
				if (!t3lib_div::inList($allowedItemsList,$row['uid'])) { // remove all allowed categories from the category result
					$itemArr[]=$row['uid'];
				}
			}
			if (!$this->row['sys_language_uid'] && !$this->row['l18n_parent']) {
				$catvals = explode(',',$this->row['category']); // get categories from the current record
				$notAllowedCats = array();
				foreach ($catvals as $k) {
					$c = explode('|',$k);
					if($c[0] && !t3lib_div::inList($allowedItemsList,$c[0])) {
						$notAllowedCats[]= '<p style="padding:0px;color:red;font-weight:bold;">- '.$c[1].' <span class="typo3-dimmed"><em>['.$c[0].']</em></span></p>';
					}
				}
				if ($notAllowedCats[0]) {
					$this->NA_Items = $this->printError($notAllowedCats,array());
				}
			}
		}
		return $itemArr;
	}
}






	/**
	 * extend class t3lib_treeview to change function wrapTitle().
	 *
	 */
class tx_ttnews_tceforms_categorytree extends tx_ttnews_categorytree {

	var $TCEforms_itemFormElName='';
	var $TCEforms_nonSelectableItemsArray=array();

	/**
	 * wraps the record titles in the tree with links or not depending on if they are in the TCEforms_nonSelectableItemsArray.
	 *
	 * @param	string		$title: the title
	 * @param	array		$v: an array with uid and title of the current item.
	 * @return	string		the wrapped title
	 */
	function wrapTitle($title,$v)	{
// 		debug($v);
		if($v['uid']>0) {
			$hrefTitle = htmlentities('[id='.$v['uid'].'] '.$v['description']);
			if (in_array($v['uid'],$this->TCEforms_nonSelectableItemsArray) || $this->disableAll) {
				$style = $this->getTitleStyles($v,$hrefTitle);
				return '<a href="#" title="'.$hrefTitle.'"><span style="color:#999;cursor:default;'.$style.'">'.$title.'</span></a>';
			} else {
				$aOnClick = 'setFormValueFromBrowseWin(\''.$this->TCEforms_itemFormElName.'\','.$v['uid'].',\''.t3lib_div::slashJS($title).'\'); return false;';
				$style = $this->getTitleStyles($v,$hrefTitle);
				return '<a href="#" onclick="'.htmlspecialchars($aOnClick).'" title="'.$hrefTitle.'"><span style="'.$style.'">'.$title.'</span></a>';
			}
		} else {
			return $title;
		}
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$v: ...
	 * @return	[type]		...
	 */
	function getTitleStyles($v, &$hrefTitle) {
		$style = '';
		if (in_array($v['uid'], $this->TCEforms_selectedItemsArray)) {
			$style .= 'font-weight:bold;';
		}
		$p = false;
		foreach ($this->TCEforms_selectedItemsArray as $selitems) {
			if (is_array($this->selectedItemsArrayParents[$selitems]) && in_array($v['uid'], $this->selectedItemsArrayParents[$selitems])) {
				$p = true;
				break;
			}
		}
		if ($p) {
			$style .= 'text-decoration:underline;background:#ffc;';
			$hrefTitle .= ' (subcategory selected)';
		}
		
		return $style;
	}

	/**
	 * Wrap the plus/minus icon in a link
	 *
	 * @param	string		HTML string to wrap, probably an image tag.
	 * @param	string		Command for 'PM' get var
	 * @param	[type]		$isExpand: ...
	 * @return	string		Link-wrapped input string
	 * @access private
	 */
	function PMiconATagWrap($icon, $cmd, $isExpand = true)	{
		if ($this->thisScript && $this->expandable && !$this->disableAll) {

			// activate dynamic ajax-based tree
			$js = htmlspecialchars('tceFormsCategoryTree.load(\''.$cmd.'\', '.intval($isExpand).', this, \''.$this->tceFormsTable.'\', \''.$this->tceFormsRecID.'\', \''.$this->storagePid.'\');');
			return '<a class="pm" onclick="'.$js.'">'.$icon.'</a>';
		} else {
			return $icon;
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tt_news/lib/class.tx_ttnews_TCAform_selectTree.php'])    {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tt_news/lib/class.tx_ttnews_TCAform_selectTree.php']);
}
?>