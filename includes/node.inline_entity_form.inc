<?php

/**
 * @file
 * Defines the inline entity form controller for Nodes.
 */

class NodeInlineEntityFormController extends EntityInlineEntityFormController {

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
   * Overrides EntityInlineEntityFormController::entityForm().
   */
  public function entityForm($entity_form, &$form_state) {
    $node = $entity_form['#entity'];
    $type = node_type_get_type($node);

    // Do some prep work on the node, similarly to node_form().
    if (!isset($node->title)) {
      $node->title = NULL;
    }
    node_object_prepare($node);

    $entity_form['title'] = array(
      '#type' => 'textfield',
      '#title' => check_plain($type->title_label),
      '#required' => TRUE,
      '#default_value' => $node->title,
      '#maxlength' => 255,
      '#weight' => -5,
    );

    field_attach_form('node', $node, $entity_form, $form_state, LANGUAGE_NONE);

    return $entity_form;
  }

}
