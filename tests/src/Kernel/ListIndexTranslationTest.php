<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\paragraphs_bundles\Traits\ListIndexHostTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The stored label is always the default-language label.
 *
 * The row's langcode is the paragraph's default language, so its label must be
 * computed in that language too; saving a translation must not flip it.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ListIndexTranslationTest extends KernelTestBase {

  use ListIndexHostTrait;

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'image',
    'link',
    'options',
    'views',
    'media',
    'media_library',
    'block',
    'block_content',
    'node',
    'language',
    'content_translation',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('media');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('paragraphs_bundles', ['paragraphs_bundles_list_index']);
    $this->createListIndexHosts();
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $manager = $this->container->get('content_translation.manager');
    $manager->setEnabled('node', 'page', TRUE);
    $manager->setEnabled('paragraph', 'container', TRUE);
    $manager->setEnabled('paragraph', 'leaf', TRUE);
  }

  /**
   * Saving a translation of a paragraph keeps the default-language label.
   */
  public function testSavingTranslationDoesNotChangeStoredLabel(): void {
    $top = $this->buildChain();
    $node = $this->saveNode('Hello', [$top]);
    $node->addTranslation('fr', ['title' => 'Bonjour'])->save();

    $pid = (int) $top->id();
    $before = $this->indexRows()[$pid]['label'];
    $this->assertStringStartsWith('Hello > ', (string) $before);

    $paragraph = $this->container->get('entity_type.manager')->getStorage('paragraph')->load($pid);
    $this->assertTrue($paragraph->isTranslatable(), 'The paragraph can be translated in this setup.');
    $translation = $paragraph->addTranslation('fr', $paragraph->toArray());
    $translation->save();

    $row = $this->indexRows()[$pid];
    $this->assertSame($before, $row['label'], 'The translation save left the label alone.');
    $this->assertSame('en', $row['langcode']);
  }

}
