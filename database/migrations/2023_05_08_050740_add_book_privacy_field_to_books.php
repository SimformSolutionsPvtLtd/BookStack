<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('books', function (Blueprint $table) {
            $table->enum('privacy_method', ['Public', 'Private'])->default('Public');
        });
        $adminRoleId = DB::table('roles')->where('system_name', '=', 'admin')->first()->id;


        $permissionId = DB::table('role_permissions')->insertGetId([
            'name'         => 'access-private-books',
            'display_name' => 'Access Private Books',
            'created_at'   => \Carbon\Carbon::now()->toDateTimeString(),
            'updated_at'   => \Carbon\Carbon::now()->toDateTimeString(),
        ]);
        DB::table('permission_role')->insert([
            'role_id'       => $adminRoleId,
            'permission_id' => $permissionId,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('privacy_method');
        });
        $permission = DB::table('role_permissions')->where('name','access-private-books')->first();
        DB::table('permission_role')->where('permission_id',$permission->permission_id)->delete();
        $permission->delete();
  
    }
};
