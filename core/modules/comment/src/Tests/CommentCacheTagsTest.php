<?php

/**
 * @file
 * Contains \Drupal\comment\Tests\CommentCacheTagsTest.
 */

namespace Drupal\comment\Tests;

use Drupal\comment\CommentInterface;
use Drupal\comment\CommentManagerInterface;
use Drupal\comment\Entity\Comment;
use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\system\Tests\Entity\EntityWithUriCacheTagsTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests the Comment entity's cache tags.
 *
 * @group comment
 */
class CommentCacheTagsTest extends EntityWithUriCacheTagsTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = array('comment');

  /**
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entityTestCamelid;

  /**
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entityTestHippopotamidae;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Give anonymous users permission to view comments, so that we can verify
    // the cache tags of cached versions of comment pages.
    $user_role = Role::load(RoleInterface::ANONYMOUS_ID);
    $user_role->grantPermission('access comments');
    $user_role->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a "bar" bundle for the "entity_test" entity type and create.
    $bundle = 'bar';
    entity_test_create_bundle($bundle, NULL, 'entity_test');

    // Create a comment field on this bundle.
    $this->addDefaultCommentField('entity_test', 'bar', 'comment');

    // Display comments in a flat list; threaded comments are not render cached.
    $field = FieldConfig::loadByName('entity_test', 'bar', 'comment');
    $field->setSetting('default_mode', CommentManagerInterface::COMMENT_MODE_FLAT);
    $field->save();

    // Create a "Camelids" test entity that the comment will be assigned to.
    $this->entityTestCamelid = entity_create('entity_test', array(
      'name' => 'Camelids',
      'type' => 'bar',
    ));
    $this->entityTestCamelid->save();

    // Create a "Llama" comment.
    $comment = entity_create('comment', array(
      'subject' => 'Llama',
      'comment_body' => array(
        'value' => 'The name "llama" was adopted by European settlers from native Peruvians.',
        'format' => 'plain_text',
      ),
      'entity_id' => $this->entityTestCamelid->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'comment',
      'status' => \Drupal\comment\CommentInterface::PUBLISHED,
    ));
    $comment->save();

    return $comment;
  }

  /**
   * Test that comments correctly invalidate the cache tag of their host entity.
   */
  public function testCommentEntity() {
    $this->verifyPageCache($this->entityTestCamelid->urlInfo(), 'MISS');
    $this->verifyPageCache($this->entityTestCamelid->urlInfo(), 'HIT');

    // Create a "Hippopotamus" comment.
    $this->entityTestHippopotamidae = entity_create('entity_test', array(
      'name' => 'Hippopotamus',
      'type' => 'bar',
    ));
    $this->entityTestHippopotamidae->save();

    $this->verifyPageCache($this->entityTestHippopotamidae->urlInfo(), 'MISS');
    $this->verifyPageCache($this->entityTestHippopotamidae->urlInfo(), 'HIT');

    $hippo_comment = Comment::create(array(
      'subject' => 'Hippopotamus',
      'comment_body' => array(
        'value' => 'The common hippopotamus (Hippopotamus amphibius), or hippo, is a large, mostly herbivorous mammal in sub-Saharan Africa',
        'format' => 'plain_text',
      ),
      'entity_id' => $this->entityTestHippopotamidae->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'comment',
      'status' => CommentInterface::PUBLISHED,
    ));
    $hippo_comment->save();

    // Ensure that a new comment only invalidates the commented entity.
    $this->verifyPageCache($this->entityTestCamelid->urlInfo(), 'HIT');
    $this->verifyPageCache($this->entityTestHippopotamidae->urlInfo(), 'MISS');
    $this->assertText($hippo_comment->getSubject());

    // Ensure that updating an existing comment only invalidates the commented
    // entity.
    $this->entity->save();
    $this->verifyPageCache($this->entityTestCamelid->urlInfo(), 'MISS');
    $this->verifyPageCache($this->entityTestHippopotamidae->urlInfo(), 'HIT');
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdditionalCacheContextsForEntity(EntityInterface $entity) {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * Each comment must have a comment body, which always has a text format.
   */
  protected function getAdditionalCacheTagsForEntity(EntityInterface $entity) {
    /** @var \Drupal\comment\CommentInterface $entity */
    return array(
      'config:filter.format.plain_text',
      'user:' . $entity->getOwnerId(),
      'user_view',
    );
  }

}
