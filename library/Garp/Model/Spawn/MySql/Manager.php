<?php
/**
 * Generate and alter tables to reflect base models and association models
 * @author David Spreekmeester | grrr.nl
 * @package Garp
 * @subpackage Model
 */
class Garp_Model_Spawn_MySql_Manager {
	/** @param Array $_models Array of Garp_Model_Spawn_Model objects */
	protected $_modelSet;
	protected $_adapter;
	
	const CUSTOM_SQL_PATH = '/data/sql/spawn.sql';


	/**
	 * @param Garp_Model_Spawn_ModelSet 	$modelSet 		The model set to model the database after.
	 * @param Array 						&$changelist 	An array of strings, describing the changes made to the database in this Spawn session.
	 */
	public function __construct(Garp_Model_Spawn_ModelSet $modelSet) {
		$totalActions = count($modelSet) * 4;
		$progress = Garp_Cli_Ui_ProgressBar::getInstance();
		$progress->init($totalActions);
		$progress->display("Initializing database...");

		$this->_modelSet = $modelSet;
		$this->_adapter = Zend_Db_Table::getDefaultAdapter();
		$this->_adapter->query('SET NAMES utf8;');


		//	Stage 1: Create base models
		foreach ($modelSet as $model) {
			$progress->display($model->id . " base table");
			$this->_createBaseModelTableIfNotExists($model);
			$progress->advance();
			$progress->display($model->id . " joint view");
			$this->_createJointView($model);
			$progress->advance();
		}

		//	Stage 2: Create binding models
		foreach ($modelSet as $model) {
			$progress->display($model->id . " many-to-many config reading");
			$habtmRelations = $model->relations->getRelations('type', 'hasAndBelongsToMany');
			if ($habtmRelations) {
				foreach ($habtmRelations as $relation) {
					if (strcmp($model->id, $relation->model) <= 0) {
						//	only sync binding tables from models A -> B, not from B -> A
						$this->_createBindingModelTableIfNotExists($relation);
					}
				}
			}
			$progress->advance();
		}

		//	Stage 3: Sync base and binding models
		foreach ($modelSet as $model) {
			$this->_syncBaseModel($model);

			$habtmRelations = $model->relations->getRelations('type', 'hasAndBelongsToMany');
			if ($habtmRelations) {
				foreach ($habtmRelations as $relation) {
					if (strcmp($model->id, $relation->model) <= 0) {
						//	only sync binding tables from models A -> B, not from B -> A
						$this->_syncBindingModel($relation);
					}
				}
			}
			$progress->advance();
		}

		//	Stage 4: Execute custom SQL
		$progress->display("Executing custom SQL");
		$this->_executeCustomSql();


		$progress->display("√ Done");
	}
	
	
	protected function _createBaseModelTableIfNotExists(Garp_Model_Spawn_Model $model) {
		$progress = Garp_Cli_Ui_ProgressBar::getInstance();
		$progress->display($model->id . " SQL render.");
		$table = $this->_getBaseModelConfigTable($model);
		$this->_createTableIfNotExists($table);
	}


	/**
	 * Creates a MySQL view for every base model, that also fetches the labels of related hasOne / belongsTo records.
	 */
	protected function _createJointView(Garp_Model_Spawn_Model $model) {
		$view = new Garp_Model_Spawn_MySql_JointView($model);
		$view->create();
	}
	
	
	protected function _createBindingModelTableIfNotExists(Garp_Model_Spawn_Relation $relation) {
		$configBindingTable = $this->_getBindingModelConfigTable($relation);
		$this->_createTableIfNotExists($configBindingTable);
	}


	protected function _syncBaseModel(Garp_Model_Spawn_Model $model) {
		$progress = Garp_Cli_Ui_ProgressBar::getInstance();
		$progress->display($model->id . " table comparison");
		$baseModelConfigTable = $this->_getBaseModelConfigTable($model);
		$baseModelLiveTable = $this->_getBaseModelLiveTable($model);
		$baseModelConfigTable->syncModel($baseModelLiveTable);
	}


	protected function _syncBindingModel(Garp_Model_Spawn_Relation $relation) {
		$progress = Garp_Cli_Ui_ProgressBar::getInstance();
		$bindingModel = $relation->getBindingModel();
		$progress->display($bindingModel->id . " table comparison");
		$configBindingTable = $this->_getBindingModelConfigTable($relation);
		$liveBindingTable = $this->_getBindingModelLiveTable($relation);
		$configBindingTable->syncBindingModel($liveBindingTable, $bindingModel);
	}


	protected function _createTableIfNotExists(Garp_Model_Spawn_MySql_Table $table) {
		if (!Garp_Model_Spawn_MySql_Table::exists($table->name)) {
			$progress = Garp_Cli_Ui_ProgressBar::getInstance();
			$progress->display($table->name . " table creation");
			if (!$table->create()) {
				throw new Exception("Unable to create the {$table->name} binding model table.");
			}			
		}
	}
	
	
	protected function _executeCustomSql() {
		$path = APPLICATION_PATH . self::CUSTOM_SQL_PATH;

		if (file_exists($path)) {
			$config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
			$db = $config->resources->db->params;
			$readSqlCommand = "mysql -u'{$db->username}' -p'{$db->password}' -D'{$db->dbname}' --host='{$db->host}' < " . $path;
			`$readSqlCommand`;
		}
	}


	protected function _getBaseModelConfigTable(Garp_Model_Spawn_Model $model) {
		$sqlFromConfig = Garp_Model_Spawn_MySql_Table::renderCreateFromSpawnModel($model);
		return new Garp_Model_Spawn_MySql_Table($sqlFromConfig, $model);
	}


	protected function _getBaseModelLiveTable(Garp_Model_Spawn_Model $model) {
		$sqlFromLive = Garp_Model_Spawn_MySql_Table::renderCreateFromLiveTable($model->id);
		return new Garp_Model_Spawn_MySql_Table($sqlFromLive, $model);
	}


	protected function _getBindingModelConfigTable(Garp_Model_Spawn_Relation $relation) {
		$bindingModel 			= $relation->getBindingModel();
		$sqlFromConfig 			= Garp_Model_Spawn_MySql_Table::renderCreateForBindingModel($relation);
		return new Garp_Model_Spawn_MySql_Table($sqlFromConfig, $bindingModel);
	}


	protected function _getBindingModelLiveTable(Garp_Model_Spawn_Relation $relation) {
		$bindingModel 			= $relation->getBindingModel();
		$bindingModelTableName 	= Garp_Model_Spawn_MySql_Table::getBindingModelTableName($bindingModel->id);
		$sqlFromLive = Garp_Model_Spawn_MySql_Table::renderCreateFromLiveTable($bindingModelTableName);
		return new Garp_Model_Spawn_MySql_Table($sqlFromLive, $bindingModel);
	}
}