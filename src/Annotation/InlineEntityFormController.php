<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Annotation\InlineEntityFormController.
 */

namespace Drupal\inline_entity_form\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an inline entity form controller annotation object.
 *
 * @see hook_inline_entity_form_controller_info_alter()
 *
 * @Annotation
 */
class InlineEntityFormController extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the controller.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * Name of deriver class.
   *
   * @var string
   */
  public $deriver;

}
