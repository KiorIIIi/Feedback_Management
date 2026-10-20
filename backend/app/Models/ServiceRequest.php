<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ServiceRequest extends Model {
 protected $fillable = ['requesting_office_id','service_type_id','subject','description','location','urgency','impact'];
 protected function casts(): array {return ['completed_at'=>'datetime','first_response_at'=>'datetime','record_version'=>'integer','category_id'=>'integer'];}
}
