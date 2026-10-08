<?php

use App\Billing\Plan;
use App\Billing\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A default of 'user' means every existing account is usable
            // immediately rather than locked out by a null role.
            $table->string('role')->default(Role::User->value)->after('password');
            $table->string('plan')->default(Plan::Free->value)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'plan']);
        });
    }
};
