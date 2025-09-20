<?php

namespace Tests\Unit\Models;

use App\Models\Stakeholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StakeholderTest extends TestCase
{
    use RefreshDatabase;

    public function test_stakeholder_has_required_attributes()
    {
        $user = User::factory()->create();

        $stakeholder = Stakeholder::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'user_id' => $user->id,
        ]);

        $this->assertEquals('John Doe', $stakeholder->name);
        $this->assertEquals('john@example.com', $stakeholder->email);
        $this->assertEquals($user->id, $stakeholder->user_id);
    }

    public function test_stakeholder_belongs_to_user()
    {
        $user = User::factory()->create();
        $stakeholder = Stakeholder::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $stakeholder->user);
        $this->assertEquals($user->id, $stakeholder->user->id);
    }

    public function test_stakeholder_has_optional_fields()
    {
        $user = User::factory()->create();

        $stakeholder = Stakeholder::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'company' => 'Tech Corp',
            'title' => 'CTO',
            'phone' => '+1234567890',
            'slack_handle' => '@jane.smith',
            'influence_level' => 'high',
            'support_level' => 'medium',
            'notes' => 'Key decision maker',
            'user_id' => $user->id,
        ]);

        $this->assertEquals('Tech Corp', $stakeholder->company);
        $this->assertEquals('CTO', $stakeholder->title);
        $this->assertEquals('+1234567890', $stakeholder->phone);
        $this->assertEquals('@jane.smith', $stakeholder->slack_handle);
        $this->assertEquals('high', $stakeholder->influence_level);
        $this->assertEquals('medium', $stakeholder->support_level);
        $this->assertEquals('Key decision maker', $stakeholder->notes);
    }

    public function test_stakeholder_can_track_contact_history()
    {
        $user = User::factory()->create();
        $now = now();

        $stakeholder = Stakeholder::create([
            'name' => 'Bob Wilson',
            'email' => 'bob@example.com',
            'last_contact_at' => $now,
            'last_contact_channel' => 'email',
            'user_id' => $user->id,
        ]);

        $this->assertEquals($now->format('Y-m-d H:i:s'), $stakeholder->last_contact_at->format('Y-m-d H:i:s'));
        $this->assertEquals('email', $stakeholder->last_contact_channel);
    }

    public function test_stakeholder_calculates_days_since_contact()
    {
        $user = User::factory()->create();
        $threeDaysAgo = now()->subDays(3);

        $stakeholder = Stakeholder::create([
            'name' => 'Alice Brown',
            'email' => 'alice@example.com',
            'last_contact_at' => $threeDaysAgo,
            'user_id' => $user->id,
        ]);

        $this->assertEquals(3, $stakeholder->days_since_contact);
    }

    public function test_stakeholder_needs_follow_up_when_no_recent_contact()
    {
        $user = User::factory()->create();
        $oldContact = now()->subDays(15);

        $stakeholder = Stakeholder::create([
            'name' => 'Charlie Davis',
            'email' => 'charlie@example.com',
            'last_contact_at' => $oldContact,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($stakeholder->needs_follow_up);
    }

    public function test_stakeholder_does_not_need_follow_up_when_recent_contact()
    {
        $user = User::factory()->create();
        $recentContact = now()->subDays(5);

        $stakeholder = Stakeholder::create([
            'name' => 'Diana Evans',
            'email' => 'diana@example.com',
            'last_contact_at' => $recentContact,
            'user_id' => $user->id,
        ]);

        $this->assertFalse($stakeholder->needs_follow_up);
    }

    public function test_stakeholder_validates_influence_level()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $user = User::factory()->create();

        Stakeholder::create([
            'name' => 'Invalid Stakeholder',
            'email' => 'invalid@example.com',
            'influence_level' => 'invalid_level',
            'user_id' => $user->id,
        ]);
    }

    public function test_stakeholder_validates_support_level()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $user = User::factory()->create();

        Stakeholder::create([
            'name' => 'Invalid Stakeholder',
            'email' => 'invalid@example.com',
            'support_level' => 'invalid_level',
            'user_id' => $user->id,
        ]);
    }
}