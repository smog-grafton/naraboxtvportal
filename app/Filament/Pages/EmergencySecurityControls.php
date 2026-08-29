<?php

namespace App\Filament\Pages;

use App\Services\RuntimeSecuritySettings;
use App\Services\SecurityEventService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class EmergencySecurityControls extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Security';
    protected static ?string $navigationLabel = 'Emergency Controls';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.emergency-security-controls';

    public array $controls = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(RuntimeSecuritySettings $settings): void
    {
        foreach (RuntimeSecuritySettings::DEFAULTS as $key => $default) {
            $this->controls[$key] = $settings->get($key);
        }
    }

    public function save(RuntimeSecuritySettings $settings, SecurityEventService $events): void
    {
        foreach (RuntimeSecuritySettings::DEFAULTS as $key => $default) {
            $settings->set($key, (bool) ($this->controls[$key] ?? false), auth()->id());
        }
        $events->record('ADMIN_SECURITY_ACTION', [
            'risk_level' => ($this->controls['lockdown_mode'] ?? false) ? 'CRITICAL' : 'HIGH',
            'action' => 'UPDATE_EMERGENCY_CONTROLS', 'actor_user_id' => auth()->id(),
            'reason' => 'Runtime emergency security controls updated.', 'metadata' => ['controls' => $this->controls],
        ]);
        Notification::make()->title('Emergency controls updated immediately')->success()->send();
    }
}
