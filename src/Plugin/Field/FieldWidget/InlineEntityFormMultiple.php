<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormMultiple.
 */

namespace Drupal\inline_entity_form\Plugin\Field\FieldWidget;

use Drupal\inline_entity_form\Plugin\Field\InlineEntityWidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Multiple value widget.
 *
 * @ingroup field_widget
 *
 * @FieldWidget(
 *   id = "inline_entity_form_multiple",
 *   label = @Translation("Inline entity form - Multiple value"),
 *   multiple_values = TRUE,
 *   field_types = {
 *     "entity_reference"
 *   },
 * )
 */
class InlineEntityFormMultiple extends InlineEntityWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    if ($this->isDefaultValueWidget($form_state)) {
      return $element;
    }

    $settings = $this->getFieldSettings();
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $this->initializeIefController();

    if (!$this->iefHandler) {
      return $element;
    }

    // Get the entity type labels for the UI strings.
    $labels = $this->labels();

    // Build a parents array for this element's values in the form.
    $parents = array_merge($element['#field_parents'], array(
      $items->getName(),
      'form',
    ));

    // Assign a unique identifier to each IEF widget.
    // Since $parents can get quite long, sha1() ensures that every id has
    // a consistent and relatively short length while maintaining uniqueness.
    $this->setIefId(sha1(implode('-', $parents)));

    // Get the langcode of the parent entity.
    $parent_langcode = $items->getParent()->getValue()->language()->getId();

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

    $element['#attached']['library'][] = 'inline_entity_form/widget';

    // Initialize the IEF array in form state.
    if (!$form_state->has(['inline_entity_form', $this->getIefId(), 'settings'])) {
      $form_state->set(['inline_entity_form', $this->getIefId(), 'settings'], $settings);
    }

    if (!$form_state->has(['inline_entity_form', $this->getIefId(), 'instance'])) {
      $form_state->set(['inline_entity_form', $this->getIefId(), 'instance'], $this->fieldDefinition);
    }

    if (!$form_state->has(['inline_entity_form', $this->getIefId(), 'form'])) {
      $form_state->set(['inline_entity_form', $this->getIefId(), 'form'], NULL);
    }

    if (!$form_state->has(['inline_entity_form', $this->getIefId(), 'array_parents'])) {
      $form_state->set(['inline_entity_form', $this->getIefId(), 'array_parents'], $parents);
    }

    $entities = $form_state->get(['inline_entity_form', $this->getIefId(), 'entities']);
    if (!isset($entities)) {
      // Load the entities from the $items array and store them in the form
      // state for further manipulation.
      $form_state->set(['inline_entity_form', $this->getIefId(), 'entities'], array());

      if (count($items)) {
        foreach ($items as $delta => $item) {
          if ($item->entity && is_object($item->entity)) {
            $form_state->set(['inline_entity_form', $this->getIefId(), 'entities', $delta], array(
              'entity' => $item->entity,
              '_weight' => $delta,
              'form' => NULL,
              'needs_save' => FALSE,
            ));
          }
        }
      }

      $entities = $form_state->get(['inline_entity_form', $this->getIefId(), 'entities']);
    }

    // Build the "Multiple value" widget.
    $element['#element_validate'] = array('inline_entity_form_update_row_weights');
    // Add the required element marker & validation.
    if ($element['#required']) {
      $element['#element_validate'][] = 'inline_entity_form_required_field';
    }

    $element['entities'] = array(
      '#tree' => TRUE,
      '#theme' => 'inline_entity_form_entity_table',
      '#entity_type' => $settings['target_type'],
      '#element' => array('cardinality' => $cardinality),
    );

    // Get the fields that should be displayed in the table.
    $target_bundles = isset($settings['handler_settings']['target_bundles']) ? $settings['handler_settings']['target_bundles'] : array();
    $fields = $this->iefHandler->tableFields($target_bundles);
    $context = array(
      'parent_entity_type' => $this->fieldDefinition->getTargetEntityTypeId(),
      'parent_bundle' => $this->fieldDefinition->getTargetBundle(),
      'field_name' => $this->fieldDefinition->getName(),
      'entity_type' => $settings['target_type'],
      'allowed_bundles' => $target_bundles,
    );
    \Drupal::moduleHandler()->alter('inline_entity_form_table_fields', $fields, $context);
    $element['entities']['#table_fields'] = $fields;

    $entities_count = $items_count = count($entities);
    if ($items_count < 10) {
      $items_count = 10;
    }
    foreach ($entities as $key => $value) {
      if (!isset($value['entity'])) {
        continue;
      }

      // Data used by theme_inline_entity_form_entity_table().
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
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

        // Add the appropriate form.
        if ($value['form'] == 'edit') {
          $element['entities'][$key]['form'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['ief-form', 'ief-form-row']],
            'inline_entity_form' => [
              '#type' => 'inline_entity_form',
              '#op' => $value['form'],
              '#save_entity' => FALSE,
              // Used by Field API and controller methods to find the relevant
              // values in $form_state.
              '#parents' => array_merge($parents, ['inline_entity_form', 'entities', $key, 'form']),
              // Store the entity on the form, later modified in the controller.
              '#entity' => $entity,
              '#entity_type' => $settings['target_type'],
              // Pass the langcode of the parent entity,
              '#language' => $parent_langcode,
              // Labels could be overridden in field widget settings. We won't have
              // access to those in static callbacks (#process, ...) so let's add
              // them here.
              '#ief_labels' => $this->labels(),
              // Identifies the IEF widget to which the form belongs.
              '#ief_id' => $this->getIefId(),
              // Identifies the table row to which the form belongs.
              '#ief_row_delta' => $key,
              // Add the pre_render callback that powers the #fieldset form element key,
              // which moves the element to the specified fieldset without modifying its
              // position in $form_state->get('values').
              '#pre_render' => ['inline_entity_form_pre_render_add_fieldset_markup'],
              '#process' => [
                ['\Drupal\inline_entity_form\Element\InlineEntityForm', 'processEntityForm'],
                [get_class($this), 'buildEntityFormActions'],
                [get_class($this), 'addIefSubmitCallbacks'],
              ]
            ]
          ];
        }
        elseif ($value['form'] == 'remove') {
          $element['entities'][$key]['form'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['ief-form', 'ief-form-row']],
            // Used by Field API and controller methods to find the relevant
            // values in $form_state.
            '#parents' => array_merge($parents, ['entities', $key, 'form']),
            // Store the entity on the form, later modified in the controller.
            '#entity' => $entity,
            // Identifies the IEF widget to which the form belongs.
            '#ief_id' => $this->getIefId(),
            // Identifies the table row to which the form belongs.
            '#ief_row_delta' => $key,
          ];
          $this->buildRemoveForm($element['entities'][$key]['form']);
        }
      }
      else {
        $row = &$element['entities'][$key];
        $row['title'] = array();
        $row['delta'] = array(
          '#type' => 'weight',
          '#delta' => $items_count,
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
        if (empty($entity_id) || $this->settings['allow_existing'] || $entity->access('delete')) {
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

    if ($cardinality > 1) {
      // Add a visual cue of cardinality count.
      $message = t('You have added @entities_count out of @cardinality_count allowed @label.', array(
        '@entities_count' => $entities_count,
        '@cardinality_count' => $cardinality,
        '@label' => $labels['plural'],
      ));
      $element['cardinality_count'] = array(
        '#markup' => '<div class="ief-cardinality-count">' . $message . '</div>',
      );
    }
    // Do not return the rest of the form if cardinality count has been reached.
    if ($cardinality > 0 && $entities_count == $cardinality) {
      return $element;
    }

    $target_bundles_count = count($target_bundles);

    // Try to open the add form (if it's the only allowed action, the
    // field is required and empty, and there's only one allowed bundle).
    if (empty($entities)) {
      if ($target_bundles_count == 1 && $this->fieldDefinition->isRequired() && !$this->settings['allow_existing']) {
        $bundle = reset($target_bundles);

        // The parent entity type and bundle must not be the same as the inline
        // entity type and bundle, to prevent recursion.
        $parent_entity_type = $this->fieldDefinition->getTargetEntityTypeId();
        $parent_bundle =  $this->fieldDefinition->getTargetBundle();
        if ($parent_entity_type != $settings['target_type'] || $parent_bundle != $bundle) {
          $form_state->set(['inline_entity_form', $this->getIefId(), 'form'], 'add');
          $form_state->set(['inline_entity_form', $this->getIefId(), 'form settings'], array(
            'bundle' => $bundle,
          ));
        }
      }
    }

    // If no form is open, show buttons that open one.
    $inline_entity_form_form = $form_state->get(['inline_entity_form', $this->getIefId(), 'form']);
    if (empty($inline_entity_form_form)) {
      $element['actions'] = array(
        '#attributes' => array('class' => array('container-inline')),
        '#type' => 'container',
        '#weight' => 100,
      );

      // The user is allowed to create an entity of at least one bundle.
      if ($target_bundles_count) {
        // Let the user select the bundle, if multiple are available.
        if ($target_bundles_count > 1) {
          $bundles = array();
          foreach ($this->entityManager->getBundleInfo($settings['target_type']) as $bundle_name => $bundle_info) {
            if (in_array($bundle_name, $target_bundles)) {
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
            '#value' => reset($target_bundles),
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

      if ($this->settings['allow_existing']) {
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
      if ($form_state->get(['inline_entity_form', $this->getIefId(), 'form']) == 'add') {
        $element['form'] = [
          '#type' => 'fieldset',
          '#attributes' => ['class' => ['ief-form', 'ief-form-bottom']],
          'inline_entity_form' => [
            '#type' => 'inline_entity_form',
            '#op' => 'add',
            '#save_entity' => FALSE,
            '#entity_type' => $settings['target_type'],
            '#bundle' => $this->determineBundle($form_state),
            '#language' => $parent_langcode,
            // Labels could be overridden in field widget settings. We won't have
            // access to those in static callbacks (#process, ...) so let's add
            // them here.
            '#ief_labels' => $this->labels(),
            // Identifies the IEF widget to which the form belongs.
            '#ief_id' => $this->getIefId(),
            // Used by Field API and controller methods to find the relevant
            // values in $form_state.
            '#parents' => array_merge($parents, ['inline_entity_form']),
            // Add the pre_render callback that powers the #fieldset form element key,
            // which moves the element to the specified fieldset without modifying its
            // position in $form_state->get('values').
            '#pre_render' => ['inline_entity_form_pre_render_add_fieldset_markup'],
            // We need to add our own #process callback that adds action elements,
            // but still keep default callback which makes sure everything will
            // actually work.
            '#process' => [
              ['\Drupal\inline_entity_form\Element\InlineEntityForm', 'processEntityForm'],
              [get_class($this), 'buildEntityFormActions'],
              [get_class($this), 'addIefSubmitCallbacks'],
            ],
          ],
        ];

        // Hide the cancel button if the reference field is required but
        // contains no values. That way the user is forced to create an entity.
        if (!$this->settings['allow_existing'] && $this->fieldDefinition->isRequired()
          && empty($entities)
          && $target_bundles_count == 1
        ) {
          $element['form']['inline_entity_form']['#process'][] = [get_class($this), 'hideCancel'];
        }
      }
      elseif ($form_state->get(['inline_entity_form', $this->getIefId(), 'form']) == 'ief_add_existing') {
        $element['form'] = array(
          '#type' => 'fieldset',
          '#attributes' => array('class' => array('ief-form', 'ief-form-bottom')),
          // Identifies the IEF widget to which the form belongs.
          '#ief_id' => $this->getIefId(),
          // Used by Field API and controller methods to find the relevant
          // values in $form_state.
          '#parents' => array_merge($parents),
          // Pass the current entity type.
          '#entity_type' => $settings['target_type'],
          // Pass the langcode of the parent entity,
          '#parent_language' => $parent_langcode,
          // Add the pre_render callback that powers the #fieldset form element key,
          // which moves the element to the specified fieldset without modifying its
          // position in $form_state->get('values').
          '#pre_render' => ['inline_entity_form_pre_render_add_fieldset_markup'],
        );

        $element['form'] += inline_entity_form_reference_form($this->iefHandler, $element['form'], $form_state);
      }

      // No entities have been added. Remove the outer fieldset to reduce
      // visual noise caused by having two titles.
      if (empty($entities)) {
        $element['#type'] = 'container';
      }
    }

    return $element;
  }
}
