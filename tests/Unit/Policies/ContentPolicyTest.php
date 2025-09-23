<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Content;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Stakeholder;
use App\Models\WorkstreamPermission;
use App\Policies\ContentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ContentPolicy $policy;
    private User $user;
    private User $contentOwner;
    private User $workstreamOwner;
    private User $unrelatedUser;
    private Workstream $workstream;
    private Release $release;
    private Content $content;
    private Content $privateContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ContentPolicy();

        // Create test users
        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->contentOwner = User::factory()->create(['name' => 'Content Owner']);
        $this->workstreamOwner = User::factory()->create(['name' => 'Workstream Owner']);
        $this->unrelatedUser = User::factory()->create(['name' => 'Unrelated User']);

        // Create workstream and release
        $this->workstream = Workstream::factory()->create([
            'name' => 'Test Workstream',
            'owner_id' => $this->workstreamOwner->id,
        ]);

        $this->release = Release::factory()->create([
            'name' => 'Test Release',
            'workstream_id' => $this->workstream->id,
        ]);

        // Create content
        $this->content = Content::factory()->create([
            'user_id' => $this->contentOwner->id,
            'type' => 'brain_dump',
            'title' => 'Test Brain Dump',
            'content' => 'This is test content',
            'status' => 'processed',
        ]);

        // Create private content
        $this->privateContent = Content::factory()->create([
            'user_id' => $this->contentOwner->id,
            'type' => 'private_note',
            'title' => 'Private Note',
            'content' => 'This is private content',
            'status' => 'processed',
        ]);
    }

    /** @test */
    public function test_user_can_view_their_own_content()
    {
        // Given: A user who owns the content
        // When: They try to view their content
        $result = $this->policy->view($this->contentOwner, $this->content);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_create_content()
    {
        // Given: Any authenticated user
        // When: They try to create content
        $result = $this->policy->create($this->user);

        // Then: Access should be granted (users can create their own content)
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_update_their_own_content()
    {
        // Given: A user who owns the content
        // When: They try to update their content
        $result = $this->policy->update($this->contentOwner, $this->content);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_update_other_users_content()
    {
        // Given: A user who does not own the content
        // When: They try to update someone else's content
        $result = $this->policy->update($this->unrelatedUser, $this->content);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_delete_their_own_content()
    {
        // Given: A user who owns the content
        // When: They try to delete their content
        $result = $this->policy->delete($this->contentOwner, $this->content);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_delete_other_users_content()
    {
        // Given: A user who does not own the content
        // When: They try to delete someone else's content
        $result = $this->policy->delete($this->unrelatedUser, $this->content);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_view_content_associated_with_workstream_they_have_access_to()
    {
        // Given: Content associated with a workstream
        $this->content->workstreams()->attach($this->workstream->id, [
            'relevance_type' => 'mentioned',
            'confidence_score' => 0.85,
        ]);

        // And: A user with view permission on that workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the content
        $result = $this->policy->view($this->user, $this->content);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_view_content_associated_with_workstream_they_dont_have_access_to()
    {
        // Given: Content associated with a workstream
        $this->content->workstreams()->attach($this->workstream->id, [
            'relevance_type' => 'mentioned',
            'confidence_score' => 0.85,
        ]);

        // And: A user without permission on that workstream
        // When: They try to view the content
        $result = $this->policy->view($this->unrelatedUser, $this->content);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_view_content_associated_with_release_they_are_stakeholder_of()
    {
        // Given: Content associated with a release
        $this->content->releases()->attach($this->release->id, [
            'relevance_type' => 'impact',
            'confidence_score' => 0.90,
        ]);

        // And: A user who is a stakeholder on that release
        $this->release->stakeholders()->attach($this->user->id, [
            'role' => 'reviewer',
        ]);

        // When: They try to view the content
        $result = $this->policy->view($this->user, $this->content);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_view_content_associated_with_release_they_are_not_stakeholder_of()
    {
        // Given: Content associated with a release
        $this->content->releases()->attach($this->release->id, [
            'relevance_type' => 'impact',
            'confidence_score' => 0.90,
        ]);

        // And: A user who is not a stakeholder on that release
        // When: They try to view the content
        $result = $this->policy->view($this->unrelatedUser, $this->content);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_workstream_owner_can_view_content_associated_with_their_workstream()
    {
        // Given: Content associated with a workstream
        $this->content->workstreams()->attach($this->workstream->id, [
            'relevance_type' => 'mentioned',
            'confidence_score' => 0.85,
        ]);

        // When: The workstream owner tries to view the content
        $result = $this->policy->view($this->workstreamOwner, $this->content);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_view_private_content_of_others()
    {
        // Given: Private content owned by another user
        // When: A different user tries to view it
        $result = $this->policy->view($this->unrelatedUser, $this->privateContent);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_view_content_through_stakeholder_mention()
    {
        // Given: Content that mentions a stakeholder
        $stakeholder = Stakeholder::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Stakeholder',
        ]);

        $this->content->stakeholders()->attach($stakeholder->id, [
            'mention_type' => 'direct_mention',
            'confidence_score' => 0.95,
        ]);

        // When: The mentioned user tries to view the content
        $result = $this->policy->view($this->user, $this->content);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_content()
    {
        // Given: No authenticated user (null)
        // When: They try to view content
        $result = $this->policy->view(null, $this->content);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_view_list_of_their_own_content()
    {
        // Given: Any authenticated user
        // When: They try to view their own content list
        $result = $this->policy->viewAny($this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_content_visibility_respects_workstream_hierarchy_permissions()
    {
        // Given: A parent-child workstream hierarchy
        $parentWorkstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        $childWorkstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
            'parent_workstream_id' => $parentWorkstream->id,
        ]);

        // And: Content associated with the child workstream
        $this->content->workstreams()->attach($childWorkstream->id, [
            'relevance_type' => 'mentioned',
            'confidence_score' => 0.85,
        ]);

        // And: A user with inherited permission on the parent workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $parentWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the content
        $result = $this->policy->view($this->user, $this->content);

        // Then: Access should be granted through inheritance
        $this->assertTrue($result);
    }

    /** @test */
    public function test_content_type_restrictions_are_enforced()
    {
        // Given: Sensitive content type
        $sensitiveContent = Content::factory()->create([
            'user_id' => $this->contentOwner->id,
            'type' => 'financial_data',
            'content' => 'Sensitive financial information',
        ]);

        // When: A user without specific permission tries to view it
        $result = $this->policy->viewSensitive($this->unrelatedUser, $sensitiveContent);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_content_sharing_permissions_are_enforced()
    {
        // Given: Content owner
        // When: They try to share their content
        $canShare = $this->policy->share($this->contentOwner, $this->content);

        // And: Another user tries to share content they don't own
        $cannotShare = $this->policy->share($this->unrelatedUser, $this->content);

        // Then: Only the owner should be able to share
        $this->assertTrue($canShare);
        $this->assertFalse($cannotShare);
    }

    /** @test */
    public function test_content_export_permissions_are_enforced()
    {
        // Given: Content owner
        // When: They try to export their content
        $canExport = $this->policy->export($this->contentOwner, $this->content);

        // And: Another user tries to export content they can only view
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        $this->content->workstreams()->attach($this->workstream->id);
        $cannotExport = $this->policy->export($this->user, $this->content);

        // Then: Only the owner should be able to export
        $this->assertTrue($canExport);
        $this->assertFalse($cannotExport);
    }

    /** @test */
    public function test_content_processing_status_affects_visibility()
    {
        // Given: Unprocessed content
        $unprocessedContent = Content::factory()->create([
            'user_id' => $this->contentOwner->id,
            'status' => 'pending',
        ]);

        // When: The owner tries to view their unprocessed content
        $ownerCanView = $this->policy->view($this->contentOwner, $unprocessedContent);

        // And: Another authorized user tries to view unprocessed content
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        $unprocessedContent->workstreams()->attach($this->workstream->id);
        $otherCannotView = $this->policy->view($this->user, $unprocessedContent);

        // Then: Only the owner should see unprocessed content
        $this->assertTrue($ownerCanView);
        $this->assertFalse($otherCannotView);
    }

    /** @test */
    public function test_content_collaboration_permissions()
    {
        // Given: Content that allows collaboration
        $collaborativeContent = Content::factory()->create([
            'user_id' => $this->contentOwner->id,
            'type' => 'collaborative_document',
        ]);

        // When: A user with workstream edit permission tries to edit the content
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        $collaborativeContent->workstreams()->attach($this->workstream->id);
        $result = $this->policy->collaborate($this->user, $collaborativeContent);

        // Then: Access should be granted for collaborative content
        $this->assertTrue($result);
    }

    /** @test */
    public function test_content_archive_permissions()
    {
        // Given: Content owner
        // When: They try to archive their content
        $canArchive = $this->policy->archive($this->contentOwner, $this->content);

        // And: A workstream owner tries to archive content in their workstream
        $this->content->workstreams()->attach($this->workstream->id);
        $workstreamOwnerCanArchive = $this->policy->archive($this->workstreamOwner, $this->content);

        // And: An unrelated user tries to archive content
        $cannotArchive = $this->policy->archive($this->unrelatedUser, $this->content);

        // Then: Owner and workstream owner can archive, others cannot
        $this->assertTrue($canArchive);
        $this->assertTrue($workstreamOwnerCanArchive);
        $this->assertFalse($cannotArchive);
    }
}