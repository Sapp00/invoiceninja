<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === "pgsql") {
                DB::statement('CREATE INDEX clients_client_hash_index ON clients (substring(client_hash FROM 1 FOR 20))');
            } else {
                $table->index([\DB::raw('client_hash(20)')]);
            }
        });


        Schema::table('client_contacts', function (Blueprint $table) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === "pgsql") {
                DB::statement('CREATE INDEX client_contacts_contact_key_index ON client_contacts (substring(contact_key FROM 1 FOR 20))');
            } else {
                $table->index([\DB::raw('contact_key(20)')]);
            }
            $table->index('email');
        });

        Schema::table('vendor_contacts', function (Blueprint $table) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === "pgsql") {
                DB::statement('CREATE INDEX vendor_contacts_contact_key_index ON vendor_contacts (substring(contact_key FROM 1 FOR 20))');
            } else {
                $table->index([\DB::raw('contact_key(20)')]);
            }
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
