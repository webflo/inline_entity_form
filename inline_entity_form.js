/**
 * @file
 * Provides JavaScript for Inline Entity Form.
 */

(function ($) {

/**
 * Allows submit buttons in entity forms to trigger uploads by undoing
 * work done by Drupal.behaviors.fileButtons.
 */
Drupal.behaviors.inlineEntityForm = {
  attach: function (context) {
    $('input.ief-entity-submit', context).unbind('mousedown', Drupal.file.disableFields);
  },
  detach: function (context) {
    $('input.form-submit', context).bind('mousedown', Drupal.file.disableFields);
  }
};

})(jQuery);
