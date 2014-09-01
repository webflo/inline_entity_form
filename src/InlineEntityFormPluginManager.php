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

class InlineEntityFormPluginManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, LanguageManager $language_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/InlineEntityForm', $namespaces, $module_handler);
    $this->setCacheBackend($cache_backend, $language_manager, 'inline_entity_form_plugins');
  }

} 
