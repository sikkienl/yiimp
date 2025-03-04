<?php
require_once('poloniex_trading.php');
require_once('binance_trading.php');
require_once('exbitron_trading.php');
require_once('kraken_trading.php');
require_once('yobit_trading.php');
require_once('hitbtc_trading.php');
require_once('kucoin_trading.php');
require_once('tradeogre_trading.php');
require_once('xeggex_trading.php');

function cancelExchangeOrder($order=false)
{
	if ($order)
		switch ($order->market)
		{
			case 'yobit':
				doYobitCancelOrder($order->uuid);
				break;
			case 'binance':
				doBinanceCancelOrder($order->uuid);
				break;
			case 'hitbtc':
				doHitBTCCancelOrder($order->uuid);
				break;
			case 'kucoin':
				doKuCoinCancelOrder($order->uuid);
				break;
			case 'tradeogre':
				doTradeogreCancelOrder($order->uuid);
				break;
			case 'xeggex':
				doXeggexCancelOrder($order->uuid);
				break;
		}
}

function runExchange($exchangeName=false)
{
	if (!empty($exchangeName))
	{
		switch($exchangeName)
		{
			case 'binance':
				doBinanceTrading(true);
				updateBinanceMarkets();
				break;

			case 'bitstamp':
				getBitstampBalances();
				break;

			case 'cexio':
				getCexIoBalances();
				break;

			case 'exbitron':
				doExbitronTrading(true);
				updateExbitronMarkets();
				break;
				
			case 'yobit':
				doYobitTrading(true);
				updateYobitMarkets();
				break;

			case 'hitbtc':
				doHitBTCTrading(true);
				updateHitBTCMarkets();
				break;

			case 'kraken':
				doKrakenTrading(true);
				updateKrakenMarkets();
				break;

			case 'kucoin':
				doKuCoinTrading(true);
				updateKucoinMarkets();
				break;

			case 'poloniex':
				doPoloniexTrading(true);
				updatePoloniexMarkets();
				break;

			case 'xeggex':
				doXeggexTrading(true);
				updateXeggexMarkets();
				break;
	
			default:
				debuglog(__FUNCTION__.' '.$exchangeName.' not implemented');
		}
	}
}
