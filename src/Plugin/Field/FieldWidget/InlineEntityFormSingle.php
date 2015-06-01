<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormSingle.
 */

namespace Drupal\inline_entity_form\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\inline_entity_form\Plugin\Field\InlineEntityWidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Single value widget.
 *
 * @ingroup field_widget
 *
 * @FieldWidget(
 *   id = "inline_entity_form_single",
 *   label = @Translation("Inline entity form - Single value"),
 *   multiple_values = FALSE,
 *   field_types = {
 *     "entity_reference"
 *   },
 * )
 */
class InlineEntityFormSingle extends InlineEntityWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $ief_id = $this->fieldDefinition;

    return $element;
  }
}
