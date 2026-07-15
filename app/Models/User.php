<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

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

    public function isVJ(): bool
    {
        if ($this->role && $this->role->name === 'vj') {
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

        $application = $this->creatorApplication;

        return $application
            && $application->status === 'approved'
            && $application->creator_type === 'media_library';
    }

    public function isCreator(): bool
    {
        return $this->isVJ() || $this->isMediaLibrary();
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
