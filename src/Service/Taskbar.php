<?php

namespace Drupal\taskbar\Service;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Link;
use Drupal\Component\Utility\SortArray;

/**
 * Taskbar.
 */
class Taskbar implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The admin context.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The local tasks manager.
   *
   * @var \Drupal\Core\Menu\LocalTaskManager
   */
  protected $localTaskManager;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * ToolbarHandler.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The admin context.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $routeMatch
   *   The route match.
   * @param \Drupal\Core\Menu\LocalTaskManager $localTaskManager
   *   The local tasks manager.
   * @param \Drupal\Core\Access\AccessManagerInterface $accessManager
   *   The access manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function __construct(RequestStack $request, ConfigFactoryInterface $config, AdminContext $adminContext, CurrentRouteMatch $routeMatch, LocalTaskManager $localTaskManager, AccessManagerInterface $accessManager, AccountInterface $account) {
    $this->request = $request;
    $this->config = $config;
    $this->adminContext = $adminContext;
    $this->routeMatch = $routeMatch;
    $this->localTaskManager = $localTaskManager;
    $this->accessManager = $accessManager;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('router.admin_context'),
      $container->get('current_route_match'),
      $container->get('plugin.manager.menu.local_task'),
      $container->get('access_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildTaskBar() {
    $items = [];

    $settings = $this->config->get('taskbar.settings');
    if ($settings->get('local_tasks_active')) {
      $items['local_tasks'] = $this->buildLocalTasks($this->t('Primary Links'), 0);
      $items['local_tasks_secondary'] = $this->buildLocalTasks($this->t('Secondary Links'), 1);
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  private function buildLocalTasks($title, $level) {
    $currentRequest = $this->request->getCurrentRequest();
    $node = $currentRequest->attributes->get('node');
    if (!$node) {
      return [];
    }

    $route = $this->routeMatch->getRouteObject();
    $isAdmin = $this->adminContext->isAdminRoute($route);
    if ($isAdmin) {
      return [];
    }

    if (empty($this->getLocalTasksFromRoute($level))) {
      return [];
    }

    return [
      '#type' => 'toolbar_item',
      '#weight' => 150,
      '#wrapper_attributes' => [
        'class' => [
          'local-task-toolbar-tab',
        ],
      ],
      'tab' => [
        '#type' => 'link',
        '#title' => $title,
        '#url' => Url::fromRoute('system.admin'),
        '#attributes' => [
          'title' => $title,
          'data-drupal-subtrees' => TRUE,
          'class' => [
            'toolbar-icon',
            'toolbar-icon-links',
          ],
        ],
        '#options' => [
          'attributes' => [
            'title' => $title,
          ],
        ],
      ],
      'tray' => [
        '#heading' => t('Primary Links'),
        'local_tasks' => [
          '#theme' => 'item_list',
          '#items' => $this->getLocalTasksFromRoute($level),
          '#attributes' => [
            'class' => [
              'toolbar-menu',
              'toolbar-links-menu',
              'toolbar-links-menu-main',
            ],
          ],
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Get local task links for the route.
   *
   * @param int $level
   *   The level of links to get.
   *
   * @return \Drupal\core\Link[]
   *   An array on Link objects.
   */
  private function getLocalTasksFromRoute($level) {
    // Get the current route.
    $currentRoute = $this->routeMatch->getRouteName();

    // Get the local tasks for the current route.
    $localTasks = $this->localTaskManager->getLocalTasks($currentRoute, $level);

    // Get links for local tasks.
    $links = $this->getLocalTaskAsLinks($localTasks);

    return $links;
  }

  /**
   * Get the link objects.
   *
   * @param array $localTasks
   *   An array of local tasks.
   *
   * @return \Drupal\core\Link[]
   *   An array of Link objects.
   */
  private function getLocalTaskAsLinks(array $localTasks) {
    $links = [];

    // Sort them by weight.
    uasort($localTasks['tabs'], [SortArray::class, 'sortByWeightProperty']);

    foreach ($localTasks['tabs'] as $task) {

      /** @var \Drupal\Core\Url $url */
      $url = $task['#link']['url'];
      $title = $task['#link']['title'];

      // Only include tasks which current user is allowed to access.
      $hasAccess = $this->accessManager->checkNamedRoute($url->getRouteName(), $url->getRouteParameters(), $this->account);

      if ($hasAccess) {
        $links[] = Link::fromTextAndUrl($title, $url);
      }
    }

    return $links;
  }

}
