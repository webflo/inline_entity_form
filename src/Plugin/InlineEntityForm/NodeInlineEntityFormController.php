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
 * Node inline form controller.
 *
 * @InlineEntityFormController(
 *   id = "entity:node",
 *   label = "Node inline form",
 * )
 */
class NodeInlineEntityFormController extends EntityInlineEntityFormController {

  /**
   * {@inheritdoc}
   */
  public function labels() {
    $labels = [
      'singular' => t('node'),
      'plural' => t('nodes'),
    ];
    return $labels;
  }

  /**
   * {@inheritdoc}
   */
  public function tableFields($bundles) {
    $fields = parent::tableFields($bundles);
    $fields['status'] = [
      'type' => 'property',
      'label' => t('Status'),
      'weight' => 100,
    ];

    return $fields;
  }

}
