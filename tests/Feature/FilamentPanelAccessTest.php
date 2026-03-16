<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_implements_filament_user_contract(): void
    {
        $user = User::factory()->make();

        $this->assertInstanceOf(FilamentUser::class, $user);
    }

    public function test_user_can_access_admin_panel(): void
    {
        $user = User::factory()->create();
        $panel = Filament::getPanel('admin');

        $this->assertTrue($user->canAccessPanel($panel));
    }
}
