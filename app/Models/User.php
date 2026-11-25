<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'password', 'pin', 'is_admin'])]
#[Hidden(['password', 'pin', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'pin' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /** Hanya admin yang boleh masuk panel /admin; nasabah ditolak. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /** Filter nasabah (bukan admin). */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('is_admin', false);
    }

    /** Rekening utama nasabah. */
    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }

    /** Semua rekening (saat ini satu per nasabah); dipakai tab "Rekening" di panel. */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** Mutasi semua rekening nasabah (tab "Mutasi"). */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, Account::class);
    }

    /** E-statement semua rekening nasabah (tab "e-Statement"). */
    public function statements(): HasManyThrough
    {
        return $this->hasManyThrough(Statement::class, Account::class);
    }

    /** Penerima transfer tersimpan. */
    public function payees(): HasMany
    {
        return $this->hasMany(Payee::class);
    }
}
