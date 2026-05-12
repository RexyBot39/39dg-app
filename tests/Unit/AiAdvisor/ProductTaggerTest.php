<?php

namespace Tests\Unit\AiAdvisor;

use App\Services\AiAdvisor\ProductTagger;
use Tests\TestCase;

class ProductTaggerTest extends TestCase
{
    private ProductTagger $tagger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tagger = new ProductTagger();
    }

    private function tagProduct(array $overrides = []): array
    {
        $base = [
            'feed_product_id' => 'test-001',
            'title'           => 'Classic Eyeglasses',
            'description'     => '',
            'material'        => '',
            'product_type'    => 'Eyeglasses',
            'color'           => 'Black',
            'size'            => '52-18-140',
            'price'           => 39.00,
            'sale_price'      => null,
            'availability'    => 'in stock',
            'public_url'      => 'https://www.39dollarglasses.com/product/test-001',
        ];

        return $this->tagger->tag(array_merge($base, $overrides));
    }

    // ── Frame shape detection ─────────────────────────────────────────────────

    /** @test */
    public function it_detects_round_shape(): void
    {
        $result = $this->tagProduct(['title' => 'Classic Round Metal Frames']);
        $this->assertSame('round', $result['frame_shape']);
    }

    /** @test */
    public function it_detects_rectangular_shape(): void
    {
        $result = $this->tagProduct(['title' => 'Rectangular Acetate Eyeglasses']);
        $this->assertSame('rectangular', $result['frame_shape']);
    }

    /** @test */
    public function it_detects_cat_eye_shape(): void
    {
        $result = $this->tagProduct(['title' => 'Cat-Eye Fashion Frames']);
        $this->assertSame('cat-eye', $result['frame_shape']);
    }

    /** @test */
    public function it_detects_rimless_shape(): void
    {
        $result = $this->tagProduct(['title' => 'Rimless Drill-Mount Titanium Frames']);
        $this->assertSame('rimless', $result['frame_shape']);
    }

    // ── Frame material detection ──────────────────────────────────────────────

    /** @test */
    public function it_detects_titanium_material(): void
    {
        $result = $this->tagProduct(['material' => 'Titanium']);
        $this->assertSame('titanium', $result['frame_material']);
    }

    /** @test */
    public function it_detects_acetate_material(): void
    {
        $result = $this->tagProduct(['material' => 'Acetate']);
        $this->assertSame('acetate', $result['frame_material']);
    }

    /** @test */
    public function it_detects_tr90_material(): void
    {
        $result = $this->tagProduct(['material' => 'TR-90']);
        $this->assertSame('tr90', $result['frame_material']);
    }

    /** @test */
    public function it_falls_back_to_title_for_material(): void
    {
        $result = $this->tagProduct(['material' => '', 'title' => 'Stainless Steel Rectangle Frames']);
        $this->assertSame('metal', $result['frame_material']);
    }

    // ── Frame size category ───────────────────────────────────────────────────

    /** @test */
    public function it_classifies_small_frames(): void
    {
        $result = $this->tagProduct(['size' => '46-16-135']);
        $this->assertSame('small', $result['frame_size_category']);
    }

    /** @test */
    public function it_classifies_medium_frames(): void
    {
        $result = $this->tagProduct(['size' => '52-18-140']);
        $this->assertSame('medium', $result['frame_size_category']);
    }

    /** @test */
    public function it_classifies_large_frames(): void
    {
        $result = $this->tagProduct(['size' => '55-18-145']);
        $this->assertSame('large', $result['frame_size_category']);
    }

    /** @test */
    public function it_classifies_xlarge_frames(): void
    {
        $result = $this->tagProduct(['size' => '58-18-150']);
        $this->assertSame('x-large', $result['frame_size_category']);
    }

    // ── Lightweight flag ──────────────────────────────────────────────────────

    /** @test */
    public function it_flags_titanium_as_lightweight(): void
    {
        $result = $this->tagProduct(['material' => 'Titanium']);
        $this->assertTrue($result['lightweight']);
    }

    /** @test */
    public function it_flags_tr90_as_lightweight(): void
    {
        $result = $this->tagProduct(['material' => 'TR-90']);
        $this->assertTrue($result['lightweight']);
    }

    /** @test */
    public function it_flags_rimless_as_lightweight(): void
    {
        $result = $this->tagProduct(['title' => 'Rimless Drill Frames', 'material' => 'Metal']);
        $this->assertTrue($result['lightweight']);
    }

    /** @test */
    public function it_does_not_flag_acetate_as_lightweight(): void
    {
        $result = $this->tagProduct(['material' => 'Acetate', 'title' => 'Classic Acetate Frames']);
        $this->assertFalse($result['lightweight']);
    }

    // ── Progressive friendly ──────────────────────────────────────────────────

    /** @test */
    public function it_marks_medium_frames_as_progressive_friendly(): void
    {
        $result = $this->tagProduct(['size' => '52-18-140', 'title' => 'Classic Rectangle Frames']);
        $this->assertTrue($result['progressive_friendly']);
    }

    /** @test */
    public function it_marks_small_frames_as_not_progressive_friendly(): void
    {
        $result = $this->tagProduct(['size' => '46-16-135']);
        $this->assertFalse($result['progressive_friendly']);
    }

    /** @test */
    public function it_marks_cat_eye_as_not_progressive_friendly(): void
    {
        $result = $this->tagProduct(['title' => 'Cat-Eye Narrow Frames', 'size' => '52-18-140']);
        $this->assertFalse($result['progressive_friendly']);
    }

    // ── Budget tier ───────────────────────────────────────────────────────────

    /** @test */
    public function it_classifies_budget_tier(): void
    {
        $result = $this->tagProduct(['price' => 25.00]);
        $this->assertSame('budget', $result['budget_tier']);
    }

    /** @test */
    public function it_classifies_mid_tier(): void
    {
        $result = $this->tagProduct(['price' => 49.00]);
        $this->assertSame('mid', $result['budget_tier']);
    }

    /** @test */
    public function it_classifies_premium_tier(): void
    {
        $result = $this->tagProduct(['price' => 89.00]);
        $this->assertSame('premium', $result['budget_tier']);
    }

    /** @test */
    public function it_uses_sale_price_for_tier_when_available(): void
    {
        $result = $this->tagProduct(['price' => 89.00, 'sale_price' => 45.00]);
        $this->assertSame('mid', $result['budget_tier']);
    }

    // ── is_recommendable ─────────────────────────────────────────────────────

    /** @test */
    public function it_marks_in_stock_product_with_price_as_recommendable(): void
    {
        $result = $this->tagProduct(['availability' => 'in stock', 'price' => 39.00]);
        $this->assertTrue($result['is_recommendable']);
    }

    /** @test */
    public function it_does_not_mark_out_of_stock_as_recommendable(): void
    {
        $result = $this->tagProduct(['availability' => 'out of stock']);
        $this->assertFalse($result['is_recommendable']);
    }

    /** @test */
    public function it_does_not_mark_product_without_price_as_recommendable(): void
    {
        $result = $this->tagProduct(['availability' => 'in stock', 'price' => null, 'sale_price' => null]);
        $this->assertFalse($result['is_recommendable']);
    }
}
