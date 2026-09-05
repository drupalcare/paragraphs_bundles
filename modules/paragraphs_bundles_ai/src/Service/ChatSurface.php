<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs_bundles_ai\Model\SurfaceDecision;

/**
 * Decides whether the node-form chat bar appears, and builds it.
 *
 * WHY THIS IS A SERVICE AND NOT A HOOK BODY. Four preconditions gate the bar,
 * none of them visible from the node form, and a hook that returned early four
 * times would be untestable without building a whole form. Here each refusal
 * is one assertion.
 *
 * WHY NO ASSISTANT IS SHIPPED. It would be the obvious convenience, and it
 * cannot work: `ai_assistant` entities require an `llm_provider` and an
 * `llm_model`, both of which name something specific to one site. Shipping a
 * default would install an assistant pointing at a provider that may not exist
 * — a thing that appears configured and fails on first use, which is worse
 * than an empty setting that says what to do. The site builder makes an
 * assistant; this module asks which one to use, and nothing more.
 *
 * IT NEVER WRITES CONFIGURATION. Placing this bar does not alter the node form
 * display, the block layout, or the assistant. The only configuration it reads
 * is its own setting, and the only thing that writes that setting is a person
 * on the settings form.
 */
final class ChatSurface implements ChatSurfaceInterface {

  /**
   * The chat block shipped by ai_chatbot.
   */
  private const string BLOCK_ID = 'ai_deepchat_block';

  /**
   * The chat processor that routes a conversation to an assistant.
   *
   * Supplied by ai_assistant_api, whose presence is checked before this is
   * used. The block will not render without one.
   */
  private const string PROCESSOR_ID = 'ai_assistant_api_processor';

