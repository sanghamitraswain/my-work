<?php

namespace Drupal\http_client_manager;


use Drupal\http_client_manager\Event\HttpClientEvents;
use Drupal\http_client_manager\Event\HttpClientHandlerStackEvent;
use Guzzle\Service\Loader\JsonLoader;
use Guzzle\Service\Loader\PhpLoader;
use Guzzle\Service\Loader\YamlLoader;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\HandlerStack;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class HttpClient implements HttpClientInterface {

  /**
   * The name of the service api of this http client instance.
   *
   * @var string
   */
  protected $serviceApi;

  /**
   * Description definition.
   *
   * @var \GuzzleHttp\Command\Guzzle\Description
   */
  protected $description;

  /**
   * The Http Service Api Handler service.
   *
   * @var HttpServiceApiHandler
   */
  protected $apiHandler;

  /**
   * An array containing the Http Service Api description.
   *
   * @var array
   */
  protected $api;

  /**
   * An array containing api source path info.
   *
   * @var array
   */
  protected $apiSourceInfo;

  /**
   * Guzzle Client definition.
   *
   * @var \GuzzleHttp\Command\Guzzle\GuzzleClient
   */
  protected $client;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * An array containing all the Guzzle commands.
   *
   * @var array
   */
  protected $commands;

  /**
   * The file locator used to find the service descriptions.
   *
   * @var \Symfony\Component\Config\FileLocator
   */
  protected $fileLocator;

  /**
   * The file loader used to load the service descriptions.
   *
   * @var \Guzzle\Service\Loader\FileLoader
   */
  protected $fileLoader;

  /**
   * Constructs an HttpClient object
   *
   * @param string $serviceApi
   *   The service api name for this instance.
   * @param \Drupal\http_client_manager\HttpServiceApiHandlerInterface $apiHandler
   *   The service api handler instance.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher instance.
   */
  public function __construct($serviceApi, HttpServiceApiHandlerInterface $apiHandler, EventDispatcherInterface $event_dispatcher) {
    $this->serviceApi = $serviceApi;
    $this->apiHandler = $apiHandler;
    $this->api = $this->apiHandler->load($this->serviceApi);
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getApi() {
    return $this->api;
  }

  /**
   * Get Api source path info.
   *
   * @return array
   *   An array containing api source path info.
   */
  protected function getApiSourceInfo() {
    if (empty($this->apiSourceInfo)) {
      $this->setApiSourceInfo();
    }
    return $this->apiSourceInfo;
  }

  /**
   * Set Api source path info.
   */
  protected function setApiSourceInfo() {
    $this->apiSourceInfo = pathinfo($this->api['source']);
  }

  /**
   * Get Client.
   *
   * @return \GuzzleHttp\Command\Guzzle\GuzzleClient
   *   The Configured Guzzle client instance.
   */
  protected function getClient() {
    if (empty($this->client)) {
      $this->setupGuzzleClient();
    }
    return $this->client;
  }

  /**
   * Setup Guzzle Client from *.http_services_api.yml files.
   */
  private function setupGuzzleClient() {
    $client = new Client($this->getClientConfig());
    $this->client = new GuzzleClient($client, $this->loadServiceDescription());
  }

  /**
   * {@inheritdoc}
   */
  public function getClientConfig() {
    $api = $this->getApi();
    $config = !empty($api['config']) ? $api['config'] : [];

    $config['handler'] = HandlerStack::create();
    $event = new HttpClientHandlerStackEvent($config['handler'], $this->serviceApi);
    $this->eventDispatcher->dispatch(HttpClientEvents::HANDLER_STACK, $event);

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  protected function loadServiceDescription() {
    if (empty($this->description)) {
      $api = $this->getApi();
      $source = $this->getApiSourceInfo();
      $loader = $this->getFileLoader();
      $locator = $this->getFileLocator();

      $description = $loader->load($locator->locate($source['basename']));
      $description['baseUrl'] = $api['config']['base_uri'];
      $this->description = new Description($description);
    }
    return $this->description;
  }

  /**
   * Get File Locator.
   *
   * @return \Symfony\Component\Config\FileLocator
   *   The file locator used to find the service descriptions.
   */
  protected function getFileLocator() {
    if (empty($this->fileLocator)) {
      $this->initFileLocator();
    }
    return $this->fileLocator;
  }

  /**
   * Set File Locator.
   */
  protected function initFileLocator() {
    $source = $this->getApiSourceInfo();
    $this->fileLocator = new FileLocator($source['dirname']);
  }

  /**
   * Get File Loader.
   *
   * @return \Guzzle\Service\Loader\FileLoader
   *   The file loader used to load the service descriptions.
   */
  protected function getFileLoader() {
    if (empty($this->fileLoader)) {
      $this->initFileLoader();
    }
    return $this->fileLoader;
  }

  /**
   * Set File Loader.
   */
  protected function initFileLoader() {
    $source = $this->getApiSourceInfo();
    $locator = $this->getFileLocator();

    switch ($source['extension']) {
      case 'json':
        $loader = new JsonLoader($locator);
        break;

      case 'yml':
        $loader = new YamlLoader($locator);
        break;

      case 'php':
        $loader = new PhpLoader($locator);
        break;

      default:
        $allowed_extensions = ['json', 'yml', 'php'];
        $message = sprintf('Invalid HTTP Services Api source provided: "%s". ', $source['filename']);
        $message .= sprintf('File extension must be one of %s.', implode(', ', $allowed_extensions));
        throw new \RuntimeException($message);
    }

    $this->fileLoader = $loader;
  }

  /**
   * {@inheritdoc}
   */
  public function getCommands() {
    if (!empty($this->commands)) {
      return $this->commands;
    }

    $description = $this->getClient()->getDescription();
    $command_names = array_keys($description->getOperations());
    $this->commands = [];

    foreach ($command_names as $command_name) {
      $this->commands[$command_name] = $description->getOperation($command_name);
    }
    return $this->commands;
  }

  /**
   * {@inheritdoc}
   */
  public function getCommand($commandName) {
    if (!empty($this->commands[$commandName])) {
      return $this->commands[$commandName];
    }
    return $this->getClient()->getDescription()->getOperation($commandName);
  }

  /**
   * {@inheritdoc}
   */
  public function call($commandName, array $params = []) {
    $client = $this->getClient();
    $command = $client->getCommand($commandName, $params);
    return $client->execute($command);
  }

  /**
   * Magic method implementation for commands execution.
   *
   * @param string $name
   *  The Guzzle command name.
   * @param array $arguments
   *  The Guzzle command parameters array.
   *
   * @return \GuzzleHttp\Command\ResultInterface|mixed
   *   The Guzzle Command execution result.
   *
   * @see HttpClientInterface::call
   */
  public function __call($name, array $arguments = []) {
    $params = !empty($arguments[0]) ? $arguments[0] : [];
    return $this->call($name, $params);
  }

}
