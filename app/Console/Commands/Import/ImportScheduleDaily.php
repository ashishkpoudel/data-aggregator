<?php

namespace App\Console\Commands\Import;

use Aic\Hub\Foundation\AbstractCommand as BaseCommand;

class ImportScheduleDaily extends BaseCommand
{

    protected $signature = 'import:daily';

    protected $description = 'Run all increment commands on sources that we\'re able to, and do a full refresh on sources that require it.';


    public function handle()
    {

        $this->call('import:collections');
        $this->call('import:collections-delete');
        $this->call('import:events-ticketed-full', ['--yes' => 'default']);
        $this->call('import:mobile');
        $this->call('import:products-full', ['--yes' => 'default']);
        $this->call('import:web');
        $this->call('import:digital-labels');

        // EventOccurrence is not included in import:web to avoid duplication
        $this->call('import:web-full', [
            '--yes' => 'default',
            // TODO: Change to 'event-occurrences' after WEB-6.0-beta40 is deployed
            'endpoint' => 'events/occurrences'
        ]);
    }

}
