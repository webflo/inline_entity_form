<?php

/**
 * @file
 * Defines the inline entity form controller for Nodes.
 */

namespace Drupal\inline_entity_form\Plugin\InlineEntityForm;

use \Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFormInterface;
use \Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;

/**
 * Node.
 *
 * @Plugin(
 *   id = "entity:node"
 * )
 */
class NodeInlineEntityFormController extends EntityInlineEntityFormController {

  /**
   * @var \Drupal\Core\Entity\EntityFormControllerInterface
   */
  protected $formController;

  /**
  * Overrides EntityInlineEntityFormController::labels().
   */
  public function labels() {
    $labels = array(
      'singular' => t('node'),
      'plural' => t('nodes'),
    );
    return $labels;
  }

  /**
   * Overrides EntityInlineEntityFormController::tableFields().
   */
  public function tableFields($bundles) {
    $fields = parent::tableFields($bundles);
    $fields['status'] = array(
      'type' => 'property',
      'label' => t('Status'),
      'weight' => 100,
    );

    return $fields;
  }

}
