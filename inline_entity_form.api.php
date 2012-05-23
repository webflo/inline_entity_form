<?php

/**
 * @file
 * Hooks provided by the Inline Entity Form module.
 */

/**
 * Defines inline entity types, managed through the inline entity form widget.
 *
 * Supported keys:
 * - file: the filepath of an include file containing the callback functions
 *   for this type.
 * - callbacks: an array of callback function names used by the widget for
 *   various operations on the referenced entities (list, add, edit, delete).
 *   - settings: Provides a settings form shown in the widget settings.
 *     The settings are later available to all form callbacks.
 *   - default fields: Provides a default list of fields that should be used to
 *     represent each selected entity.
 *   - form: Provides an add / edit form.
 *   - delete form: Provides a confirmation delete form.
 * - labels: an array of labels shown in the widget for this entity type.
 *   - add fieldset: Label of the fieldset shown around the add form.
 *   - add button: Label of the button that launches the add form.
 *   - save button: Label of the save button on the add / edit form.
 * - empty text: Text shown above the add form fieldset when no entities
 *   have been added yet.
 * - css - an array of css filepaths for the inline entity type.
 *   - base - The base css file, included in all themes.
 *   - seven - The Seven-specific css file, included in that theme only.
 *
 * @return
 *   An array of types, keyed by entity type.
 */
function hook_inline_entity_type_info() {
  $types = array();
  $types['commerce_product'] = array(
    'file' => drupal_get_path('module', 'inline_entity_form') . '/includes/commerce_product.type.inc',
    'callbacks' => array(
      'settings' => 'inline_entity_form_commerce_product_settings',
      'default fields' => 'inline_entity_form_commerce_product_default_fields',
      'form' => 'inline_entity_form_commerce_product_form',
      'delete form' => 'inline_entity_form_commerce_product_delete_form',
    ),
    'labels' => array(
      'add fieldset' => t('Add new product variation'),
      'add button' => t('Add variation'),
      'save button' => t('Save variation'),
    ),
    'empty text' => t('No product variations have been created. At least one variation is required.'),
    'css' => array(
      'base' => drupal_get_path('module', 'inline_entity_form') . '/includes/entity_types/commerce-product.css',
      'seven' => drupal_get_path('module', 'inline_entity_form') . '/includes/entity_types/commerce-product.seven.css',
    ),
  );

  return $types;
}

/**
 * Allows modules to alter inline entity types defined by other modules.
 *
 * @param $types
 *   The array of inline entity type arrays.
 *
 * @see hook_inline_entity_type_info()
 */
function hook_inline_entity_type_info_alter(&$types) {
  $types['commerce_product']['labels']['save button'] = t('Save');
}
