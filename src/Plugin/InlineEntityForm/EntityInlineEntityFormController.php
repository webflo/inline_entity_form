<?php

/**
 * @file
 * Defines the base inline entity form controller.
 */

namespace Drupal\inline_entity_form\Plugin\InlineEntityForm;

use Drupal\Component\Utility\NestedArray;
use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;

/**
 * Generic entity inline form.
 *
 * @Plugin(
 *   id = "entity",
 *   deriver = "Drupal\inline_entity_form\Plugin\Deriver\EntityInlineEntityForm",
 * )
 *
 * @see \Drupal\inline_entity_form\Plugin\Deriver\EntityInlineEntityForm
 */
class EntityInlineEntityFormController {

  protected $entityType;
  public $settings;

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    list(, $this->entityType) = explode(':', $plugin_id, 2);
    $this->settings = $configuration + $this->defaultSettings();
  }

  /**
   * Returns an array of css filepaths for the current entity type, keyed
   * by theme name.
   *
   * If provided, the "base" CSS file is included for all themes.
   * If a CSS file matching the current theme exists, it will also be included.
   *
   * @code
   * return array(
   *   'base' => drupal_get_path('module', 'test_module') . '/css/inline_entity_form.base.css',
   *   'seven' => drupal_get_path('module', 'test_module') . '/css/inline_entity_form.seven.css',
   * );
   * @endcode
   */
  public function css() {
    return array();
  }

  /**
   * Returns an array of entity type labels (singular, plural) fit to be
   * included in the UI text.
   */
  public function defaultLabels() {
    $labels = array(
      'singular' => t('entity'),
      'plural' => t('entities'),
    );

    return $labels;

    $info = \Drupal::entityManager()->getDefinition($this->entityType);
    // Commerce and its contribs declare permission labels that can be used
    // for more precise and user-friendly strings.
    if (!empty($info['permission labels'])) {
      $labels = $info['permission labels'];
    }

    return $labels;
  }

  public function labels() {
    $labels = $this->defaultLabels();

    // The admin has specified the exact labels that should be used.
    if ($this->settings['override_labels']) {
      $labels = array(
        'singular' => $this->settings['label_singular'],
        'plural' => $this->settings['label_plural'],
      );
    }

    return $labels;
  }

  /**
   * Returns an array of fields used to represent an entity in the IEF table.
   *
   * The fields can be either Field API fields or properties defined through
   * hook_entity_property_info().
   *
   * Modules can alter the output of this method through
   * hook_inline_entity_form_table_fields_alter().
   *
   * @param $bundles
   *   An array of allowed bundles for this widget.
   *
   * @return
   *   An array of field information, keyed by field name. Allowed keys:
   *   - type: 'field' or 'property',
   *   - label: Human readable name of the field, shown to the user.
   *   - weight: The position of the field relative to other fields.
   *   Special keys for type 'field', all optional:
   *   - formatter: The formatter used to display the field, or "hidden".
   *   - settings: An array passed to the formatter. If empty, defaults are used.
   *   - delta: If provided, limits the field to just the specified delta.
   */
  public function tableFields($bundles) {
    $info = \Drupal::entityManager()->getDefinition($this->entityType);
    // $metadata = \Drupal::entityManager()->getFieldDefinitions($this->entityType);
    $metadata = array();

    $fields = array();
    if ($info->hasKey('label')) {
      $label_key = $info->getKey('label');
      $fields[$label_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$label_key]['label'] : t('Label'),
        'weight' => 1,
      );
    }
    else {
      $id_key = $info->getKey('id');
      $fields[$id_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$id_key]['label'] : t('ID'),
        'weight' => 1,
      );
    }
    if (count($bundles) > 1) {
      $bundle_key = $info->getKey('bundle');
      $fields[$bundle_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$bundle_key]['label'] : t('Type'),
        'weight' => 2,
      );
    }

    return $fields;
  }

  /**
   * Returns a setting value.
   *
   * @param $name
   *   The name of the setting value to return.
   *
   * @return
   *   A setting value.
   */
  public function getSetting($name) {
    return $this->settings[$name];
  }

  /**
   * Returns an array of default settings in the form of key => value.
   */
  public function defaultSettings() {
    $defaults = array();
    $defaults['allow_existing'] = FALSE;
    $defaults['match_operator'] = 'CONTAINS';
    $defaults['delete_references'] = FALSE;
    $defaults['override_labels'] = FALSE;
    $defaults['label_singular'] = '';
    $defaults['label_plural'] = '';

    return $defaults;
  }

  /**
   * Returns the entity type managed by this controller.
   *
   * @return
   *   The entity type.
   */
  public function entityType() {
    return $this->entityType;
  }

  /**
   * Returns the entity form to be shown through the IEF widget.
   *
   * When adding data to $form_state it should be noted that there can be
   * several IEF widgets on one master form, each with several form rows,
   * leading to possible key collisions if the keys are not prefixed with
   * $entity_form['#parents'].
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function entityForm($entity_form, FormStateInterface $form_state) {
    /**
     * @var \Drupal\Core\Entity\EntityInterface $entity
     */
    $entity = $entity_form['#entity'];
    $operation = 'default';

    $child_form_state = new Drupal\Core\Form\FormState();
    $controller = \Drupal::entityManager()->getFormObject($entity->getEntityTypeId(), $operation);
    $controller->setEntity($entity);
    $child_form_state->addBuildInfo('callback_object', $controller);
    $child_form_state->addBuildInfo('base_form_id', $controller->getBaseFormID());
    $child_form_state->addBuildInfo('form_id', $controller->getFormID());
    $child_form_state->addBuildInfo('args', array());

    // Copy values to child form.
    $child_form_state->setUserInput($form_state->getUserInput());
    $child_form_state->setValues($form_state->getValues());
    $child_form_state->setStorage($form_state->getStorage());

    $child_form_state->set('form_display', entity_load('entity_form_display', $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $operation));

    // Since some of the submit handlers are run, redirects need to be disabled.
    $child_form_state->set('no_redirect', TRUE);

    // When a form is rebuilt after Ajax processing, its #build_id and #action
    // should not change.
    // @see drupal_rebuild_form()
    $rebuild_info = $child_form_state->getRebuildInfo();
    $rebuild_info['copy']['#build_id'] = TRUE;
    $rebuild_info['copy']['#action'] = TRUE;
    $child_form_state->setRebuildInfo($rebuild_info);

    $child_form_state->set('inline_entity_form', $form_state->get('inline_entity_form'));
    $child_form_state->set('langcode', $entity->langcode->value);

    $child_form_state->set('field', $form_state->get('field'));
    $child_form_state->setTriggeringElement($form_state->getTriggeringElement());
    $child_form_state->setSubmitHandlers($form_state->getSubmitHandlers());

    $entity_form['#ief_parents'] = $entity_form['#parents'];

    $entity_form = $controller->buildForm($entity_form, $child_form_state);

    foreach ($child_form_state->get('inline_entity_form') as $id => $data) {
      $form_state->set(['inline_entity_form', $id], $data);
    }

    $form_state->set('field', $child_form_state->get('field'));
    return $entity_form;
  }

  /**
   * Validates the entity form.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function entityFormValidate($entity_form, &$form_state) {
    /*
    $info = \Drupal::entityManager()->getDefinition($this->entityType);
    $entity = $entity_form['#entity'];
    $form_state['form_display']->validateFormValues($entity, $entity_form, $form_state);
    */
  }

  /**
   * Handles the submission of an entity form.
   *
   * Prepares the entity stored in $entity_form['#entity'] for saving by copying
   * the values from the form to matching properties and, if the entity is
   * fieldable, invoking Field API submit.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function entityFormSubmit(&$entity_form, FormStateInterface $form_state) {
    /**
     * @var \Drupal\Core\Entity\EntityInterface $entity
     */
    $entity = $entity_form['#entity'];
    $operation = 'default';

    $child_form = $entity_form;

    $child_form_state = new FormState();
