<?php
namespace Hypercharge;

use \Mockery as m;

require_once(dirname(__DIR__).'/vendor/autoload.php');
require_once(dirname(__DIR__).'/vendor/vierbergenlars/simpletest/autorun.php');


abstract class HyperchargeTestCase extends \UnitTestCase {

	function tearDown() {
		m::close();
		Config::setFactory(new Factory());
	}

	/**
	* @param string $fileName e.g. "payment_notification.json" for /test/fixtures/payment_notification.json
	* @return mixed array for *.json, string for other
	*/
	function fixture($fileName) {
		return self::parseIfJson(file_get_contents(__DIR__.'/fixtures/'.$fileName), $fileName);
	}

	/**
	* @param string $fileName e.g. "sale.json" for /vendor/hypercharge/hypercharge-schema/test/fixtures/sale.json
	* @return mixed array for *.json, string for other
	*/
	function schemaRequest($fileName) {
		return self::parseIfJson(JsonSchemaFixture::request($fileName)."\n", $fileName);
	}

	/**
	* @param string $fileName e.g. "sale.json" for /vendor/hypercharge/hypercharge-schema/test/fixtures/sale.json
	* @return mixed array for *.json, string for other
	*/
	function schemaResponse($fileName) {
		return self::parseIfJson(JsonSchemaFixture::response($fileName)."\n", $fileName);
	}

	/**
	* @return string|array
	*/
	static function parseIfJson($str, $name) {
		if(preg_match('/\.json$/', $name)) return json_decode($str, true);
		return $str;
	}

	/**
	* @param string xml
	* @return array
	*/
	function parseXml($xml) {
		$dom = new \SimpleXMLElement($xml);
		return XmlSerializer::dom2hash($dom);
	}

	/**
	* sets $this->credentials to the part ($name) of credentials.json
	* you should use it in test setUp()
	* please do not confuse credentials name with Config:ENV_*
	* @param string $name  see first level in /test/credentials.json
	* @return object  { user:String, password:String }
	*/
	function credentials($name='sandbox') {
		$str = file_get_contents(__DIR__.'/credentials.json');
		$this->credentials = json_decode($str)->{$name};

		Config::set($this->credentials->user, $this->credentials->password, Config::ENV_SANDBOX);

		if($name == 'development') {
			$this->mockUrls();
		}
	}

	function mockUrls() {
		$mode = Config::getMode();

		$c = $this->credentials;
		$factory = m::mock('Hypercharge\Factory[createPaymentUrl,createTransactionUrl]');

		////////////
		// Payments

		// action = 'create'
		$url = m::mock('Hypercharge\PaymentUrl[getUrl]', array($mode));
		$url->shouldReceive('getUrl')->andReturn($c->paymentHost.'/payment');
		$factory->shouldReceive('createPaymentUrl')->with()->andReturn($url);

		foreach(array('cancel', 'void', 'capture', 'refund', 'reconcile') as $action) {
			$url = m::mock('Hypercharge\PaymentUrl[getUrl]', array($mode, $action));
			$url->shouldReceive('getUrl')->andReturn($c->paymentHost.'/payment');
			$factory->shouldReceive('createPaymentUrl')->with($action)->andReturn($url);
		}

		///////////////
		// Transactions
		//
		foreach(array('process', 'reconcile', 'reconcile/by_date') as $action) {
			$url = m::mock('Hypercharge\TransactionUrl[getUrl]', array($mode, $c->channelTokens->USD, $action));
			$url->shouldReceive('getUrl')->andReturn($c->gatewayHost);
			$factory->shouldReceive('createTransactionUrl')->with($c->channelTokens->USD, $action)->andReturn($url);
		}

		Config::setFactory($factory);
	}

	/**
	* mocks the network layer
	* @param int $times how often XmlWebservice::call is expected to be called
	* @return Mockery of Hypercharge\Curl
	*/
	function curlMock($times=1) {
		$curl = m::mock('Hypercharge\Curl');
		$factory = m::mock('Hypercharge\Factory[createHttpsClient]');
		$factory->shouldReceive('createHttpsClient')->times($times)->with('the user', 'the passw')->andReturn($curl);
		Config::setFactory($factory);
		Config::set('the user', 'the passw', Config::ENV_SANDBOX);
		Config::setIdSeparator(false);
		return $curl;
	}
}