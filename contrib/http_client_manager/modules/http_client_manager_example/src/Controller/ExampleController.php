<?php

namespace Drupal\http_client_manager_example\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\http_client_manager\Entity\HttpConfigRequest;
use Drupal\http_client_manager\HttpClientInterface;
use Drupal\http_client_manager_example\Response\FindPostsResponse;
use GuzzleHttp\Command\Result;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ExampleController.
 *
 * @package Drupal\http_client_manager_example\Controller
 */
class ExampleController extends ControllerBase {

  /**
   * JsonPlaceholder Http Client.
   *
   * @var \Drupal\http_client_manager\HttpClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(HttpClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('example_api.http_client')
    );
  }

  /**
   * Get Client.
   *
   * @return \Drupal\http_client_manager\HttpClientInterface
   *   The Http Client instance.
   */
  public function getClient() {
    return $this->httpClient;
  }

  /**
   * Find posts.
   *
   * @param int|NULL $postId
   *   The post Id.
   *
   * @return string
   *   The service response.
   */
  public function findPosts($postId = NULL) {
    $client = $this->getClient();
    $post_link = TRUE;
    $command = 'FindPosts';
    $params = [];

    if (!empty($postId)) {
      $post_link = FALSE;
      $command = 'FindPost';
      $params = ['postId' => $postId];
    }
    $response = $client->call($command, $params);

    if (!empty($postId)) {
      $response = [$postId => $response->toArray()];
    }

    $build = [];
    foreach ($response as $id => $post) {
      $build[$id] = $this->buildPostResponse($post, $post_link);
    }

    return $build;
  }

  /**
   * Build Post response.
   *
   * @param array $post
   *   The Post response item.
   * @param bool $post_link
   *   TRUE for a "Read more" link, otherwise "Back to list" link.
   *
   * @return array
   *   A render array of the post.
   */
  protected function buildPostResponse(array $post, $post_link) {
    $route = 'http_client_manager_example.find_posts';
    $link_text = $post_link ? $this->t('Read more') : $this->t('Back to list');
      $route_params = $post_link ? ['postId' => $post['id']] : [];

    $output = [
      '#type' => 'fieldset',
      '#title' => $post['id'] . ') ' . $post['title'],
      'body' => [
        '#markup' => '<p>' . $post['body'] . '</p>',
      ],
      'link' => [
        '#markup' => Link::createFromRoute($link_text, $route, $route_params)
          ->toString(),
      ]
    ];

    return $output;
  }

  /**
   * Create post.
   *
   * @return array
   *   The service response.
   */
  public function createPost() {
    if ($request = HttpConfigRequest::load('create_post')) {
      $response = '<pre>' . print_r($request->execute(), TRUE) . '</pre>';
    }
    else {
      $response = $this->t('Unable to load "create_post" configured request.');
    }

    return [
      '#type' => 'markup',
      '#markup' => $response,
    ];
  }

}
