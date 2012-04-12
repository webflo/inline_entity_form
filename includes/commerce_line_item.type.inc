<?php

/**
 * @file
 * Provides functionality for inline managing commerce line items.
 */

function inline_entity_form_commerce_line_item_default_fields($types) {
  $fields = array();

  $fields['line_item_label'] = array(
    'type' => 'extra_field',
    'label' => t('Label'),
    'visible' => TRUE,
    'weight' => 1,
  );
  $fields['commerce_unit_price'] = array(
    'type' => 'field',
    'label' => t('Unit price'),
    'formatter' => 'commerce_price_formatted_amount',
    'settings' => array(),
    'visible' => TRUE,
    'weight' => 2,
  );
  $fields['quantity'] = array(
    'type' => 'extra_field',
    'label' => t('Quantity'),
    'visible' => TRUE,
    'weight' => 3,
  );
  $fields['commerce_total'] = array(
    'type' => 'field',
    'label' => t('Total'),
    'formatter' => 'commerce_price_formatted_amount',
    'settings' => array(),
    'visible' => TRUE,
    'weight' => 4,
  );

  return $fields;
}

/**
 * IEF add/edit form callback: Returns the line item form to be embedded.
 */
function inline_entity_form_commerce_line_item_form($form, &$form_state) {
  $line_item = $form['#entity'];

  $form['#element_validate'] = array(
    'inline_entity_form_commerce_line_item_form_validate',
    'inline_entity_form_process_submit',
  );
  $form['#element_submit'] = array(
    'inline_entity_form_commerce_line_item_form_submit',
  );
  // Ensure this include file is loaded when the form is rebuilt from the cache.
  $form_state['build_info']['files']['inline_form'] = drupal_get_path('module', 'inline_entity_form') . '/includes/commerce_line_item.type.inc';

  $form['#pre_render'][] = 'inline_entity_form_pre_render_add_fieldset_markup';
  $form['line_item_details'] = array(
    '#type' => 'fieldset',
    '#title' => t('Line item details'),
    '#attributes' => array('class' => array('ief-line_item-details', 'ief-entity-fieldset')),
  );
  $form['line_item_label'] = array(
    '#type' => 'textfield',
    '#title' => t('Line item label'),
    '#description' => t('Supply the line item label to be used for this line item.'),
    '#default_value' => $line_item->line_item_label,
    '#maxlength' => 128,
    '#required' => TRUE,
    '#weight' => -10,
    '#fieldset' => 'line_item_details',
  );
  $form['quantity'] = array(
    '#type' => 'textfield',
    '#datatype' => 'integer',
    '#title' => t('Quantity'),
    '#description' => t('The quantity of line items.'),
    '#default_value' => (int) $line_item->quantity,
    '#size' => 4,
    '#maxlength' => max(4, strlen($line_item->quantity)),
    '#required' => TRUE,
    '#fieldset' => 'line_item_details',
  );
  field_attach_form('commerce_line_item', $line_item, $form, $form_state, LANGUAGE_NONE);

  // Tweaks specific to product line items.
  if (in_array($line_item->type, commerce_product_line_item_types())) {
    $form['line_item_label']['#access'] = FALSE;
    $form['commerce_display_path']['#access'] = FALSE;
    $form['commerce_product']['#weight'] = -100;
  }

  // Add all fields to the main fieldset.
  foreach (field_info_instances('commerce_line_item', $line_item->type) as $a => $instance) {
    $form[$instance['field_name']]['#fieldset'] = 'line_item_details';
  }

  return $form;
}

/**
 * IEF add/edit form validation callback.
 */
function inline_entity_form_commerce_line_item_form_validate($form, &$form_state) {
  $line_item = $form['#entity'];

  $parents_path = implode('][', $form['#parents']);
  $line_item_values = drupal_array_get_nested_value($form_state['values'], $form['#parents']);
  $line_item_label = trim($line_item_values['line_item_label']);
  $quantity = $line_item_values['quantity'];
  // Trim leading and trailing whitespace from the line item label.
  drupal_array_set_nested_value($form_state['values'], array_merge($form['#parents'], array('line_item_label')), $line_item_label);

  if (!is_numeric($quantity) || $quantity <= 0) {
    form_set_error($parents_path . '][quantity', t('You must specify a positive number for the quantity'));
  }
  elseif ($form['quantity']['#datatype'] == 'integer' &&
    (int) $quantity != $quantity) {
    form_set_error($parents_path . '][quantity', t('You must specify a whole number for the quantity.'));
  }

  field_attach_form_validate('commerce_line_item', $line_item, $form, $form_state);
}

/**
 * IEF add/edit form submit callback: Modifies the passed-in line item before it
 * is saved.
 */
function inline_entity_form_commerce_line_item_form_submit($form, &$form_state) {
  $line_item = $form['#entity'];
  $line_item_values = drupal_array_get_nested_value($form_state['values'], $form['#parents']);
  $line_item->line_item_label = $line_item_values['line_item_label'];
  $line_item->quantity = sprintf("%.2f", $line_item_values['quantity']);

  field_attach_submit('commerce_line_item', $line_item, $form, $form_state);

  // Product line item types by default get the product SKU as the label.
  if (empty($line_item->line_item_id) && in_array($line_item->type, commerce_product_line_item_types())) {
    $product = commerce_product_load($line_item->commerce_product[LANGUAGE_NONE][0]['product_id']);
    $line_item->line_item_label = $product->sku;
  }
}

/**
 * IEF delete form callback: Returns the confirmation message.
 */
function inline_entity_form_commerce_line_item_delete_form($form, $form_state) {
  $line_item = $form['#entity'];
  $form['message'] = array(
    '#markup' => '<div>' . t('Are you sure you want to delete %title?', array('%title' => $line_item->line_item_label)) . '</div>',
  );

  return $form;
}
