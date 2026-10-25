<?php
namespace App\Models;

// Keep session/Sanctum behavior unchanged on User; Passport has a separate provider.
class OAuthUser extends \Illuminate\Foundation\Auth\User implements \Laravel\Passport\Contracts\OAuthenticatable
{
    use \Laravel\Passport\HasApiTokens;
    protected $table = 'users';
    protected $hidden = ['password', 'remember_token'];
}
