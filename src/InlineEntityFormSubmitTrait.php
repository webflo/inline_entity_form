<?php
/**
 * @file
 * Contains \Drupal\inline_entity_form\InlineEntityFormSubmitTrait.
 */

namespace Drupal\inline_entity_form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Useful functions for handling IEF submit in custom forms.
 */
trait InlineEntityFormSubmitTrait {

  /**
   * Submits IEF child form.
   *
   * @param $element
   *   IEF form element.
   * @param FormStateInterface $form_state
   *   Current state of the form.
   */
  protected function submitIef(&$element, FormStateInterface $form_state) {
    foreach ($element['#ief_element_submit'] as $ief_submit_callback) {
      if (is_callable($ief_submit_callback)) {
        $ief_submit_callback($element, $form_state);
      }
    }
  }

}
