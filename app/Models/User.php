<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    public function contributor(): HasOne
    {
        return $this->hasOne(Contributor::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /**
     * Contributors this user follows.
     *
     * `contributor_follows` carries its own timestamps, so `withTimestamps()`
     * is required for "Following since" to show anything but null.
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(Contributor::class, 'contributor_follows')
            ->withTimestamps();
    }

    /**
     * The plan this account is on.
     *
     * Deliberately reads the LOCAL `subscriptions` table rather than calling
     * Stripe: no gateway keys are configured yet, and the account page must
     * still render. Cashier writes `stripe_status` locally on every webhook,
     * so this stays correct once billing is live.
     *
     * Falls back to the Free plan — every account is on something.
     */
    public function currentPlan(): ?Plan
    {
        $priceId = $this->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->latest('created_at')
            ->value('stripe_price');

        if ($priceId) {
            $plan = Plan::where('stripe_monthly_price_id', $priceId)
                ->orWhere('stripe_annual_price_id', $priceId)
                ->first();

            if ($plan) {
                return $plan;
            }
        }

        return Plan::where('slug', 'free')->first();
    }

    /**
     * Up to two initials for the avatar bubble. Users have no avatar column —
     * only contributors do — so the contributor avatar wins when one exists.
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return Str::upper(Str::substr($this->email, 0, 1));
        }

        $first = Str::upper(Str::substr($words[0], 0, 1));
        $last = count($words) > 1 ? Str::upper(Str::substr(end($words), 0, 1)) : '';

        return $first.$last;
    }

    public function avatarUrl(): ?string
    {
        return $this->contributor?->avatar;
    }
}
