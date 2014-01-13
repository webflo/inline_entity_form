<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormSingle.
 */

namespace Drupal\inline_entity_form\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;

/**
 * Single value widget.
 *
 * @FieldWidget(
 *   id = "inline_entity_form_single",
 *   label = @Translation("Inline entity form - Single value"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class InlineEntityFormSingle extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, array &$form_state) {
    $ief_id = $this->fieldDefinition;

    return $element;
  }

}
