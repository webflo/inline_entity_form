<?php

/**
 * Contains \Drupal\inline_entity_form\InlineEntityForm\NodeInlineEntityFormHandler.
 */

namespace Drupal\inline_entity_form\InlineEntityForm;

/**
 * Node inline form handler.
 */
class NodeInlineEntityFormHandler extends EntityInlineEntityFormHandler {

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
