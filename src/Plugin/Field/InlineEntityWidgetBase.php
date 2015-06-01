<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Plugin\Field\InlineEntityWidgetBase.
 */

namespace Drupal\inline_entity_form\Plugin\Field;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

abstract class InlineEntityWidgetBase extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The inline entity from handler.
   *
   * @var \Drupal\inline_entity_form\InlineEntityFormHandlerInterface
   */
  protected $iefHandler;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $string_translation;

  /**
   * The inline entity form id.
   *
   * @var string
   */
  protected $iefId;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityManagerInterface $entity_manager, TranslationInterface $string_translation) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityManager = $entity_manager;
    $this->string_translation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    $keys = array_diff(parent::__sleep(), array('iefHandler'));
    return $keys;
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup() {
    parent::__wakeup();
    $this->initializeIefController();
  }

  /**
   * @param mixed $iefId
   */
  public function setIefId($iefId) {
    $this->iefId = $iefId;
  }

  /**
   * @return mixed
   */
  public function getIefId() {
    return $this->iefId;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      "allow_existing" => FALSE,
      "match_operator" => "CONTAINS",
      "delete_references" => FALSE,
      "override_labels" => FALSE,
      "label_singular" => "",
      "label_plural" => "",
    );
  }

  function initializeIefController() {
    if (!isset($this->iefHandler)) {
      $this->iefHandler = inline_entity_form_get_controller($this->fieldDefinition);
    }
  }

  /**
   * Returns an array of entity type labels (singular, plural) fit to be
   * included in the UI text.
   *
   * base one of the widgets out of the other one. We could use a trait if that
   * won't be possible.
   *
   * @return array
   *   Array containing two values:
   *     - singular: label for singular form,
   *     - plural: label for plural form.
   */
  protected function labels() {
    // The admin has specified the exact labels that should be used.
    if ($this->settings && $this->settings['override_labels']) {
      $labels = array();
      foreach($this->settings as $key => $value){
        // Recovering the configuration associated with labels if have a value.
        if(preg_match('/^label_/', $key) && $value){
          $label_options = substr($key, strlen('label_'));
          $labels[$label_options] = $value;
        }
      }
      return $labels;
    }
    else {
      $this->initializeIefController();
      return $this->iefHandler->labels();
    }
  }

  /**
   * Returns the settings form for the current entity type.
   *
   * The settings form is embedded into the IEF widget settings form.
   * Settings are later injected into the controller through $this->settings.
   *
   * @param $field
   *   The definition of the reference field used by IEF.
   * @param $instance
   *   The definition of the reference field instance.
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $this->initializeIefController();
    $labels = $this->iefHandler->labels();
    $states_prefix = 'instance[widget][settings][type_settings]';

    $element['allow_existing'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow users to add existing @label.', array('@label' => $labels['plural'])),
      '#default_value' => $this->settings['allow_existing'],
    );
    $element['match_operator'] = array(
      '#type' => 'select',
      '#title' => t('Autocomplete matching'),
      '#default_value' => $this->settings['match_operator'],
      '#options' => array(
        'STARTS_WITH' => t('Starts with'),
        'CONTAINS' => t('Contains'),
      ),
      '#description' => t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of nodes.'),
      '#states' => array(
        'visible' => array(
          ':input[name="' . $states_prefix . '[allow_existing]"]' => array('checked' => TRUE),
        ),
      ),
    );
    // The single widget doesn't offer autocomplete functionality.
    if ($form_state->get(['widget', 'type']) == 'inline_entity_form_single') {
      $form['allow_existing']['#access'] = FALSE;
      $form['match_operator']['#access'] = FALSE;
    }

    $element['delete_references'] = array(
      '#type' => 'checkbox',
      '#title' => t('Delete referenced @label when the parent entity is deleted.', array('@label' => $labels['plural'])),
      '#default_value' => $this->settings['delete_references'],
    );

    $element['override_labels'] = array(
      '#type' => 'checkbox',
      '#title' => t('Override labels'),
      '#default_value' => $this->settings['override_labels'],
    );
    $element['label_singular'] = array(
      '#type' => 'textfield',
      '#title' => t('Singular label'),
      '#default_value' => $this->settings['label_singular'],
      '#states' => array(
        'visible' => array(
          ':input[name="' . $states_prefix . '[override_labels]"]' => array('checked' => TRUE),
        ),
      ),
    );
    $element['label_plural'] = array(
      '#type' => 'textfield',
      '#title' => t('Plural label'),
      '#default_value' => $this->settings['label_plural'],
      '#states' => array(
        'visible' => array(
          ':input[name="' . $states_prefix . '[override_labels]"]' => array('checked' => TRUE),
        ),
      ),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    if ($this->isDefaultValueWidget($form_state)) {
      $items->filterEmptyItems();
      return;
    }

    $this->initializeIefController();
    $field_name = $this->fieldDefinition->getName();

    // Extract the values from $form_state->getValues().
    $parents = array_merge($form['#parents'], array($field_name, 'form'));
    $ief_id = sha1(implode('-', $parents));
    $this->setIefId($ief_id);

    $inline_entity_form_state = $form_state->get('inline_entity_form');
    if (isset($inline_entity_form_state[$this->getIefId()])) {
      $values = $inline_entity_form_state[$this->getIefId()];
      $key_exists = TRUE;
    }
    else {
      $values = [];
      $key_exists = FALSE;
    }

    if ($key_exists) {
      $values = $values['entities'];

      // Account for drag-and-drop reordering if needed.
      if (!$this->handlesMultipleValues()) {
        // Remove the 'value' of the 'add more' button.
        unset($values['add_more']);

        // The original delta, before drag-and-drop reordering, is needed to
        // route errors to the corect form element.
        foreach ($values as $delta => &$value) {
          $value['_original_delta'] = $delta;
        }

        usort($values, function ($a, $b) {
          return SortArray::sortByKeyInt($a, $b, '_weight');
        });
      }

      foreach ($values as $delta => &$item) {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $entity = $item['entity'];
        if (!empty($item['needs_save'])) {
          $entity->save();
        }
        if (!empty($item['delete'])) {
          $entity->delete();
          unset($items[$delta]);
        }
      }

      // Let the widget massage the submitted values.
      $values = $this->massageFormValues($values, $form, $form_state);

      // Assign the values and remove the empty ones.
      $items->setValue($values);
      $items->filterEmptyItems();

      // Put delta mapping in $form_state, so that flagErrors() can use it.
      $field_state = WidgetBase::getWidgetState($form['#parents'], $field_name, $form_state);
      foreach ($items as $delta => $item) {
        $field_state['original_deltas'][$delta] = isset($item->_original_delta) ? $item->_original_delta : $delta;
        unset($item->_original_delta, $item->_weight);
      }

      WidgetBase::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $items = array();

    // Convert form values to actual entity reference values.
    foreach ($values as $value) {
      $item = $value;
      if (isset($item['entity'])) {
        $item['target_id'] = $item['entity']->id();
        $items[] = $item;
      }
    }

    // Sort items by _weight.
    usort($items, function ($a, $b) {
      return SortArray::sortByKeyInt($a, $b, '_weight');
    });

    return $items;
  }

  /**
   * Adds submit callbacks to the inline entity form.
   *
   * @param array $element
   *   Form array structure.
   */
  public static function addIefSubmitCallbacks($element) {
    $element['#ief_element_submit'][] = [get_called_class(), 'submitSaveEntity'];
    return $element;
  }

  /**
   * Adds actions to the inline entity form.
   *
   * @param array $element
   *   Form array structure.
   */
  public static function buildEntityFormActions($element) {
    // Build a delta suffix that's appended to button #name keys for uniqueness.
    $delta = $element['#ief_id'];
    if ($element['#op'] == 'add') {
      $save_label = t('Create @type_singular', ['@type_singular' => $element['#ief_labels']['singular']]);
    }
    else {
      $delta .= '-' . $element['#ief_row_delta'];
      $save_label = t('Update @type_singular', ['@type_singular' => $element['#ief_labels']['singular']]);
    }

    // Add action submit elements.
    $element['actions'] = [
      '#type' => 'container',
      '#weight' => 100,
    ];
    $element['actions']['ief_' . $element['#op'] . '_save'] = [
      '#type' => 'submit',
      '#value' => $save_label,
      '#name' => 'ief-' . $element['#op'] . '-submit-' . $delta,
      '#limit_validation_errors' => [$element['#parents']],
      '#attributes' => ['class' => ['ief-entity-submit']],
      '#ajax' => [
        'callback' => 'inline_entity_form_get_element',
        'wrapper' => 'inline-entity-form-' . $element['#ief_id'],
      ],
    ];
    $element['actions']['ief_' . $element['#op'] . '_cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => 'ief-' . $element['#op'] . '-cancel-' . $delta,
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => 'inline_entity_form_get_element',
        'wrapper' => 'inline-entity-form-' . $element['#ief_id'],
      ],
    ];

    // Add submit handlers depending on operation.
    if ($element['#op'] == 'add') {
      $element['actions']['ief_add_save']['#submit'] = [
        ['\Drupal\inline_entity_form\Element\InlineEntityForm', 'triggerIefSubmit'],
        'inline_entity_form_close_child_forms',
        'inline_entity_form_close_form',
      ];
      $element['actions']['ief_add_cancel']['#submit'] = [
        'inline_entity_form_close_child_forms',
        'inline_entity_form_close_form',
        'inline_entity_form_cleanup_form_state',
      ];
    }
    else {
      $element['actions']['ief_edit_save']['#ief_row_delta'] = $element['#ief_row_delta'];
      $element['actions']['ief_edit_cancel']['#ief_row_delta'] = $element['#ief_row_delta'];

      $element['actions']['ief_edit_save']['#submit'] = [
        ['\Drupal\inline_entity_form\Element\InlineEntityForm', 'triggerIefSubmit'],
        'inline_entity_form_close_child_forms',
        [get_called_class(), 'submitCloseRow'],
      ];
      $element['actions']['ief_edit_cancel']['#submit'] = [
        'inline_entity_form_close_child_forms',
        [get_called_class(), 'submitCloseRow'],
        'inline_entity_form_cleanup_row_form_state',
      ];
    }

    return $element;
  }

  /**
   * Hides cancel button.
   *
   * @param array $element
   *   Form array structure.
   */
  public static function hideCancel($element) {
    $element['actions']['ief_add_cancel']['#access'] = FALSE;
    return $element;
  }

  /**
   * Builds remove form.
   *
   * @param array $form
   *   Form array structure.
   */
  protected function buildRemoveForm(&$form) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $form['#entity'];
    $entity_id = $entity->id();
    $entity_label = $entity->label();
    $labels = $this->labels();

    if ($entity_label) {
      $message = t('Are you sure you want to remove %label?', ['%label' => $entity_label]);
    }
    else {
      $message = t('Are you sure you want to remove this %entity_type?', ['%entity_type' => $labels['singular']]);
    }

    $form['message'] = [
      '#theme_wrappers' => ['container'],
      '#markup' => $message,
    ];

    if (!empty($entity_id) && $this->settings['allow_existing'] && $entity->access('delete')) {
      $form['delete'] = [
        '#type' => 'checkbox',
        '#title' => t('Delete this @type_singular from the system.', array('@type_singular' => $labels['singular'])),
      ];
    }

    // Build a deta suffix that's appended to button #name keys for uniqueness.
    $delta = $form['#ief_id'] . '-' . $form['#ief_row_delta'];

    // Add actions to the form.
    $form['actions'] = [
      '#type' => 'container',
      '#weight' => 100,
    ];
    $form['actions']['ief_remove_confirm'] = [
      '#type' => 'submit',
      '#value' => t('Remove'),
      '#name' => 'ief-remove-confirm-' . $delta,
      '#limit_validation_errors' => [$form['#parents']],
      '#ajax' => [
        'callback' => 'inline_entity_form_get_element',
        'wrapper' => 'inline-entity-form-' . $form['#ief_id'],
      ],
      '#submit' => [[get_class($this), 'submitConfirmRemove']],
      '#ief_row_delta' => $form['#ief_row_delta'],
    ];
    $form['actions']['ief_remove_cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => 'ief-remove-cancel-' . $delta,
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => 'inline_entity_form_get_element',
        'wrapper' => 'inline-entity-form-' . $form['#ief_id'],
      ],
      '#submit' => [[get_class($this), 'submitCloseRow']],
      '#ief_row_delta' => $form['#ief_row_delta'],
    ];
  }

  /**
   * Button #submit callback: Closes a row form in the IEF widget.
   *
   * @param $form
   *   The complete parent form.
   * @param $form_state
   *   The form state of the parent form.
   *
   * @see inline_entity_form_open_row_form().
   */
  public static function submitCloseRow($form, FormStateInterface $form_state) {
    $element = inline_entity_form_get_element($form, $form_state);
    $ief_id = $element['#ief_id'];
    $delta = $form_state->getTriggeringElement()['#ief_row_delta'];

    $form_state->setRebuild();
    $form_state->set(['inline_entity_form', $ief_id, 'entities', $delta, 'form'], NULL);
  }


  /**
   * Remove form submit callback.
   *
   * The row is identified by #ief_row_delta stored on the triggering
   * element.
   * This isn't an #element_validate callback to avoid processing the
   * remove form when the main form is submitted.
   *
   * @param $form
   *   The complete parent form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public static function submitConfirmRemove($form, FormStateInterface $form_state) {
    $element = inline_entity_form_get_element($form, $form_state);
    $delta = $form_state->getTriggeringElement()['#ief_row_delta'];

    /** @var \Drupal\Core\Field\FieldDefinitionInterface $instance */
    $instance = $form_state->get(['inline_entity_form', $element['#ief_id'], 'instance']);

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $element['entities'][$delta]['form']['#entity'];
    $entity_id = $entity->id();

    $widget = \Drupal::entityManager()
      ->getStorage('entity_form_display')
      ->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.default')
      ->getComponent($instance->getName());

    $form_values = NestedArray::getValue($form_state->getValues(), $element['entities'][$delta]['form']['#parents']);
    $form_state->setRebuild();

    // This entity hasn't been saved yet, we can just unlink it.
    if (empty($entity_id) || ($widget['settings']['allow_existing'] && empty($form_values['delete']))) {
      $form_state->set(['inline_entity_form', $element['#ief_id'], 'entities', $delta], NULL);
    }
    else {
      $delete = $form_state->get(['inline_entity_form', $element['#ief_id'], 'delete']);
      $delete['delete'][] = $entity_id;
      $form_state->set(['inline_entity_form', $element['#ief_id'], 'delete'], $delete);
      $form_state->set(['inline_entity_form', $element['#ief_id'], 'entities', $delta], NULL);
      if ($form_values['delete'] == '1') {
        $entity->delete();
      }
    }
  }

  /**
   * Determines bundle to be used when creating entity.
   *
   * @param FormStateInterface $form_state
   *   Current form state.
   *
   * @return string
   *   Bundle machine name.
   *
   * @TODO - Figure out if can be simplified.
   */
  protected function determineBundle(FormStateInterface $form_state) {
    $ief_settings = $form_state->get(['inline_entity_form', $this->getIefId()]);
    if (!empty($ief_settings['form settings']['bundle'])) {
      return $ief_settings['form settings']['bundle'];
    }
    elseif (!empty($ief_settings['bundle'])) {
      return $ief_settings['bundle'];
    }
    else {
      return reset($ief_settings['settings']['handler_settings']['target_bundles']);
    }
  }

  /**
   * Marks created/edited entity with "needs save" flag.
   *
   * Note that at this point the entity is not yet saved, since the user might
   * still decide to cancel the parent form.
   *
   * @param $entity_form
   *  The form of the entity being managed inline.
   * @param $form_state
   *   The form state of the parent form.
   */
  public static function submitSaveEntity($entity_form, FormStateInterface $form_state) {
    $ief_id = $entity_form['#ief_id'];
    $entity = $entity_form['#entity'];

    if ($entity_form['#op'] == 'add') {
      // Determine the correct weight of the new element.
      $weight = 0;
      $entities = $form_state->get(['inline_entity_form', $ief_id, 'entities']);
      if (!empty($entities)) {
        $weight = max(array_keys($entities)) + 1;
      }
      // Add the entity to form state, mark it for saving, and close the form.
      $entities[] = array(
        'entity' => $entity,
        '_weight' => $weight,
        'form' => NULL,
        'needs_save' => TRUE,
      );
      $form_state->set(['inline_entity_form', $ief_id, 'entities'], $entities);
    }
    else {
      $delta = $entity_form['#ief_row_delta'];
      $entities = $form_state->get(['inline_entity_form', $ief_id, 'entities']);
      $entities[$delta]['entity'] = $entity;
      $entities[$delta]['needs_save'] = TRUE;
      $form_state->set(['inline_entity_form', $ief_id, 'entities'], $entities);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $entity_custom_label = $this->labels();

    $summary[] = t('Allow users to add existing @entity: @allow_existing', array('@entity' => $this->string_translation->formatPlural(count($entity_custom_label), $entity_custom_label['singular'], $entity_custom_label['plural']),'@allow_existing' => ($this->getSetting('allow_existing')) ? t('Yes') : t('No')));
    $summary[] = t('Autocomplete matching: @match_operator', array('@match_operator' => $this->getSetting('match_operator')));
    $summary[] = t('Delete referenced entity when the parent entity is deleted: @delete_references', array('@delete_references' => ($this->getSetting('delete_references')) ? t('Yes') : t('No')));
    $summary[] = t('Override labels: @override_labels', array('@override_labels' => ($this->getSetting('override_labels')) ? t('Yes') : t('No')));

    if($this->getSetting('override_labels')){
      $summary[] = t('Singular label: @label_singular', array('@label_singular' => $this->getSetting('label_singular')));
      $summary[] = t('Plural label: @label_plural', array('@label_plural' => $this->getSetting('label_plural')));
    }

    return $summary;
  }
}
