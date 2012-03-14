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
  'add label' => t('Add product variation'),
  'save label' => t('Save variation'),
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
  // Some form elements belong in a fieldset for presentation, but can't
  // be moved into one because of the form_state['values'] hierarchy. Those
  // elements can add a #fieldset => 'fieldset_name' property, and they'll
  // be moved to their fieldset during pre_render.
  $form['#pre_render'][] = 'commerce_ipf_pre_render_add_fieldset_markup';
  $form['product_attributes'] = array(
    '#type' => 'fieldset',
    '#title' => t('Product attributes'),
    '#attributes' => array('class' => array('container-inline', 'ipf-product-attributes')),
  );
  $form['product_details'] = array(
    '#type' => 'fieldset',
    '#title' => t('Product details'),
    '#attributes' => array('class' => array('ipf-product-details')),
  );
  $form['product_image'] = array(
    '#type' => 'fieldset',
    '#title' => t('Product variation image'),
    '#attributes' => array('class' => array('ipf-product-image')),
  );

  $language = !empty($product->language) ? $product->language : LANGUAGE_NONE;
  $form['sku'] = array(
    '#type' => 'textfield',
    '#title' => t('Product SKU'),
    '#description' => t('Supply a unique identifier for this product using letters, numbers, hyphens, and underscores. Commas may not be used.'),
    '#default_value' => $product->sku,
    '#maxlength' => 128,
    '#required' => TRUE,
    '#fieldset' => 'product_details',
  );
  field_attach_form('commerce_product', $product, $form, $form_state, $language);

  // Arrange attributes.
  $attributes = _commerce_ipf_commerce_product_attributes($product->type);
  if (empty($attributes)) {
    // Hide the fieldset, it will be empty.
    $form['product_attributes']['#access'] = FALSE;
  }
  else {
    foreach ($attributes as $field_name) {
      $form[$field_name]['#fieldset'] = 'product_attributes';
    }
  }

  // Arrange images and other non-attribute fields.
  $imagefields = array();
  foreach (field_info_instances('commerce_product', $product->type) as $name => $instance) {
    $field_name = $instance['field_name'];
    $field = field_info_field($field_name);

    if ($field['type'] == 'image') {
      $form[$field_name]['#fieldset'] = 'product_image';
      $imagefields[] = $field_name;
    }
    elseif (!in_array($field_name, $attributes)) {
      $form[$field_name]['#fieldset'] = 'product_details';
    }
  }

  if (count($imagefields) == 0) {
    // The fieldset is empty, hide it.
    $form['product_image']['#access'] = FALSE;
  }
  elseif (count($imagefields) == 1) {
    // There's only one image on the product, no need to show its title, the
    // fieldset title is enough.
    $field_name = reset($imagefields);
    $language = $form[$field_name]['#language'];
    unset($form[$field_name][$language][0]['#title']);
  }

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

/**
 * Returns a list of field names that are used as attributes for the given
 * product type.
 *
 * Copied from commerce_cart.module
 */
function _commerce_ipf_commerce_product_attributes($type) {
  $attributes = array();
  // Loop through all the field instances on that product type.
  foreach (field_info_instances('commerce_product', $type) as $name => $instance) {
    // A field qualifies if it is single value, required and uses a widget
    // with a definite set of options. For the sake of simplicity, this is
    // currently restricted to fields defined by the options module.
    $field = field_info_field($instance['field_name']);

    // Get the array of Cart settings pertaining to this instance.
    $commerce_cart_settings = commerce_cart_field_instance_attribute_settings($instance);

    // If the instance is of a field type that is eligible to function as
    // a product attribute field and if its attribute field settings
    // specify that this functionality is enabled...
    if (commerce_cart_field_attribute_eligible($field) && $commerce_cart_settings['attribute_field']) {
      $attributes[] = $field['field_name'];
    }
  }

  return $attributes;
}
