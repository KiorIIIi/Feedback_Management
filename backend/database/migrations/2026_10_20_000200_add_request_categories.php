<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('request_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handling_office_id')->constrained('offices');
            $table->string('name', 80);
            $table->unique(['handling_office_id', 'name']);
            $table->unique(['id', 'handling_office_id']);
        });
        Schema::table('service_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign(['category_id', 'handling_office_id'], 'request_category_office_fk')
                ->references(['id', 'handling_office_id'])->on('request_categories');
        });
    }
    public function down(): void {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign('request_category_office_fk');
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('request_categories');
    }
};
