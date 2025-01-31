<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_user_using_factory(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /** @test */
    public function it_has_correct_fillable_attributes(): void
    {
        $user = new User();
        $expectedFillable = ['name', 'email', 'password'];

        $this->assertEquals($expectedFillable, $user->getFillable());
    }

    /** @test */
    public function it_hides_sensitive_attributes(): void
    {
        $user = User::factory()->create();
        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    /** @test */
    public function it_casts_attributes_correctly(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->assertIsString($user->password);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
    }

    /** @test */
    public function it_hashes_password_when_creating_user(): void
    {
        $plainPassword = 'password123';
        $user = User::factory()->create([
            'password' => $plainPassword
        ]);

        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertTrue(\Hash::check($plainPassword, $user->password));
    }
}
