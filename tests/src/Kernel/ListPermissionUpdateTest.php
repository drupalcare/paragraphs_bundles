<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The post-update that keeps the Paragraphs List open to the roles that had it.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ListPermissionUpdateTest extends KernelTestBase {

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
    $module_path = $this->container->get('extension.list.module')->getPath('paragraphs_bundles');
    require_once $this->root . '/' . $module_path . '/paragraphs_bundles.post_update.php';
  }

  /**
   * Only roles that administer the site get the permission; twice is harmless.
   */
  public function testGrantsOnlyToRolesThatAdministerTheSite(): void {
    Role::create(['id' => 'site_admin', 'label' => 'Site admin', 'permissions' => ['administer site configuration']])->save();
    Role::create(['id' => 'editor', 'label' => 'Editor', 'permissions' => ['access content']])->save();
    Role::create([
      'id' => 'already',
      'label' => 'Already',
      'permissions' => ['administer site configuration', 'access paragraphs list'],
    ])->save();
    Role::create(['id' => 'root', 'label' => 'Root', 'is_admin' => TRUE])->save();

    $message = paragraphs_bundles_post_update_grant_paragraphs_list_permission();
    $this->assertStringContainsString('site_admin', $message);
    $this->assertStringNotContainsString('already', $message);

    $this->assertTrue(Role::load('site_admin')->hasPermission('access paragraphs list'));
    $this->assertFalse(Role::load('editor')->hasPermission('access paragraphs list'), 'A role without site administration is not granted it.');
    $this->assertTrue(Role::load('already')->hasPermission('access paragraphs list'));
    $this->assertSame([], Role::load('root')->getPermissions(), 'An admin role is left alone.');

    $this->assertStringContainsString('No role needed', paragraphs_bundles_post_update_grant_paragraphs_list_permission());
  }

}
