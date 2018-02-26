<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:all';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all import commands';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $this->call('db:reset'); // Add --yes flag?
        $this->call('migrate');
        $this->call('import:collections-full');
        $this->call('import:exhibitions-legacy');
        $this->call('import:events-ticketed-full', ['--yes' => 'default']);
        $this->call('import:events-legacy');
        $this->call('import:dsc', ['--yes' => 'default', '-v' => 'default']);
        $this->call('import:mobile');
        $this->call('import:library', ['--yes' => 'default']);
        $this->call('import:archive', ['--yes' => 'default']);
        $this->call('import:sites', ['--yes' => 'default']);
        $this->call('import:set-ulan-uris');
        $this->call('import:terms-legacy');
        $this->call('import:products-full', ['--yes' => 'default']);


    }

}
