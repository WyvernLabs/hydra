<?php

namespace Pterodactyl\Models;

use Pterodactyl\Rules\Username;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\In;
use Illuminate\Auth\Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Pterodactyl\Contracts\Models\Identifiable;
use Pterodactyl\Models\Traits\HasAccessTokens;
use Illuminate\Auth\Passwords\CanResetPassword;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Pterodactyl\Services\Users\ProfileMediaService;
use Pterodactyl\Models\Traits\HasRealtimeIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Pterodactyl\Notifications\SendPasswordReset as ResetPasswordNotification;

/**
 * Pterodactyl\Models\User.
 *
 * @property int $id
 * @property string|null $external_id
 * @property string $uuid
 * @property string $username
 * @property string $email
 * @property string|null $name_first
 * @property string|null $name_last
 * @property string $password
 * @property string|null $remember_token
 * @property string $language
 * @property bool $root_admin
 * @property bool $use_totp
 * @property string|null $totp_secret
 * @property \Illuminate\Support\Carbon|null $totp_authenticated_at
 * @property bool $gravatar
 * @property string|null $avatar
 * @property string|null $banner
 * @property string $banner_style
 * @property string|null $banner_color_1
 * @property string|null $banner_color_2
 * @property string|null $profile_accent
 * @property string|null $bio
 * @property bool $show_online
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\ApiKey[] $apiKeys
 * @property int|null $api_keys_count
 * @property string $name
 * @property \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property int|null $notifications_count
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\RecoveryToken[] $recoveryTokens
 * @property int|null $recovery_tokens_count
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Server[] $servers
 * @property int|null $servers_count
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\UserSSHKey[] $sshKeys
 * @property int|null $ssh_keys_count
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\AdminRole[] $adminRoles
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\ApiKey[] $tokens
 * @property int|null $tokens_count
 *
 * @method static \Database\Factories\UserFactory factory(...$parameters)
 * @method static Builder|User newModelQuery()
 * @method static Builder|User newQuery()
 * @method static Builder|User query()
 * @method static Builder|User whereCreatedAt($value)
 * @method static Builder|User whereEmail($value)
 * @method static Builder|User whereExternalId($value)
 * @method static Builder|User whereGravatar($value)
 * @method static Builder|User whereId($value)
 * @method static Builder|User whereLanguage($value)
 * @method static Builder|User whereNameFirst($value)
 * @method static Builder|User whereNameLast($value)
 * @method static Builder|User wherePassword($value)
 * @method static Builder|User whereRememberToken($value)
 * @method static Builder|User whereRootAdmin($value)
 * @method static Builder|User whereTotpAuthenticatedAt($value)
 * @method static Builder|User whereTotpSecret($value)
 * @method static Builder|User whereUpdatedAt($value)
 * @method static Builder|User whereUseTotp($value)
 * @method static Builder|User whereUsername($value)
 * @method static Builder|User whereUuid($value)
 *
 * @mixin \Eloquent
 */
