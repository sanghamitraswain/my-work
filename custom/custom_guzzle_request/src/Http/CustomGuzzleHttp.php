<?php

namespace Drupal\custom_guzzle_request\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Http\Message\RequestInterface;

/** 
 * Get a response code from any URL using Guzzle in Drupal 8!
 * 
 * Usage: 
 * In the head of your document:
 * 
 * use Drupal\custom_guzzle_request\Http\CustomGuzzleHttp;
 * 
 * In the area you want to return the result, using any URL for $url:
 *
 * $check = new CustomGuzzleHttp();
 * $response = $check->performRequest($url);
 *  
 **/

class CustomGuzzleHttp {
  use StringTranslationTrait;
  
  public function performRequest($requestUrl) {
    $client = new \GuzzleHttp\Client();
    try {
			$response = $client->request('GET', $requestUrl);
			$response = (string) $response->getBody();
			//$response = $request->getBody()->getContents();  
			//print_r($response);exit;
			return $response;
    } catch (RequestException $e) {
      return($this->t('Error'));
    }

  }
}