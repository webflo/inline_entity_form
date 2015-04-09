<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Tests\CustomFormTest.
 */

namespace Drupal\inline_entity_form\Tests;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the IEF element on a custom form.
 *
 * @group inline_entity_form
 */
class CustomFormTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['inline_entity_form_test'];

  /**
   * Tests IEF on a custom form.
   */
  public function testDisplayPluginAlterHooks() {
    $this->drupalGet('ief-test');
    $this->assertText(t('Title'), 'Title field found on the form.');

    $edit = ['inline_entity_form[title][0][value]' => $this->randomString()];
    $this->drupalPostForm('ief-test', $edit, t('Save'));
    $message = t('Created @entity_type @label.', ['@entity_type' => t('Content'), '@label' => $edit['inline_entity_form[title][0][value]']]);
    $this->assertText($message, 'Status message found on the page.');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity.manager')->getStorage('node')->load(1);
    $this->assertEqual($node->label(), $edit['inline_entity_form[title][0][value]'], 'Node title correctly saved to the database.');
    $this->assertEqual($node->bundle(), 'ief_test', 'Correct bundle used when creating the new node.');
  }

}
