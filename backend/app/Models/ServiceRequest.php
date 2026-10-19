<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ServiceRequest extends Model {
 protected $guarded = ['id'];
 protected function casts(): array {return ['completed_at'=>'datetime','first_response_at'=>'datetime','record_version'=>'integer'];}
}
