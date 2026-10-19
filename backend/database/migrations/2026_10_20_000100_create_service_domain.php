<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('offices',function(Blueprint $t){$t->id();$t->string('office_code')->unique();$t->string('name');});
  Schema::table('users',function(Blueprint $t){$t->string('role')->default('requester');$t->string('classification')->default('Staff');$t->boolean('is_active')->default(true);$t->foreignId('office_id')->nullable()->constrained('offices');});
  Schema::create('office_memberships',function(Blueprint $t){$t->foreignId('user_id')->constrained();$t->foreignId('office_id')->constrained();$t->primary(['user_id','office_id']);});
  Schema::create('service_types',function(Blueprint $t){$t->id();$t->string('name')->unique();});
  Schema::create('personnel_eligibilities',function(Blueprint $t){$t->foreignId('user_id')->constrained();$t->foreignId('service_type_id')->constrained();$t->primary(['user_id','service_type_id']);});
  Schema::create('priority_rule_versions',function(Blueprint $t){$t->id();$t->string('version_number')->unique();$t->date('effective_from');$t->date('effective_until')->nullable();$t->boolean('is_demo')->default(true);});
  Schema::create('priority_rules',function(Blueprint $t){$t->id();$t->foreignId('priority_rule_version_id')->constrained();$t->string('urgency');$t->string('impact');$t->string('resulting_priority');$t->unique(['priority_rule_version_id','urgency','impact'],'priority_matrix_cell');});
  Schema::create('service_requests',function(Blueprint $t){$t->id();$t->string('job_number')->unique();$t->foreignId('requester_id')->constrained('users');$t->foreignId('requesting_office_id')->nullable()->constrained('offices');$t->foreignId('handling_office_id')->constrained('offices');$t->foreignId('service_type_id')->constrained();$t->string('subject',200);$t->text('description');$t->string('location');$t->string('urgency');$t->string('impact');$t->string('priority')->nullable();$t->foreignId('priority_rule_id')->nullable()->constrained();$t->string('status')->default('Pending');$t->unsignedInteger('record_version')->default(1);$t->timestamp('first_response_at')->nullable();$t->timestamp('completed_at')->nullable();$t->timestamps();$t->index(['handling_office_id','status']);});
  Schema::create('assignments',function(Blueprint $t){$t->id();$t->foreignId('service_request_id')->constrained();$t->foreignId('personnel_id')->constrained('users');$t->foreignId('assigned_by')->constrained('users');$t->timestamp('assigned_at');$t->timestamp('ended_at')->nullable();});
  Schema::create('request_events',function(Blueprint $t){$t->id();$t->foreignId('service_request_id')->constrained();$t->foreignId('actor_id')->constrained('users');$t->string('kind');$t->text('message');$t->string('visibility')->default('requester');$t->json('metadata')->nullable();$t->timestamp('created_at')->useCurrent();});
  Schema::create('request_revisions',function(Blueprint $t){$t->id();$t->foreignId('service_request_id')->constrained();$t->foreignId('author_id')->constrained('users');$t->json('snapshot');$t->timestamp('created_at')->useCurrent();});
  Schema::create('service_evaluations',function(Blueprint $t){$t->foreignId('request_id')->primary()->constrained('service_requests');$t->unsignedTinyInteger('rating');$t->string('category')->nullable();$t->text('remarks')->nullable();$t->timestamp('created_at')->useCurrent();});
  Schema::create('general_feedback',function(Blueprint $t){$t->id();$t->foreignId('user_id')->nullable()->constrained();$t->string('category');$t->string('subject',200);$t->text('message');$t->string('unverified_job_number')->nullable();$t->unsignedTinyInteger('rating')->nullable();$t->string('contact_name')->nullable();$t->string('contact_email')->nullable();$t->timestamp('created_at')->useCurrent();});
  Schema::create('audit_entries',function(Blueprint $t){$t->id();$t->foreignId('actor_user_id')->nullable()->constrained('users');$t->string('actor_kind');$t->string('action');$t->foreignId('service_request_id')->nullable()->constrained();$t->json('before')->nullable();$t->json('after')->nullable();$t->timestamp('occurred_at')->useCurrent();});
  Schema::create('portal_notifications',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained();$t->foreignId('service_request_id')->nullable()->constrained();$t->string('message');$t->timestamp('read_at')->nullable();$t->timestamp('created_at')->useCurrent();});
  Schema::create('report_jobs',function(Blueprint $t){$t->id();$t->foreignId('requested_by')->constrained('users');$t->string('status')->default('pending');$t->json('filters');$t->string('path')->nullable();$t->text('error')->nullable();$t->timestamps();});
 }
 public function down(): void {
  foreach(['report_jobs','portal_notifications','audit_entries','general_feedback','service_evaluations','request_revisions','request_events','assignments','service_requests','priority_rules','priority_rule_versions','personnel_eligibilities','service_types','office_memberships'] as $table) Schema::dropIfExists($table);
  Schema::table('users',function(Blueprint $t){$t->dropConstrainedForeignId('office_id');$t->dropColumn(['role','classification','is_active']);});
  Schema::dropIfExists('offices');
 }
};