//    $child_form_state->set('values', NestedArray::getValue($form_state['values'], $entity_form['#parents']));

    $controller = \Drupal::entityManager()->getFormObject($entity->getEntityTypeId(), $operation);
    $controller->setEntity($entity);

    $child_form_state->addBuildInfo('callback_object', $controller);
    $child_form_state->addBuildInfo('base_form_id', $controller->getBaseFormID());
    $child_form_state->addBuildInfo('form_id', $controller->getFormID());
    $child_form_state->addBuildInfo('args', array());

    // Copy values to child form.
    $child_form_state->setUserInput($form_state->getUserInput());
    $child_form_state->setValues($form_state->getValues());
    $child_form_state->setStorage($form_state->getStorage());

    $child_form_state->set('form_display', entity_get_form_display($entity->getEntityTypeId(), $entity->bundle(), $operation));

    // Since some of the submit handlers are run, redirects need to be disabled.
    $child_form_state->disableRedirect();

    // When a form is rebuilt after Ajax processing, its #build_id and #action
    // should not change.
    // @see drupal_rebuild_form()
    $rebuild_info = $child_form_state->getRebuildInfo();
    $rebuild_info['copy']['#build_id'] = TRUE;
    $rebuild_info['copy']['#action'] = TRUE;
    $child_form_state->setRebuildInfo($rebuild_info);

    $child_form_state->set('inline_entity_form', $form_state->get('inline_entity_form'));
    $child_form_state->set('langcode', $entity->langcode->value);

    $child_form_state->set('field', $form_state->get('field'));
    $child_form_state->setTriggeringElement($form_state->getTriggeringElement());
    $child_form_state->setSubmitHandlers($form_state->getSubmitHandlers());

    $child_form['#ief_parents'] = $entity_form['#parents'];

    $controller->submitForm($child_form, $child_form_state);
    $controller->save($child_form, $child_form_state);
    $entity_form['#entity'] = $controller->getEntity();

    foreach ($child_form_state->get('inline_entity_form') as $id => $data) {
      $form_state->set(['inline_entity_form', $id], $data);
    }
  }

  /**
   * Cleans up the form state for each field.
   *
   * After field_attach_submit() has run and the entity has been saved, the form
   * state still contains field data in $form_state['field']. Unless that
   * data is removed, the next form with the same #parents (reopened add form,
   * for example) will contain data (i.e. uploaded files) from the previous form.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  protected function cleanupFieldFormState($entity_form, &$form_state) {
    $bundle = $entity_form['#entity']->bundle();
    /**
     * @var \Drupal\field\Entity\FieldInstanceConfig[] $instances
     */
    $instances = field_info_instances($entity_form['#entity_type'], $bundle);
    foreach ($instances as $instance) {
      $field_name = $instance->getFieldName();
      if (isset($entity_form[$field_name])) {
        $parents = $entity_form[$field_name]['#parents'];

        $field_state = WidgetBase::getWidgetState($parents, $field_name, $form_state);
        unset($field_state['items']);
        unset($field_state['entity']);
        $field_state['items_count'] = 0;
        WidgetBase::getWidgetState($parents, $field_name, $form_state, $field_state);
      }
    }
  }

  /**
   * Returns the remove form to be shown through the IEF widget.
   *
   * @param $remove_form
   *   The remove form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function removeForm($remove_form, &$form_state) {
    $entity = $remove_form['#entity'];
    $entity_id = $entity->id();
    $entity_label = $entity->label();

    $remove_form['message'] = array(
      '#markup' => '<div>' . t('Are you sure you want to remove %label?', array('%label' => $entity_label)) . '</div>',
    );
    if (!empty($entity_id) && $this->getSetting('allow_existing')) {
      $access = $entity->access('delete');
      if ($access) {
        $labels = $this->labels();
        $remove_form['delete'] = array(
          '#type' => 'checkbox',
          '#title' => t('Delete this @type_singular from the system.', array('@type_singular' => $labels['singular'])),
        );
      }
    }

    return $remove_form;
  }

  /**
   * Handles the submission of a remove form.
   * Decides what should happen to the entity after the removal confirmation.
   *
   * @param $remove_form
   *   The remove form.
   * @param $form_state
   *   The form state of the parent form.
   *
   * @return
   *   IEF_ENTITY_UNLINK or IEF_ENTITY_UNLINK_DELETE.
   */
  public function removeFormSubmit($remove_form, FormStateInterface $form_state) {
    $entity = $remove_form['#entity'];
    $entity_id = $entity->id();
    $form_values = NestedArray::getValue($form_state->getValues(), $remove_form['#parents']);
    // This entity hasn't been saved yet, we can just unlink it.
    if (empty($entity_id)) {
      return IEF_ENTITY_UNLINK;
    }
    // If existing entities can be referenced, the delete happens only when
    // specifically requested (the "Permanently delete" checkbox).
    if ($this->getSetting('allow_existing') && empty($form_values['delete'])) {
      return IEF_ENTITY_UNLINK;
    }

    return IEF_ENTITY_UNLINK_DELETE;
  }

  /**
   * Permanently saves the given entity.
   *
   * @param $entity
   *   The entity to save.
   * @param array $context
   *   Available keys:
   *   - parent_entity_type: The type of the parent entity.
   *   - parent_entity: The parent entity.
   */
  public function save(EntityInterface $entity, $context) {
    return $entity->save();
  }

  /**
   * Delete permanently saved entities.
   *
   * @param $ids
   *   An array of entity IDs.
   * @param array $context
   *   Available keys:
   *   - parent_entity_type: The type of the parent entity.
   *   - parent_entity: The parent entity.
   */
  public function delete($ids, $context) {
    entity_delete_multiple($this->entityType, $ids);
  }

  /**
   * @param $entity_form
   * @param $form_state
   * @param $entity
   * @param $operation
   * @return array
   */
  protected function buildChildFormState(&$entity_form, &$form_state, $entity, $operation) {
    $child_form_state = new FormState();
    $controller = \Drupal::entityManager()->getFormObject($entity->getEntityTypeId(), $operation);
    $controller->setEntity($entity);

    $child_form_state->addBuildInfo('callback_object', $controller);
    $child_form_state->addBuildInfo('base_form_id', $controller->getBaseFormID());
    $child_form_state->addBuildInfo('form_id', $controller->getFormID());
    $child_form_state->addBuildInfo('args', array());
    $child_form_state->set('form_display', entity_load('entity_form_display', $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $operation));

    // Since some of the submit handlers are run, redirects need to be disabled.
    $child_form_state->disableRedirect();

    // When a form is rebuilt after Ajax processing, its #build_id and #action
    // should not change.
    // @see drupal_rebuild_form()
    $rebuild_info = $child_form_state->getRebuildInfo();
    $rebuild_info['copy']['#build_id'] = TRUE;
    $rebuild_info['copy']['#action'] = TRUE;
    $child_form_state->setRebuildInfo($rebuild_info);

    // $child_form_state['values'] = NestedArray::getValue($form_state['values'], $entity_form['#parents']);
    // $child_form_state['#parents'] = array();
    $child_form_state->setValues($form_state->getValues());

    $child_form_state->setValue('menu', []);
    $child_form_state->setButtons([]);
    $child_form_state->set('inline_entity_form', $form_state->get('inline_entity_form'));
    $child_form_state->set('langcode', $entity->langcode->value);

    $child_form_state->setTriggeringElement($form_state->getTriggeringElement());
    $child_form_state->setSubmitHandlers($form_state->getSubmitHandlers());

    $this->child_form_state = $child_form_state;
    $this->child_form_controller = $controller;
  }
}
