<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'plan',
        'plan_status',
        'renewal_date',
        'role_id',
        'email_verified_at',
        'marketing_emails_enabled',
        'marketing_opt_in_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'renewal_date' => 'date',
            'marketing_emails_enabled' => 'boolean',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function userSubscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function userRentals()
    {
        return $this->hasMany(UserRental::class);
    }

    public function userPurchases()
    {
        return $this->hasMany(UserPurchase::class);
    }

    public function watchHistory()
    {
        return $this->hasMany(WatchHistory::class);
    }

    public function playerPreference()
    {
        return $this->hasOne(PlayerPreference::class);
    }

    public function playbackSessions()
    {
        return $this->hasMany(PlaybackSession::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function userNotifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function contentRequests()
    {
        return $this->hasMany(ContentRequest::class);
    }

    public function creatorApplication()
    {
        return $this->hasOne(CreatorApplication::class);
    }

    public function creatorPermission()
    {
        return $this->hasOne(CreatorPermission::class);
    }

    public function creatorClaims()
    {
        return $this->hasMany(CreatorClaim::class);
    }

    public function creatorWallet()
    {
        return $this->hasOne(CreatorWallet::class);
    }

    public function creatorPayoutMethods()
    {
        return $this->hasMany(CreatorPayoutMethod::class);
    }

    public function vjProfile()
    {
        return $this->hasOne(VJ::class);
    }

    public function mediaLibraryProfile()
    {
        return $this->hasOne(MediaLibrary::class);
    }

    public function isAdmin(): bool
    {
        return $this->role && $this->role->name === 'admin';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->isAdmin();
    }

    public function isVJ(): bool
    {
        if ($this->role && $this->role->name === 'vj') {
            return true;
        }

        if ($this->vjProfile()->where('is_active', true)->exists()) {
            return true;
        }

        $application = $this->creatorApplication;

        return $application
            && $application->status === 'approved'
            && $application->creator_type === 'vj';
    }

    public function isMediaLibrary(): bool
    {
        if ($this->role && $this->role->name === 'media_library') {
            return true;
        }

        if ($this->mediaLibraryProfile()->where('is_active', true)->exists()) {
            return true;
        }

        $application = $this->creatorApplication;

        return $application
            && $application->status === 'approved'
            && $application->creator_type === 'media_library';
    }

    public function isCreator(): bool
    {
        return $this->isVJ() || $this->isMediaLibrary();
    }

    public function hasCreatorWorkspace(): bool
    {
        if ($this->isAdmin() || $this->isCreator()) {
            return true;
        }

        return $this->creatorApplication()
            ->whereNotIn('status', [
                CreatorApplication::STATUS_REJECTED,
                CreatorApplication::STATUS_SUSPENDED,
                CreatorApplication::STATUS_REVOKED,
            ])
            ->exists();
    }

    public function isVerifiedCreator(): bool
    {
        $permission = $this->creatorPermission;

        return $permission
            && $permission->identity_verified
            && ! $permission->is_suspended
            && ! $permission->is_revoked;
    }

    public function isCustomer(): bool
    {
        return $this->role && $this->role->name === 'customer';
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (blank($user->marketing_opt_in_token)) {
                $user->marketing_opt_in_token = bin2hex(random_bytes(16));
            }
        });
    }
}