  /**
   * Constructs the surface.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Reads which assistant the site chose.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Checks for ai_chatbot, which is not a dependency of this module.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Confirms the chosen assistant still exists.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Checked for the permission this module adds.
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   Builds the chat block.
   * @param \Drupal\paragraphs_bundles_ai\Service\TargetResolverInterface $resolver
   *   Names the content types that actually have a paragraph field.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly BlockManagerInterface $blockManager,
    private readonly TargetResolverInterface $resolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function decide(?string $contentType = NULL): SurfaceDecision {
    // Permission first. Someone without it does not need to hear about the
    // site's AI configuration, and checking it last would leak that anyway
    // through the reason text.
    if (!$this->currentUser->hasPermission(self::PERMISSION)) {
      return SurfaceDecision::hide(
        'You do not have the "use the Paragraphs Bundles assistant" permission.',
      );
    }

    if (!$this->moduleHandler->moduleExists('ai_chatbot')) {
      return SurfaceDecision::hide(
        'The AI Chatbot module (ai_chatbot, part of drupal/ai) is not enabled, so there is no chat bar to show.',
      );
    }

    if (!$this->moduleHandler->moduleExists('ai_assistant_api')) {
      return SurfaceDecision::hide(
        'The AI Assistant API module (ai_assistant_api, part of drupal/ai) is not enabled, so there is no assistant to talk to.',
      );
    }

    // Config values are mixed. Casting one is an assumption; this is a check.
    $setting = $this->configFactory
      ->get('paragraphs_bundles_ai.settings')
      ->get('assistant');
    $chosen = is_string($setting) ? trim($setting) : '';

    if ($chosen === '') {
      return SurfaceDecision::hide(
        'No assistant has been chosen yet. Create one at Configuration › AI › AI Assistants, then choose it on this page.',
      );
    }

    // An assistant that was chosen and later deleted is the case that would
    // otherwise fail as a broken block rather than as a hidden one.
    //
    // AND LOADING IT CAN THROW, which is not defensive theatre — it was
    // measured on sts.local 2026-09-04. A stored assistant whose `description`
    // is null cannot be constructed at all: the contrib entity types that
    // property as a non-nullable string, so EntityBase's constructor raises a
    // TypeError before the storage can return NULL. Config written by an older
    // version, a partial import, or another module's half-finished save all
    // produce it.
    //
    // Unhandled, that TypeError does not break this bar — it breaks EVERY node
    // add and edit form on the site, because this runs from form_alter. A
    // surface whose entire promise is "absent unless wanted" must not be able
    // to take the node form down with it. So the throw is caught HERE, around
    // this one statement, and turned into the same visible refusal as every
    // other missing precondition. It is never swallowed: the message goes into
    // the reason the settings page prints.
    try {
      $exists = $this->entityTypeManager
        ->getStorage('ai_assistant')
        ->load($chosen);
    }
    catch (\Throwable $error) {
      return SurfaceDecision::hide(sprintf(
        'The chosen assistant "%s" is stored but cannot be loaded (%s). Its configuration is damaged — re-save it, or choose another one.',
        $chosen,
        $error->getMessage(),
      ));
    }

    if ($exists === NULL) {
      return SurfaceDecision::hide(sprintf(
        'The chosen assistant "%s" no longer exists. Choose another one.',
        $chosen,
      ));
    }

    // A content type with no paragraph reference field cannot take sections.
    // Offering the assistant there invites a conversation whose only possible
    // outcome is a refusal — the tools would work correctly and the person
    // would still have wasted their time. Checked last because it is the only
    // precondition that depends on WHICH form is being built.
    if ($contentType !== NULL && $contentType !== '') {
      $usable = $this->resolver->contentTypesWithParagraphFields();

      if (!isset($usable[$contentType])) {
        return SurfaceDecision::hide(sprintf(
          'The content type "%s" has no paragraph field, so there is nowhere here for the assistant to add sections. It appears on: %s.',
          $contentType,
          $usable === [] ? 'no content type on this site' : implode(', ', array_keys($usable)),
        ));
      }
    }

    return SurfaceDecision::show($chosen);
  }

  /**
   * {@inheritdoc}
   */
  public function build(?string $contentType = NULL): array {
    $decision = $this->decide($contentType);

    if (!$decision->show) {
      return [];
    }

    // TWO THINGS HERE ARE NOT OBVIOUS, and both were measured on sts.local
    // 2026-09-04 rather than reasoned about.
    //
    // First, the assistant is chosen through the chat PROCESSOR's
    // `assistant_id`, not through a top-level key on the block. The block's
    // own `ai_assistant` key exists but only feeds an ajax callback on its
    // configuration form; setting it alone selects nothing.
    //
    // Second, `chat_processor_plugin` has to be named explicitly. The block's
    // defaultConfiguration() leaves it as an empty string, and its build()
    // passes that straight to createInstance() — so the default configuration
    // of this block throws PluginNotFoundException on render. That failure
    // does not announce itself from inside form_alter: the bar was simply
    // absent, which is the exact "control that silently does nothing" this
    // module is supposed to be free of.
    $block = $this->blockManager->createInstance(self::BLOCK_ID, [
      'chat_processor_plugin' => self::PROCESSOR_ID,
      'plugin_configuration' => [
        'assistant_id' => $decision->assistant,
        'stream_output' => FALSE,
        'verbose_mode' => FALSE,
      ],
      'bot_name' => 'Sections',
      // THE TWO KEYS BELOW WERE BOTH WRONG IN THE FIRST VERSION, and only a
      // browser could show it — the block took the values without complaint
      // and did something else.
      //
      // `toggle_state` accepts exactly 'remember', 'open' or 'close'. It was
      // set to 'collapsed', which is not one of them, so the panel opened. An
      // invalid value here does not warn: it is read, ignored, and the control
      // silently does nothing. 'close' is the brief's "hidden until you click
      // it", and it is preferred over 'remember' so that a node form never
      // opens with a chat panel over the fields because of something the
      // person did yesterday.
      'toggle_state' => 'close',
      // `placement` defaults to 'toolbar', which styles the widget for
      // Drupal's toolbar. Left at the default it rendered 679px tall inside
      // the node form, measured on sts.local 2026-09-04 — the opposite of a
      // bar that stays out of the way. 'bottom-right' floats it instead.
      'placement' => 'bottom-right',
      'collapse_minimal' => TRUE,
      'first_message' => 'Describe the sections you want — say the content type, the section, and how many.',
    ]);

    // The manager's return is only `object` to a static analyser, and a block
    // plugin that is not one would fail at ->build() rather than here.
    if (!$block instanceof BlockPluginInterface) {
      return [];
    }

    return [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => ['class' => ['paragraphs-bundles-ai-chat']],
      'chat' => $block->build(),
      // The bar depends on a setting, an entity and the user's permissions.
      // Without these a cached form shows one person's answer to another.
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'config:paragraphs_bundles_ai.settings',
          'config:ai_assistant_api.ai_assistant.' . $decision->assistant,
        ],
      ],
    ];
  }

}
