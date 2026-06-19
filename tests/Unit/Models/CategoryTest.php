<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    private function makeCategory(array $attrs = []): Category
    {
        return new Category(array_merge([
            'name' => 'Test Category',
            'icon' => 'bolt',
            'community_default_visible' => true,
            'community_visibility_locked' => false,
            'community_visibility_help' => null,
        ], $attrs));
    }

    // ── defaultCommunityVisible ───────────────────────────────────────────────

    public function test_default_community_visible_returns_true_when_set_true(): void
    {
        $category = $this->makeCategory(['community_default_visible' => true]);

        $this->assertTrue($category->defaultCommunityVisible());
    }

    public function test_default_community_visible_returns_false_when_set_false(): void
    {
        $category = $this->makeCategory(['community_default_visible' => false]);

        $this->assertFalse($category->defaultCommunityVisible());
    }

    // ── locksCommunityVisibility ──────────────────────────────────────────────

    public function test_locks_community_visibility_returns_false_when_not_locked(): void
    {
        $category = $this->makeCategory(['community_visibility_locked' => false]);

        $this->assertFalse($category->locksCommunityVisibility());
    }

    public function test_locks_community_visibility_returns_true_when_locked(): void
    {
        $category = $this->makeCategory(['community_visibility_locked' => true]);

        $this->assertTrue($category->locksCommunityVisibility());
    }

    // ── resolveCommunityVisibility ────────────────────────────────────────────

    public function test_resolve_uses_requested_when_not_locked_and_requested_is_true(): void
    {
        $category = $this->makeCategory([
            'community_default_visible' => false,
            'community_visibility_locked' => false,
        ]);

        $this->assertTrue($category->resolveCommunityVisibility(true));
    }

    public function test_resolve_uses_requested_when_not_locked_and_requested_is_false(): void
    {
        $category = $this->makeCategory([
            'community_default_visible' => true,
            'community_visibility_locked' => false,
        ]);

        $this->assertFalse($category->resolveCommunityVisibility(false));
    }

    public function test_resolve_uses_category_default_when_not_locked_and_requested_is_null(): void
    {
        $categoryPublic = $this->makeCategory([
            'community_default_visible' => true,
            'community_visibility_locked' => false,
        ]);
        $categoryPrivate = $this->makeCategory([
            'community_default_visible' => false,
            'community_visibility_locked' => false,
        ]);

        $this->assertTrue($categoryPublic->resolveCommunityVisibility(null));
        $this->assertFalse($categoryPrivate->resolveCommunityVisibility(null));
    }

    public function test_resolve_ignores_requested_true_when_locked_private(): void
    {
        $category = $this->makeCategory([
            'community_default_visible' => false,
            'community_visibility_locked' => true,
        ]);

        $this->assertFalse($category->resolveCommunityVisibility(true));
    }

    public function test_resolve_ignores_requested_false_when_locked_public(): void
    {
        $category = $this->makeCategory([
            'community_default_visible' => true,
            'community_visibility_locked' => true,
        ]);

        $this->assertTrue($category->resolveCommunityVisibility(false));
    }

    public function test_resolve_ignores_null_when_locked_private(): void
    {
        $category = $this->makeCategory([
            'community_default_visible' => false,
            'community_visibility_locked' => true,
        ]);

        $this->assertFalse($category->resolveCommunityVisibility(null));
    }

    // ── casts ─────────────────────────────────────────────────────────────────

    public function test_community_fields_are_cast_to_boolean(): void
    {
        $category = $this->makeCategory([
            'community_default_visible' => 1,
            'community_visibility_locked' => 0,
        ]);

        $this->assertIsBool($category->community_default_visible);
        $this->assertIsBool($category->community_visibility_locked);
    }
}
