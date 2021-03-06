<?php
/**
 * @author David Spreekmeester | grrr.nl
 */
class Garp_Spawn_Behavior_Type_Sluggable extends Garp_Spawn_Behavior_Type_Abstract {
    const SLUG_FIELD_PARAM = 'slugField';
    const BASE_FIELD_PARAM = 'baseField';

    /**
     * @var Array $_defaultParams
     */
    protected $_defaultParams = array(
        'baseField' => 'name',
        'slugField' => 'slug'
    );

    protected $_slugFieldConfig = array(
        'type' => 'text',
        'index' => true,
        'maxLength' => 255,
        'editable' => false,
        'unique' => true,
        'required' => false,
        'multiline' => false,
    );



    public function getFields() {
        return array($this->_getSlugFieldName() => $this->_getSlugFieldConfig());
    }

    public function getParams() {
        $params         = parent::getParams();
        $defaultParams  = $this->getDefaultParams();

        foreach ($defaultParams as $paramName => $paramValue) {
            if (!array_key_exists($paramName, $params)) {
                $params[$paramName] = $paramValue;
            }
        }

        return $params;
    }

    /**
     * @return Array
     */
    public function getDefaultParams() {
        return $this->_defaultParams;
    }

    /**
     * @return  Bool    Whether this behavior needs to be registered with an observer
     *                  called in the PHP model's init() method
     */
    public function needsPhpModelObserver() {
        $model = $this->getModel();

        return
            !$model->isMultilingual() ||
            ($model->isMultilingual() && !$this->_baseFieldIsMultilingual()) ||
            ($model->isTranslated() && $this->_baseFieldIsMultilingual())
        ;
    }

    /**
     * @return  Array   Configuration of the slug field
     */
    protected function _getSlugFieldConfig() {
        $slugFieldConfig = $this->_slugFieldConfig;
        $model = $this->getModel();
        if ($this->_baseFieldIsMultilingual()) {
            if ($model instanceof Garp_Spawn_Model_I18n) {
                // Add slug and lang as combined unique key to the model
                // Only applicable to I18n models hence the icky instanceof check
                $existingUnique = $model->unique ?: array();
                $model->unique = array_merge($existingUnique, array(array('lang', 'slug')));
                unset($slugFieldConfig['unique']);
            }
            $slugFieldConfig['multilingual'] = true;
        }

        $params = $this->getParams();
        if (array_key_exists('editable', $params)) {
            $slugFieldConfig['editable'] = $params['editable'];
        }

        if (array_key_exists('required', $params)) {
            $slugFieldConfig['required'] = $params['required'];
        }
        return $slugFieldConfig;
    }

    /**
     * Whether one or more base fields are multilingual
     */
    protected function _baseFieldIsMultilingual() {
        $modelBaseFields = $this->_getModelBaseFields();

        foreach ($modelBaseFields as $field) {
            if ($field->isMultilingual()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return  Array   One or more model fields on which the slug is based
     */
    protected function _getModelBaseFields() {
        $baseFields     = array();
        $model          = $this->getModel();
        $baseFieldNames = $this->_getBaseFieldNames();

        foreach ($baseFieldNames as $name) {
            if (!$model->fields->exists($name)) {
                continue;
            }
            $baseFields[] = $model->fields->getField($name);
        }

        return $baseFields;
    }

    /**
     * @return  Array   One or more field names on which the slug is based
     */
    protected function _getBaseFieldNames() {
        $params         = $this->getParams();
        $baseFieldNames = (array)$params[self::BASE_FIELD_PARAM];
        return $baseFieldNames;
    }

    /**
     * @return  Array   Configuration of the slug field
     */
    protected function _getSlugFieldName() {
        $params = $this->getParams();
        return $params[self::SLUG_FIELD_PARAM];
    }

}

