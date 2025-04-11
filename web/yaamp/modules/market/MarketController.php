<?php

class MarketController extends CommonController
{
	public function actionUpdate()
	{
		if(!$this->admin) return;

		$market = getdbo('db_markets', getiparam('id'));
		if(!$market) {
			user()->setFlash('error', "invalid market");
			$this->goback();
			return;
		}
		$coin = getdbo('db_coins', $market->coinid);

		if(isset($_POST['db_markets']))
		{
			$market->setAttributes($_POST['db_markets'], false);
			if($market->save())
				$this->redirect(array('admin/coin', 'id'=>$coin->id));
		}

		$this->render('update', array('market'=>$market, 'coin'=>$coin));
	}

	public function actionEnable()
	{
		if(!$this->admin) return;

		$enable = (int) getiparam('en');
		$market = getdbo('db_markets', getiparam('id'));
		if($market) {
			$market->disabled = $enable ? 0 : 9;
			$market->save();
		}
		$this->goback();
	}

	public function actionDelete()
	{
		if(!$this->admin) return;

		$market = getdbo('db_markets', getiparam('id'));
		if($market) $market->delete();
		$this->goback();
	}

	public function actionSellto()
	{
		if(!$this->admin) return;

		$market = getdbo('db_markets', getiparam('id'));
		if(!$market) {
			user()->setFlash('error', "invalid market");
			$this->goback();
			return;
		}
		$coin = getdbo('db_coins', $market->coinid);
		$amount = getparam('amount');

		$remote = new WalletRPC($coin);

		$info = $remote->getinfo();
		if(!$info || !$info['balance'])
		{
			user()->setFlash('error', "not enough balance $coin->name");
			$this->redirect(array('admin/coin', 'id'=>$coin->id));
		}

		$deposit_info = $remote->validateaddress($market->deposit_address);
		if(!$deposit_info || !isset($deposit_info['isvalid']) || !$deposit_info['isvalid'])
		{
			user()->setFlash('error', "invalid address $coin->name, $market->deposit_address");
			$this->redirect(array('admin/coin', 'id'=>$coin->id));
		}

		$amount = min($amount, $info['balance'] - $info['paytxfee']);
//		$amount = max($amount, $info['balance'] - $info['paytxfee']);
		$amount = round($amount, 8);

		debuglog("selling ($market->deposit_address, $amount)");

		$tx = $remote->sendtoaddress($market->deposit_address, $amount);
		if(!$tx)
		{
			user()->setFlash('error', $remote->error);
			$this->redirect(array('admin/coin', 'id'=>$coin->id));
		} else {
			$market->lastsent = time();
			$market->save();
			BackendUpdatePoolBalances($coin->id);
		}

		 $exchange_deposit = new db_exchange_deposit;
		 $exchange_deposit->market = $market->name;
		 $exchange_deposit->coinid = $coin->id;
		 $exchange_deposit->send_time = time();
		 $exchange_deposit->quantity = $amount;
		 $exchange_deposit->price_estimate = $coin->price;
		 $exchange_deposit->status = 'waiting';
		 $exchange_deposit->tx = $tx;
		 $exchange_deposit->save();

		$this->redirect(array('admin/coin', 'id'=>$coin->id));
	}

}
