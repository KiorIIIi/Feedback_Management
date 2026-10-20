<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable {
 protected $fillable=['name','email','password','classification'];
 protected $hidden=['password','remember_token'];
 protected function casts():array{return ['password'=>'hashed','is_active'=>'boolean'];}
}
