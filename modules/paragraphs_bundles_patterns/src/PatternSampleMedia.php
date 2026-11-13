<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_patterns;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\MediaInterface;

/**
 * Supplies the sample pictures as saved files and reused media items.
 *
 * Each picture is saved once per site, the first time a Pattern needs it. A
 * fixed UUID finds it again, so inserting many Patterns adds no new media.
 */
final class PatternSampleMedia {

  use StringTranslationTrait;

  /**
   * The generated pictures, by shape. See scripts/make-sample-pictures.py.
   */
  private const PICTURES = [
    'landscape' => ['sample-landscape-1.jpg', 'sample-landscape-2.jpg', 'sample-landscape-3.jpg'],
    'portrait' => ['sample-portrait-1.jpg', 'sample-portrait-2.jpg', 'sample-portrait-3.jpg'],
    'square' => ['sample-square-1.jpg', 'sample-square-2.jpg'],
  ];

  private const DIRECTORY = 'public://pb-patterns/samples';

  /**
   * How many pictures of each shape this build has used.
   *
   * @var array<string, int>
   */
  private array $used = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Starts a new build, so its pictures rotate from the first one.
   */
  public function reset(): void {
    $this->used = [];
  }

  /**
   * Returns the field value for one sample picture.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   A media reference field or an image field.
   * @param string $shape
   *   The picture's shape: landscape, portrait or square.
   * @param list<\Drupal\Core\StringTranslation\TranslatableMarkup> $messages
   *   Collects a note when the picture has to be left out.
   *
   * @return array<string, mixed>|null
   *   The field value, or NULL to leave the field empty.
   */
  public function fieldValue(FieldDefinitionInterface $definition, string $shape, array &$messages): ?array {
    $names = self::PICTURES[$shape] ?? self::PICTURES['landscape'];
    $this->used[$shape] = ($this->used[$shape] ?? 0) + 1;
    $name = $names[($this->used[$shape] - 1) % count($names)];
    $alt = (string) $this->t('Sample picture of abstract shapes');
    $file = $this->file($name);
    if ($file === NULL) {
      $messages[] = $this->t('A sample picture could not be saved. Add your own picture to the section.');
      return NULL;
    }
    if ($definition->getType() === 'image') {
      return ['target_id' => $file->id(), 'alt' => $alt];
    }
    if ($definition->getType() !== 'entity_reference' || $definition->getSetting('target_type') !== 'media') {
      return NULL;
    }
    foreach ($this->imageTypes($definition) as $bundle => $sourceField) {
      if (!$this->entityTypeManager->getAccessControlHandler('media')->createAccess($bundle, $this->currentUser)) {
        $messages[] = $this->t('A sample picture was left out because you may not create that kind of media. Add your own picture to the section.');
        continue;
      }
      $media = $this->media($bundle, $sourceField, $file, $alt);
      return ['target_id' => $media->id()];
    }
    return NULL;
  }

  /**
   * Lists the media types of a field that hold an image.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   A media reference field.
   *
   * @return array<string, string>
   *   Source field names keyed by media type.
   */
  private function imageTypes(FieldDefinitionInterface $definition): array {
    $settings = $definition->getSetting('handler_settings');
    $bundles = is_array($settings) && is_array($settings['target_bundles'] ?? NULL) ? $settings['target_bundles'] : [];
    $storage = $this->entityTypeManager->getStorage('media_type');
    $types = [];
    foreach ($bundles === [] ? $storage->loadMultiple() : $storage->loadMultiple(array_values($bundles)) as $id => $type) {
      /** @var \Drupal\media\MediaTypeInterface $type */
      $source = $type->getSource();
      if ($source->getPluginId() === 'image') {
        $types[(string) $id] = (string) $source->getConfiguration()['source_field'];
      }
    }
    return $types;
  }

  /**
   * Returns the saved file for a picture, saving it the first time.
   *
   * @param string $name
   *   The picture's file name.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file, or NULL when it cannot be saved.
   */
  private function file(string $name): ?FileInterface {
    $uri = self::DIRECTORY . '/' . $name;
    $storage = $this->entityTypeManager->getStorage('file');
    $found = $storage->loadByProperties(['uri' => $uri]);
    $file = reset($found);
    if ($file instanceof FileInterface) {
      return $file;
    }
    $source = $this->moduleExtensionList->getPath('paragraphs_bundles_patterns') . '/images/samples/' . $name;
    $directory = self::DIRECTORY;
    $data = file_get_contents($source);
    if ($data === FALSE || !$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      return NULL;
    }
    return $this->fileRepository->writeData($data, $uri, FileExists::Replace);
  }

  /**
   * Returns the one shared media item for a file.
   *
   * @param string $bundle
   *   The media type.
   * @param string $sourceField
   *   Its image field.
   * @param \Drupal\file\FileInterface $file
   *   The picture.
   * @param string $alt
   *   The alternative text.
   *
   * @return \Drupal\media\MediaInterface
   *   The media item.
   */
  private function media(string $bundle, string $sourceField, FileInterface $file, string $alt): MediaInterface {
    $uuid = $this->uuid($bundle . ':' . $file->getFilename());
    $existing = $this->load($uuid);
    if ($existing !== NULL) {
      return $existing;
    }
    $media = $this->entityTypeManager->getStorage('media')->create([
      'uuid' => $uuid,
      'bundle' => $bundle,
      'name' => $alt,
      'uid' => $this->currentUser->id(),
      'status' => TRUE,
      $sourceField => ['target_id' => $file->id(), 'alt' => $alt],
    ]);
    assert($media instanceof MediaInterface);
    try {
      $media->save();
    }
    catch (EntityStorageException $e) {
      // Another request created it between the load and the save.
      return $this->load($uuid) ?? throw $e;
    }
    return $media;
  }

  /**
   * Loads a media item by UUID.
   *
   * @param string $uuid
   *   The UUID.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The media item, if it exists.
   */
  private function load(string $uuid): ?MediaInterface {
    $found = $this->entityTypeManager->getStorage('media')->loadByProperties(['uuid' => $uuid]);
    $media = reset($found);
    return $media instanceof MediaInterface ? $media : NULL;
  }

  /**
   * Makes the fixed UUID of a sample media item.
   *
   * @param string $name
   *   What identifies the item.
   *
   * @return string
   *   A name-based UUID, the same on every call.
   */
  private function uuid(string $name): string {
    $hash = md5('paragraphs_bundles_patterns:' . $name);
    $hash[12] = '3';
    $hash[16] = dechex((hexdec($hash[16]) & 0x3) | 0x8);
    $uuid = sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    assert(Uuid::isValid($uuid));
    return $uuid;
  }

}
