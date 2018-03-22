<?php

/**
 * @file
 * Contains \Drupal\mymodule\Controller\MymoduleController.
 */
namespace Drupal\mymodule\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\custom_guzzle_request\Http\CustomGuzzleHttp;

/**
 * Controller.
 */
class MymoduleController extends ControllerBase {

  /**
   * Callback function to get the data from webservice
   */
  public function getResponse() {
		$url = 'http://www.webservicex.com/globalweather.asmx?op=GetWeather';
		//$url = 'http://jsonplaceholder.typicode.com/posts/1';
		$check = new CustomGuzzleHttp();
    $response = $check->performRequest($url);
    if ($response) {
      $result = json_decode($response);
      $data = array();
      $data['title'] = 'sample response';
      $data['info'] = (array)$result;
    }
		dpm($data);
		return $data;
  }
}









