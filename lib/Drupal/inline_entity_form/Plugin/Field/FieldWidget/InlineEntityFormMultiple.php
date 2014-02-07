<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormMultiple.
 */

namespace Drupal\inline_entity_form\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\field\Entity\FieldInstance;

/**
 * Multiple value widget.
 *
 * @FieldWidget(
 *   id = "inline_entity_form_multiple",
 *   label = @Translation("Inline entity form - Multiple value"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = true
 * )
 */
class InlineEntityFormMultiple extends WidgetBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The inline entity from controller.
   *
   * @var \Drupal\inline_entity_form\Plugin\InlineEntityForm\EntityInlineEntityFormController
   */
  protected $iefController;

  function initializeIefController() {
    if (!isset($this->iefController)) {
      $this->iefController = inline_entity_form_get_controller($this->fieldDefinition);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, array &$form_state) {
    $this->entityManager = \Drupal::entityManager();
    $settings = $this->getFieldSettings();


    $entity_info = $this->entityManager->getDefinition($settings['target_type']);
    $cardinality = $this->fieldDefinition->getCardinality();
    $this->initializeIefController();

    if (!$this->iefController) {
      return $element;
    }

    // Get the entity type labels for the UI strings.
    $labels = $this->iefController->labels();

    // Build a parents array for this element's values in the form.
    $parents = array_merge($element['#field_parents'], array(
      $element['#field_name']
    ));

    // Get the langcode of the parent entity.
    $parent_langcode = $element['#entity']->language()->id;

    // Assign a unique identifier to each IEF widget.
    // Since $parents can get quite long, sha1() ensures that every id has
    // a consistent and relatively short length while maintaining uniqueness.
    $ief_id = sha1(implode('-', $parents));
    // Determine the wrapper ID for the entire element.
    $wrapper = 'inline-entity-form-' . $ief_id;

    $element = array(
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#description' => NULL,
      '#prefix' => '<div id="' . $wrapper . '">',
      '#suffix' => '</div>',
      '#ief_id' => $ief_id,
      '#ief_root' => TRUE,
    ) + $element;

    $element['#attached']['js'][] = drupal_get_path('module', 'inline_entity_form') . '/inline_entity_form.js';

    // Initialize the IEF array in form state.
    if (empty($form_state['inline_entity_form'][$ief_id])) {
      $form_state['inline_entity_form'][$ief_id] = array(
        'form' => NULL,
        'settings' => $settings,
        'instance' => $this->fieldDefinition,
      );

      // Load the entities from the $items array and store them in the form
      // state for further manipulation.
      $form_state['inline_entity_form'][$ief_id]['entities'] = array();

      if (count($items)) {
        $entity_ids = array();
        foreach ($items as $item) {
          $entity_ids[] = $item->target_id;
        }

        $delta = 0;

        // @fixme.
        $entity_ids = array_filter($entity_ids);

        foreach (entity_load_multiple($settings['target_type'], $entity_ids) as $entity) {
          $form_state['inline_entity_form'][$ief_id]['entities'][$delta] = array(
            'entity' => $entity,
            '_weight' => $delta,
            'form' => NULL,
            'needs_save' => FALSE,
          );

          $delta++;
        }
      }
    }

    // Build the "Multiple value" widget.
    $element['#element_validate'] = array('inline_entity_form_update_row_weights');
    // Add the required element marker & validation.
    if ($element['#required']) {
      $element['#title'] .= ' ' . theme('form_required_marker', array('element' => $element));
      $element['#element_validate'][] = 'inline_entity_form_required_field';
    }

    $element['entities'] = array(
      '#tree' => TRUE,
      '#theme' => 'inline_entity_form_entity_table',
      '#entity_type' => $settings['target_type'],
    );

    // Get the fields that should be displayed in the table.
    $fields = $this->iefController->tableFields($settings['handler_settings']['target_bundles']);
    $context = array(
      'parent_entity_type' => $this->fieldDefinition->entity_type,
      'parent_bundle' => $this->fieldDefinition->bundle,
      'field_name' => $this->fieldDefinition->getName(),
      'entity_type' => $settings['target_type'],
      'allowed_bundles' => $settings['handler_settings']['target_bundles'],
    );
    drupal_alter('inline_entity_form_table_fields', $fields, $context);
    $element['entities']['#table_fields'] = $fields;

    foreach ($form_state['inline_entity_form'][$ief_id]['entities'] as $key => $value) {
      // Data used by theme_inline_entity_form_entity_table().
      $element['entities'][$key]['#entity'] = $entity = $value['entity'];
      $element['entities'][$key]['#needs_save'] = $value['needs_save'];

      // Handle row weights.
      $element['entities'][$key]['#weight'] = $value['_weight'];

      // First check to see if this entity should be displayed as a form.
      if (!empty($value['form'])) {
        $element['entities'][$key]['delta'] = array(
          '#type' => 'value',
          '#value' => $value['_weight'],
        );
        $element['entities'][$key]['form'] = array(
          '#type' => 'container',
          '#attributes' => array('class' => array('ief-form', 'ief-form-row')),
          '#op' => $value['form'],
          // Used by Field API and controller methods to find the relevant
          // values in $form_state.
          '#parents' => array_merge($parents, array('entities', $key, 'form')),
          // Store the entity on the form, later modified in the controller.
          '#entity' => $entity,
          '#entity_type' => $settings['target_type'],
          // Pass the langcode of the parent entity,
          '#parent_language' => $parent_langcode,
          // Identifies the IEF widget to which the form belongs.
          '#ief_id' => $ief_id,
          // Identifies the table row to which the form belongs.
          '#ief_row_delta' => $key,
        );
        // Prepare data for the form callbacks.
        $form = & $element['entities'][$key]['form'];

        // Add the appropriate form.
        if ($value['form'] == 'edit') {
          $form += inline_entity_form_entity_form($this->iefController, $form, $form_state);
        }
        elseif ($value['form'] == 'remove') {
          $form += inline_entity_form_remove_form($this->iefController, $form, $form_state);
        }
      }
      else {
        $row = & $element['entities'][$key];
        $row['delta'] = array(
          '#type' => 'weight',
          '#delta' => 50,
          '#default_value' => $value['_weight'],
          '#attributes' => array('class' => array('ief-entity-delta')),
        );
        // Add an actions container with edit and delete buttons for the entity.
        $row['actions'] = array(
          '#type' => 'container',
          '#attributes' => array('class' => array('ief-entity-operations')),
        );

        // Make sure entity_access is not checked for unsaved entities.
        $entity_id = $entity->id();
        if (empty($entity_id) || $entity->access('update')) {
          $row['actions']['ief_entity_edit'] = array(
            '#type' => 'submit',
            '#value' => t('Edit'),
            '#name' => 'ief-' . $ief_id . '-entity-edit-' . $key,
            '#limit_validation_errors' => array(),
            '#ajax' => array(
              'callback' => 'inline_entity_form_get_element',
              'wrapper' => $wrapper,
            ),
            '#submit' => array('inline_entity_form_open_row_form'),
            '#ief_row_delta' => $key,
            '#ief_row_form' => 'edit',
          );
        }

        // If 'allow_existing' is on, the default removal operation is unlink
        // and the access check for deleting happens inside the controller
        // removeForm() method.
        if (empty($entity_id) || $this->iefController->getSetting('allow_existing') || $entity->access('delete')) {
          $row['actions']['ief_entity_remove'] = array(
            '#type' => 'submit',
            '#value' => t('Remove'),
            '#name' => 'ief-' . $ief_id . '-entity-remove-' . $key,
            '#limit_validation_errors' => array(),
            '#ajax' => array(
              'callback' => 'inline_entity_form_get_element',
              'wrapper' => $wrapper,
            ),
            '#submit' => array('inline_entity_form_open_row_form'),
            '#ief_row_delta' => $key,
            '#ief_row_form' => 'remove',
          );
        }
      }
    }

    $entity_count = count($form_state['inline_entity_form'][$ief_id]['entities']);
    if ($cardinality > 1) {
      // Add a visual cue of cardinality count.
      $message = t('You have added @entities_count out of @cardinality_count allowed @label.', array(
        '@entities_count' => $entity_count,
        '@cardinality_count' => $cardinality,
        '@label' => $labels['plural'],
      ));
      $element['cardinality_count'] = array(
        '#markup' => '<div class="ief-cardinality-count">' . $message . '</div>',
      );
    }
    // Do not return the rest of the form if cardinality count has been reached.
    if ($cardinality > 0 && $entity_count == $cardinality) {
      return $element;
    }

    // Try to open the add form (if it's the only allowed action, the
    // field is required and empty, and there's only one allowed bundle).
    if (empty($form_state['inline_entity_form'][$ief_id]['entities'])) {
      if (count($settings['handler_settings']['target_bundles']) == 1 && $this->fieldDefinition->isRequired() && !$this->iefController->getSetting('allow_existing')) {
        $bundle = reset($settings['handler_settings']['target_bundles']);

        // The parent entity type and bundle must not be the same as the inline
        // entity type and bundle, to prevent recursion.
        if ($element['#entity_type'] != $settings['entity_type'] || $element['#bundle'] != $bundle) {
          $form_state['inline_entity_form'][$ief_id]['form'] = 'add';
          $form_state['inline_entity_form'][$ief_id]['form settings'] = array(
            'bundle' => $bundle,
          );
        }
      }
    }

    // If no form is open, show buttons that open one.
    if (empty($form_state['inline_entity_form'][$ief_id]['form'])) {
      $element['actions'] = array(
        '#attributes' => array('class' => array('container-inline')),
        '#type' => 'container',
        '#weight' => 100,
      );

      // The user is allowed to create an entity of at least one bundle.
      if (count($settings['handler_settings']['target_bundles'])) {
        // Let the user select the bundle, if multiple are available.
        if (count($settings['handler_settings']['target_bundles']) > 1) {
          $bundles = array();
          foreach ($this->entityManager->getBundleInfo($settings['target_type']) as $bundle_name => $bundle_info) {
            if (in_array($bundle_name, $settings['handler_settings']['target_bundles'])) {
              $bundles[$bundle_name] = $bundle_info['label'];
            }
          }

          $element['actions']['bundle'] = array(
            '#type' => 'select',
            '#options' => $bundles,
          );
        }
        else {
          $element['actions']['bundle'] = array(
            '#type' => 'value',
            '#value' => reset($settings['handler_settings']['target_bundles']),
          );
        }

        $element['actions']['ief_add'] = array(
          '#type' => 'submit',
          '#value' => t('Add new @type_singular', array('@type_singular' => $labels['singular'])),
          '#name' => 'ief-' . $ief_id . '-add',
          '#limit_validation_errors' => array(array_merge($parents, array('actions'))),
          '#ajax' => array(
            'callback' => 'inline_entity_form_get_element',
            'wrapper' => $wrapper,
          ),
          '#submit' => array('inline_entity_form_open_form'),
          '#ief_form' => 'add',
        );
      }

      if ($this->iefController->getSetting('allow_existing')) {
        $element['actions']['ief_add_existing'] = array(
          '#type' => 'submit',
          '#value' => t('Add existing @type_singular', array('@type_singular' => $labels['singular'])),
          '#name' => 'ief-' . $ief_id . '-add-existing',
          '#limit_validation_errors' => array(array_merge($parents, array('actions'))),
          '#ajax' => array(
            'callback' => 'inline_entity_form_get_element',
            'wrapper' => $wrapper,
          ),
          '#submit' => array('inline_entity_form_open_form'),
          '#ief_form' => 'ief_add_existing',
        );
      }
    }
    else {
      // There's a form open, show it.
      $element['form'] = array(
        '#type' => 'fieldset',
        '#attributes' => array('class' => array('ief-form', 'ief-form-bottom')),
        // Identifies the IEF widget to which the form belongs.
        '#ief_id' => $ief_id,
        // Used by Field API and controller methods to find the relevant
        // values in $form_state.
        '#parents' => array_merge($parents, array('form')),
        // Pass the current entity type.
        '#entity_type' => $settings['target_type'],
        // Pass the langcode of the parent entity,
        '#parent_language' => $parent_langcode,
      );

      if ($form_state['inline_entity_form'][$ief_id]['form'] == 'add') {
        $element['form']['#op'] = 'add';
        $element['form'] += inline_entity_form_entity_form($this->iefController, $element['form'], $form_state);

        // Hide the cancel button if the reference field is required but
        // contains no values. That way the user is forced to create an entity.
        if (!$this->iefController->getSetting('allow_existing') && $this->fieldDefinition->isRequired()
          && empty($form_state['inline_entity_form'][$ief_id]['entities'])
          && count($settings['handler_settings']['target_bundles']) == 1
        ) {
          $element['form']['actions']['ief_add_cancel']['#access'] = FALSE;
        }
      }
      elseif ($form_state['inline_entity_form'][$ief_id]['form'] == 'ief_add_existing') {
        $element['form'] += inline_entity_form_reference_form($this->iefController, $element['form'], $form_state);
      }

      // No entities have been added. Remove the outer fieldset to reduce
      // visual noise caused by having two titles.
      if (empty($form_state['inline_entity_form'][$ief_id]['entities'])) {
        $element['#type'] = 'container';
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, array &$form_state) {
    $field_name = $this->fieldDefinition->getName();

    // Extract the values from $form_state['values'].
//    $path = array_merge($form['#parents'], array($field_name));
//    $key_exists = NULL;

//    $values = NestedArray::getValue($form_state['values'], $path, $key_exists);

    $parents = array($field_name);
    $ief_id = sha1(implode('-', $parents));

    $key_exists = NULL;
    $path = array_merge(array('inline_entity_form'), array($ief_id));
    $values = NestedArray::getValue($form_state, $path, $key_exists);

    if ($key_exists) {
      $values = $values['entities'];

      // Remove the 'value' of the 'add more' button.
      unset($values['add_more']);

      foreach ($values as $delta => &$item) {
        if ($item['needs_save']) {
          $item['entity']->save();
        }
        if ($item['delete']) {
          $item['entity']->delete();
          unset($items[$delta]);
        }
      }

      // Let the widget turn the submitted values into actual field values.
      // Make sure the '_weight' entries are persisted in the process.
      $weights = array();
      // Check that $values[0] is an array, because if it's a string, then in
      // PHP 5.3, ['_weight'] returns the first character.
      if (isset($values[0]) && is_array($values[0]) && isset($values[0]['_weight'])) {
        foreach ($values as $delta => $value) {
          $weights[$delta] = $value['_weight'];
        }
      }
      $items->setValue($this->massageFormValues($values, $form, $form_state));

      foreach ($items as $delta => $item) {
        // Put back the weight.
        if (isset($weights[$delta])) {
          $item->_weight = $weights[$delta];
        }
        // The tasks below are going to reshuffle deltas. Keep track of the
        // original deltas for correct reporting of errors in flagErrors().
        $item->_original_delta = $delta;
      }

      // Account for drag-n-drop reordering.
      $this->sortItems($items);

      // Remove empty values.
      $items->filterEmptyValues();

      // Put delta mapping in $form_state, so that flagErrors() can use it.
      $field_state = field_form_get_state($form['#parents'], $field_name, $form_state);
      foreach ($items as $delta => $item) {
        $field_state['original_deltas'][$delta] = $item->_original_delta;
        unset($item->_original_delta);
      }

      field_form_set_state($form['#parents'], $field_name, $form_state, $field_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, array &$form_state) {
    $items = array();

    // Convert form values to actual entity reference values.
    foreach($values as $value) {
      $item = $value;
      $item['target_id'] = $item['entity']->id();
      $items[] = $item;
    }

    return $items;
  }


}

