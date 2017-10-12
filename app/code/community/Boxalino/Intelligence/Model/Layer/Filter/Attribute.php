<?php

/**
 * Class Boxalino_Intelligence_Model_Layer_Filter_Attribute
 */
class Boxalino_Intelligence_Model_Layer_Filter_Attribute extends Mage_Catalog_Model_Layer_Filter_Attribute{

    /**
     * @var null
     */
    protected $bxFacets = null;

    /**
     * @var string
     */
    protected $fieldName = '';

    /**
     * @var null
     */
    protected $locale = null;

    /**
     * @param $bxFacets
     */
    public function setFacets($bxFacets) {
        $this->bxFacets = $bxFacets;
        return $this;
    }

    /**
     * @return null
     */
    public function getFacets(){
        return $this->bxFacets;
    }
    
    /**
     * @param $fieldName
     */
    public function setFieldName($fieldName) {
        $this->fieldName = $fieldName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getName(){
        return $this->bxFacets->getFacetLabel($this->fieldName, $this->getLocale());
    }


    /**
     * @return string
     */
    public function getFieldName(){
        return $this->fieldName;
    }

    /**
     * @return null|string
     */
    public function getLocale(){
        if(is_null($this->locale)){
            $this->locale = substr(Mage::getStoreConfig('general/locale/code'), 0, 2);
        }
        return $this->locale;
    }

    /**
     * @return $this
     */
    public function _initItems(){
        $bxHelperData =  Mage::helper('boxalino_intelligence');
        if(!$bxHelperData->getAdapter()->areThereSubPhrases()){
            $data = $this->_getItemsData();
            $items = [];
            foreach ($data as $itemData) {
                $selected = isset($itemData['selected']) ? $itemData['selected'] : null;
                $type = isset($itemData['type']) ? $itemData['type'] : null;
                $hidden = isset($itemData['hidden']) ? $itemData['hidden'] : null;
                $items[$itemData['label']] = $this->_createItem($itemData['label'], $itemData['value'],
                    $itemData['paramValue'], $itemData['count'], $selected, $type, $hidden);
            }
            $this->_items = $items;
        }
        return $this;
    }

    /**
     * @param string $label
     * @param mixed $value
     * @param int $count
     * @param null $selected
     * @param null $type
     * @return mixed
     */
    public function _createItem($label, $value, $paramValue, $count = 0, $selected = null, $type = null, $hidden = null){
        return Mage::getModel('catalog/layer_filter_item')
            ->setFilter($this)
            ->setLabel($label)
            ->setValue($value)
            ->setParamValue($paramValue)
            ->setCount($count)
            ->setSelected($selected)
            ->setType($type)
            ->setHidden($hidden);
    }

    /**
     * @return mixed
     */
    public function getAttributeModel() {
        return Mage::getModel('eav/config')->getAttribute('catalog_product', substr($this->fieldName, 9))->getSource();
    }

    /**
     * @return bool
     */
    public function isSystemFilter() {
        $source = $this->getAttributeModel();
        return sizeof($source->getAllOptions()) > 1;
    }

    /**
     * @return array
     */
    protected function _getItemsData(){
        $fieldName = $this->fieldName;
        $bxFacets = $this->bxFacets;
        $data = [];
        $bxDataHelper = Mage::helper('boxalino_intelligence');
        $order = $bxFacets->getFacetExtraInfo($fieldName, 'valueorderEnums');
        $isSystemFilter = $this->isSystemFilter();
        $facetOptions = $bxDataHelper->getFacetOptions();
        $isMultiValued = isset($facetOptions[$fieldName]) ? true : false;
        if($isSystemFilter) {
            $this->_requestVar = str_replace('bx_products_', '', $bxFacets->getFacetParameterName($fieldName));
        } else {
            $this->_requestVar = $bxFacets->getFacetParameterName($fieldName);
        }
        if ($fieldName == $bxFacets->getCategoryfieldName()) {
            $count = 1;
            $parentCategories = $bxFacets->getParentCategories();
            $parentCount = count($parentCategories);
            $value = false;
            foreach ($parentCategories as $key => $parentCategory) {
                if ($count == 1) {
                    $count++;
                    $homeLabel = Mage::helper('boxalino_intelligence')->__("All Categories");
                    $data[] = array(
                        'label' => strip_tags($homeLabel),
                        'value' => strip_tags($homeLabel),
                        'paramValue' => 2,
                        'count' => $bxFacets->getParentCategoriesHitCount($key),
                        'selected' => $value,
                        'type' => 'home parent',
                        'hidden' => false
                    );
                    continue;
                }
                if ($parentCount == $count++) {
                    $value = true;
                }
                $data[] = array(
                    'label' => strip_tags($parentCategory),
                    'value' => strip_tags($parentCategory),
                    'paramValue' => $value ? null : $key,
                    'count' => $bxFacets->getParentCategoriesHitCount($key),
                    'selected' => $value,
                    'type' => 'parent',
                    'hidden' => false
                );
            }
            $facetValues = null;
            if(!is_null($order)){
                $facetLabels = $bxFacets->getCategoriesKeyLabels();
                $childId = explode('/',end($facetLabels))[0];
                $category_model = Mage::getModel('catalog/category');
                $childParentId = $category_model->load($childId)->getParentId();
                end($parentCategories);
                $parentId = key($parentCategories);
                $id = (($parentId == null) ? 2 : (($parentId == $childParentId) ? $parentId : $childParentId));

                $cat = $category_model->load($id);
                foreach($cat->getChildrenCategories() as $category){
                    if(isset($facetLabels[$category->getName()])) {
                        $facetValues[] = $facetLabels[$category->getName()];
                    }
                }
            }
            if($facetValues == null){
                $facetValues = $bxFacets->getFacetValues($fieldName);
            }

            foreach ($facetValues as $facetValue) {
                $id =  $bxFacets->getFacetValueParameterValue($fieldName, $facetValue);
                if (Mage::helper('catalog/category')->canShow((int)$id)) {
                    $data[] = array(
                        'label' => strip_tags($bxFacets->getFacetValueLabel($fieldName, $facetValue)),
                        'value' => strip_tags($bxFacets->getFacetValueLabel($fieldName, $facetValue)),
                        'paramValue' => $id,
                        'count' => $bxFacets->getFacetValueCount($fieldName, $facetValue),
                        'selected' => false,
                        'type' => $value ? 'children' : 'home',
                        'hidden' => $bxFacets->isFacetValueHidden($fieldName, $facetValue)
                    );
                }
            }
        } else {
            $attributeModel = $this->getAttributeModel();
            if ($order == 2) {
                $values = $attributeModel->getAllOptions();
                $responseValues = $bxDataHelper->useValuesAsKeys($bxFacets->getFacetValues($fieldName));
                $selectedValues = $bxDataHelper->useValuesAsKeys($bxFacets->getSelectedValues($fieldName));
                foreach ($values as $value) {
                    $label = is_array($value) ? $value['label'] : $value;
                    if (isset($responseValues[$label])) {
                        $facetValue = $responseValues[$label];
                        $selected = isset($selectedValues[$facetValue]) ? true : false;
                        $paramValue = $this->getParamValue($isSystemFilter, $bxFacets, $fieldName, $facetValue, $attributeModel, $selectedValues, $selected, $isMultiValued);
                        $data[] = array(
                            'label' => strip_tags($bxFacets->getFacetValueLabel($fieldName, $facetValue)),
                            'value' => strip_tags($bxFacets->getFacetValueLabel($fieldName, $facetValue)),
                            'paramValue' => $paramValue,
                            'count' => $bxFacets->getFacetValueCount($fieldName, $facetValue),
                            'selected' => $selected,
                            'type' => 'flat',
                            'hidden' => $bxFacets->isFacetValueHidden($fieldName, $facetValue)
                        );
                    }
                }
            } else {
                $selectedValues = $bxDataHelper->useValuesAsKeys($bxFacets->getSelectedValues($fieldName));
                $responseValues = $bxFacets->getFacetValues($fieldName);

                foreach ($responseValues as $facetValue) {

                    $selected = isset($selectedValues[$facetValue]) ? true : false;
                    $paramValue = $this->getParamValue($isSystemFilter, $bxFacets, $fieldName, $facetValue, $attributeModel, $selectedValues, $selected, $isMultiValued);

                    $data[] = array(
                        'label' => strip_tags($bxFacets->getFacetValueLabel($fieldName, $facetValue)),
                        'value' => strip_tags($bxFacets->getFacetValueLabel($fieldName, $facetValue)),
                        'paramValue' => $paramValue,
                        'count' => $bxFacets->getFacetValueCount($fieldName, $facetValue),
                        'selected' => $selected,
                        'type' => 'flat',
                        'hidden' => $bxFacets->isFacetValueHidden($fieldName, $facetValue)
                    );
                }
            }
        }
        return $data;
    }

    /**
     * @param $isSystemFilter
     * @param $bxFacets
     * @param $fieldName
     * @param $facetValue
     * @param $attributeModel
     * @param $selectedValues
     * @param $selected
     * @param bool $setCurrentSelection
     * @return null|string
     */
    public function getParamValue($isSystemFilter, $bxFacets, $fieldName, $facetValue, $attributeModel, $selectedValues, $selected, $setCurrentSelection=false) {
        $paramValue = ($selected ? null : ($isSystemFilter ? $attributeModel->getOptionId($facetValue) : $bxFacets->getFacetValueParameterValue($fieldName, $facetValue)));
        if($selected && isset($selectedValues[$facetValue]))unset($selectedValues[$facetValue]);
        if($setCurrentSelection && sizeof($selectedValues)>0) {
            $separator = Mage::getStoreConfig('bxSearch/advanced/parameter_separator');
            if(!is_null($paramValue)) $paramValue .= $separator;
            if(!$isSystemFilter) {
                $paramValue .= implode($separator, $selectedValues);
                return $paramValue;
            }else {
                $changedSelection = array();
                foreach($selectedValues as $selected) {
                    $changedSelection[] = $attributeModel->getOptionId($selected);
                }
                $paramValue .= implode($separator, $changedSelection);
            }
        }
        return $paramValue;
    }
}
