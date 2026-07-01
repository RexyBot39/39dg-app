<?php

namespace App\Services\AiAdvisor;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reads an eyeglass or contact-lens prescription from an uploaded image
 * using GPT-4o vision. Extracts structured values; does NOT store the image.
 *
 * Returns a normalized structure the widget shows back to the user for
 * confirmation before any lens recommendation is made.
 */
class PrescriptionReader
{
    private const MODEL = 'gpt-4o';

    private const SYSTEM = <<<'PROMPT'
You are an optical assistant that reads prescription images and extracts the values.
Extract EXACTLY what is written — never guess or fill in missing values.

Return ONLY valid JSON in this shape:
{
  "type": "glasses" | "contacts" | "unknown",
  "od": {"sph": "", "cyl": "", "axis": "", "add": "", "bc": "", "dia": ""},
  "os": {"sph": "", "cyl": "", "axis": "", "add": "", "bc": "", "dia": ""},
  "pd": "",
  "confidence": "high" | "medium" | "low",
  "notes": ""
}

Rules:
- OD = right eye, OS = left eye.
- For glasses: sph, cyl, axis, add (bc/dia empty).
- For contacts: sph, bc (base curve), dia (diameter), cyl/axis if toric.
- Keep signs (+/-) exactly as written. Preserve decimals (e.g. -2.50).
- For PD, return the number only, no units (e.g. "63" not "63 mm").
- If a field is not present, leave it "".
- If the image is unreadable, not a prescription, or values are ambiguous,
  set confidence "low" and explain in notes.
- Put any concerns (blurry, handwritten, partial) in notes.
Respond with JSON only, no prose, no markdown.
PROMPT;

    /**
     * @param string $base64Image  raw base64 (no data: prefix)
     * @param string $mime         e.g. image/jpeg, image/png
     * @return array normalized prescription data
     */
    public function read(string $base64Image, string $mime): array
    {
        $apiKey = config('ai-advisor.openai.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(40)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => self::MODEL,
                'max_tokens' => 600,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => 'Read this prescription and extract the values as JSON.'],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => "data:{$mime};base64,{$base64Image}",
                        ]],
                    ]],
                ],
            ]);

        if ($response->failed()) {
            Log::error('[PrescriptionReader] Vision API error', ['status' => $response->status()]);
            throw new RuntimeException('Prescription reading failed: HTTP ' . $response->status());
        }

        $content = $response->json('choices.0.message.content');
        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new RuntimeException('Could not parse prescription response.');
        }

        return $this->normalize($data);
    }

    private function normalize(array $d): array
    {
        $eye = function ($e) {
            $e = is_array($e) ? $e : [];
            return [
                'sph'  => trim((string)($e['sph']  ?? '')),
                'cyl'  => trim((string)($e['cyl']  ?? '')),
                'axis' => trim((string)($e['axis'] ?? '')),
                'add'  => trim((string)($e['add']  ?? '')),
                'bc'   => trim((string)($e['bc']   ?? '')),
                'dia'  => trim((string)($e['dia']  ?? '')),
            ];
        };
        $type = in_array($d['type'] ?? '', ['glasses', 'contacts'], true) ? $d['type'] : 'unknown';
        $conf = in_array($d['confidence'] ?? '', ['high', 'medium', 'low'], true) ? $d['confidence'] : 'low';

        return [
            'type'       => $type,
            'od'         => $eye($d['od'] ?? []),
            'os'         => $eye($d['os'] ?? []),
            'pd'         => trim((string)($d['pd'] ?? '')),
            'confidence' => $conf,
            'notes'      => trim((string)($d['notes'] ?? '')),
        ];
    }
}
