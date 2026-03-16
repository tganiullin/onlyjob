<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_horizon_dashboard(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    }

    public function test_guest_cannot_view_horizon_dashboard(): void
    {
        $this->assertFalse(Gate::forUser(null)->allows('viewHorizon'));
    }
}
