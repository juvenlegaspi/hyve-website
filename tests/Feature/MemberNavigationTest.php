<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_navigation_keeps_its_smooth_scroll_anchors(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="#overview"', false)
            ->assertSee('data-nav-mode="home"', false);
    }

    public function test_member_portal_navigation_links_back_to_the_public_website_sections(): void
    {
        $member = User::factory()->create();

        $response = $this->actingAs($member)->get(route('member.index'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('home').'#overview"', false)
            ->assertSee('href="'.route('home').'#services"', false)
            ->assertSee('href="'.route('home').'#spaces"', false)
            ->assertSee('href="'.route('home').'#contact"', false)
            ->assertSee('Back to HYVE website')
            ->assertDontSee('href="#overview"', false);
    }
}
