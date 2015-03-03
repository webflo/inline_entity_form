<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\InlineEntityFormPluginManager.
 */

namespace Drupal\inline_entity_form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for inline entity form controllers.
 */
class InlineEntityFormPluginManager extends DefaultPluginManager {

  /**
   * Constructs a InlineEntityFormPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The 'field type' plugin manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, LanguageManager $language_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/InlineEntityForm', $namespaces, $module_handler, 'Drupal\inline_entity_form\InlineEntityFormControllerInterface', 'Drupal\inline_entity_form\Annotation\InlineEntityFormController');

    $this->setCacheBackend($cache_backend, 'inline_entity_form_plugins');
    $this->alterInfo('inline_entity_form_controller_info');
  }

}
