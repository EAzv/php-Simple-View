<?php

use PHPUnit\Framework\TestCase;
require "vendor/autoload.php";

/**
*
* @requires PHP 5.6
* @author Eduardo Azevedo eduh.azvdo@gmail.com
*
* @coversDefaultClass SimpleView/SimpleView
*/
class SimpleViewTest extends TestCase
{
	const DS = DIRECTORY_SEPARATOR;
	const FOLDER = __DIR__ . self::DS;
	const FILES = self::FOLDER . 'files' . self::DS;

	//
	private $view;

	public function setUp ()
	{
		$this->view = new SimpleView\SimpleView([
				'folder' => self::FILES,
				'assets' => array(
					'pre_def' => '/paranoid/',
				),
			]);
	}

	public function testBasic ()
	{
		$raw = file_get_contents(self::FILES.'basic.html');
		$result = $this->view->read('basic'.self::DS.'basic');
		$this->assertEquals($raw, $result, "Simple file");
	}

	public function testAssets ()
	{
		$this->view->set('assets_variable', 'Assets Test')
			->config('assets', [
									'str_arg' => '/weird/',
									'one_arg' => ['/freak/'],
									'two_arg' => ['/evil/','?worm?']
								]);
		$raw = file_get_contents(self::FILES.'assets.html');
		$result = $this->view->read('assets'.self::DS.'test');
		$this->assertEquals($raw, $result, "Assets test");
	}

	public function testOneLevelWrap ()
	{
		$raw = file_get_contents(self::FILES.'one_level_wrap.html');
		$result = $this->view->read('one_level_wrap'.self::DS.'content');
		$this->assertEquals($raw, $result, "One level wrap");
	}

	public function testTwoLevelWrap ()
	{
		$raw = file_get_contents(self::FILES.'two_level_wrap.html');
		$result = $this->view->read('two_level_wrap'.self::DS.'layer');
		$this->assertEquals($raw, $result, "Two levels wrap");
	}


}
