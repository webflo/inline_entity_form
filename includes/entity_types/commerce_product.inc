<?php

/**
 * @file
 * CTools plugin. Provides functionality for inline managing commerce products.
 */

$plugin = array(
  'table' => 'commerce_ipf_commerce_product_build_table',
  'form' => 'commerce_ipf_commerce_product_form',
  'form validate' => 'commerce_ipf_commerce_product_form_validate',
  'form submit' => 'commerce_ipf_commerce_product_form_submit',
  'delete form' => 'commerce_ipf_commerce_product_delete_form',
  'add label' => t('Add a new product'),
  'save label' => t('Save product'),
);

/**
 * IPF table callback: Returns the table used to identify existing products.
 * Additional elements (weight, actions) are appended to this table later on.
 */
function commerce_ipf_commerce_product_build_table($products) {
  $table['#header'] = array(
    array('data' => t('Product title'), 'class' => array('ipf-product-header')),
    array('data' => t('SKU')),
  );

  $table['#rows'] = array();
  foreach ($products as $product) {
    $table['#rows'][] = array(
      array('data' => check_plain($product->title), 'class' => array('commerce-ipf-product-title')),
      array('data' => check_plain($product->sku), 'class' => array('commerce-ipf-product-sku')),
    );
  }

  return $table;
}

/**
 * IPF add/edit form callback: Returns the product form to be embedded.
 *
 * When adding data to $form_state it should be noted that there can be several
 * IPF widgets on one master form, each with several form rows, leading to
 * possible key collisions if the keys are not prefixed with $parents.
 */
function commerce_ipf_commerce_product_form($form, &$form_state, $parents, $product) {
  // Field API relies on #parents when invoked on subforms, using it to find
  // the field values in $form_state.
  $form['#parents'] = $parents;

  $language = !empty($product->language) ? $product->language : LANGUAGE_NONE;
  $form['sku'] = array(
    '#type' => 'textfield',
    '#title' => t('Product SKU'),
    '#description' => t('Supply a unique identifier for this product using letters, numbers, hyphens, and underscores. Commas may not be used.'),
    '#default_value' => $product->sku,
    '#maxlength' => 128,
    '#required' => TRUE,
    '#weight' => -10,
  );
  field_attach_form('commerce_product', $product, $form, $form_state, $language);

  return $form;
}

/**
 * IPF add/edit form validation callback.
 */
function commerce_ipf_commerce_product_form_validate($form, &$form_state, $parents, $product) {
  $parents_path = implode('][', $parents);
  $product_values = drupal_array_get_nested_value($form_state['values'], $parents);
  $sku = trim($product_values['sku']);

  // Ensure the proposed SKU is unique.
  if (!commerce_product_validate_sku_unique($sku, $product->product_id)) {
    form_set_error($parents_path . '][sku', t('This SKU is already in use and must be unique. Please supply another value.'));
  }
  // Validate the SKU for invalid characters.
  if (!commerce_product_validate_sku($sku)) {
    form_set_error($parents_path . '][sku', t('The SKU %sku contains invalid characters.', array('%sku' => $sku)));
  }
  // Trim leading and trailing whitespace from the SKU.
  drupal_array_set_nested_value($form_state['values'], array_merge($parents, array('sku')), $sku);

  field_attach_form_validate('commerce_product', $product, $form, $form_state);
}

/**
 * IPF add/edit form submit callback: Modifies the passed-in product before it
 * is saved.
 */
function commerce_ipf_commerce_product_form_submit($form, &$form_state, $parents, $product) {
  $product_values = drupal_array_get_nested_value($form_state['values'], $parents);
  $product->title = $product_values['sku'];
  $product->sku = $product_values['sku'];
  field_attach_submit('commerce_product', $product, $form, $form_state);
}

/**
 * IPF delete form callback: Returns the confirmation message.
 */
function commerce_ipf_commerce_product_delete_form($form, $form_state, $parents, $product) {
  $form['message'] = array(
    '#markup' => '<div>' . t('Are you sure you want to delete %title?', array('%title' => $product->title)) . '</div>',
  );

  return $form;
}
