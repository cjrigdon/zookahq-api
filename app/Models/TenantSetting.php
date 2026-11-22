<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $fillable = [
        'timezone',
        'locale',
        'currency',
        'company_name',
        'branding',
        'defaults',
    ];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'defaults' => 'array',
        ];
    }

    public function theme(): string
    {
        $theme = $this->branding['theme'] ?? config('themes.default');

        return in_array($theme, config('themes.presets'), true)
            ? $theme
            : config('themes.default');
    }

    /** @return array{enabled: bool, title: string, intro: string} */
    public function leadIntake(): array
    {
        $intake = is_array($this->branding['lead_intake'] ?? null)
            ? $this->branding['lead_intake']
            : [];

        return [
            'enabled' => array_key_exists('enabled', $intake) ? (bool) $intake['enabled'] : true,
            'title' => is_string($intake['title'] ?? null) && $intake['title'] !== ''
                ? $intake['title']
                : 'Request a proposal',
            'intro' => is_string($intake['intro'] ?? null) && $intake['intro'] !== ''
                ? $intake['intro']
                : 'Tell us about your event and we will follow up shortly.',
        ];
    }
}
