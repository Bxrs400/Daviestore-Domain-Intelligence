<?php

namespace App\Services;

class IndustryClassificationService
{
    public function classify(array $page): array
    {
        $text = strtolower(implode(' ', [$page['title'] ?? '', $page['description'] ?? '', implode(' ', $page['headings'] ?? []), $page['body'] ?? '']));
        $rules = ['Fintech' => ['payment', 'banking', 'fintech', 'invoice', 'crypto', 'insurance'], 'Healthcare' => ['health', 'medical', 'clinic', 'patient', 'telehealth', 'pharma'], 'SaaS' => ['software', 'platform', 'saas', 'cloud', 'workflow', 'automation'], 'Developer Tools' => ['developer', 'api', 'sdk', 'deploy', 'devops', 'database'], 'Ecommerce' => ['shop', 'store', 'commerce', 'cart', 'retail', 'marketplace'], 'Education' => ['school', 'course', 'learning', 'education', 'student', 'academy']];
        $scores = collect($rules)->mapWithKeys(fn (array $keywords, string $category) => [$category => collect($keywords)->filter(fn (string $keyword) => str_contains($text, $keyword))->count()]);
        $max = $scores->max() ?: 0;
        $category = $max ? $scores->sortDesc()->keys()->first() : 'Other';
        return ['category' => $category, 'confidence' => $max ? min(99, 50 + ($max * 9)) : 35, 'keywords' => $rules[$category] ?? []];
    }
}
