<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestCategories {
    public function __construct(private RequestAccess $access) {}

    public function create(User $actor, string $name): object {
        $this->access->admin($actor);
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name === '') throw ValidationException::withMessages(['name'=>'Enter a category name.']);
        try {
            return DB::transaction(function () use ($actor, $name) {
                DB::table('offices')->where('id',$actor->office_id)->lockForUpdate()->firstOrFail();
                $existing = DB::table('request_categories')->where('handling_office_id', $actor->office_id)->get();
                if ($existing->contains(fn ($category) => mb_strtolower($category->name) === mb_strtolower($name))) {
                    throw ValidationException::withMessages(['name'=>'This office already has that category.']);
                }
                $id = DB::table('request_categories')->insertGetId(['handling_office_id'=>$actor->office_id, 'name'=>$name]);
                DB::table('audit_entries')->insert([
                    'actor_user_id'=>$actor->id, 'actor_kind'=>'human', 'action'=>'request_category.created',
                    'after'=>json_encode(['category_id'=>$id, 'handling_office_id'=>$actor->office_id, 'name'=>$name]),
                    'occurred_at'=>now(),
                ]);
                return DB::table('request_categories')->find($id);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name'=>'This office already has that category.']);
        }
    }
}
