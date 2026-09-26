<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The menu: `module` is a sidebar group, `submodule` is a screen inside it.
 * `user_permission` holds what each user may do, per branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('icon')->nullable();
            $table->tinyInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('submodule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id')->index();
            $table->string('name');
            $table->string('url')->nullable();
            $table->tinyInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('user_permission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->text('module_id')->nullable();      // "1,2,6"
            $table->text('submodule_id')->nullable();   // "1,2,3,18"
            $table->text('permissions')->nullable();    // {"1":{"view":1,"add":0,...}}
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission');
        Schema::dropIfExists('submodule');
        Schema::dropIfExists('module');
    }
};
