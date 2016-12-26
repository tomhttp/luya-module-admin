<?php

namespace luya\admin\proxy;

use Yii;
use yii\base\Object;
use Curl\Curl;
use yii\helpers\Json;
use luya\Exception;
use yii\helpers\Console;

/**
 * @since 1.0.0
 */
class ClientTable extends Object
{
	private $_data = null;
	
	/**
	 * @var \luya\admin\proxy\ClientBuild
	 */
	public $build = null;
	
	public function __construct(ClientBuild $build, array $data, array $config = [])
	{
		$this->build = $build;
		$this->_data = $data;
		parent::__construct($config);
	}
	
	public function getPks()
	{
		return $this->_data['pks'];
	}
	
	public function getName()
	{
		return $this->_data['name'];
	}
	
	public function getRows()
	{
		return $this->_data['rows'];
	}
	
	public function getFields()
	{
		return $this->_data['fields'];
	}
	
	public function getOffsetTotal()
	{
		return $this->_data['offset_total'];
	}
	
	public function isComplet()
	{
		return $this->getRows() == count($this->getContentRows());
	}
	
	private $_contentRows = null;
	
	public function getContentRows()
	{
		if ($this->_contentRows === null) {
			$data = [];
			$this->build->command->outputInfo($this->getName() . ' fetching data from proxy:');
			Console::startProgress(0, $this->getOffsetTotal());
			for ($i=0; $i<$this->getOffsetTotal(); $i++) {
				$data = array_merge($this->request($i), $data);
				usleep(100);
				Console::updateProgress($i, $this->getOffsetTotal());
			}
			Console::endProgress();
			$this->_contentRows = $data;
		}
		
		return $this->_contentRows;
	}
	
	public function syncData()
	{
		if (!$this->isComplet()) {
			throw new Exception('unable to sync data from incomplet buffer');
		}
		
		Yii::$app->db->createCommand()->truncateTable($this->getName())->execute();
		Yii::$app->db->createCommand()->batchInsert($this->getName(), $this->getFields(), $this->getContentRows())->execute();
	}
	
	private function request($offset)
	{
		$curl = new Curl();
		$curl->get($this->build->requestUrl, ['buildToken' => $this->build->buildToken, 'table' => $this->name, 'offset' => $offset]);
		
		if (!$curl->error) {
			return Json::decode($curl->response);
		}		
	}
}