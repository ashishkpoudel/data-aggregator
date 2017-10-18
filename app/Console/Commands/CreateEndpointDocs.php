<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CreateEndpointDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:endpoints
                            {appUrl? : The root URL to use for the documentation. Defaults to APP_URL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate documentation for API endpoints';

    protected $appUrl;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {

        $appUrl = config("app.url");

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        if ($this->argument('appUrl'))
        {

            $this->appUrl = $this->argument('appUrl');

        }

        $doc = '';

        $doc .= "# Collections\n\n";
        $doc .= \App\Models\Collections\Artwork::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Agent::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Artist::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\CorporateBody::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Department::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\ObjectType::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Category::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\AgentType::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Gallery::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Exhibition::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Image::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Video::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Link::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Sound::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Collections\Text::instance()->docEndpoints($this->appUrl);

        $doc .= "# Shop\n\n";
        $doc .= \App\Models\Shop\Category::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Shop\Product::instance()->docEndpoints($this->appUrl);

        $doc .= "# Events and Membership\n\n";
        $doc .= \App\Models\Membership\Event::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Membership\Event::instance()->docMembershipEndpoint($this->appUrl);

        $doc .= "# Mobile\n\n";
        $doc .= \App\Models\Mobile\Tour::instance()->docEndpoints($this->appUrl);
        //$doc .= \App\Models\Mobile\TourStop::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Mobile\Sound::instance()->docEndpoints($this->appUrl);

        $doc .= "# Digital Scholarly Catalogs\n\n";
        $doc .= \App\Models\Dsc\Publication::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Dsc\TitlePage::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Dsc\Section::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Dsc\WorkOfArt::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Dsc\Footnote::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Dsc\Figure::instance()->docEndpoints($this->appUrl);
        $doc .= \App\Models\Dsc\Collector::instance()->docEndpoints($this->appUrl);

        $doc .= "# Static Archive\n\n";
        $doc .= \App\Models\StaticArchive\Site::instance()->docEndpoints($this->appUrl);

        Storage::disk('local')->put('ENDPOINTS.md', $doc);

    }

}
