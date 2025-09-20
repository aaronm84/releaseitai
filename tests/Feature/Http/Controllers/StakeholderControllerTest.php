<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Stakeholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StakeholderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_index_displays_stakeholders_for_authenticated_user()
    {
        // Create stakeholders for this user
        $stakeholders = Stakeholder::factory()->count(3)->create([
            'user_id' => $this->user->id
        ]);

        // Create stakeholders for another user (should not appear)
        $otherUser = User::factory()->create();
        Stakeholder::factory()->count(2)->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->get(route('stakeholders.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Stakeholders/Index')
                 ->has('stakeholders', 3)
                 ->has('metrics')
        );
    }

    public function test_index_can_filter_by_search()
    {
        $stakeholder1 = Stakeholder::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $stakeholder2 = Stakeholder::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com'
        ]);

        $response = $this->get(route('stakeholders.index', ['search' => 'John']));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->has('stakeholders', 1)
                 ->where('stakeholders.0.name', 'John Doe')
        );
    }

    public function test_index_can_filter_by_influence_level()
    {
        Stakeholder::factory()->create([
            'user_id' => $this->user->id,
            'influence_level' => 'high'
        ]);

        Stakeholder::factory()->create([
            'user_id' => $this->user->id,
            'influence_level' => 'low'
        ]);

        $response = $this->get(route('stakeholders.index', ['influence_level' => 'high']));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->has('stakeholders', 1)
                 ->where('stakeholders.0.influence_level', 'high')
        );
    }

    public function test_show_displays_stakeholder_for_owner()
    {
        $stakeholder = Stakeholder::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->get(route('stakeholders.show', $stakeholder));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Stakeholders/Show')
                 ->where('stakeholder.id', $stakeholder->id)
        );
    }

    public function test_show_returns_404_for_non_owner()
    {
        $otherUser = User::factory()->create();
        $stakeholder = Stakeholder::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->get(route('stakeholders.show', $stakeholder));

        $response->assertNotFound();
    }

    public function test_create_displays_form()
    {
        $response = $this->get(route('stakeholders.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Stakeholders/Create')
        );
    }

    public function test_store_creates_stakeholder()
    {
        $stakeholderData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'company' => 'Tech Corp',
            'title' => 'CTO',
            'influence_level' => 'high',
            'support_level' => 'medium',
            'notes' => 'Key decision maker'
        ];

        $response = $this->post(route('stakeholders.store'), $stakeholderData);

        $response->assertRedirect(route('stakeholders.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('stakeholders', [
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'company' => 'Tech Corp'
        ]);
    }

    public function test_store_validates_required_fields()
    {
        $response = $this->post(route('stakeholders.store'), []);

        $response->assertSessionHasErrors(['name', 'email']);
    }

    public function test_store_validates_unique_email_per_user()
    {
        Stakeholder::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'john@example.com'
        ]);

        $response = $this->post(route('stakeholders.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_store_allows_same_email_for_different_users()
    {
        $otherUser = User::factory()->create();
        Stakeholder::factory()->create([
            'user_id' => $otherUser->id,
            'email' => 'john@example.com'
        ]);

        $response = $this->post(route('stakeholders.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $response->assertRedirect(route('stakeholders.index'));
        $this->assertDatabaseHas('stakeholders', [
            'user_id' => $this->user->id,
            'email' => 'john@example.com'
        ]);
    }

    public function test_edit_displays_form_for_owner()
    {
        $stakeholder = Stakeholder::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->get(route('stakeholders.edit', $stakeholder));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Stakeholders/Edit')
                 ->where('stakeholder.id', $stakeholder->id)
        );
    }

    public function test_edit_returns_404_for_non_owner()
    {
        $otherUser = User::factory()->create();
        $stakeholder = Stakeholder::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->get(route('stakeholders.edit', $stakeholder));

        $response->assertNotFound();
    }

    public function test_update_modifies_stakeholder_for_owner()
    {
        $stakeholder = Stakeholder::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Old Name'
        ]);

        $updateData = [
            'name' => 'New Name',
            'email' => $stakeholder->email,
            'company' => 'New Company'
        ];

        $response = $this->put(route('stakeholders.update', $stakeholder), $updateData);

        $response->assertRedirect(route('stakeholders.show', $stakeholder));
        $response->assertSessionHas('success');

        $stakeholder->refresh();
        $this->assertEquals('New Name', $stakeholder->name);
        $this->assertEquals('New Company', $stakeholder->company);
    }

    public function test_update_returns_404_for_non_owner()
    {
        $otherUser = User::factory()->create();
        $stakeholder = Stakeholder::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->put(route('stakeholders.update', $stakeholder), [
            'name' => 'New Name',
            'email' => $stakeholder->email
        ]);

        $response->assertNotFound();
    }

    public function test_destroy_deletes_stakeholder_for_owner()
    {
        $stakeholder = Stakeholder::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->delete(route('stakeholders.destroy', $stakeholder));

        $response->assertRedirect(route('stakeholders.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('stakeholders', [
            'id' => $stakeholder->id
        ]);
    }

    public function test_destroy_returns_404_for_non_owner()
    {
        $otherUser = User::factory()->create();
        $stakeholder = Stakeholder::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->delete(route('stakeholders.destroy', $stakeholder));

        $response->assertNotFound();

        $this->assertDatabaseHas('stakeholders', [
            'id' => $stakeholder->id
        ]);
    }

    public function test_guest_cannot_access_stakeholder_routes()
    {
        auth()->logout();

        $stakeholder = Stakeholder::factory()->create();

        $this->get(route('stakeholders.index'))->assertRedirect(route('login'));
        $this->get(route('stakeholders.create'))->assertRedirect(route('login'));
        $this->get(route('stakeholders.show', $stakeholder))->assertRedirect(route('login'));
        $this->get(route('stakeholders.edit', $stakeholder))->assertRedirect(route('login'));
        $this->post(route('stakeholders.store'))->assertRedirect(route('login'));
        $this->put(route('stakeholders.update', $stakeholder))->assertRedirect(route('login'));
        $this->delete(route('stakeholders.destroy', $stakeholder))->assertRedirect(route('login'));
    }
}