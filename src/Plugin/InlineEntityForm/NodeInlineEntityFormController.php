<?php

/**
 * @file
 * Defines the inline entity form controller for Nodes.
 */

namespace Drupal\inline_entity_form\Plugin\InlineEntityForm;

use \Drupal\Component\Utility\NestedArray;
use \Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;

/**
 * Node.
 *
 * @Plugin(
 *   id = "node"
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

  /**
   * Overrides EntityInlineEntityFormController::entityForm().
   */
  public function entityForm($entity_form, &$form_state) {
    /**
     * @var \Drupal\Core\Entity\ContentEntityInterface $entity
     */
    $entity = $entity_form['#entity'];

    /*
    $entity_form += entity_get_form($entity, 'default', $form_state);
    */

    $this->formController = \Drupal::entityManager()->getFormObject($entity->getEntityTypeId(), 'default');
    $this->formController->setEntity($entity);

//    $form_display_id = $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . 'default';
//    $form_state['form_display'] = entity_load('entity_form_display', $form_display_id);

    $child_form_state = new FormState();
    $child_form_state->set('values',NestedArray::getValue($form_state['values'], $entity_form['#parents']));

//    $child_form_state['form_display'] = entity_load('entity_form_display', $form_display_id);

    $entity_form = $this->formController->buildForm($entity_form, $child_form_state);

    $form_state['ief_form_controller'] = $this->formController;
//    $form_state['ief_entity_form_display'] = entity_load('entity_form_display', $form_display_id);

    return $entity_form;

    /*
    $node = $entity_form['#entity'];
    $type = node_type_load($node->bundle());
    $extra_fields = field_info_extra_fields('node', $node->bundle(), 'form');

    // Do some prep work on the node, similarly to node_form().
    if (!isset($node->title)) {
      $node->title = NULL;
    }

//    node_object_prepare($node);

    $entity_form['title'] = array(
      '#type' => 'textfield',
      '#title' => check_plain($type->title_label),
      '#required' => TRUE,
      '#default_value' => $node->title->value,
      '#maxlength' => 255,
      // The label might be missing if the Title module has replaced it.
      '#weight' => !empty($extra_fields['title']) ? $extra_fields['title']['weight'] : -5,
    );
    $entity_form['status'] = array(
      '#type' => 'radios',
      '#access' => user_access('administer nodes'),
      '#title' => t('Status'),
      '#default_value' => $node->status->value,
      '#options' => array(1 => t('Published'), 0 => t('Unpublished')),
      '#required' => TRUE,
      '#weight' => 99,
    );

    $langcode = $node->language()->id;
    field_attach_form($node, $entity_form, $form_state, $langcode);

    return $entity_form;
    */
  }

  /**
   * Overrides EntityInlineEntityFormController::entityFormSubmit().
   */
  public function entityFormSubmit(&$entity_form, &$form_state) {
    $entity = $entity_form['#entity'];
    $operation = 'default';

    $child_form['#entity'] = $entity;

    $child_form_state = new FormState();
    $controller = \Drupal::entityManager()->getFormObject($entity->getEntityTypeId(), $operation);
    $controller->setEntity($entity);
    $child_form_state['build_info']['callback_object'] = $controller;
    $child_form_state['build_info']['base_form_id'] = $controller->getBaseFormID();
    $child_form_state['build_info']['args'] = array();

    $child_form_state['values'] = NestedArray::getValue($form_state['values'], $entity_form['#parents']);
    $child_form_state['values']['menu'] = array();
    $child_form_state['buttons'] = array();

    $this->formController = \Drupal::entityManager()->getFormController($entity->getEntityTypeId(), 'default');
    $this->formController->setEntity($entity);
    $child_form = $this->formController->buildForm($child_form, $child_form_state);

    $entity_form['#entity'] = $this->formController->submit($child_form, $child_form_state);
    $debug = TRUE;

    /*
    parent::entityFormSubmit($entity_form, $form_state);
    */


    /*
    parent::entityFormSubmit($entity_form, $form_state);

    $child_form_state = form_state_defaults();
    $child_form_state['values'] = NestedArray::getValue($form_state['values'], $entity_form['#parents']);

    $node = $entity_form['#entity'];
    $node->validated = TRUE;
    foreach (\Drupal::moduleHandler()->getImplementations('node_submit') as $module) {
      $function = $module . '_node_submit';
      $function($node, $entity_form, $child_form_state);
    }
    */
  }

}