#[Attributes\Identifiable('user')]
class User extends Model implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract,
    HasLocalePreference,
    Identifiable
{
    use Authenticatable;
    use Authorizable;
    use AvailableLanguages;
    use CanResetPassword;
    /** @use \Pterodactyl\Models\Traits\HasAccessTokens<\Pterodactyl\Models\ApiKey> */
    use HasAccessTokens;
    use Notifiable;
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasRealtimeIdentifier;

    public const USER_LEVEL_USER = 0;
    public const USER_LEVEL_ADMIN = 1;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'user';

    /**
     * Level of servers to display when using access() on a user.
     */
    protected string $accessLevel = 'all';

    /**
     * Hydra: admin permissions of this user, resolved once per request.
     */
    protected ?array $resolvedAdminPermissions = null;

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * A list of mass-assignable variables.
     */
    protected $fillable = [
        'external_id',
        'username',
        'email',
        'name_first',
        'name_last',
        'password',
        'language',
        'use_totp',
        'totp_secret',
        'totp_authenticated_at',
        'gravatar',
        'root_admin',
    ];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'root_admin' => 'boolean',
        'use_totp' => 'boolean',
        'gravatar' => 'boolean',
        'totp_authenticated_at' => 'datetime',
        'show_online' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     */
    protected $hidden = ['password', 'remember_token', 'totp_secret', 'totp_authenticated_at', 'avatar', 'banner'];

    /**
     * Default values for specific fields in the database.
     */
    protected $attributes = [
        'external_id' => null,
        'root_admin' => false,
        'language' => 'en',
        'use_totp' => false,
        'totp_secret' => null,
    ];

    /**
     * Rules verifying that the data being stored matches the expectations of the database.
     */
    public static array $validationRules = [
        'uuid' => 'required|string|size:36|unique:users,uuid',
        'email' => 'required|email:strict|between:1,191|unique:users,email',
        'external_id' => 'sometimes|nullable|string|max:191|unique:users,external_id',
        'username' => 'required|between:1,191|unique:users,username',
        'name_first' => 'required|string|between:1,191',
        'name_last' => 'required|string|between:1,191',
        'password' => 'sometimes|nullable|string',
        'root_admin' => 'boolean',
        'language' => 'string',
        'use_totp' => 'boolean',
        'totp_secret' => 'nullable|string',
        'banner_style' => 'sometimes|string|in:gradient,image',
        'banner_color_1' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'banner_color_2' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'profile_accent' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'bio' => 'sometimes|nullable|string|max:240',
        'show_online' => 'sometimes|boolean',
    ];

    /**
     * Implement language verification by overriding Eloquence's gather
     * rules function.
     */
    public static function getRules(): array
    {
        $rules = parent::getRules();

        $rules['language'][] = new In(array_keys((new self())->getAvailableLanguages()));
        $rules['username'][] = new Username();

        return $rules;
    }

    /**
     * Return the user model in a format that can be passed over to Vue templates.
     */
    public function toVueObject(): array
    {
        return Collection::make($this->toArray())->except(['id', 'external_id', 'admin_roles'])
            ->merge([
                'identifier' => $this->identifier,
                'admin_access' => $this->isAdmin(),
                'admin_permissions' => $this->adminPermissions(),
                'server_access' => $this->canAccessAllServers(),
            ])
            ->merge($this->profile())
            ->toArray();
    }

    /**
     * Send the password reset notification.
     *
     * @param string $token
     */
    public function sendPasswordResetNotification($token)
    {
        Activity::event('auth:reset-password')
            ->withRequestMetadata()
            ->subject($this)
            ->log('sending password reset email');

        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Store the username as a lowercase string.
     */
    public function setUsernameAttribute(string $value)
    {
        $this->attributes['username'] = mb_strtolower($value);
    }

    /**
     * Return a concatenated result for the accounts full name.
     */
    public function getNameAttribute(): string
    {
        return trim($this->name_first . ' ' . $this->name_last);
    }

    /**
     * Returns all servers that a user owns.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\Server, $this>
     */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'owner_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class)
            ->where('key_type', ApiKey::TYPE_ACCOUNT);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\RecoveryToken, $this>
     */
    public function recoveryTokens(): HasMany
    {
        return $this->hasMany(RecoveryToken::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\UserSSHKey, $this>
     */
    public function sshKeys(): HasMany
    {
        return $this->hasMany(UserSSHKey::class);
    }

    /**
     * Returns all the activity logs where this user is the subject — not to
     * be confused by activity logs where this user is the _actor_.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany<\Pterodactyl\Models\ActivityLog, $this>
     */
    public function activity(): MorphToMany
    {
        return $this->morphToMany(ActivityLog::class, 'subject', 'activity_log_subjects');
    }

    /**
     * Returns all the servers that a user can access by way of being the owner of the
     * server, or because they are assigned as a subuser for that server.
     *
     * @return \Illuminate\Database\Eloquent\Builder<\Pterodactyl\Models\Server>
     */
    public function accessibleServers(): Builder
    {
        return Server::query()
            ->select('servers.*')
            ->leftJoin('subusers', 'subusers.server_id', '=', 'servers.id')
            ->where(function (Builder $builder) {
                $builder->where('servers.owner_id', $this->id)->orWhere('subusers.user_id', $this->id);
            })
            ->groupBy('servers.id');
    }

    /**
     * Hydra: a user counts as online if they were seen within this many seconds.
     */
    public const ONLINE_WINDOW = 300;

    /**
     * Hydra: whether the user was active in the panel within the last minutes.
     */
    public function isOnline(): bool
    {
        return !is_null($this->last_seen_at) && $this->last_seen_at->gt(now()->subSeconds(self::ONLINE_WINDOW));
    }

    /**
     * Hydra: URL of the uploaded profile picture, or null.
     */
    public function avatarUrl(): ?string
    {
        return ProfileMediaService::url($this, 'avatar');
    }

    /**
     * Hydra: URL of the uploaded profile banner, or null.
     */
    public function bannerUrl(): ?string
    {
        return ProfileMediaService::url($this, 'banner');
    }

    /**
     * Hydra: the public part of the profile, as shown on the account page.
     */
    public function profile(): array
    {
        return [
            'avatar_url' => $this->avatarUrl(),
            'banner_url' => $this->bannerUrl(),
            'banner_style' => $this->banner_style ?? 'gradient',
            'banner_color_1' => $this->banner_color_1,
            'banner_color_2' => $this->banner_color_2,
            'profile_accent' => $this->profile_accent,
            'bio' => $this->bio,
            'show_online' => $this->show_online ?? true,
        ];
    }

    /**
     * Hydra: roles of the admin area held by this user.
     *
     * @return BelongsToMany<AdminRole, $this>
     */
    public function adminRoles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_user')->orderBy('sort')->orderBy('name');
    }

    /**
     * Hydra: every admin permission this user has through their roles. Root
     * admins have all of them.
     *
     * @return string[]
     */
    public function adminPermissions(): array
    {
        if ($this->root_admin) {
            return AdminRole::allPermissions();
        }

        if (is_null($this->resolvedAdminPermissions)) {
            $this->resolvedAdminPermissions = !$this->exists ? [] : AdminRole::normalizePermissions(
                $this->adminRoles->pluck('permissions')->flatten()->unique()->all()
            );
        }

        return $this->resolvedAdminPermissions;
    }

    /**
     * Hydra: whether this user may open the admin area at all.
     */
    public function isAdmin(): bool
    {
        return $this->root_admin || count($this->adminPermissions()) > 0;
    }

    /**
     * Hydra: whether this user has the given admin permission, e.g. "servers.view".
     */
    public function hasAdminPermission(string $permission): bool
    {
        return $this->root_admin || in_array($permission, $this->adminPermissions(), true);
    }

    /**
     * Hydra: whether this user keeps full access (console, power, every tab) to
     * suspended servers they can open. Everyone else only keeps files,
     * databases and backups of a suspended server.
     */
    public function canUseSuspendedServers(): bool
    {
        return $this->hasAdminPermission('servers.suspended');
    }

    /**
     * Hydra: whether this user may open every server in the client area with
     * full rights, like a root admin.
     */
    public function canAccessAllServers(): bool
    {
        return $this->hasAdminPermission('servers.access');
    }

    /**
     * Hydra: whether this user may grant all the given admin permissions, which
     * is only the case when they hold every one of them.
     */
    public function canGrantAdminPermissions(array $permissions): bool
    {
        return $this->root_admin || count(array_diff($permissions, $this->adminPermissions())) === 0;
    }

    /**
     * Hydra: whether this user may manage the other user's account in the admin
     * area. Nobody but root admins may touch a root admin, and nobody may touch
     * an account with admin permissions they do not hold themselves.
     */
    public function outranks(User $other): bool
    {
        if ($this->root_admin) {
            return true;
        }

        return !$other->root_admin && $this->canGrantAdminPermissions($other->adminPermissions());
    }

    /**
     * Hydra: forget the cached admin permissions, e.g. after the roles changed.
     */
    public function flushAdminPermissions(): self
    {
        $this->resolvedAdminPermissions = null;
        $this->unsetRelation('adminRoles');

        return $this;
    }

    /**
     * Hydra: notifications (emails) are sent in the language of the user.
     */
    public function preferredLocale(): string
    {
        return $this->language ?: config('app.locale', 'en');
    }
}
