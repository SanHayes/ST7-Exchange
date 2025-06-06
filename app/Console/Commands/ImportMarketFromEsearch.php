<?php

namespace App\Console\Commands;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use App\Models\MarketHour;
use App\Models\CurrencyMatch;
class ImportMarketFromEsearch extends Command
{
	protected $signature = "market:import";
	protected $description = "Command description";
	public function __construct()
	{
		parent::__construct();
	}
	public function handle()
	{
		$from_host = ['http://mhy.happyawn.com:9200', '39.109.113.168:9200'];
		$to_host = ['45.192.181.101:9200'];
		$from_host = ['host' => '39.109.113.168:9200'];
		$to_host = ['host' => '45.192.181.101:9200'];
		$from_client = self::getEsearchClient($from_host);
		$to_client = self::getEsearchClient($to_host);
		$huobi_matchs = CurrencyMatch::getHuobiMatchs();
		$from = 1543593600;
		$to = 1545235200;
		foreach ($huobi_matchs as $key => $match) {
			$base_currency = $match->currency_name;
			$quote_currency = $match->legal_name;
			$type = strtoupper($base_currency . '.' . $quote_currency) . '.1day';
			$result = self::getEsearchMarket($from_client, $base_currency, $quote_currency, '1day', $from, $to);
			$params = [];
			foreach ($result as $key => $value) {
				$params['body'][] = ['index' => ['_index' => 'market.kline', '_type' => $type]];
				$params['body'][] = $value;
			}
			$result = $to_client->bulk($params);
			var_dump($result);
		}
	}
	public static function getEsearchClient($hosts)
	{
		$es_client = ClientBuilder::create()->setHosts($hosts)->build();
		return $es_client;
	}
	public static function getEsearchMarket($es_client, $base_currency, $quote_currency, $peroid, $from, $to)
	{
		$size = 0;
		$base_currency = strtoupper($base_currency);
		$quote_currency = strtoupper($quote_currency);
		$interval_list = ["1min" => 60, "5min" => 300, "15min" => 900, "30min" => 1800, "60min" => 3600, "1hour" => 3600, "1day" => 86400, "1week" => 604808, "1mon" => 2592000, "1year" => 31536000];
		$interval = $interval_list[$peroid];
		$size = intval(($to - $from) / $interval) + 100;
		$type = $base_currency . '.' . $quote_currency . '.' . $peroid;
		$params = ['index' => 'market.kline', 'type' => $type, 'body' => ['query' => ['bool' => ['filter' => ['range' => ['id' => ['gte' => $from, 'lte' => $to]]]]], 'sort' => ['id' => ['order' => 'asc']], 'size' => $size]];
		$result = $es_client->search($params);
		if (isset($result['hits'])) {
			$data = array_column($result['hits']['hits'], '_source');
		} else {
			$data = [];
		}
		return $data;
	}
}
