<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileRepositoryInterface;
use Drupal\paragraphs_bundles_ai\Model\FieldFact;

/**
 * Default supplier of the shipped placeholder picture.
 *
 * WHERE THE PICTURE COMES FROM. `images/pb-ai-placeholder.png` ships inside
 * this module: 1600x900, drawn with GD rather than downloaded, so there is no
 * third-party licence travelling with a module that goes to drupal.org. It is
 * copied into the site's own files the first time something asks for it.
 *
 * ONE FILE, ONE MEDIA ITEM, HOWEVER MANY SECTIONS. Both lookups find what is
 * already there before creating anything, keyed by the destination uri and by
 * the file the media item points at. A page of twelve sections therefore adds
 * one file to the site, not twelve, and a person who replaces the picture on
 * one section does not silently change the other eleven — they each hold their
 * own reference to a shared file, which is how Drupal media works everywhere
 * else.
 *
 * IT REFUSES RATHER THAN GUESSES. Media not installed, the media type missing,
 * its source field unreadable, the file unwritable: each returns NULL, and the
 * composer turns that into a sentence telling the person to add a picture
 * themselves. A silent empty field is the failure this class exists to end.
 */
final class DefaultImage implements DefaultImageInterface {

  use StringTranslationTrait;

  /**
   * The file that ships with the module.
   */
  private const string SHIPPED = 'images/pb-ai-placeholder.png';

  /**
   * Where it is copied to inside the site's public files.
   */
  private const string DESTINATION = 'public://pb-ai/pb-ai-placeholder.png';

  /**
   * Constructs the supplier.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Loads and creates files, media items and media types.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleList
   *   Finds this module on disk, so the shipped file is read from where it
   *   actually is rather than from a path assembled by hand.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Creates the destination directory.
   * @param \Drupal\file\FileRepositoryInterface|null $fileRepository
   *   Writes the bytes and registers the managed file. NULL when the File
   *   module is not installed, in which case no picture can be supplied at
   *   all — the '@?' in services.yml is what makes that a NULL rather than a
   *   container that will not compile.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleExtensionList $moduleList,
    private readonly FileSystemInterface $fileSystem,
    private readonly ?FileRepositoryInterface $fileRepository,
  ) {}

  /**
   * Alt text for the picture.
   *
   * REQUIRED, NOT DECORATION. `pb_content_ai_image` sets alt_field_required,
   * measured 2026-09-17, so a value without alt text does not save. It also
   * says what the picture is, which is the honest thing for a screen reader to
   * announce about a stand-in nobody chose.
   *
   * A METHOD AND NOT A CONSTANT, WHICH IS THE WHOLE REASON IT MOVED: a PHP
   * constant cannot hold a t() call, so as long as this was `private const
   * ALT` the one string a screen reader actually announces was the one string
   * in this module that could never be translated.
   *
   * @return string
   *   The alt text, in the language of the person the page is being built for.
   */
  private function alt(): string {
    return (string) $this->t('Placeholder picture supplied by Paragraphs Bundles AI. Replace it with your own.');
  }

  /**
   * {@inheritdoc}
   */
  public function valueFor(FieldFact $fact): ?array {
    if (!$fact->isPicture()) {
      return NULL;
    }

    if ($fact->type === 'image') {
      $fileId = $this->fileId();

      return $fileId === NULL ? NULL : ['target_id' => $fileId, 'alt' => $this->alt()];
    }

    $bundle = $this->mediaBundleFor($fact);

    if ($bundle === NULL) {
      return NULL;
    }

    $mediaId = $this->mediaId($bundle);

    return $mediaId === NULL ? NULL : ['target_id' => $mediaId];
  }

  /**
   * {@inheritdoc}
   */
  public function fileId(): ?int {
    if ($this->fileRepository === NULL) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('file');
    $existing = $storage->loadByProperties(['uri' => self::DESTINATION]);

    if ($existing !== []) {
      $file = reset($existing);

      return (int) $file->id();
    }

    $source = $this->moduleList->getPath('paragraphs_bundles_ai') . '/' . self::SHIPPED;

    if (!is_file($source)) {
      return NULL;
    }

    $data = file_get_contents($source);

    if ($data === FALSE) {
      return NULL;
    }

    $directory = dirname(self::DESTINATION);

    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      return NULL;
    }

    $file = $this->fileRepository->writeData($data, self::DESTINATION, FileExists::Replace);

    return (int) $file->id();
  }

  /**
   * {@inheritdoc}
   */
  public function mediaId(string $mediaBundle): ?int {
    if (!$this->entityTypeManager->hasDefinition('media')) {
      return NULL;
    }

    $sourceField = $this->sourceField($mediaBundle);

    if ($sourceField === NULL) {
      return NULL;
    }

    $fileId = $this->fileId();

    if ($fileId === NULL) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('media');

    // REUSE BEFORE CREATE, and keyed by the FILE rather than by the name. A
    // person may rename the media item; it is still the same picture, and
    // making a second one because the name changed would litter their library.
    $found = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', $mediaBundle)
      ->condition($sourceField . '.target_id', $fileId)
      ->range(0, 1)
      ->execute();

    if ($found !== []) {
      return (int) reset($found);
    }

    $media = $storage->create([
      'bundle' => $mediaBundle,
      'name' => (string) $this->t('Placeholder picture'),
      'status' => 1,
      $sourceField => ['target_id' => $fileId, 'alt' => $this->alt()],
    ]);
    $media->save();

    return (int) $media->id();
  }

  /**
   * The media type to make, for one reference field.
   *
   * THE EMPTY-MEANS-ALL TRAP AGAIN. A media reference that accepts everything
   * stores an EMPTY list, not a list of every type — so an empty `accepts` is
   * not "no types allowed", it is "any". In that case the first media type
   * whose source is an image is the right answer, and picking one that is not
   * an image source would create a media item that can never hold a picture.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\FieldFact $fact
   *   The reference field.
   *
   * @return string|null
   *   A media type id, or NULL when the site has no image media type at all.
   */
  private function mediaBundleFor(FieldFact $fact): ?string {
    if ($fact->accepts !== []) {
      return $fact->accepts[0];
    }

    if (!$this->entityTypeManager->hasDefinition('media_type')) {
      return NULL;
    }

    foreach ($this->entityTypeManager->getStorage('media_type')->loadMultiple() as $id => $type) {
      if (method_exists($type, 'getSource') && $type->getSource()->getPluginId() === 'image') {
        return (string) $id;
      }
    }

    return NULL;
  }

  /**
   * The field a media type keeps its file in.
   *
   * @param string $mediaBundle
   *   Media type id.
   *
   * @return string|null
   *   The source field name, or NULL when the type is missing or does not say.
   */
  private function sourceField(string $mediaBundle): ?string {
    if (!$this->entityTypeManager->hasDefinition('media_type')) {
      return NULL;
    }

    $type = $this->entityTypeManager->getStorage('media_type')->load($mediaBundle);

    if ($type === NULL || !method_exists($type, 'getSource')) {
      return NULL;
    }

    $configured = $type->getSource()->getConfiguration()['source_field'] ?? '';
    $configured = is_string($configured) ? trim($configured) : '';

    return $configured === '' ? NULL : $configured;
  }

}
