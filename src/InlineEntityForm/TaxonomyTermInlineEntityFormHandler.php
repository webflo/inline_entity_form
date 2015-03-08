<?php

/**
 * Contains \Drupal\inline_entity_form\InlineEntityForm\TaxonomyTermInlineEntityFormHandler.
 */

namespace Drupal\inline_entity_form\InlineEntityForm;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Taxonomy term inline form handler.
 */
class TaxonomyTermInlineEntityFormHandler extends EntityInlineEntityFormHandler {

  /**
   * {@inheritdoc}
   */
  public function labels() {
    $labels = [
      'singular' => t('term'),
      'plural' => t('terms'),
    ];
    return $labels;
  }

  /**
   * Overrides EntityInlineEntityFormHandler::tableFields().
   *
   * We can't use the parent class method because the taxonomy term metadata
   * wrapper doesn't have a property that matches the entity bundle key.
   * @todo: Remove this method once http://drupal.org/node/1662558 is fixed.
   */
  public function tableFields($bundles) {
    $fields = array();

    $info = $this->entityManager->getDefinition($this->entityTypeId);
    $metadata = entity_get_property_info($this->entityType);

    $label_key = $info['entity_keys']['label'];
    $fields[$label_key] = array(
      'type' => 'property',
      'label' => $metadata ? $metadata[$label_key]['label'] : t('Label'),
      'weight' => 1,
    );

    // Add the vocabulary type.
    $fields['vocabulary'] = array(
      'type' => 'property',
      'label' => t('Vocabulary'),
      'weight' => 2,
    );

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function entityForm($entity_form, FormStateInterface $form_state) {
    $term = $entity_form['#entity'];
    $extra_fields = field_info_extra_fields('taxonomy_term', $term->vocabulary_machine_name, 'form');

    $defaults = array(
      'name' => '',
      'description' => '',
      'format' => NULL,
      'tid' => NULL,
      'weight' => 0,
    );
    foreach ($defaults as $key => $value) {
      if (!isset($term->$key)) {
        $term->$key = $value;
      }
    }

    $entity_form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#default_value' => $term->name,
      '#maxlength' => 255,
      '#required' => TRUE,
      // The label might be missing if the Title module has replaced it.
      '#weight' => !empty($extra_fields['name']) ? $extra_fields['name']['weight'] : -5,
    );
    $entity_form['description'] = array(
      '#type' => 'text_format',
      '#title' => t('Description'),
      '#default_value' => $term->description,
      '#format' => $term->format,
      '#weight' => $extra_fields['description']['weight'],
    );

    $langcode = $term->language->id();
    field_attach_form($term, $entity_form, $form_state, $langcode);

    return $entity_form;
  }

  /**
   * {@inheritdoc}
   */
  public function entityFormSubmit(&$entity_form, FormStateInterface $form_state) {
    parent::entityFormSubmit($entity_form, $form_state);

    $entity = $entity_form['#entity'];

    // Set the vocabulary ID.
    $vocabularies = taxonomy_vocabulary_get_names();
    if (isset($vocabularies[$entity->vocabulary_machine_name])) {
      $entity->vid = $vocabularies[$entity->vocabulary_machine_name]->vid;
    }

    // Separate the description and format.
    $entity->format = $entity->description['format'];
    $entity->description = $entity->description['value'];
  }

}
