<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\bootstrap5\Html;

class Coins extends ActiveRecord
{
    public function getOfficialSymbol()
	{
		if(!empty($this->symbol2))
			return $this->symbol2;
		else
			return $this->symbol;
	}
	public function getSymbol_show()
	{
		// virtual property $coin->symbol_show
		return $this->getOfficialSymbol();
	}

    /**
	 * Link for txs
	 * @param string $label link content
	 * @param array $params 'height'=>123 or 'hash'=>'xxx' or 'txid'=>'xxx'
	 * @param array $htmlOptions target/title ...
	 */
	public function createExplorerLink($label, $params=array(), $htmlOptions=array(), $force=false)
	{
		if($this->id == 6 && isset($params['txid'])) {
			// BTC txid
			$url = 'https://blockchain.info/tx/'.$params['txid'];
			$htmlOpts = array_merge(array('target'=>'_blank', 'class' => 'profile-link'), $htmlOptions);
			return Html::a($label, $url, $htmlOpts);
		}
		else if (YIIMP_PUBLIC_EXPLORER || $force || user()->getState('yaamp_admin')) {
			$urlParams = array_merge(array('id'=>$this->id), $params);
/*			Yii::import('application.modules.explorer.ExplorerController');
			$TmpExplorer = new ExplorerController(1); // php8 workaround
    		$url = $TmpExplorer->createUrl('/explorer', $urlParams);
			return CHtml::link($label, trim($url,'?'), $htmlOptions);*/
			return Html::a($label, ['/explorer', $urlParams], ['class' => 'profile-link']);
		}
		return $label;
	}

}