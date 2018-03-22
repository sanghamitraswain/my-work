<?php

namespace Drupal\http_client_manager;

use Drupal\Component\Discovery\YamlDiscovery;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\Controller\ControllerResolver;

/**
 * Class HttpServiceApiHandler.
 *
 * @package Drupal\http_client_manager
 */
class HttpServiceApiHandler implements HttpServiceApiHandlerInterface {

  /**
   * Drupal root.
   *
   * @var string
   */
  protected $root;

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;
  /**
   * Drupal\Core\StringTranslation\TranslationManager definition.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $stringTranslation;
  /**
   * Drupal\Core\Controller\ControllerResolver definition.
   *
   * @var \Drupal\Core\Controller\ControllerResolver
   */
  protected $controllerResolver;

  /**
   * All defined services api descriptions.
   *
   * @var array
   */
  protected $servicesApi;

  /**
   * The HTTP Client Manager config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * HttpServiceApiHandler constructor.
   *
   * @param string $root
   *   The Application root.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler service.
   * @param \Drupal\Core\StringTranslation\TranslationManager $string_translation
   *   The string translation manager.
   * @param \Drupal\Core\Controller\ControllerResolver $controller_resolver
   *   The controller resolver service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct($root, ModuleHandler $module_handler, TranslationManager $string_translation, ControllerResolver $controller_resolver, ConfigFactoryInterface $config_factory) {
    $this->root = $root;
    $this->moduleHandler = $module_handler;
    $this->stringTranslation = $string_translation;
    $this->controllerResolver = $controller_resolver;
    $this->config = $config_factory->get('http_client_manager.settings');
    $this->servicesApi = $this->getServicesApi();
  }

  /**
   * Gets the YAML discovery.
   *
   * @return \Drupal\Component\Discovery\YamlDiscovery
   *   The YAML discovery.
   */
  protected function getYamlDiscovery() {
    if (!isset($this->yamlDiscovery)) {
      $this->yamlDiscovery = new YamlDiscovery('http_services_api', $this->moduleHandler->getModuleDirectories());
    }
    return $this->yamlDiscovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getServicesApi() {
    if (empty($this->servicesApi)) {
      $this->buildServicesApiYaml();
    }
    return $this->servicesApi;
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    if (empty($this->servicesApi[$id])) {
      $message = sprintf('Undefined Http Service Api id "%s"', $id);
      throw new \InvalidArgumentException($message);
    }
    return $this->servicesApi[$id];
  }

  /**
   * {@inheritdoc}
   */
  public function moduleProvidesApi($module_name) {
    $servicesApi = $this->getServicesApi();
    foreach ($servicesApi as $serviceApi) {
      if ($serviceApi['provider'] == $module_name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Builds all services api provided by .http_services_api.yml files.
   *
   * @return array[]
   *   Each return api is an array with the following keys:
   *   - id: The machine name of the Service Api.
   *   - title: The human-readable name of the API.
   *   - api_path: The Guzzle description path (relative to module directory).
   *   - base_url: The Service API base url.
   *   - provider: The provider module of the Service Api.
   *   - source: The absolute path to the Service API description file.
   *   - config: An array of additional configurations for the HttpClient class.
   *
   * @code
   * example_service:
   *   title: "Example Service"
   *   api_path: src/HttpService/example_service.json
   *   base_url: "http://www.example.com/api/v1"
   *   config:
   *     command.params:
   *       command.request_options:
   *         timeout: 4
   *         connect_timeout: 3
   * @endcode
   */
  protected function buildServicesApiYaml() {
    $this->servicesApi = array();
    $items = $this->getYamlDiscovery()->findAll();

    foreach ($items as $provider => $servicesApi) {
      $module_path = $this->moduleHandler->getModule($provider)->getPath();

      foreach ($servicesApi as $id => $serviceApi) {
        $this->overrideServiceApiDefinition($id, $serviceApi);
        $this->validateServiceApiDefinition($id, $serviceApi);
        $default = [
          'id' => $id,
          'provider' => $provider,
          'source' => $this->root . '/' . $module_path . '/' . $serviceApi['api_path'],
          'config' => [],
        ];
        $this->servicesApi[$id] = array_merge($default, $serviceApi);
      }
    }
  }

  /**
   * Override Service API definition.
   *
   * Checks for overriding configurations in settings.php for the given Service
   * API Definition.
   *
   * @param string $id
   *   The service api id.
   * @param array $serviceApi
   *   An array of service api definition.
   */
  protected function overrideServiceApiDefinition($id, array &$serviceApi) {
    $settings = Settings::get('http_services_api', []);
    if (empty($settings[$id]) || !$this->config->get('enable_overriding_service_definitions')) {
      return;
    }

    $overrides = array_flip(self::getOverridableProperties());
    $settings[$id] = array_intersect_key($settings[$id], $overrides);
    $serviceApi = array_replace_recursive($serviceApi, $settings[$id]);
  }

  /**
   * Get overridable Service API properties.
   *
   * @return array
   *   An array containing a list of overridable property names.
   */
  public static function getOverridableProperties() {
    return [
      'title',
      'api_path',
      'config',
    ];
  }

  /**
   * Validates Service api definition.
   *
   * @param string $id
   *   The service api id.
   * @param array $serviceApi
   *   An array of service api definition.
   *
   * @return bool
   *   Whether or not the api is valid.
   */
  protected function validateServiceApiDefinition($id, array $serviceApi) {
    foreach (self::getOverridableProperties() as $property) {
      if (!isset($serviceApi[$property])) {
        $message = sprintf('Missing required parameter "%s" in "%s" service api definition', $property, $id);
        throw new \RuntimeException($message);
      }
    }
  }

  /**
   * Returns all module names.
   *
   * @return string[]
   *   Returns the human readable names of all modules keyed by machine name.
   */
  protected function getModuleNames() {
    $modules = array();
    foreach (array_keys($this->moduleHandler->getModuleList()) as $module) {
      $modules[$module] = $this->moduleHandler->getName($module);
    }
    asort($modules);
    return $modules;
  }

}
