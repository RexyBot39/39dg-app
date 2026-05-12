<?php

namespace App\Services\AiAdvisor;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class KnowledgeBaseLoader
{
    private const LENS_TOPICS = [
        'single_vision'     => 'lens-types',
        'reading_lenses'    => 'lens-types',
        'reading'           => 'lens-types',
        'progressive'       => 'progressives',
        'progressives'      => 'progressives',
        'high_index'        => 'lens-materials',
        'high-index'        => 'lens-materials',
        'polycarbonate'     => 'lens-materials',
        'lens_materials'    => 'lens-materials',
        'lens_types'        => 'lens-types',
        'anti_reflective'   => 'coatings',
        'ar_coating'        => 'coatings',
        'blue_light'        => 'coatings',
        'coatings'          => 'coatings',
        'tints'             => 'coatings',
        'polarized'         => 'sunglass-lenses',
        'uv_protection'     => 'coatings',
        'sunglasses'        => 'sunglass-lenses',
        'sunglass_lenses'   => 'sunglass-lenses',
        'lens_replacement'  => 'lens-replacement',
    ];

    private const FRAME_TOPICS = [
        'frame_sizing'          => 'frame-sizing',
        'frame_size'            => 'frame-sizing',
        'sizing'                => 'frame-sizing',
        'frame_materials'       => 'frame-materials',
        'frame_material'        => 'frame-materials',
        'frame_shapes'          => 'frame-styles',
        'frame_styles'          => 'frame-styles',
        'frame_style'           => 'frame-styles',
        'lightweight_frames'    => 'frame-styles',
        'lightweight'           => 'frame-styles',
        'budget_frames'         => 'frame-styles',
        'strong_prescription'   => 'strong-prescription-frame-guide',
        'strong_rx'             => 'strong-prescription-frame-guide',
        'strong_rx_friendly'    => 'strong-prescription-frame-guide',
        'progressive_frames'    => 'strong-prescription-frame-guide',
    ];

    private const SPECIALTY_BRANDS = [
        'neurolux'       => 'neurolux',
        'lumeo'          => 'lumeo',
        'ocusleep'       => 'ocusleep',
        'blue495'        => 'blue495',
        'ultimateview'   => 'ultimateview-hd',
        'ultimateview_hd'=> 'ultimateview-hd',
        'transitions'    => 'transitions',
        'photochromic'   => 'transitions',
    ];

    private string $knowledgeBasePath;

    public function __construct()
    {
        $this->knowledgeBasePath = resource_path('ai-advisor/knowledge');
    }

    public function getLensInfo(string $topic): string
    {
        $topic = strtolower(trim($topic));
        $file  = self::LENS_TOPICS[$topic] ?? null;

        if ($file === null) {
            // Fuzzy match: find any lens topic file whose name contains the topic word
            foreach (self::LENS_TOPICS as $key => $filename) {
                if (str_contains($key, $topic) || str_contains($topic, $key)) {
                    $file = $filename;
                    break;
                }
            }
        }

        return $this->loadFile($file ?? 'lens-types', "lens topic: {$topic}");
    }

    public function getFrameGuide(string $topic): string
    {
        $topic = strtolower(trim($topic));
        $file  = self::FRAME_TOPICS[$topic] ?? null;

        if ($file === null) {
            foreach (self::FRAME_TOPICS as $key => $filename) {
                if (str_contains($key, $topic) || str_contains($topic, $key)) {
                    $file = $filename;
                    break;
                }
            }
        }

        return $this->loadFile($file ?? 'frame-styles', "frame topic: {$topic}");
    }

    public function getSpecialtyLensInfo(string $brand): string
    {
        $brand = strtolower(trim($brand));
        $file  = self::SPECIALTY_BRANDS[$brand] ?? null;

        if ($file === null) {
            throw new RuntimeException("Unknown specialty brand: {$brand}");
        }

        return $this->loadFile($file, "specialty brand: {$brand}");
    }

    public function getSupportHandoff(): string
    {
        return $this->loadFile('support-handoff', 'support handoff');
    }

    public function getDisclaimers(): string
    {
        return $this->loadFile('disclaimers', 'disclaimers');
    }

    public function getTopicCategory(string $topic): ?string
    {
        $topic = strtolower(trim($topic));

        if (isset(self::LENS_TOPICS[$topic])) {
            return 'lens';
        }

        if (isset(self::FRAME_TOPICS[$topic])) {
            return 'frame';
        }

        if (isset(self::SPECIALTY_BRANDS[$topic])) {
            return $topic; // return the brand name as the category
        }

        return null;
    }

    private function loadFile(string $filename, string $context): string
    {
        $cacheKey = "ai_advisor_kb_{$filename}";

        return Cache::remember($cacheKey, 3600, function () use ($filename, $context) {
            $path = "{$this->knowledgeBasePath}/{$filename}.md";

            if (!file_exists($path)) {
                return "No information found for {$context}. Please direct the customer to our support team.";
            }

            return file_get_contents($path);
        });
    }
}
