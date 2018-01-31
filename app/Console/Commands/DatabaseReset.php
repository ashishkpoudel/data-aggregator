<?php

namespace App\Console\Commands;

use DB;
use Schema;

use Aic\Hub\Foundation\AbstractCommand;

class DatabaseReset extends AbstractCommand
{

    protected $signature = 'db:reset';

    protected $description = 'Removes all tables in current database';

    public function handle()
    {

        if( $this->confirmReset() )
        {
            $this->dropTables();

        } else {

            $this->info('Database reset command aborted. Whew!');

        }

    }

    private function confirmReset()
    {

        return (
            $this->confirm('Are you sure you want to drop all tables in `'.env('DB_DATABASE').'`? [y|N]')
        ) && (
            env('APP_ENV') === 'local' || $this->confirm('You aren\'t running in `local` environment. Are you really sure? [y|N]')
        ) && (
            env('APP_ENV') !== 'production' || $this->confirm('You are in production! Are you really, really sure? [y|N]')
        );

    }

    private function dropTables()
    {

        $tables = DB::select('SHOW TABLES');

        // TODO: Return if there's no tables?

        foreach( $tables as $table )
        {
            $table_array = get_object_vars( $table );
            $table_name = $table_array[ key( $table_array ) ];
            Schema::drop( $table_name );
            $this->info( 'Dropped table ' . $table_name );
        }

    }

}