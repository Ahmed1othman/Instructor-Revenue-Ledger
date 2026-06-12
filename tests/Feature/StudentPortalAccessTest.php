<?php

use App\Filament\Resources\InstructorBalanceResource;
use App\Filament\Resources\SubscriptionResource;
use App\Models\User;

it('shows the student portal placeholder for non-admin users', function (): void {
    $this->get(route('student.portal'))
        ->assertOk()
        ->assertSee('Student portal is out of scope for this challenge.');
});

it('denies non-admin users access to filament financial resources', function (): void {
    $student = User::factory()->create(['is_admin' => false]);

    $this->actingAs($student)
        ->get(SubscriptionResource::getUrl('index'))
        ->assertForbidden();

    $this->actingAs($student)
        ->get(InstructorBalanceResource::getUrl('index'))
        ->assertForbidden();
});

it('allows admin users to access filament financial resources', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(SubscriptionResource::getUrl('index'))
        ->assertOk();
});
