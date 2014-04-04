<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormMultiple.
 */

namespace Drupal\inline_entity_form\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
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

  protected $iefId;

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

    // Assign a unique identifier to each IEF widget.
    // Since $parents can get quite long, sha1() ensures that every id has
    // a consistent and relatively short length while maintaining uniqueness.
    $this->setIefId(sha1(implode('-', $parents)));

    // Get the langcode of the parent entity.
    $parent_langcode = $element['#entity']->language()->id;

    // Determine the wrapper ID for the entire element.
    $wrapper = 'inline-entity-form-' . $this->getIefId();

    $element = array(
        '#type' => 'fieldset',
        '#tree' => TRUE,
        '#description' => NULL,
        '#prefix' => '<div id="' . $wrapper . '">',
        '#suffix' => '</div>',
        '#ief_id' => $this->getIefId(),
        '#ief_root' => TRUE,
      ) + $element;

    $element['#attached']['js'][] = drupal_get_path('module', 'inline_entity_form') . '/inline_entity_form.js';

    // Initialize the IEF array in form state.
    if (empty($form_state['inline_entity_form'][$this->getIefId()]['settings'])) {
      $form_state['inline_entity_form'][$this->getIefId()]['settings'] = $settings;
    }

    if (empty($form_state['inline_entity_form'][$this->getIefId()]['instance'])) {
      $form_state['inline_entity_form'][$this->getIefId()]['instance'] = $this->fieldDefinition;
    }

    if (empty($form_state['inline_entity_form'][$this->getIefId()]['form'])) {
      $form_state['inline_entity_form'][$this->getIefId()]['form'] = NULL;
    }

    if (empty($form_state['inline_entity_form'][$this->getIefId()]['array_parents'])) {
      $form_state['inline_entity_form'][$this->getIefId()]['array_parents'] = $parents;
    }

    if (empty($form_state['inline_entity_form'][$this->getIefId()]['entities'])) {
      // Load the entities from the $items array and store them in the form
      // state for further manipulation.
      $form_state['inline_entity_form'][$this->getIefId()]['entities'] = array();

      if (count($items)) {
        foreach ($items as $delta => $item) {
          if ($item->entity && is_object($item->entity)) {
            $form_state['inline_entity_form'][$this->getIefId()]['entities'][$delta] = array(
              'entity' => $item->entity,
              '_weight' => $delta,
              'form' => NULL,
              'needs_save' => FALSE,
            );
          }
        }
      }
    }

    // Build the "Multiple value" widget.
    $element['#element_validate'] = array('inline_entity_form_update_row_weights');
    // Add the required element marker & validation.
    if ($element['#required']) {
      $element['#title'] .= ' ' . _theme('form_required_marker', array('element' => $element));
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

    foreach ($form_state['inline_entity_form'][$this->getIefId()]['entities'] as $key => $value) {
      if (!isset($value['entity'])) {
        continue;
      }

      // Data used by theme_inline_entity_form_entity_table().
      $entity = $value['entity'];
      $element['entities'][$key]['#entity'] = $value['entity'];
      $element['entities'][$key]['#item'] = $items->offsetGet($key);
      $element['entities'][$key]['#needs_save'] = $value['needs_save'];

      // Handle row weights.
      $element['entities'][$key]['#weight'] = $value['_weight'];

      // First check to see if this entity should be displayed as a form.
      if (!empty($value['form'])) {
        $element['entities'][$key]['title'] = array();
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
          '#ief_id' => $this->getIefId(),
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
        $row['title'] = array();
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
            '#name' => 'ief-' . $this->getIefId() . '-entity-edit-' . $key,
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
            '#name' => 'ief-' . $this->getIefId() . '-entity-remove-' . $key,
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

    $entity_count = count($form_state['inline_entity_form'][$this->getIefId()]['entities']);
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
    if (empty($form_state['inline_entity_form'][$this->getIefId()]['entities'])) {
      if (count($settings['handler_settings']['target_bundles']) == 1 && $this->fieldDefinition->isRequired() && !$this->iefController->getSetting('allow_existing')) {
        $bundle = reset($settings['handler_settings']['target_bundles']);

        // The parent entity type and bundle must not be the same as the inline
        // entity type and bundle, to prevent recursion.
        if ($element['#entity_type'] != $settings['target_type'] || $element['#bundle'] != $bundle) {
          $form_state['inline_entity_form'][$this->getIefId()]['form'] = 'add';
          $form_state['inline_entity_form'][$this->getIefId()]['form settings'] = array(
            'bundle' => $bundle,
          );
        }
      }
    }

    // If no form is open, show buttons that open one.
    if (empty($form_state['inline_entity_form'][$this->getIefId()]['form'])) {
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
          '#name' => 'ief-' . $this->getIefId() . '-add',
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
          '#name' => 'ief-' . $this->getIefId() . '-add-existing',
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
        '#ief_id' => $this->getIefId(),
        // Used by Field API and controller methods to find the relevant
        // values in $form_state.
        '#parents' => array_merge($parents, array('form')),
        // Pass the current entity type.
        '#entity_type' => $settings['target_type'],
        // Pass the langcode of the parent entity,
        '#parent_language' => $parent_langcode,
      );

      if ($form_state['inline_entity_form'][$this->getIefId()]['form'] == 'add') {
        $element['form']['#op'] = 'add';
        $element['form'] += inline_entity_form_entity_form($this->iefController, $element['form'], $form_state);

        // Hide the cancel button if the reference field is required but
        // contains no values. That way the user is forced to create an entity.
        if (!$this->iefController->getSetting('allow_existing') && $this->fieldDefinition->isRequired()
          && empty($form_state['inline_entity_form'][$this->getIefId()]['entities'])
          && count($settings['handler_settings']['target_bundles']) == 1
        ) {
          $element['form']['actions']['ief_add_cancel']['#access'] = FALSE;
        }
      }
      elseif ($form_state['inline_entity_form'][$this->getIefId()]['form'] == 'ief_add_existing') {
        $element['form'] += inline_entity_form_reference_form($this->iefController, $element['form'], $form_state);
      }

      // No entities have been added. Remove the outer fieldset to reduce
      // visual noise caused by having two titles.
      if (empty($form_state['inline_entity_form'][$this->getIefId()]['entities'])) {
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
    if (isset($form['#ief_parents'])) {
      $parents = array_merge($form['#ief_parents'], array($this->fieldDefinition->getName()));
    }
    else {
      $parents = array($field_name);
    }
    $ief_id = sha1(implode('-', $parents));
    $this->setIefId($ief_id);

    $key_exists = NULL;
    $path = array_merge(array('inline_entity_form'), array($this->getIefId()));
    $values = NestedArray::getValue($form_state, $path, $key_exists);

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

      // @todo: remove the duplicate entity save.
      foreach ($values as $delta => &$item) {
        if (!empty($item['needs_save'])) {
          if (!$item['entity']->id()) {
            unset($item['entity']->uuid);
          }
          $item['entity']->save();
        }
        if (!empty($item['delete'])) {
          $item['entity']->delete();
          unset($items[$delta]);
        }
      }

      // Let the widget massage the submitted values.
      $values = $this->massageFormValues($values, $form, $form_state);

      // Assign the values and remove the empty ones.
      $items->setValue($values);
      $items->filterEmptyItems();

      // Put delta mapping in $form_state, so that flagErrors() can use it.
      $field_state = field_form_get_state($form['#parents'], $field_name, $form_state);
      foreach ($items as $delta => $item) {
        $field_state['original_deltas'][$delta] = isset($item->_original_delta) ? $item->_original_delta : $delta;
        unset($item->_original_delta, $item->_weight);
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
    foreach ($values as $value) {
      $item = $value;
      if (isset($item['entity'])) {
        $item['target_id'] = $item['entity']->id();
        $items[] = $item;
      }
    }

    return $items;
  }


}

